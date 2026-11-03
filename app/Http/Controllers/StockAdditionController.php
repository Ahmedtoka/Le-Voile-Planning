<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\StockAdditionLine;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\DocNumber;
use App\Services\FlowEngine;
use App\Services\FlowMessage;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إذن الإضافة — أول مستند في دورة القماش.
 *
 * لما القماش يوصل، بيتسجّل هنا وبيتولّد منه الحوض (الرسالة) بحالة
 * «تحت الفحص». الكمية بتدخل المخزن **محجوزة** — ممنوع تشغيلها.
 * الإفراج بيحصل بإذن الاستلام الخام بعد ما الفحص والمعمل يخلّصوا.
 */
class StockAdditionController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = StockAddition::with(['supplier', 'warehouse', 'consignment']);
        $this->applyFilters($q, $request,
            ['doc_no', 'supplier_doc_no', 'paper_serial', 'consignment_no', 'supplier.name'],
            'doc_date',
            ['status' => 'status', 'supplier_id' => 'supplier_id', 'warehouse_id' => 'warehouse_id']
        );

        $base = StockAddition::query();

        return view('additions.index', [
            'title'   => 'أذون الإضافة (تحت الفحص)',
            // approved = لسه موصلش حاجة · receiving = وصل جزء ومستني الباقي
            'awaitingPos' => PurchaseOrder::with(['supplier', 'lines'])
                ->whereIn('stage', ['approved', 'receiving'])
                ->latest('id')->limit(8)->get(),
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','total_qty','total_rolls','status','consignment_no'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي']],
                ['name' => 'supplier_id', 'label' => 'كل الموردين', 'options' => \App\Models\Supplier::orderBy('name')->pluck('name','id'), 'width' => 160],
                ['name' => 'warehouse_id', 'label' => 'كل المخازن', 'options' => \App\Models\Warehouse::orderBy('name')->pluck('name','id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'إجمالي الأذون', 'value' => $base->count(),
                 'note' => 'كل أذون الإضافة المسجلة.'],
                ['label' => 'نزلت الفحص', 'value' => (clone $base)->where('status','approved')->count(), 'tone' => 'ok',
                 'note' => 'اتسجلت واتولّدت رسايلها ونزلت طابور الفحص.'],
                ['label' => 'مسودات', 'value' => (clone $base)->where('status','draft')->count(), 'tone' => 'muted',
                 'note' => 'لسه ما نزلتش الفحص.'],
                ['label' => 'كمية داخلة', 'value' => number_format((float) (clone $base)->where('status','approved')->sum('total_qty'), 0), 'tone' => 'brand',
                 'note' => 'مجموع كميات الأذون بوحدتها (كجم/طن/قطعة) — للوزن الدقيق شوف رصيد الرسايل.'],
                ['label' => 'أتواب داخلة', 'value' => number_format((int) (clone $base)->where('status','approved')->sum('total_rolls')),
                 'note' => 'العدد اللي المورد قال عليه — الفحص هيجرده.'],
            ],
        ]);
    }

    /**
     * إذن إضافة جديد.
     * لو جاي بطلب شراء (?purchase_order_id=)، السطور بتتملى تلقائيًا من
     * أصناف الطلب بالكميات المتبقية — أمين المخزن يكمّل عدد الأتواب
     * والكميات الفعلية اللي وصلت وخلاص.
     */
    public function create(Request $request)
    {
        $row = new StockAddition([
            'doc_date'     => now()->toDateString(),
            'status'       => 'draft',
            // «استلام حاويات» = بدون دورة فحص — الإذن نفسه هو النهائي
            'receipt_type' => $request->get('type') === 'container' ? 'container' : 'normal',
        ]);
        $preset   = [];
        $po       = null;
        $sourcing = null;
        $sourcings = collect();

        if ($poId = $request->get('purchase_order_id')) {
            $po = PurchaseOrder::with(['lines.color', 'lines.fabricType', 'supplier', 'sourcings.supplier', 'sourcings.lines'])->find($poId);
        }

        if ($po) {
            /* التسكينات (الميتينج 37): الاستلام بيتم على تسكينة (مورد + كمية).
               لو فيه أكتر من تسكينة عليها باقي، المخزن يختار؛ ولو واحدة بتتاخد لوحدها. */
            $sourcings = $po->sourcings->filter(fn ($s) => $s->outstanding_qty > 0.0005)->values();
            if ($sid = (int) $request->get('po_sourcing_id')) $sourcing = $po->sourcings->firstWhere('id', $sid);
            if (!$sourcing) $sourcing = $sourcings->first() ?: $po->sourcings->last();

            $row->purchase_order_id = $po->id;
            $row->po_sourcing_id    = $sourcing?->id;
            $row->supplier_id       = $sourcing?->supplier_id ?: $po->supplier_id;
            $row->warehouse_id      = ($sourcing?->warehouse_id ?: $po->warehouse_id)
                ?: Warehouse::where('type', 'fabric')->value('id');

            // نوع الاستلام قرار صاحب الطلب — مش المخزن
            $row->receipt_type = $po->inspection_exempt ? 'container' : 'normal';

            foreach ($po->lines as $l) {
                $remaining = max(0, (float) $l->qty - (float) $l->received_qty);
                // البند اللي اتقفل (اكتمل) ما بيظهرش تاني في الإذن
                if ($l->is_closed || $remaining <= 0) continue;

                $srcLine = $sourcing?->lines->firstWhere('purchase_order_line_id', $l->id);
                // الطلب متسكّن على تسكينات؟ يظهر بس اللي على التسكينة دي وليه باقي
                if ($po->sourcings->isNotEmpty() && (!$srcLine || $srcLine->outstanding_qty <= 0.0005)) continue;

                $preset[] = $this->poLineArray($l) + [
                    'po_sourcing_line_id' => $srcLine?->id,
                    // بوحدة سطر الطلب — التسكينة ممكن تكون متسجلة بوحدة تانية (طن)
                    'src_qty'             => $srcLine ? \App\Services\DocumentEffects::toUnit($srcLine->qty, $srcLine->unit, $l->unit) : null,
                    'src_received'        => $srcLine ? \App\Services\DocumentEffects::toUnit($srcLine->received_qty, $srcLine->unit, $l->unit) : null,
                    'rolls_count'  => '',
                    'qty'          => '',        // أمين المخزن بيكتب المستلم فعلًا
                    'color_id'     => $l->color_id,
                    'color_action' => null,
                ];
            }
        }

        return view('additions.form', $this->formData([
            'row'       => $row,
            'mode'      => 'create',
            'preset'    => $preset,
            'poInfo'    => $po,
            'sourcing'  => $sourcing,
            'sourcings' => $sourcings,
            'consignmentPreview' => $po ? $this->consignmentPreview($po) : null,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $sa = DB::transaction(function () use ($data) {
            $sa = StockAddition::create($data['header'] + [
                'doc_no'     => DocNumber::next('stock_addition', 'stock_additions'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($sa, $data['lines']);
            $sa->refresh()->recalcTotals();
            return $sa;
        });

        // الحفظ = خلاص: الرسايل بتتولّد والقماش بينزل الفحص فورًا
        return $this->finish($sa->refresh());
    }

    public function edit(StockAddition $stock_addition)
    {
        $stock_addition->load([
            'lines.color', 'lines.fabricType', 'lines.accessory',
            'lines.poLine.color', 'lines.poLine.fabricType',
            'lines.consignment.fabricType', 'lines.consignment.color',
            'consignment',
        ]);

        // سطور التسكينة — الباقي بيتحسب على اللي اتسكّن مع المورد ده، مش على الطلب كله
        $srcLines = \App\Models\PoSourcingLine::whereIn('id', $stock_addition->lines->pluck('po_sourcing_line_id')->filter())->get()->keyBy('id');

        // نفس شكل بيانات الإنشاء — عشان الشاشة ترسم السطور المرتبطة بالطلب صح
        $saved = $stock_addition->lines->map(function ($l) use ($srcLines) {
            $base = $l->only(['id', 'item_code', 'item_name', 'fabric_type_id', 'color_id',
                              'po_color_id', 'po_line_id', 'po_sourcing_line_id', 'color_action', 'rolls_count', 'qty', 'unit',
                              'fabric_form', 'bag_g', 'tube_g', 'gross_qty', 'packaging_kg',
                              'remainder_note']);
            $base['remainder_eta'] = $l->remainder_eta?->format('Y-m-d');
            if (($src = $srcLines->get($l->po_sourcing_line_id)) && $l->poLine) {
                $base['src_qty']      = \App\Services\DocumentEffects::toUnit($src->qty, $src->unit, $l->poLine->unit);
                $base['src_received'] = \App\Services\DocumentEffects::toUnit($src->received_qty, $src->unit, $l->poLine->unit);
            }
            return $l->poLine ? $this->poLineArray($l->poLine) + $base : $base;
        })->all();

        return view('additions.form', $this->formData([
            'row'      => $stock_addition,
            'mode'     => 'edit',
            'preset'   => $saved,
            'sourcing' => $stock_addition->sourcing,
            'consignmentPreview' => !$stock_addition->consignment_no && $stock_addition->purchaseOrder
                ? $this->consignmentPreview($stock_addition->purchaseOrder) : null,
        ]));
    }

    /** معاينة رقم الرسالة — النمط نفسه، والرقم النهائي بيتأكد عند الاعتماد */
    private function consignmentPreview(PurchaseOrder $po): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $po->supplier?->code ?? 'CN'), 0, 4)) ?: 'CN';
        $seq    = \App\Models\Consignment::whereDate('arrival_date', now()->toDateString())->count();

        return DocNumber::consignmentNo($prefix, now(), $po->po_no, $seq);
    }

    /** بيانات سطر الطلب اللي بتتعرض كليبلات ثابتة في الإذن */
    private function poLineArray(\App\Models\PurchaseOrderLine $l): array
    {
        return [
            'po_line_id'     => $l->id,
            'item_code'      => $l->color?->code,
            'item_name'      => trim(($l->fabricType?->name ?? '') . ' ' . ($l->color?->name ?? '')),
            'fabric_type_id' => $l->fabric_type_id,
            'fabric_name'    => $l->fabricType?->name ?? '—',
            'po_color_id'    => $l->color_id,
            'po_color_label' => $l->color?->label ?? ($l->color?->code ?? '—'),
            'po_ordered'     => (float) $l->qty,
            'po_min'         => (float) $l->min_allowed_qty,   // حد الإقفال — الكمية ناقص نسبة الزيادة
            'po_received'    => (float) $l->received_qty,
            'unit'           => $l->unit,     // الوحدة من الطلب — كيلو أو متر (الميتينج 37)
        ];
    }

    public function update(Request $request, StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($stock_addition, $data) {
            $stock_addition->update($data['header']);
            $this->syncLines($stock_addition, $data['lines'], true);
            $stock_addition->refresh()->recalcTotals();
        });

        return $this->finish($stock_addition->refresh());
    }

    /**
     * الحُرّاس اللي لازم يعدّوا قبل ما الإذن يخلص.
     * بيرجّع نص المشكلة، أو null لو كل حاجة تمام.
     *
     * مفصولين في ميثود لوحدها لأن «حفظ» بقى هو نفسه «خلّص» —
     * فالحفظ بينده عليهم، ولو وقع الإذن بيفضل مسودة والمستخدم يصلّح.
     */
    /** سطر التسكينة اللي الصنف ده جاي عليه — بنفس طريقة DocumentEffects وقت الإقفال */
    private function sourcingLineFor(StockAddition $sa, $line, $poLine): ?\App\Models\PoSourcingLine
    {
        if ($line->po_sourcing_line_id) return \App\Models\PoSourcingLine::find($line->po_sourcing_line_id);
        if (!$sa->po_sourcing_id) return null;
        return \App\Models\PoSourcingLine::where('po_sourcing_id', $sa->po_sourcing_id)
            ->where('purchase_order_line_id', $poLine->id)->first();
    }

    /**
     * المشكلة بترجع [اسم الحقل => الرسالة] — الحقل بيتعلّم بالأحمر في الشاشة والرسالة تحته.
     * أرقام السطور على ترتيب سطور الإذن المحفوظ — نفس ترتيبها في شاشة التعديل.
     */
    private function guard(StockAddition $stock_addition): ?array
    {
        $lines = $stock_addition->lines()->orderBy('id')->get()->values();
        $stock_addition->setRelation('lines', $lines);

        if (!$lines->count()) {
            return ['msg' => 'مينفعش تحفظ إذن فاضي — اكتب الكمية لصنف واحد على الأقل.'];
        }

        // لازم عدد أتواب على كل سطر قماش — الفحص (أو الجرد) بيتم عليه
        foreach ($lines as $i => $line) {
            if ($line->fabric_type_id && (int) $line->rolls_count <= 0) {
                return ["lines.$i.rolls_count" => $stock_addition->receipt_type === 'container'
                    ? 'اكتب عدد الأتواب — الجرد والفحص الاستدلالي بيتم عليه.'
                    : 'اكتب عدد الأتواب — الفحص هيجرد عليه.'];
            }
        }

        // أكتر من لون في نفس الاستلامة عادي — كل لون بياخد رسالته (حوضه) لوحده

        /* رقم رسالة مكتوب يدوي؟ لازم ما يكونش مستخدم قبل كده —
           وإلا هيدمج الاستلامة دي في حوض استلامة تانية ويبوّظ الاتنين. */
        $manualNo = trim((string) $stock_addition->consignment_no);
        if ($manualNo !== '') {
            $taken = \App\Models\Consignment::where('consignment_no', $manualNo)
                ->when($stock_addition->consignment_id, fn ($q) =>
                    $q->where('id', '!=', $stock_addition->consignment_id))
                ->exists();
            if ($taken) {
                return ['consignment_no' => 'رقم الرسالة «' . $manualNo . '» مستخدم قبل كده لاستلامة تانية — '
                    . 'سيبه فاضي والسيستم هيولّد رقم جديد، أو اكتب رقم مختلف.'];
            }
        }

        /* انحراف اللون الأول — لازم قرار قبل أي حسبة على الطلب،
           لأن القرار نفسه هو اللي بيحدد السطر اللي الكمية هتتحسب عليه. */
        foreach ($lines as $i => $line) {
            if ($line->fabric_type_id && $line->po_color_id
                && $line->color_id != $line->po_color_id && !$line->color_action) {
                return ["lines.$i.color_id" => 'اللون الواصل مختلف عن المطلوب — اختار القرار: '
                    . 'تسكينه مكان اللون المطلوب، أو فتح طلب جديد والأصلي يفضل مطلوب.'];
            }
        }

        /* الاستلام الجزئي — لاين لاين: كل صنف اتستلم أقل من المطلوب لازم
           يبقى على سطره «الباقي هيوصل إمتى». بنقيس بمسطرة الإقفال نفسها
           (الحد الأدنى المقبول = الكمية ناقص نسبة الزيادة) — عشان توريد
           كامل بفرق طبيعي ما يطلبش تاريخ لباقي مش موجود. */
        if ($po = $stock_addition->purchaseOrder) {
            $po->load('lines');

            foreach ($lines as $i => $line) {
                if (!$line->fabric_type_id || $line->color_action === 'new_po') continue;

                $poLine = $line->po_line_id
                    ? $po->lines->firstWhere('id', $line->po_line_id)
                    : $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id
                        && ($line->color_action === 'substitute' && $line->po_color_id
                              ? $line->po_color_id : $line->color_id) == $l->color_id);
                if (!$poLine) continue;

                // المقارنة مع الطلب على القائم — الكيس والماسورة مش باقي على المورد
                $incoming = \App\Services\DocumentEffects::toUnit((float) ($line->gross_qty ?: $line->qty), $line->unit, $poLine->unit);

                /* الطلب متسكّن؟ الباقي على المورد ده = تسكينته بس — باقي الطلب على تسكينة تانية
                   (طالب 2,500 ومسكّن 1,000 على المورد ده ووصل 1,000 ⇒ مفيش باقي عليه) */
                $srcLine = $this->sourcingLineFor($stock_addition, $line, $poLine);
                $left = $srcLine
                    ? max(0, \App\Services\DocumentEffects::toUnit((float) $srcLine->qty - (float) $srcLine->received_qty, $srcLine->unit, $poLine->unit) - $incoming)
                    : max(0, (float) $poLine->min_allowed_qty - (float) $poLine->received_qty - $incoming);

                if ($left > 0.0001 && !$line->remainder_eta) {
                    $name = trim(($poLine->fabricType?->name ?? '') . ' ' . ($poLine->color?->code ?? ''));
                    return ["lines.$i.remainder_eta" => '«' . $name . '» اتستلم جزئي — باقي ' . ($srcLine ? 'على التسكينة ' : '')
                        . rtrim(rtrim(number_format($left, 3), '0'), '.') . ' ' . $poLine->unit
                        . '. حدد «الباقي هيوصل إمتى؟» — لو المورد مش محدد، حط تاريخ تقديري واكتب السبب في الملاحظة.'];
                }
            }
        }

        /* حارس نسبة الزيادة: الوصول هو الاستلام الفعلي على الطلب.
           طالب 50 بزيادة 5%؟ أقصى استلام تراكمي = 52.5 — أكتر من كده بيترفض. */
        if ($po = $stock_addition->purchaseOrder) {
            $po->load('lines');
            foreach ($lines as $i => $line) {
                if (!$line->fabric_type_id) continue;
                if ($line->color_action === 'new_po') continue;   // مش بيتحسب على الطلب الأصلي

                $matchColor = $line->color_action === 'substitute' && $line->po_color_id
                    ? $line->po_color_id : $line->color_id;

                $poLine = $line->po_line_id
                    ? $po->lines->firstWhere('id', $line->po_line_id)
                    : $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id && $l->color_id == $matchColor);
                if (!$poLine) continue;

                $qty = \App\Services\DocumentEffects::toUnit((float) ($line->gross_qty ?: $line->qty), $line->unit, $poLine->unit);
                if ((float) $poLine->received_qty + $qty > $poLine->max_allowed_qty + 0.0001) {
                    $this->alertTolerance($stock_addition, $po, $poLine, $qty);
                    return ["lines.$i." . ($line->gross_qty ? 'gross_qty' : 'qty') => 'الكمية بتتعدى نسبة الزيادة المسموح بها (' . $poLine->tolerance_pct
                        . '%) على الطلب ' . $po->po_no . ' — المستلم قبل كده '
                        . rtrim(rtrim(number_format((float) $poLine->received_qty, 2), '0'), '.')
                        . ' والمطلوب ' . rtrim(rtrim(number_format((float) $poLine->qty, 2), '0'), '.')
                        . ' ' . $poLine->unit . '. التخطيط والمشتريات اتبلّغوا.'];
                }
            }
        }
        return null;
    }

    /**
     * تجاوز نسبة الزيادة ⇒ إشعار للتخطيط والمشتريات (مرة واحدة لكل إذن).
     * القرار بتاعهم: التخطيط يزوّد النسبة أو يعدّل الطلب، أو المشتريات تحلّها مع المورد —
     * الزيادة مش مسؤولية التخطيط ومفيش طلب شراء جديد ليها (الميتينج).
     */
    private function alertTolerance(StockAddition $sa, PurchaseOrder $po, \App\Models\PurchaseOrderLine $poLine, float $incoming): void
    {
        if ($sa->tolerance_alerted_at) return;

        $name = trim(($poLine->fabricType?->name ?? '') . ' ' . ($poLine->color?->code ?? ''));
        $body = 'الإذن ' . $sa->doc_no . ' على الطلب ' . $po->po_no . ' — الصنف «' . $name . '»: المطلوب '
            . rtrim(rtrim(number_format((float) $poLine->qty, 3), '0'), '.') . ' ' . $poLine->unit
            . ' بزيادة ' . rtrim(rtrim(number_format((float) $poLine->tolerance_pct, 2), '0'), '.') . '%'
            . '، والوارد التراكمي ' . rtrim(rtrim(number_format((float) $poLine->received_qty + $incoming, 3), '0'), '.')
            . '. المخزن مش هيقدر يستلم لحد ما التخطيط يزوّد النسبة أو يعدّل الطلب، أو المشتريات تحلها مع المورد.';

        \App\Services\Notifier::broadcastToRole('planner', 'tolerance_breach', 'توريد متعدي نسبة الزيادة', $body,
            route('purchase-orders.edit', $po), 'danger');
        \App\Services\Notifier::broadcastToRole('purchasing', 'tolerance_breach', 'توريد متعدي نسبة الزيادة — راجع المورد', $body,
            route('purchase-orders.edit', $po), 'danger');

        $sa->forceFill(['tolerance_alerted_at' => now()])->saveQuietly();
    }

    /**
     * الحفظ = خلاص. مفيش زرار «نزّل للفحص» منفصل.
     *
     * القماش وصل فعلًا والفاحص مستنيه — فأول ما الإذن يتحفظ صح،
     * بيتولّد حوض (رسالة) لكل لون، الطلب بيتحدّث، والقماش بينزل
     * طابور الفحص في نفس اللحظة (أو المخزن على طول لو الاستلام حاويات).
     */
    private function finish(StockAddition $stock_addition)
    {
        if ($problem = $this->guard($stock_addition)) {
            // الإذن بيفضل مسودة — المستخدم يصلّح ويحفظ تاني
            return redirect()->route('stock-additions.edit', $stock_addition)
                ->withErrors($problem)->with('draft_saved', true);
        }

        FlowEngine::complete($stock_addition,
            'الإذن ' . $stock_addition->doc_no . ' اتحفظ ونزل الخطوة اللي بعدها');

        $consignments = $stock_addition->lines()->whereNotNull('consignment_id')
            ->pluck('consignment_id')->unique()->count();

        \App\Services\ActivityLogger::log('approved', $stock_addition,
            'الإذن ' . $stock_addition->doc_no . ' اتنزّل للفحص — اتولّد ' . $consignments . ' رسالة');

        return redirect()->route('stock-additions.edit', $stock_addition)
            ->with(FlowMessage::flash('addition.approved', $stock_addition));
    }

    /** الراوت القديم — بيوصّل لنفس التخليص عشان أي لينك قديم ما يقعش */
    public function submit(StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isEditable(), 403);

        return $this->finish($stock_addition);
    }

    public function print(StockAddition $stock_addition)
    {
        $stock_addition->load(['lines.color', 'lines.fabricType', 'lines.accessory', 'supplier', 'warehouse']);
        return view('print.stock_addition', ['sa' => $stock_addition]);
    }

    public function destroy(StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isDraft(), 403);
        $stock_addition->delete();
        return redirect()->route('stock-additions.index')->with('success', 'تم الحذف.');
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'إذن إضافة',
            'suppliers'    => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses'   => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes'  => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'accessories'  => Accessory::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'pos'          => PurchaseOrder::with('supplier')
                                  ->whereIn('stage', ['approved', 'receiving'])
                                  ->latest('id')->get()
                                  ->mapWithKeys(fn ($p) => [$p->id =>
                                      $p->po_no . ' — ' . ($p->supplier?->name ?? '')
                                      . ' · توريد ' . ($p->delivery_date?->format('Y-m-d') ?? '—')]),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'         => ['required', 'date'],
            'paper_serial'     => ['required', 'string', 'max:40'],   // مسلسل إذن الإضافة الورقي — إجباري
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'warehouse_id'      => ['required', 'exists:warehouses,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'po_sourcing_id'    => ['nullable', 'exists:po_sourcings,id'],
            'consignment_no'    => ['nullable', 'string', 'max:60'],
            // إجباري: بيتربط بالفحص والمعمل والاستلام وبيظهر كلينك في كل الشاشات
            'supplier_doc_no'   => ['required', 'string', 'max:60'],
            'receipt_type'      => ['nullable', 'in:normal,container'],
            'remainder_eta'     => ['nullable', 'date'],
            'remainder_note'    => ['nullable', 'string', 'max:191'],
            'notes'             => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.item_code'      => ['nullable', 'string', 'max:40'],
            'lines.*.item_name'      => ['nullable', 'string', 'max:191'],
            'lines.*.fabric_type_id' => ['nullable', 'exists:fabric_types,id'],
            'lines.*.color_id'       => ['nullable', 'exists:colors,id'],
            'lines.*.po_color_id'    => ['nullable', 'exists:colors,id'],
            // السطر لازم يكون من نفس الطلب المختار — مش أي سطر في السيستم
            'lines.*.po_line_id'     => ['nullable',
                \Illuminate\Validation\Rule::exists('purchase_order_lines', 'id')
                    ->where('purchase_order_id', (int) $request->input('purchase_order_id'))],
            'lines.*.po_sourcing_line_id' => ['nullable', 'exists:po_sourcing_lines,id'],
            'lines.*.color_action'   => ['nullable', 'in:substitute,new_po'],
            // الصافي والقائم (الميتينج 37) — للكيلو بس
            'lines.*.fabric_form'    => ['nullable', 'in:open,closed'],
            'lines.*.bag_g'          => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'lines.*.tube_g'         => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'lines.*.gross_qty'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.remainder_eta'  => ['nullable', 'date'],
            'lines.*.remainder_note' => ['nullable', 'string', 'max:191'],
            'lines.*.accessory_id'   => ['nullable', 'exists:accessories,id'],
            'lines.*.rolls_count'    => ['nullable', 'integer', 'min:0'],
            // السطر الفاضي = الصنف ده ما وصلش المرة دي — بيتشال لوحده
            'lines.*.qty'            => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit'           => ['required', 'string', 'max:20'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'warehouse_id'        => 'المخزن',
            'supplier_id'         => 'المورد',
            'supplier_doc_no'     => 'رقم إذن المورد',
            'paper_serial'        => 'مسلسل إذن الإضافة',
            'lines.*.qty'         => 'الكمية',
            'lines.*.rolls_count' => 'عدد الأتواب',
        ]);

        /* الصافي من القائم (الميتينج 37): للكيلو — الصافي = القائم − (كيس + ماسورة) × الأتواب ÷ 1000.
           بالمتر مفيش وزن ولا كيس ولا ماسورة. السيرفر هو اللي بيحسب — مش الشاشة. */
        foreach ($v['lines'] as &$l) {
            if (lv_is_meter($l['unit'] ?? 'كجم')) {
                $l['fabric_form'] = null; $l['bag_g'] = null; $l['tube_g'] = null; $l['gross_qty'] = null; $l['packaging_kg'] = 0;
                continue;
            }
            $gross = (float) ($l['gross_qty'] ?? 0);
            if ($gross > 0) {
                if (($l['fabric_form'] ?? '') !== 'open') $l['tube_g'] = null;   // المقفول كيس بس
                $pack = lv_packaging_kg((float) ($l['bag_g'] ?? 0), (float) ($l['tube_g'] ?? 0), (int) ($l['rolls_count'] ?? 0));
                $l['packaging_kg'] = $pack;
                $l['qty'] = round(max(0, $gross - $pack), 3);
            } else {
                $l['packaging_kg'] = 0;
            }
        }
        unset($l);

        // اللي ما اتكتبلوش كمية = ما وصلش المرة دي — بيتشال من الإذن
        $lines = array_values(array_filter($v['lines'], fn ($l) => (float) ($l['qty'] ?? 0) > 0));
        unset($v['lines']);

        // نوع الاستلام بييجي من طلب الشراء (قرار التخطيط) — المخزن ما بيغيّروش
        if (!empty($v['purchase_order_id']) && ($po = PurchaseOrder::find($v['purchase_order_id']))) {
            $v['receipt_type'] = $po->inspection_exempt ? 'container' : 'normal';
        }

        if (!count($lines)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['msg' =>
                'اكتب الكمية المستلمة لصنف واحد على الأقل — السطور الفاضية معناها الصنف ما وصلش.']);
        }

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(StockAddition $sa, array $lines, bool $replace = false): void
    {
        if ($replace) $sa->lines()->delete();

        /* السطور المضافة يدوي على إذن مربوط بطلب: بنكمّل po_color_id تلقائيًا
           عشان انحراف اللون ما يتسرّبش من غير قرار —
           لو اللون موجود في الطلب ⇒ مطابق، ولو الخامة موجودة بألوان تانية بس
           ⇒ بنسجل المطلوب وبيظهر سؤال القرار في الشاشة. */
        $poLines = $sa->purchase_order_id
            ? \App\Models\PurchaseOrderLine::where('purchase_order_id', $sa->purchase_order_id)->get()
            : collect();

        foreach ($lines as $l) {
            $l['rolls_count'] = $l['rolls_count'] ?? 0;

            if (empty($l['po_color_id']) && !empty($l['fabric_type_id']) && $poLines->isNotEmpty()) {
                $sameFabric = $poLines->where('fabric_type_id', $l['fabric_type_id']);
                if ($sameFabric->isNotEmpty()) {
                    $exact = $sameFabric->first(fn ($p) => $p->color_id == ($l['color_id'] ?? null));
                    $l['po_color_id'] = $exact ? $exact->color_id : $sameFabric->first()->color_id;
                }
            }

            StockAdditionLine::create(['stock_addition_id' => $sa->id] + $l);
        }
    }
}
