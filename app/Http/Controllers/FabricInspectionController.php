<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricInspection;
use App\Models\FabricType;
use App\Models\InspectionRoll;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تقرير فحص القماش.
 *
 * المخرج اللي بيهم السيستم كله: أقل عرض (مش المتوسط) — لأن الماركر
 * ما ينفعش يطلع أكبر من عرض القماش. وحجم العيّنة بيتسجّل عشان يفضل
 * واضح إن الأرقام دي تقديرية.
 */
class FabricInspectionController extends Controller
{
    public function index(Request $request)
    {
        $q = FabricInspection::with(['consignment', 'fabricType', 'color', 'inspector'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);
        if ($r = $request->get('result')) $q->where('result', $r);
        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($qq) => $qq->where('doc_no', 'like', "%{$term}%")
                                     ->orWhere('paper_serial', 'like', "%{$term}%"));
        }

        return view('inspections.index', [
            'title'   => 'تقارير فحص القماش',
            'rows'    => $q->paginate(25)->withQueryString(),
            'results' => FabricInspection::RESULTS,
        ]);
    }

    public function create(Request $request)
    {
        $row = new FabricInspection([
            'doc_date'       => now()->toDateString(),
            'status'         => 'draft',
            'result'         => 'pending',
            'inspector_id'   => auth()->id(),
            'consignment_id' => $request->get('consignment_id'),
        ]);

        if ($row->consignment_id && $c = Consignment::find($row->consignment_id)) {
            $row->fabric_type_id = $c->fabric_type_id;
            $row->color_id       = $c->color_id;
            $row->supplier_id    = $c->supplier_id;
            $row->total_rolls    = $c->rolls_count;
        }

        return view('inspections.form', $this->formData(['row' => $row, 'mode' => 'create']));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $insp = DB::transaction(function () use ($data) {
            $insp = FabricInspection::create($data['header'] + [
                'doc_no'     => DocNumber::next('fabric_inspection', 'fabric_inspections'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncRolls($insp, $data['rolls']);
            $insp->refresh()->recalc();
            return $insp;
        });

        return redirect()->route('inspections.edit', $insp)->with('success', 'تم إنشاء التقرير ' . $insp->doc_no);
    }

    public function edit(FabricInspection $inspection)
    {
        $inspection->load(['rolls', 'consignment', 'approval.steps']);
        return view('inspections.form', $this->formData(['row' => $inspection, 'mode' => 'edit']));
    }

    public function update(Request $request, FabricInspection $inspection)
    {
        abort_unless($inspection->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($inspection, $data) {
            $inspection->update($data['header']);
            $this->syncRolls($inspection, $data['rolls'], true);
            $inspection->refresh()->recalc();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(FabricInspection $inspection)
    {
        abort_unless($inspection->isEditable(), 403);

        if (!$inspection->rolls()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل تقرير من غير أتواب مفحوصة.']);
        }
        if ($inspection->result === 'pending') {
            return back()->withErrors(['msg' => 'حدد نتيجة الفحص (مقبول / مرفوض) قبل الإرسال.']);
        }

        ApprovalEngine::submit($inspection);
        return back()->with('success', 'تم الإرسال للاعتماد.');
    }

    public function print(FabricInspection $inspection)
    {
        $inspection->load(['rolls', 'consignment', 'fabricType', 'color', 'supplier']);
        return view('print.inspection', ['insp' => $inspection]);
    }

    public function destroy(FabricInspection $inspection)
    {
        abort_unless($inspection->isDraft(), 403);
        $inspection->delete();
        return redirect()->route('inspections.index')->with('success', 'تم الحذف.');
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'تقرير فحص قماش',
            'consignments' => Consignment::whereIn('status', ['received','inspecting','inspected','lab_pending'])
                                ->latest('id')->pluck('consignment_no', 'id'),
            'fabricTypes'  => FabricType::orderBy('name')->pluck('name', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'suppliers'    => Supplier::orderBy('name')->pluck('name', 'id'),
            'inspectors'   => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'results'      => FabricInspection::RESULTS,
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'       => ['required', 'date'],
            'paper_serial'   => ['nullable', 'string', 'max:40'],
            'consignment_id' => ['required', 'exists:consignments,id'],
            'fabric_type_id' => ['nullable', 'exists:fabric_types,id'],
            'color_id'       => ['nullable', 'exists:colors,id'],
            'supplier_id'    => ['nullable', 'exists:suppliers,id'],
            'inspector_id'   => ['nullable', 'exists:users,id'],
            'total_rolls'    => ['required', 'integer', 'min:1'],
            'result'         => ['required', 'in:pending,accepted,accepted_with_notes,rejected'],
            'notes'          => ['nullable', 'string'],

            'rolls'                   => ['required', 'array', 'min:1'],
            'rolls.*.roll_no'         => ['nullable', 'string', 'max:40'],
            'rolls.*.length_m'        => ['required', 'numeric', 'min:0.01'],
            'rolls.*.width_cm'        => ['required', 'numeric', 'min:1'],
            'rolls.*.gsm'             => ['nullable', 'numeric', 'min:0'],
            'rolls.*.defects_count'   => ['nullable', 'integer', 'min:0'],
            'rolls.*.defect_desc'     => ['nullable', 'string'],
            'rolls.*.notes'           => ['nullable', 'string'],
        ], [], [
            'consignment_id'   => 'الحوض',
            'total_rolls'      => 'إجمالي أتواب الحوض',
            'rolls'            => 'الأتواب المفحوصة',
            'rolls.*.length_m' => 'طول التوب',
            'rolls.*.width_cm' => 'العرض',
        ]);

        $rolls = $v['rolls'];
        unset($v['rolls']);

        return ['header' => $v, 'rolls' => $rolls];
    }

    private function syncRolls(FabricInspection $insp, array $rolls, bool $replace = false): void
    {
        if ($replace) $insp->rolls()->delete();

        foreach ($rolls as $i => $r) {
            $len = (float) $r['length_m'];
            $def = (int) ($r['defects_count'] ?? 0);

            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'roll_no'       => $r['roll_no'] ?? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'length_m'      => $len,
                'width_cm'      => $r['width_cm'],
                'gsm'           => $r['gsm'] ?? null,
                'defects_count' => $def,
                'defect_pct'    => $len > 0 ? round(($def / $len) * 100, 3) : null,
                'defect_desc'   => $r['defect_desc'] ?? null,
                'notes'         => $r['notes'] ?? null,
            ]);
        }
    }
}
