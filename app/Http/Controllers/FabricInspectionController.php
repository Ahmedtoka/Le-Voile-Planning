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
use App\Services\FlowMessage;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تقرير فحص القماش — الخطوة اللي بعد إذن الإضافة.
 *
 * بيعمل حاجتين:
 *  ① **الجرد** — كام توب موجود فعلًا مقابل اللي المورد قال عليه.
 *     أي فرق بيتسجّل وبيتبعت تنبيه لمراقب المخزون.
 *  ② **قياس كل توب** — الطول والعرض والعيوب. المخرج اللي بيهم
 *     السيستم كله هو **أقل عرض** (مش المتوسط)، لأن الماركر ما ينفعش
 *     يطلع أوسع من القماش.
 *
 * والفحص عيّنة مش 100% — حجم العيّنة بيتسجّل ويفضل ظاهر على كل رقم
 * مبني عليه.
 */
class FabricInspectionController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = FabricInspection::with(['consignment', 'fabricType', 'color', 'inspector']);
        $this->applyFilters($q, $request,
            ['doc_no', 'paper_serial', 'consignment.consignment_no'],
            'doc_date',
            ['status' => 'status', 'result' => 'result', 'inspector_id' => 'inspector_id']
        );
        if ($request->boolean('variance')) $q->where('rolls_variance', '!=', 0);

        $base = FabricInspection::query();

        /* طابور الفحص: القماش اللي وصل بإذن إضافة ولسه ما اتفحصش.
           بيوري كمان إن ده وصول جزئي وكام باقي على الطلب وهيوصل إمتى،
           عشان الفاحص يعرف يبدأ دلوقتي ولا يستنى الشحنة تكمل. */
        $awaiting = Consignment::with(['fabricType', 'color', 'supplier', 'purchaseOrder.lines', 'stockAdditions'])
            ->where('status', 'under_inspection')
            ->whereDoesntHave('inspections', fn ($x) => $x->whereIn('status', ['pending', 'approved']))
            ->latest('id')->limit(10)->get();

        $waiting = Consignment::where('status', 'under_inspection')
            ->whereDoesntHave('inspections', fn ($x) => $x->whereIn('status', ['pending','approved']))->count();

        return view('inspections.index', [
            'title'    => 'تقارير فحص القماش',
            'awaiting' => $awaiting,
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','counted_rolls','min_width_cm','defect_pct','result','status'])->paginate(25)->withQueryString(),
            'results' => FabricInspection::RESULTS,
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','pending'=>'تحت الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض']],
                ['name' => 'result', 'label' => 'كل النتائج', 'options' => FabricInspection::RESULTS, 'width' => 160],
                ['name' => 'inspector_id', 'label' => 'كل الفاحصين', 'options' => \App\Models\User::orderBy('name')->pluck('name','id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'أحواض مستنية فحص', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'وصلت بإذن إضافة ولسه ما اتفحصتش.'],
                ['label' => 'إجمالي التقارير', 'value' => $base->count(), 'note' => 'كل تقارير الفحص.'],
                ['label' => 'فروق جرد', 'value' => (clone $base)->where('rolls_variance','!=',0)->count(),
                 'tone' => 'danger', 'note' => 'عدد أتواب مختلف عن اللي المورد قال عليه.'],
                ['label' => 'تنبيهات عرض', 'value' => (clone $base)->where('width_alert', true)->count(),
                 'tone' => 'danger', 'note' => 'فرق عرض كبير بين أتواب حوض واحد.'],
                ['label' => 'مرفوض', 'value' => (clone $base)->where('result','rejected')->count(), 'tone' => 'danger',
                 'note' => 'قماش اترفض في الفحص.'],
            ],
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

        $preset = [];

        if ($row->consignment_id && $c = Consignment::find($row->consignment_id)) {
            $row->fabric_type_id  = $c->fabric_type_id;
            $row->color_id        = $c->color_id;
            $row->supplier_id     = $c->supplier_id;
            $row->declared_rolls  = $c->rolls_count;   // اللي جه في إذن الإضافة
            $row->counted_rolls   = $c->rolls_count;   // الفاحص يصححه بعد الجرد
            $row->counted_kg      = $c->total_kg;

            /* سطر جاهز لكل توب وصل — الفاحص بيكتب الطول والعرض وخلاص.
               اللي مش هيقيسه يمسحه، والباقي بيتحسب عيّنة تلقائيًا. */
            foreach ($c->rolls()->orderBy('roll_no')->get() as $r) {
                $preset[] = [
                    'roll_no'  => $r->roll_no,
                    'length_m' => $r->length_m,
                    'width_cm' => $r->width_cm,
                ];
            }
        }

        return view('inspections.form', $this->formData([
            'row'       => $row,
            'mode'      => 'create',
            'preset'    => $preset,
            'arrivedPo' => isset($c) ? $c->purchaseOrder?->load('lines') : null,
            'arrived'   => isset($c) ? $c->load('fabricType', 'color', 'supplier') : null,
        ]));
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
        $inspection->load(['rolls', 'consignment.purchaseOrder.lines',
            'consignment.fabricType', 'consignment.color', 'consignment.supplier', 'approval.steps']);

        return view('inspections.form', $this->formData([
            'row'       => $inspection,
            'mode'      => 'edit',
            'arrived'   => $inspection->consignment,
            'arrivedPo' => $inspection->consignment?->purchaseOrder,
        ]));
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
        $noWidth = $inspection->rolls()
            ->where(fn ($q) => $q->whereNull('width_cm')->orWhere('width_cm', '<=', 0))
            ->count();

        if ($noWidth) {
            return back()->withErrors(['msg' => 'كل توب مفحوص لازم يكون له عرض — منه بيتحدد أقل عرض.']);
        }

        ApprovalEngine::submit($inspection);
        return back()->with(FlowMessage::flash('inspection.submitted', $inspection));
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
            // الأحواض اللي لسه تحت الفحص
            'consignments' => Consignment::onHold()->latest('id')->pluck('consignment_no', 'id'),
            'fabricTypes'  => FabricType::orderBy('name')->pluck('name', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'suppliers'    => Supplier::orderBy('name')->pluck('name', 'id'),
            'inspectors'   => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'results'      => FabricInspection::RESULTS,
            'preset'       => [],
            'arrivedPo'    => null,
            'arrived'      => null,
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
            'declared_rolls' => ['required', 'integer', 'min:0'],
            'counted_rolls'  => ['required', 'integer', 'min:1'],
            'counted_kg'     => ['nullable', 'numeric', 'min:0'],
            'result'         => ['required', 'in:pending,accepted,accepted_with_notes,rejected'],
            'notes'          => ['nullable', 'string'],

            // السطور بتيجي جاهزة بعدد أتواب الرسالة — اللي ما اتقاسش بيتساب
            'rolls'                   => ['required', 'array', 'min:1'],
            'rolls.*.roll_no'         => ['nullable', 'string', 'max:40'],
            'rolls.*.length_m'        => ['nullable', 'numeric', 'min:0'],
            'rolls.*.width_cm'        => ['nullable', 'numeric', 'min:0'],
            'rolls.*.gsm'             => ['nullable', 'numeric', 'min:0'],
            'rolls.*.defects_count'   => ['nullable', 'integer', 'min:0'],
            'rolls.*.defect_desc'     => ['nullable', 'string'],
            'rolls.*.notes'           => ['nullable', 'string'],
        ], [], [
            'consignment_id'   => 'الحوض',
            'declared_rolls'   => 'الأتواب حسب إذن الإضافة',
            'counted_rolls'    => 'الأتواب المجرودة فعليًا',
            'rolls'            => 'الأتواب المفحوصة',
            'rolls.*.length_m' => 'طول التوب',
            'rolls.*.width_cm' => 'العرض',
        ]);

        $rolls = $v['rolls'];
        unset($v['rolls']);

        // لازم توب واحد متقاس على الأقل (طول + عرض) — من غيره مفيش أقل عرض
        $measured = collect($rolls)->filter(fn ($r) =>
            (float) ($r['length_m'] ?? 0) > 0 && (float) ($r['width_cm'] ?? 0) > 0)->count();

        if (!$measured) {
            throw \Illuminate\Validation\ValidationException::withMessages(['msg' =>
                'لازم تقيس توب واحد على الأقل (الطول والعرض) — منه بيتحدد أقل عرض اللي الماركر بيتبني عليه.']);
        }

        $v['total_rolls'] = $v['counted_rolls'];   // المرجع هو المجرود مش المصرّح

        return ['header' => $v, 'rolls' => $rolls];
    }

    private function syncRolls(FabricInspection $insp, array $rolls, bool $replace = false): void
    {
        if ($replace) $insp->rolls()->delete();

        foreach ($rolls as $i => $r) {
            $len = (float) ($r['length_m'] ?? 0);
            $def = (int) ($r['defects_count'] ?? 0);

            /* السطور الجاهزة اللي الفاحص ما قاسهاش بتتساب — العيّنة =
               الأتواب اللي اتقاست فعلًا، مش كل أتواب الرسالة. */
            if ($len <= 0 && (float) ($r['width_cm'] ?? 0) <= 0 && $def === 0) continue;

            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'roll_no'       => $r['roll_no'] ?? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'length_m'      => $len,
                'width_cm'      => $r['width_cm'] ?? null,
                'gsm'           => $r['gsm'] ?? null,
                'defects_count' => $def,
                'defect_pct'    => $len > 0 ? round(($def / $len) * 100, 3) : null,
                'defect_desc'   => $r['defect_desc'] ?? null,
                'notes'         => $r['notes'] ?? null,
            ]);
        }
    }
}
