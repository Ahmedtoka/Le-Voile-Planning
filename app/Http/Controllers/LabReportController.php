<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\LabGsmReading;
use App\Models\LabReport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use App\Services\FlowMessage;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** تقرير انكماش قماش ومطابقة ألوان — مصدر متوسط البنشر للحسبة. */
class LabReportController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = LabReport::with(['consignment', 'fabricType', 'color', 'technician']);

        /* طابور المعمل: اللي خلص فحص ومستني قراءات البنشر والانكماش */
        $awaiting = Consignment::with(['fabricType', 'color', 'supplier'])
            ->whereIn('status', ['inspected'])
            ->whereDoesntHave('labReports', fn ($x) => $x->whereIn('status', ['pending', 'approved']))
            ->latest('id')->limit(10)->get();
        $this->applyFilters($q, $request,
            ['doc_no', 'paper_serial', 'consignment.consignment_no'],
            'doc_date',
            ['status' => 'status', 'technician_id' => 'technician_id', 'fabric_type_id' => 'fabric_type_id']
        );

        $base    = LabReport::query();
        $waiting = Consignment::onHold()
            ->whereDoesntHave('labReports', fn ($x) => $x->whereIn('status', ['pending','approved']))->count();

        return view('lab.index', [
            'awaiting' => $awaiting,
            'title'   => 'تقارير المعمل',
            'rows'    => $this->applySort($q, $request, ['doc_no','doc_date','avg_gsm','status'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','pending'=>'تحت الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض']],
                ['name' => 'fabric_type_id', 'label' => 'كل الخامات', 'options' => FabricType::orderBy('name')->pluck('name','id'), 'width' => 160],
                ['name' => 'technician_id', 'label' => 'كل الفنيين', 'options' => User::orderBy('name')->pluck('name','id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'أحواض مستنية معمل', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'من غير بنشر، حسبة أمر الشغل مش هتطلع.'],
                ['label' => 'إجمالي التقارير', 'value' => $base->count(), 'note' => 'كل تقارير المعمل.'],
                ['label' => 'ألوان غير مطابقة', 'value' => (clone $base)->where('color_match_ok', false)->count(),
                 'tone' => 'danger', 'note' => 'اللون خرج عن العينة المعتمدة.'],
                ['label' => 'متوسط البنشر', 'value' => round((float) (clone $base)->avg('avg_gsm'), 1) ?: '—',
                 'sub' => 'جم/م²', 'note' => 'متوسط كل التقارير — مؤشر عام على الخامات.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $row = new LabReport([
            'doc_date'       => now()->toDateString(),
            'status'         => 'draft',
            'technician_id'  => auth()->id(),
            'consignment_id' => $request->get('consignment_id'),
        ]);

        if ($row->consignment_id && $c = Consignment::find($row->consignment_id)) {
            $row->fabric_type_id = $c->fabric_type_id;
            $row->color_id       = $c->color_id;
            $row->supplier_id    = $c->supplier_id;
        }

        return view('lab.form', $this->formData([
            'row'     => $row,
            'mode'    => 'create',
            'arrived' => isset($c) ? $c->load('fabricType', 'color', 'supplier') : null,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $lab = DB::transaction(function () use ($data, $request) {
            $lab = LabReport::create($data['header'] + [
                'doc_no'     => DocNumber::next('lab_report', 'lab_reports'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncReadings($lab, $data['readings']);
            $this->storeSwatch($lab, $request);
            $lab->refresh()->recalc();
            return $lab;
        });

        return redirect()->route('lab-reports.edit', $lab)->with('success', 'تم إنشاء التقرير ' . $lab->doc_no);
    }

    public function edit(LabReport $lab_report)
    {
        $lab_report->load(['readings', 'consignment.fabricType', 'consignment.color',
            'consignment.supplier', 'approval.steps']);
        return view('lab.form', $this->formData([
            'row'     => $lab_report,
            'mode'    => 'edit',
            'arrived' => $lab_report->consignment,
        ]));
    }

    public function update(Request $request, LabReport $lab_report)
    {
        abort_unless($lab_report->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($lab_report, $data, $request) {
            $lab_report->update($data['header']);
            $this->syncReadings($lab_report, $data['readings'], true);
            $this->storeSwatch($lab_report, $request);
            $lab_report->refresh()->recalc();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(LabReport $lab_report)
    {
        abort_unless($lab_report->isEditable(), 403);
        if (!$lab_report->readings()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل تقرير من غير قراءات بنشر.']);
        }
        ApprovalEngine::submit($lab_report);
        return back()->with(FlowMessage::flash('lab.submitted', $lab_report));
    }

    public function print(LabReport $lab_report)
    {
        $lab_report->load(['readings', 'consignment', 'fabricType', 'color', 'supplier']);
        return view('print.lab_report', ['lab' => $lab_report]);
    }

    public function destroy(LabReport $lab_report)
    {
        abort_unless($lab_report->isDraft(), 403);
        $lab_report->delete();
        return redirect()->route('lab-reports.index')->with('success', 'تم الحذف.');
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'تقرير انكماش ومطابقة ألوان',
            'consignments' => Consignment::onHold()->latest('id')->pluck('consignment_no', 'id'),
            'fabricTypes'  => FabricType::orderBy('name')->pluck('name', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'suppliers'    => Supplier::orderBy('name')->pluck('name', 'id'),
            'technicians'  => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'arrived'      => null,
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'             => ['required', 'date'],
            'paper_serial'         => ['nullable', 'string', 'max:40'],
            'consignment_id'       => ['required', 'exists:consignments,id'],
            'fabric_type_id'       => ['nullable', 'exists:fabric_types,id'],
            'color_id'             => ['nullable', 'exists:colors,id'],
            'supplier_id'          => ['nullable', 'exists:suppliers,id'],
            'technician_id'        => ['nullable', 'exists:users,id'],
            's1_shrink_len_pct'    => ['nullable', 'numeric'],
            's1_shrink_width_pct'  => ['nullable', 'numeric'],
            's2_shrink_len_pct'    => ['nullable', 'numeric'],
            's2_shrink_width_pct'  => ['nullable', 'numeric'],
            'color_match_ok'       => ['nullable', 'boolean'],
            'notes'                => ['nullable', 'string'],

            'readings'           => ['required', 'array', 'min:1'],
            'readings.*.roll_no' => ['nullable', 'string', 'max:40'],
            'readings.*.gsm'     => ['required', 'numeric', 'min:1'],
        ], [], [
            'consignment_id'  => 'الحوض',
            'readings'        => 'قراءات البنشر',
            'readings.*.gsm'  => 'وزن البنشر',
        ]);

        $readings = $v['readings'];
        unset($v['readings']);
        $v['color_match_ok'] = $request->boolean('color_match_ok');

        return ['header' => $v, 'readings' => $readings];
    }

    private function syncReadings(LabReport $lab, array $readings, bool $replace = false): void
    {
        if ($replace) $lab->readings()->delete();

        foreach ($readings as $i => $r) {
            LabGsmReading::create([
                'lab_report_id' => $lab->id,
                'roll_no'       => $r['roll_no'] ?? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'gsm'           => $r['gsm'],
            ]);
        }
    }

    private function storeSwatch(LabReport $lab, Request $request): void
    {
        if ($request->hasFile('color_swatch')) {
            $path = $request->file('color_swatch')->store('swatches', 'public');
            $lab->forceFill(['color_swatch_path' => $path])->saveQuietly();
        }
    }
}
