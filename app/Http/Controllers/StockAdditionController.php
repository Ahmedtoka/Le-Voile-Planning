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
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
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
            ['doc_no', 'paper_serial', 'consignment_no', 'supplier.name'],
            'doc_date',
            ['status' => 'status', 'supplier_id' => 'supplier_id', 'warehouse_id' => 'warehouse_id']
        );

        $base = StockAddition::query();

        return view('additions.index', [
            'title'   => 'أذون الإضافة (تحت الفحص)',
            // approved = لسه موصلش حاجة · receiving = وصل جزء ومستني الباقي
            'awaitingPos' => PurchaseOrder::with(['supplier', 'lines'])
                ->whereIn('stage', ['approved', 'receiving'])
                ->orderBy('delivery_date')->limit(8)->get(),
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','total_qty','total_rolls','status','consignment_no'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','pending'=>'تحت الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض']],
                ['name' => 'supplier_id', 'label' => 'كل الموردين', 'options' => \App\Models\Supplier::orderBy('name')->pluck('name','id'), 'width' => 160],
                ['name' => 'warehouse_id', 'label' => 'كل المخازن', 'options' => \App\Models\Warehouse::orderBy('name')->pluck('name','id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'إجمالي الأذون', 'value' => $base->count(),
                 'note' => 'كل أذون الإضافة المسجلة.'],
                ['label' => 'مستنية اعتماد', 'value' => (clone $base)->where('status','pending')->count(), 'tone' => 'warn',
                 'note' => 'اتبعتت ولسه ما اتعمدتش.'],
                ['label' => 'مسودات', 'value' => (clone $base)->where('status','draft')->count(), 'tone' => 'muted',
                 'note' => 'لسه ما اتبعتتش للاعتماد.'],
                ['label' => 'كجم داخلة', 'value' => number_format((float) (clone $base)->where('status','approved')->sum('total_qty'), 0), 'tone' => 'brand',
                 'note' => 'إجمالي الكميات اللي دخلت المخزن بالأذون المعتمدة.'],
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
        $preset = [];
        $po     = null;

        if ($poId = $request->get('purchase_order_id')) {
            $po = PurchaseOrder::with(['lines.color', 'lines.fabricType', 'supplier'])->find($poId);
        }

        if ($po) {
            $row->purchase_order_id = $po->id;
            $row->supplier_id       = $po->supplier_id;
            $row->warehouse_id      = $po->warehouse_id
                ?: Warehouse::where('type', 'fabric')->value('id');

            foreach ($po->lines as $l) {
                $remaining = max(0, (float) $l->qty - (float) $l->received_qty);
                if ($remaining <= 0) continue;

                /* البيانات الجاية من الطلب ثابتة زي ما هي:
                   طلبت طن؟ بتستلم بالطن — مفيش تحويل ولا تعديل على الطلب. */
                $preset[] = $this->poLineArray($l) + [
                    'rolls_count'  => '',
                    'qty'          => '',        // أمين المخزن بيكتب المستلم فعلًا
                    'color_id'     => $l->color_id,
                    'color_action' => null,
                ];
            }
        }

        return view('additions.form', $this->formData([
            'row'    => $row,
            'mode'   => 'create',
            'preset' => $preset,
            'poInfo' => $po,
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

        return redirect()->route('stock-additions.edit', $sa)->with('success', 'تم إنشاء الإذن ' . $sa->doc_no);
    }

    public function edit(StockAddition $stock_addition)
    {
        $stock_addition->load([
            'lines.color', 'lines.fabricType', 'lines.accessory',
            'lines.poLine.color', 'lines.poLine.fabricType',
            'approval.steps', 'consignment',
        ]);

        // نفس شكل بيانات الإنشاء — عشان الشاشة ترسم السطور المرتبطة بالطلب صح
        $saved = $stock_addition->lines->map(function ($l) {
            $base = $l->only(['id', 'item_code', 'item_name', 'fabric_type_id', 'color_id',
                              'po_color_id', 'po_line_id', 'color_action', 'rolls_count', 'qty', 'unit',
                              'remainder_note']);
            $base['remainder_eta'] = $l->remainder_eta?->format('Y-m-d');
            return $l->poLine ? $this->poLineArray($l->poLine) + $base : $base;
        })->all();

        return view('additions.form', $this->formData([
            'row'    => $stock_addition,
            'mode'   => 'edit',
            'preset' => $saved,
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
            'unit'           => $l->unit,     // طلبت طن ⇒ الوحدة طن — ثابتة
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

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isEditable(), 403);
        if (!$stock_addition->lines()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل إذن فاضي.']);
        }

        // لازم عدد أتواب على كل سطر قماش — الفحص (أو الجرد) بيتم عليه
        $missing = $stock_addition->lines()
            ->whereNotNull('fabric_type_id')->where('rolls_count', '<=', 0)->count();

        if ($missing) {
            return back()->withErrors(['msg' => $stock_addition->receipt_type === 'container'
                ? 'لازم تكتب عدد الأتواب لكل صنف قماش — الجرد والفحص الاستدلالي بيتم عليه.'
                : 'لازم تكتب عدد الأتواب لكل صنف قماش — الفحص هيجرد عليه.']);
        }

        /* الرسالة (الحوض) بتتولد بلون واحد — أكتر من لون في نفس الإذن
           هيتسجل كله على لون أول سطر ويبوّظ التتبع. كل لون بإذن منفصل. */
        $fabricColors = $stock_addition->lines
            ->filter(fn ($l) => $l->fabric_type_id)
            ->pluck('color_id')->filter()->unique();

        if ($fabricColors->count() > 1) {
            return back()->withErrors(['msg' =>
                'الإذن فيه أكتر من لون قماش — الرسالة بتتولد بلون واحد. '
                . 'اعمل إذن إضافة منفصل لكل لون (سيب في الإذن ده لون واحد بس).']);
        }

        /* انحراف اللون الأول — لازم قرار قبل أي حسبة على الطلب،
           لأن القرار نفسه هو اللي بيحدد السطر اللي الكمية هتتحسب عليه. */
        foreach ($stock_addition->lines as $line) {
            if ($line->fabric_type_id && $line->po_color_id
                && $line->color_id != $line->po_color_id && !$line->color_action) {
                return back()->withErrors(['msg' =>
                    'فيه صنف وصل بلون مختلف عن المطلوب في الطلب — افتح الإذن واختار القرار: '
                    . 'تسكينه مكان اللون المطلوب، أو فتح طلب جديد والأصلي يفضل مطلوب.']);
            }
        }

        /* الاستلام الجزئي — لاين لاين: كل صنف اتستلم أقل من المطلوب لازم
           يبقى على سطره «الباقي هيوصل إمتى». بنقيس بمسطرة الإقفال نفسها
           (الحد الأدنى المقبول = الكمية ناقص نسبة الزيادة) — عشان توريد
           كامل بفرق طبيعي ما يطلبش تاريخ لباقي مش موجود. */
        if ($po = $stock_addition->purchaseOrder) {
            $po->load('lines');

            foreach ($stock_addition->lines as $line) {
                if (!$line->fabric_type_id || $line->color_action === 'new_po') continue;

                $poLine = $line->po_line_id
                    ? $po->lines->firstWhere('id', $line->po_line_id)
                    : $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id
                        && ($line->color_action === 'substitute' && $line->po_color_id
                              ? $line->po_color_id : $line->color_id) == $l->color_id);
                if (!$poLine) continue;

                $incoming = \App\Services\DocumentEffects::toUnit($line->qty, $line->unit, $poLine->unit);
                $left = max(0, (float) $poLine->min_allowed_qty - (float) $poLine->received_qty - $incoming);

                if ($left > 0.0001 && !$line->remainder_eta) {
                    $name = trim(($poLine->fabricType?->name ?? '') . ' ' . ($poLine->color?->code ?? ''));
                    return back()->withErrors(['msg' =>
                        'الصنف «' . $name . '» اتستلم جزئي — باقي '
                        . rtrim(rtrim(number_format($left, 3), '0'), '.') . ' ' . $poLine->unit
                        . '. حدد على السطر «الباقي هيوصل إمتى؟» قبل الإرسال '
                        . '(لو المورد مش محدد، حط تاريخ تقديري واكتب السبب في الملاحظة).']);
                }
            }
        }

        /* حارس نسبة الزيادة: الوصول هو الاستلام الفعلي على الطلب.
           طالب 50 بزيادة 5%؟ أقصى استلام تراكمي = 52.5 — أكتر من كده بيترفض. */
        if ($po = $stock_addition->purchaseOrder) {
            $po->load('lines');
            foreach ($stock_addition->lines as $line) {
                if (!$line->fabric_type_id) continue;
                if ($line->color_action === 'new_po') continue;   // مش بيتحسب على الطلب الأصلي

                $matchColor = $line->color_action === 'substitute' && $line->po_color_id
                    ? $line->po_color_id : $line->color_id;

                $poLine = $line->po_line_id
                    ? $po->lines->firstWhere('id', $line->po_line_id)
                    : $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id && $l->color_id == $matchColor);
                if (!$poLine) continue;

                $qty = \App\Services\DocumentEffects::toUnit($line->qty, $line->unit, $poLine->unit);
                if ((float) $poLine->received_qty + $qty > $poLine->max_allowed_qty + 0.0001) {
                    return back()->withErrors(['msg' =>
                        'الكمية بتتعدى نسبة الزيادة المسموح بها (' . $poLine->tolerance_pct
                        . '%) على الطلب ' . $po->po_no . ' — المستلم قبل كده '
                        . rtrim(rtrim(number_format((float) $poLine->received_qty, 2), '0'), '.')
                        . ' والمطلوب ' . rtrim(rtrim(number_format((float) $poLine->qty, 2), '0'), '.')
                        . ' ' . $poLine->unit . '.']);
                }
            }
        }
        ApprovalEngine::submit($stock_addition);
        return back()->with(FlowMessage::flash('addition.submitted', $stock_addition));
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
            'paper_serial'     => ['nullable', 'string', 'max:40'],
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'warehouse_id'      => ['required', 'exists:warehouses,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'consignment_no'    => ['nullable', 'string', 'max:60'],
            'supplier_doc_no'   => ['nullable', 'string', 'max:60'],
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
            'lines.*.color_action'   => ['nullable', 'in:substitute,new_po'],
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
            'lines.*.qty'         => 'الكمية',
            'lines.*.rolls_count' => 'عدد الأتواب',
        ]);

        // اللي ما اتكتبلوش كمية = ما وصلش المرة دي — بيتشال من الإذن
        $lines = array_values(array_filter($v['lines'], fn ($l) => (float) ($l['qty'] ?? 0) > 0));
        unset($v['lines']);

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
