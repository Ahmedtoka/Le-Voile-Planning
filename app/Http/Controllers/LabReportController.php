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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** تقرير انكماش قماش ومطابقة ألوان — مصدر متوسط البنشر للحسبة. */
class LabReportController extends Controller
{
    public function index(Request $request)
    {
        $q = LabReport::with(['consignment', 'fabricType', 'color', 'technician'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);
        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($qq) => $qq->where('doc_no', 'like', "%{$term}%")
                                     ->orWhere('paper_serial', 'like', "%{$term}%"));
        }

        return view('lab.index', ['title' => 'تقارير المعمل', 'rows' => $q->paginate(25)->withQueryString()]);
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

        return view('lab.form', $this->formData(['row' => $row, 'mode' => 'create']));
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
        $lab_report->load(['readings', 'consignment', 'approval.steps']);
        return view('lab.form', $this->formData(['row' => $lab_report, 'mode' => 'edit']));
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
        return back()->with('success', 'تم الإرسال للاعتماد.');
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
