<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricInspection;
use App\Models\FabricType;
use App\Models\InspectionRoll;
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
            ->whereDoesntHave('inspections', fn ($x) => $x->where('status', 'approved'))
            ->latest('id')->limit(10)->get();

        $waiting = Consignment::where('status', 'under_inspection')
            ->whereDoesntHave('inspections', fn ($x) => $x->where('status', 'approved'))->count();

        return view('inspections.index', [
            'title'    => 'تقارير فحص القماش',
            'awaiting' => $awaiting,
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','counted_rolls','min_width_cm','defect_pct','result','status'])->paginate(25)->withQueryString(),
            'results' => FabricInspection::RESULTS,
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي']],
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
            'result'         => '',       // الفاحص لازم يختار — مفيش قيمة افتراضية
            'inspector_id'   => auth()->id(),
            'consignment_id' => $request->get('consignment_id'),
        ]);

        $preset = [];

        if ($row->consignment_id && $c = Consignment::find($row->consignment_id)) {
            $row->fabric_type_id  = $c->fabric_type_id;
            $row->color_id        = $c->color_id;
            $row->supplier_id     = $c->supplier_id;
            $row->declared_rolls  = $c->rolls_count;   // اللي جه في إذن الإضافة
            $row->counted_kg      = $c->total_kg;

            /* ملحوظة مهمة: مش بنولّد سطر لكل توب وصل.
               عدد الأتواب اللي المورد قال عليها ملهاش لازمة هنا — اللي يهم
               هو الجرد الفعلي. الفاحص بيكتب «الأتواب المجرودة فعليًا»
               ويدوس «تم»، فالسطور بتتفتح بالعدد ده. الصفحة بتفتح بسطر واحد. */
            $row->counted_rolls = null;
        }

        return view('inspections.form', $this->formData([
            'row'       => $row,
            'mode'      => 'create',
            'preset'    => $preset,
            'arrivedPo' => isset($c) ? $c->purchaseOrder?->load('lines') : null,
            'arrived'   => isset($c) ? $c->load('fabricType', 'color', 'supplier') : null,
            'lab'       => isset($c) ? $c->labReports()->with('readings')->latest('id')->first() : null,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $insp = DB::transaction(function () use ($data, $request) {
            $insp = FabricInspection::create($data['header'] + [
                'doc_no'     => DocNumber::next('fabric_inspection', 'fabric_inspections'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncRolls($insp, $data['rolls']);
            $insp->refresh()->recalc();
            $this->syncLab($insp, $data['lab'], $request);
            return $insp;
        });

        return $this->finish($insp->refresh());
    }

    public function edit(FabricInspection $inspection)
    {
        $inspection->load(['rolls', 'inspector', 'consignment.purchaseOrder.lines',
            'consignment.fabricType', 'consignment.color', 'consignment.supplier', ]);

        return view('inspections.form', $this->formData([
            'row'       => $inspection,
            'mode'      => 'edit',
            'arrived'   => $inspection->consignment,
            'arrivedPo' => $inspection->consignment?->purchaseOrder,
            'lab'       => $inspection->consignment?->labReports()->with('readings')->latest('id')->first(),
        ]));
    }

    public function update(Request $request, FabricInspection $inspection)
    {
        abort_unless($inspection->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($inspection, $data, $request) {
            $inspection->update($data['header']);
            $this->syncRolls($inspection, $data['rolls'], true);
            $inspection->refresh()->recalc();
            $this->syncLab($inspection, $data['lab'], $request);
        });

        return $this->finish($inspection->refresh());
    }

    /** اللي لازم يتظبط قبل ما التقرير يخلص — [الحقل => الرسالة] أو null (الحقل بيتعلّم بالأحمر في الشاشة) */
    private function guard(FabricInspection $inspection): ?array
    {
        $rolls = $inspection->rolls()->get()->values();
        if (!$rolls->count()) {
            return ['counted_rolls' => 'مينفعش تحفظ تقرير من غير أتواب مقيسة — اكتب الأتواب المجرودة ودوس «تم».'];
        }

        if ($inspection->result === 'pending' || !$inspection->result) {
            return ['result' => 'اختار نتيجة الفحص الأول: مقبول، مقبول بملاحظات، ولا مرفوض.'];
        }

        // «مقبول بملاحظات» و«مرفوض» — لازم سبب مكتوب
        if (in_array($inspection->result, ['accepted_with_notes', 'rejected'], true)
            && trim((string) $inspection->notes) === '') {
            return ['notes' => $inspection->result === 'rejected'
                ? 'الرفض لازم يكون معاه سبب مكتوب — هيظهر في شاشة المرفوضات وللمورد.'
                : 'اخترت «مقبول بملاحظات» — لازم تكتب الملاحظة.'];
        }

        // المرفوض مش لازم يتقاس — وزنه هو المهم
        foreach ($rolls as $i => $r) {
            if ($r->status !== 'rejected' && (float) $r->width_cm <= 0) {
                return ["rolls.$i.width_cm" => 'التوب ' . ($r->roll_no ?: $i + 1) . ' من غير عرض — كل توب مقيس لازم يكون له عرض، منه بيتحدد أقل عرض اللي الماركر بيتبني عليه.'];
            }
        }

        return null;
    }

    /**
     * الحفظ = خلاص. مفيش زرار «نزّل للمعمل» منفصل.
     * المقبول بينزل طابور المعمل، والمرفوض بينزل شاشة المرفوضات.
     */
    private function finish(FabricInspection $inspection)
    {
        if ($problem = $this->guard($inspection)) {
            return redirect()->route('inspections.edit', $inspection)
                ->withErrors($problem)->with('draft_saved', true);
        }

        FlowEngine::complete($inspection, 'تقرير الفحص ' . $inspection->doc_no . ' اتحفظ وخلص');

        /* المعمل المدمج: لو الفاحص كتب بنشر للأتواب، تقرير المعمل بيتقفل مع الفحص
           والرسالة تنزل استلام الخام على طول. الرسالة المرفوضة ما بيتعملهاش معمل. */
        $lab = $inspection->result !== 'rejected' && $inspection->consignment
            ? $inspection->consignment->labReports()->where('status', 'draft')->latest('id')->first()
            : null;
        if ($lab && $lab->readings()->count()) {
            $lab->refresh()->recalc();
            FlowEngine::complete($lab, 'تقرير المعمل ' . $lab->doc_no . ' اتحفظ مع الفحص ' . $inspection->doc_no);
            return redirect()->route('inspections.edit', $inspection)
                ->with(FlowMessage::flash('lab.done', $lab));
        }

        return redirect()->route('inspections.edit', $inspection)
            ->with(FlowMessage::flash('inspection.done', $inspection));
    }

    /**
     * تقرير المعمل المدمج في ورقة الفحص (الميتينج: نفس الأتواب ⇒ نفس الورقة).
     * بيتعمل/بيتحدث كمسودة على نفس الرسالة، والقراءات = بنشر الأتواب السليمة.
     */
    private function syncLab(FabricInspection $insp, array $lab, Request $request): void
    {
        $readings = $insp->rolls()->where('status', '!=', 'rejected')->where('gsm', '>', 0)->orderBy('id')->get();
        $hasData  = $readings->count() > 0
            || collect(['s1_shrink_len_pct', 's1_shrink_width_pct', 's2_shrink_len_pct', 's2_shrink_width_pct',
                        's3_shrink_len_pct', 's3_shrink_width_pct', 's4_shrink_len_pct', 's4_shrink_width_pct'])
                ->contains(fn ($k) => ($lab[$k] ?? null) !== null && $lab[$k] !== '')
            || !empty($lab['has_color_break']) || !empty($lab['color_match_ok']) || $request->hasFile('color_swatch');

        $c = $insp->consignment;
        if (!$c) return;

        $report = $c->labReports()->where('status', 'draft')->latest('id')->first();
        if (!$hasData && !$report) return;

        $payload = [
            'doc_date'            => $insp->doc_date,
            'consignment_id'      => $c->id,
            'fabric_type_id'      => $c->fabric_type_id,
            'color_id'            => $c->color_id,
            'supplier_id'         => $c->supplier_id,
            'technician_id'       => auth()->id(),
            'paper_serial'        => $lab['paper_serial'] ?? null,
            'color_match_ok'      => (bool) ($lab['color_match_ok'] ?? false),
            'has_color_break'     => (bool) ($lab['has_color_break'] ?? false),
            'color_break_roll_no' => !empty($lab['has_color_break']) ? ($lab['color_break_roll_no'] ?? null) : null,
            'color_break_note'    => !empty($lab['has_color_break']) ? ($lab['color_break_note'] ?? null) : null,
            'notes'               => $lab['notes'] ?? null,
        ];
        foreach (['s1', 's2', 's3', 's4'] as $s) {
            $payload["{$s}_shrink_len_pct"]   = ($lab["{$s}_shrink_len_pct"] ?? '') !== '' ? $lab["{$s}_shrink_len_pct"] : null;
            $payload["{$s}_shrink_width_pct"] = ($lab["{$s}_shrink_width_pct"] ?? '') !== '' ? $lab["{$s}_shrink_width_pct"] : null;
        }

        if ($report) {
            $report->update($payload);
        } else {
            $report = LabReport::create($payload + [
                'doc_no'     => DocNumber::next('lab_report', 'lab_reports'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
        }

        $report->readings()->delete();
        foreach ($readings as $r) {
            LabGsmReading::create(['lab_report_id' => $report->id, 'roll_no' => $r->roll_no, 'gsm' => $r->gsm]);
        }
        if ($request->hasFile('color_swatch')) {
            $report->forceFill(['color_swatch_path' => $request->file('color_swatch')->store('swatches', 'public')])->saveQuietly();
        }
        $report->refresh()->recalc();
    }

    /** الراوت القديم — بيوصّل لنفس التخليص */
    public function submit(FabricInspection $inspection)
    {
        abort_unless($inspection->isEditable(), 403);

        return $this->finish($inspection);
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
            'lab'          => null,
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
            'result'         => ['required', 'in:accepted,accepted_with_notes,rejected'],
            'notes'          => ['nullable', 'string'],

            // السطور بتيجي جاهزة بعدد أتواب الرسالة — اللي ما اتقاسش بيتساب
            'rolls'                   => ['required', 'array', 'min:1'],
            'rolls.*.roll_no'         => ['nullable', 'string', 'max:40'],
            'rolls.*.length_m'        => ['nullable', 'numeric', 'min:0'],
            'rolls.*.labeled_length_m' => ['nullable', 'numeric', 'min:0'],   // بالمتر: الأمتار المكتوبة على التوب (الميتينج 37)
            'rolls.*.width_cm'        => ['nullable', 'numeric', 'min:0'],
            'rolls.*.status'          => ['nullable', 'in:ok,rejected'],
            'rolls.*.net_kg'          => ['nullable', 'numeric', 'min:0'],
            'rolls.*.gsm'             => ['nullable', 'numeric', 'min:0'],
            'rolls.*.defect_type'     => ['nullable', 'string', 'max:40'],

            // المعمل المدمج — كله اختياري
            'lab'                        => ['nullable', 'array'],
            'lab.paper_serial'           => ['nullable', 'string', 'max:40'],
            'lab.s1_shrink_len_pct'      => ['nullable', 'numeric'], 'lab.s1_shrink_width_pct' => ['nullable', 'numeric'],
            'lab.s2_shrink_len_pct'      => ['nullable', 'numeric'], 'lab.s2_shrink_width_pct' => ['nullable', 'numeric'],
            'lab.s3_shrink_len_pct'      => ['nullable', 'numeric'], 'lab.s3_shrink_width_pct' => ['nullable', 'numeric'],
            'lab.s4_shrink_len_pct'      => ['nullable', 'numeric'], 'lab.s4_shrink_width_pct' => ['nullable', 'numeric'],
            'lab.color_match_ok'         => ['nullable', 'boolean'],
            'lab.has_color_break'        => ['nullable', 'boolean'],
            'lab.color_break_roll_no'    => ['nullable', 'string', 'max:120'],
            'lab.color_break_note'       => ['nullable', 'string', 'max:191'],
            'lab.notes'                  => ['nullable', 'string'],
            'color_swatch'               => ['nullable', 'image', 'max:4096'],
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
            'result'           => 'نتيجة الفحص',
        ]);

        $rolls = $v['rolls'];
        $lab   = $v['lab'] ?? [];
        unset($v['rolls'], $v['lab'], $v['color_swatch']);

        $meter = (bool) \App\Models\Consignment::find($v['consignment_id'])?->is_meter;

        /* بالمتر: الأمتار المكتوبة على التوب هي «طوله» ومفيش وزن ولا بنشر (الميتينج 37) */
        if ($meter) {
            foreach ($rolls as &$r) {
                $r['length_m'] = $r['labeled_length_m'] ?? $r['length_m'] ?? null;
                $r['net_kg'] = null; $r['gsm'] = null;
            }
            unset($r);
        }

        // التوب المرفوض لازم يتوزن (أو بالمتر: أمتاره) — ده اللي بيتخصم من الرسالة والطلب
        foreach ($rolls as $k => $r) {
            if (($r['status'] ?? 'ok') !== 'rejected') continue;
            if (!$meter && (float) ($r['net_kg'] ?? 0) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(["rolls.$k.net_kg" =>
                    'التوب ' . ($r['roll_no'] ?? '') . ' متعلّم «مرفوض» من غير وزن — الأتواب مش متساوية، اوزنه واكتب وزنه بالكيلو.']);
            }
            if ($meter && (float) ($r['length_m'] ?? 0) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(["rolls.$k.labeled_length_m" =>
                    'التوب ' . ($r['roll_no'] ?? '') . ' متعلّم «مرفوض» من غير أمتار — اكتب الأمتار المكتوبة عليه عشان تتخصم.']);
            }
        }

        // لازم توب واحد متقاس على الأقل (العرض — وبالكيلو الطول كمان) — من غيره مفيش أقل عرض
        $measured = collect($rolls)->filter(fn ($r) =>
            ($r['status'] ?? 'ok') !== 'rejected'
            && ($meter || (float) ($r['length_m'] ?? 0) > 0) && (float) ($r['width_cm'] ?? 0) > 0)->count();

        if (!$measured && $v['result'] !== 'rejected') {
            $first = array_key_first($rolls) ?? 0;
            throw \Illuminate\Validation\ValidationException::withMessages(["rolls.$first.width_cm" => $meter
                ? 'لازم تقيس عرض توب واحد على الأقل — منه بيتحدد أقل عرض اللي الماركر بيتبني عليه.'
                : 'لازم تقيس توب واحد على الأقل (الطول والعرض) — منه بيتحدد أقل عرض اللي الماركر بيتبني عليه.']);
        }

        // العيّنة: الحد الأدنى من الإعدادات (الميتينج 37: 10%)
        $minPct = (float) lv_setting('min_inspection_sample_pct', 10);
        $sampled = collect($rolls)->filter(fn ($r) => (float) ($r['width_cm'] ?? 0) > 0 || ($r['status'] ?? 'ok') === 'rejected')->count();
        if ($v['result'] !== 'rejected' && (int) $v['counted_rolls'] > 0 && $sampled / (int) $v['counted_rolls'] * 100 < $minPct - 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages(['counted_rolls' =>
                'العيّنة قليلة: فحصت ' . $sampled . ' من ' . (int) $v['counted_rolls'] . ' توب — الحد الأدنى '
                . lv_num($minPct, 0) . '% (يعني ' . (int) ceil((int) $v['counted_rolls'] * $minPct / 100) . ' توب على الأقل).']);
        }

        $v['total_rolls'] = $v['counted_rolls'];   // المرجع هو المجرود مش المصرّح

        return ['header' => $v, 'rolls' => $rolls, 'lab' => $lab];
    }

    private function syncRolls(FabricInspection $insp, array $rolls, bool $replace = false): void
    {
        if ($replace) $insp->rolls()->delete();

        foreach ($rolls as $i => $r) {
            $len = (float) ($r['length_m'] ?? 0);
            $def = (int) ($r['defects_count'] ?? 0);
            $rej = ($r['status'] ?? 'ok') === 'rejected';
            $dtype = trim((string) ($r['defect_type'] ?? ''));
            $ddesc = trim((string) ($r['defect_desc'] ?? ''));
            $defectDesc = $dtype !== '' ? ($dtype . ($ddesc !== '' ? ': ' . $ddesc : '')) : ($ddesc !== '' ? $ddesc : null);
            if ($dtype !== '' && $def === 0) $def = 1;   // اختار عيب من غير عدد = عيب واحد

            /* السطور الجاهزة اللي الفاحص ما قاسهاش بتتساب — العيّنة =
               الأتواب اللي اتقاست فعلًا، مش كل أتواب الرسالة. المرفوض بيتسجل حتى لو ما اتقاسش. */
            if (!$rej && $len <= 0 && (float) ($r['width_cm'] ?? 0) <= 0 && $def === 0 && (float) ($r['gsm'] ?? 0) <= 0) continue;

            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'roll_no'       => $r['roll_no'] ?? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'length_m'      => $len,
                'labeled_length_m' => ($r['labeled_length_m'] ?? '') !== '' ? $r['labeled_length_m'] : null,
                'width_cm'      => $r['width_cm'] ?? null,
                'status'        => $rej ? 'rejected' : 'ok',
                'net_kg'        => $r['net_kg'] ?? null,
                'gsm'           => ($r['gsm'] ?? '') !== '' ? $r['gsm'] : null,
                'defects_count' => $def,
                'defect_pct'    => $len > 0 ? round(($def / $len) * 100, 3) : null,
                'defect_desc'   => $defectDesc,
                'notes'         => $r['notes'] ?? null,
            ]);
        }
    }
}
