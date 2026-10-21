<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\LabGsmReading;
use App\Models\LabReport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocNumber;
use App\Services\FlowEngine;
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
            ->whereDoesntHave('labReports', fn ($x) => $x->where('status', 'approved'))
            ->latest('id')->limit(10)->get();
        $this->applyFilters($q, $request,
            ['doc_no', 'paper_serial', 'consignment.consignment_no'],
            'doc_date',
            ['status' => 'status', 'technician_id' => 'technician_id', 'fabric_type_id' => 'fabric_type_id']
        );

        $base    = LabReport::query();
        // المتفحص بس — اللي لسه ما اتفحصش مش من شغل المعمل
        $waiting = Consignment::where('status', 'inspected')
            ->whereDoesntHave('labReports', fn ($x) => $x->where('status', 'approved'))->count();

        return view('lab.index', [
            'awaiting' => $awaiting,
            'title'   => 'تقارير المعمل',
            'rows'    => $this->applySort($q, $request, ['doc_no','doc_date','avg_gsm','status'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي']],
                ['name' => 'fabric_type_id', 'label' => 'كل الخامات', 'options' => FabricType::orderBy('name')->pluck('name','id'), 'width' => 160],
                ['name' => 'technician_id', 'label' => 'كل الفنيين', 'options' => User::orderBy('name')->pluck('name','id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'أحواض مستنية معمل', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'من غير بنشر، حسبة أمر الشغل مش هتطلع.'],
                ['label' => 'إجمالي التقارير', 'value' => $base->count(), 'note' => 'كل تقارير المعمل.'],
                ['label' => 'ألوان غير مطابقة', 'value' => (clone $base)->where('color_match_ok', false)->count(),
                 'tone' => 'danger', 'note' => 'اللون خرج عن العينة المعتمدة.'],
                ['label' => 'متوسط البنشر', 'value' => round((float) (clone $base)->where('status', 'approved')->avg('avg_gsm'), 1) ?: '—',
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

        $preset = [];
        if ($row->consignment_id && $c = Consignment::find($row->consignment_id)) {
            $row->fabric_type_id = $c->fabric_type_id;
            $row->color_id       = $c->color_id;
            $row->supplier_id    = $c->supplier_id;

            /* الأتواب اللي اتفحصت بتنزل جاهزة — قراءة بنشر لكل توب (الميتينج).
               المرفوض مش بينزل، والفني يقدر يضيف أتواب زيادة ما اتفحصتش. */
            $insp = $c->inspections()->where('status', 'approved')->latest('id')->first();
            if ($insp) {
                foreach ($insp->rolls()->where('status', '!=', 'rejected')->orderBy('id')->get() as $r) {
                    $preset[] = ['roll_no' => $r->roll_no, 'gsm' => ''];
                }
            }
        }

        return view('lab.form', $this->formData([
            'row'     => $row,
            'mode'    => 'create',
            'preset'  => $preset,
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

        return $this->finish($lab->refresh());
    }

    public function edit(LabReport $lab_report)
    {
        $lab_report->load(['readings', 'consignment.fabricType', 'consignment.color',
            'consignment.supplier', ]);
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

        return $this->finish($lab_report->refresh());
    }

    /**
     * الحفظ = خلاص. مفيش زرار «نزّله للإفراج» منفصل —
     * أول ما القراءات تتحفظ، الرسالة بتظهر في شاشة استلام الخام.
     */
    private function finish(LabReport $lab_report)
    {
        if (!$lab_report->readings()->count()) {
            return redirect()->route('lab-reports.edit', $lab_report)
                ->withErrors(['msg' => 'مينفعش تحفظ تقرير من غير قراءة بنشر واحدة على الأقل.']);
        }

        FlowEngine::complete($lab_report, 'تقرير المعمل ' . $lab_report->doc_no . ' اتحفظ وخلص');

        return redirect()->route('lab-reports.edit', $lab_report)
            ->with(FlowMessage::flash('lab.done', $lab_report));
    }

    /** الراوت القديم — بيوصّل لنفس التخليص */
    public function submit(LabReport $lab_report)
    {
        abort_unless($lab_report->isEditable(), 403);

        return $this->finish($lab_report);
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
            'preset'       => [],
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
            's3_shrink_len_pct'    => ['nullable', 'numeric'],
            's3_shrink_width_pct'  => ['nullable', 'numeric'],
            's4_shrink_len_pct'    => ['nullable', 'numeric'],
            's4_shrink_width_pct'  => ['nullable', 'numeric'],
            'color_match_ok'       => ['nullable', 'boolean'],
            'has_color_break'      => ['nullable', 'boolean'],
            'color_break_roll_no'  => ['nullable', 'string', 'max:120'],
            'color_break_note'     => ['nullable', 'string', 'max:191'],
            'notes'                => ['nullable', 'string'],

            'readings'           => ['required', 'array', 'min:1'],
            'readings.*.roll_no' => ['nullable', 'string', 'max:40'],
            'readings.*.gsm'     => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'consignment_id'  => 'الحوض',
            'readings'        => 'قراءات البنشر',
            'readings.*.gsm'  => 'وزن البنشر',
        ]);

        // القراءة الفاضية = التوب ده ما اتقاسش — بيتشال من غير ما يوقف الحفظ
        $readings = array_values(array_filter($v['readings'], fn ($r) => (float) ($r['gsm'] ?? 0) > 0));
        unset($v['readings']);
        $v['color_match_ok']  = $request->boolean('color_match_ok');
        $v['has_color_break'] = $request->boolean('has_color_break');
        if (!$v['has_color_break']) { $v['color_break_roll_no'] = null; $v['color_break_note'] = null; }

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
