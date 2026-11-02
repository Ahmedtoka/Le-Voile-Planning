<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\FabricType;
use App\Models\ProductModel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\DocNumber;
use App\Services\DocumentEffects;
use App\Services\FlowMessage;
use App\Services\Notifier;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * طلب الشراء — مستند واحد بيمر على إيدين، زي الورقة بالظبط.
 *
 *   ① التخطيط   يكتب اللون والصنف والكمية والوحدة ونسبة الزيادة وملاحظة لكل لون
 *               → الحفظ بينزّله للمشتريات على طول
 *   ② المشتريات تحدد المورد والسعر وطريقة الدفع وتاريخ التوريد
 *               → الحفظ بيخلّي الطلب جاهز للاستلام فورًا
 *   ③ الحسابات  بيوصلها المستحق للمتابعة وبتسجّل علمها — من غير ما توقّف حاجة
 *   ④ المخزن    بيشوف الطلب جاي ويستلم عليه أذون إضافة
 *
 * مفيش اعتمادات: كل خطوة بتنفّذ نفسها وبتفتح اللي بعدها.
 */
class PurchaseOrderController extends Controller
{
    use FiltersIndex;

    /** الأعمدة اللي مسموح الترتيب بيها في شاشات الطلبات */
    private const SORTABLE = ['po_no','po_date','delivery_date','total','total_qty','stage','status'];

    public function index(Request $request)
    {
        $q = PurchaseOrder::with(['supplier', 'requester', 'sourcer', 'financer', 'lines']);

        if ($s = $request->get('stage'))         $q->where('stage', $s);
        if ($sup = $request->get('supplier_id')) $q->where('supplier_id', $sup);
        if ($term = trim((string) $request->get('q'))) $q->where('po_no', 'like', "%{$term}%");
        if ($request->boolean('mine'))           $q->where('requested_by', auth()->id());

        if ($from = $request->get('from')) $q->whereDate('po_date', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('po_date', '<=', $to);

        $b = fn () => PurchaseOrder::query();

        return view('po.index', [
            'title'     => 'طلبات الشراء',
            'rows'      => $this->applySort($q, $request, self::SORTABLE)->paginate(25)->withQueryString(),
            'summary'   => [
                ['label' => 'مفتوحة', 'value' => $b()->whereNotIn('stage',['closed','cancelled'])->count(), 'tone' => 'brand',
                 'note' => 'لسه ما اتقفلتش.'],
                ['label' => 'عند المشتريات', 'value' => $b()->where('stage','purchasing')->count(), 'tone' => 'warn',
                 'note' => 'مستنية مورد وسعر وتاريخ توريد.'],
                ['label' => 'مستحق ما اتشافش', 'value' => $b()->whereNotNull('sourced_at')->whereNull('finance_at')
                        ->whereNotIn('stage',['closed','cancelled'])->count(), 'tone' => 'warn',
                 'note' => 'اتسعّر ولسه الحسابات ما سجّلتش علمها بيه.'],
                ['label' => 'مستحق متوقع', 'value' => number_format((float) $b()->whereIn('stage',['finance','approved','receiving'])->whereNotNull('supplier_id')->sum('total'), 0),
                 'sub' => config('lvplanning.currency'), 'tone' => 'brand',
                 'note' => 'إجمالي قيمة الطلبات اللي اتسعّرت ولسه ما اتقفلتش.'],
                ['label' => 'توريد متأخر', 'value' => $b()->whereIn('stage',['approved','receiving'])
                        ->whereNotNull('delivery_date')->whereDate('delivery_date','<',now())->count(),
                 'tone' => 'danger', 'note' => 'فات تاريخ التوريد وما وصلش كامل.'],
            ],
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'stages'    => PurchaseOrder::STAGES,
            'counts'    => PurchaseOrder::query()
                              ->selectRaw('stage, COUNT(*) c')->groupBy('stage')->pluck('c', 'stage'),
        ]);
    }

    /**
     * تاب المشتريات — كل الطلبات، بفلتر: مستنية تسعير / اتسعّرت.
     * الأحدث فوق. اللي اتسعّر بيفضل ظاهر بحالته «اتسعّر — مستني الاستلام».
     */
    public function purchasingQueue(Request $request)
    {
        $q = PurchaseOrder::with(['requester', 'supplier', 'lines.color', 'lines.fabricType', 'sourcings.supplier'])
            ->whereNotIn('stage', ['planning', 'cancelled']);

        // الفلتر: مستني / اتسعّر / الكل
        // «مستني تسعير» = لسه ما اتسكّنش خالص، أو اتسكّن جزء ولسه فيه كمية من غير مورد (الميتينج 37)
        $unsourced = fn ($qq) => $qq->whereNotIn('stage', ['closed', 'cancelled'])
            ->whereHas('lines', fn ($l) => $l->whereColumn('qty', '>', 'sourced_qty'));
        $state = $request->get('state', 'pending');
        if ($state === 'pending') $q->where(fn ($qq) => $qq->where('stage', 'purchasing')->orWhere($unsourced));
        if ($state === 'priced')  $q->whereNotIn('stage', ['purchasing'])->whereDoesntHave('lines', fn ($l) => $l->whereColumn('qty', '>', 'sourced_qty'));

        if ($term = trim((string) $request->get('q'))) $q->where('po_no', 'like', "%{$term}%");
        if ($u = $request->get('requested_by'))        $q->where('requested_by', $u);

        $pendingCount = PurchaseOrder::where(fn ($qq) => $qq->where('stage', 'purchasing')->orWhere($unsourced))->count();

        return view('po.purchasing', [
            'title'      => 'المشتريات',
            'state'      => $state,
            // الأحدث فوق — آخر طلب معمول هو أول واحد
            'rows'       => $this->applySort($q, $request, self::SORTABLE)->paginate(25)->withQueryString(),
            'requesters' => User::whereIn('id', PurchaseOrder::whereNotNull('requested_by')
                                ->pluck('requested_by')->unique())->pluck('name', 'id'),
            'counts'     => [
                'pending' => $pendingCount,
                'priced'  => PurchaseOrder::whereNotIn('stage', ['planning', 'purchasing', 'cancelled'])
                                ->whereDoesntHave('lines', fn ($l) => $l->whereColumn('qty', '>', 'sourced_qty'))->count(),
            ],
            'summary'    => [
                ['label' => 'مستنية تسعير', 'value' => $pendingCount,
                 'tone' => 'warn', 'note' => 'نزلت من التخطيط ولسه فيها كمية من غير مورد — كلها أو جزء منها.'],
                ['label' => 'أقدم طلب مستني',
                 'value' => ($old = PurchaseOrder::where('stage', 'purchasing')->oldest('requested_at')->first())
                             ? (int) $old->requested_at?->diffInDays(now(), true) . ' يوم' : '—',
                 'tone' => 'muted', 'note' => 'من ساعة ما التخطيط طلبه.'],
                ['label' => 'اتسعّرت النهارده',
                 'value' => PurchaseOrder::whereDate('sourced_at', now()->toDateString())->count(),
                 'tone' => 'ok', 'note' => 'خلّصت تسعير ونزلت للحسابات والمخزن.'],
                ['label' => 'مستنية الاستلام', 'value' => PurchaseOrder::whereIn('stage', ['approved', 'receiving'])->count(),
                 'tone' => 'brand', 'note' => 'اتسعّرت ومستنية القماش يوصل المخزن.',
                 'link' => [route('stock-additions.index'), 'أذون الإضافة']],
            ],
        ]);
    }

    /** صفحة الطلب — بند التخطيط بس، مفيش أي حاجة تانية */
    public function create()
    {
        return view('po.create', [
            'title'            => 'طلب شراء جديد',
            'colors'           => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes'      => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'models'           => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'defaultTolerance' => lv_setting('default_po_tolerance_pct', 5),
        ]);
    }

    /**
     * ① التخطيط بينشئ الطلب — والحفظ بينزّله للمشتريات تلقائيًا.
     * مفيش خطوة «نزّل» منفصلة: أول ما التخطيط يحفظ، الطلب يظهر فورًا
     * في تاب المشتريات ويوصلهم إشعار.
     */
    public function store(Request $request)
    {
        $data = $this->validatePlanning($request);

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create($data['header'] + [
                'po_no'        => DocNumber::next('purchase_order', 'purchase_orders', 'po_no'),
                'po_date'      => now()->toDateString(),   // وقت الطلب — مش بيتكتب يدوي
                'employee_id'  => auth()->id(),            // اللي عامل الطلب
                'stage'        => 'purchasing',            // الحفظ = نزول للمشتريات على طول
                'status'       => 'draft',
                'created_by'   => auth()->id(),
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);
            $this->syncLines($po, $data['lines']);
            $po->models()->sync($data['model_ids']);
            $po->recalcTotals();
            return $po;
        });

        Notifier::broadcastToRole('purchasing', 'po_sourcing',
            'طلب شراء جديد من ' . (auth()->user()?->name ?? 'التخطيط'),
            $po->po_no . ' — ' . $po->lines()->count() . ' صنف · '
                . rtrim(rtrim(number_format((float) $po->total_qty, 3), '0'), '.') . ' إجمالي'
                . ($po->planning_note ? ' · ' . $po->planning_note : ''),
            route('purchase-orders.edit', $po), 'warning');

        ActivityLogger::log('created', $po, 'طلب شراء جديد ' . $po->po_no . ' — نزل للمشتريات');

        // «اطلب» ⇒ رجوع لقايمة طلبات الشراء — الطلب بقى عند المشتريات
        return redirect()->route('purchase-orders.index')->with(FlowMessage::flash('po.created', $po));
    }

    /** صفحة عرض الطلب — قراءة بس، بتوجّهك لصفحة الدور المناسب */
    public function edit(PurchaseOrder $purchase_order)
    {
        $purchase_order->load([
            'lines.color', 'lines.fabricType', 'supplier', 'warehouse', 'models', 'productModel',
            'requester', 'sourcer', 'financer',
            'sourcings.supplier', 'sourcings.sourcer', 'sourcings.lines.poLine.fabricType', 'sourcings.lines.poLine.color',
        ]);

        return view('po.show', [
            'title' => 'طلب شراء ' . $purchase_order->po_no,
            'row'   => $purchase_order,
        ]);
    }



    /** صفحة التسعير — بند المشتريات: تسكينة جديدة طول ما فيه كمية من غير مورد */
    public function sourceForm(PurchaseOrder $purchase_order)
    {
        // لو الطلب اتسكّن كله، افتح صفحة العرض
        if (!$purchase_order->purchasingEditable()) {
            return redirect()->route('purchase-orders.edit', $purchase_order);
        }

        $purchase_order->load(['lines.color', 'lines.fabricType', 'requester', 'supplier', 'models', 'productModel',
                               'sourcings.supplier', 'sourcings.lines.poLine.fabricType', 'sourcings.lines.poLine.color']);

        return view('po.source', [
            'title'      => 'تسعير الطلب ' . $purchase_order->po_no,
            'row'        => $purchase_order,
            'suppliers'  => Supplier::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
        ]);
    }

    /**
     * ② المشتريات: تسكينة — مورد + كمية من كل بند + سعر + تاريخ توريد (الميتينج 37).
     * الطلب ممكن يتسكّن على مرات وأكتر من مورد؛ كل تسكينة بتبقى جاهزة للاستلام فورًا،
     * والباقي من غير مورد بيفضل ظاهر في «مستنية تسعير».
     */
    public function saveSourcing(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->purchasingEditable(), 403, 'الطلب اتسكّن كله — مفيش كمية باقية للتسعير.');

        $data = $request->validate([
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'warehouse_id'   => ['nullable', 'exists:warehouses,id'],
            'delivery_date'  => ['required', 'date'],
            'payment_method' => ['required', 'in:' . implode(',', array_keys(PurchaseOrder::PAYMENT_METHODS))],
            'discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'prices'                 => ['required', 'array'],
            'prices.*.id'            => ['required', 'exists:purchase_order_lines,id'],
            'prices.*.qty'           => ['nullable', 'numeric', 'min:0'],
            'prices.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'supplier_id'    => 'المورد',
            'delivery_date'  => 'تاريخ التوريد',
            'payment_method' => 'طريقة الدفع',
            'prices'         => 'الأسعار',
        ]);

        $purchase_order->load('lines');
        $picked = collect($data['prices'])->filter(fn ($p) => (float) ($p['qty'] ?? 0) > 0)->values();
        if ($picked->isEmpty()) {
            return back()->withInput()->withErrors(['prices' => 'اكتب الكمية اللي هتتسكّن مع المورد ده لصنف واحد على الأقل.']);
        }
        foreach ($picked as $p) {
            $line = $purchase_order->lines->firstWhere('id', (int) $p['id']);
            if (!$line) continue;
            if ((float) $p['qty'] > $line->unsourced_qty + 0.0005) {
                return back()->withInput()->withErrors(['prices' =>
                    'الصنف «' . trim(($line->fabricType?->name ?? '') . ' ' . ($line->color?->code ?? '')) . '»: الباقي من غير تسكين '
                    . lv_num($line->unsourced_qty, 3) . ' ' . $line->unit . ' بس — مينفعش تسكّن أكتر منه.']);
            }
            if ((float) ($p['unit_price'] ?? 0) <= 0) {
                return back()->withInput()->withErrors(['prices' => 'اكتب سعر الوحدة للأصناف اللي بتسكّنها.']);
            }
        }

        $sourcing = DB::transaction(function () use ($purchase_order, $data, $picked) {
            $sourcing = \App\Models\PoSourcing::create([
                'purchase_order_id' => $purchase_order->id,
                'seq'               => (int) $purchase_order->sourcings()->max('seq') + 1,
                'supplier_id'       => $data['supplier_id'],
                'warehouse_id'      => $data['warehouse_id'] ?? null,
                'delivery_date'     => $data['delivery_date'],
                'payment_method'    => $data['payment_method'],
                'discount_pct'      => $data['discount_pct'] ?? 0,
                'tax_pct'           => $data['tax_pct'] ?? 0,
                'notes'             => $data['notes'] ?? null,
                'sourced_by'        => auth()->id(),
                'sourced_at'        => now(),
            ]);

            foreach ($picked as $p) {
                $line = $purchase_order->lines->firstWhere('id', (int) $p['id']);
                if (!$line) continue;
                $qty = round((float) $p['qty'], 3);
                \App\Models\PoSourcingLine::create([
                    'po_sourcing_id'         => $sourcing->id,
                    'purchase_order_line_id' => $line->id,
                    'qty'                    => $qty,
                    'unit'                   => $line->unit,
                    'unit_price'             => $p['unit_price'],
                    'line_total'             => round($qty * (float) $p['unit_price'], 2),
                ]);
                // على سطر الطلب: المسكّن يزيد، وآخر سعر يتسجل للعرض، وإجمالي السطر = مجموع تسكيناته
                $line->refresh();
                $line->update([
                    'sourced_qty' => (float) $line->sourced_qty + $qty,
                    'unit_price'  => $p['unit_price'],
                    'line_total'  => (float) $line->sourcingLines()->sum('line_total'),
                ]);
            }
            $sourcing->recalcTotals();

            $purchase_order->refresh()->load('lines', 'sourcings');
            $first = $purchase_order->sourcings->first();
            $purchase_order->update([
                // بيانات الهيدر = أول تسكينة (للتوافق مع الشاشات القديمة) — التفاصيل في التسكينات
                'supplier_id'    => $purchase_order->supplier_id ?: $first->supplier_id,
                'warehouse_id'   => $purchase_order->warehouse_id ?: ($data['warehouse_id'] ?? null),
                'delivery_date'  => $purchase_order->sourcings->min('delivery_date'),
                'payment_method' => $purchase_order->payment_method ?: $data['payment_method'],
                'sourced_by'     => auth()->id(),
                'sourced_at'     => now(),
                // أي تسكينة = الطلب بقى جاهز للاستلام على التسكينة دي. الباقي من غير مورد يفضل في طابور التسعير.
                'stage'          => $purchase_order->stage === 'receiving' ? 'receiving' : 'approved',
                'status'         => 'approved',
            ]);

            $purchase_order->refresh()->recalcTotals();
            return $sourcing;
        });

        $purchase_order->refresh();
        $leftMsg = $purchase_order->hasUnsourcedQty()
            ? ' — ولسه فيه كمية من غير مورد، الطلب فاضل في «مستنية تسعير».'
            : '';

        // أمين المخزن: فيه توريد جاي بتاريخ محدد
        Notifier::broadcastToRole('storekeeper', 'po_ready',
            'طلب اتسعّر — استلام متوقع ' . $purchase_order->delivery_date?->format('Y-m-d'),
            $purchase_order->po_no . ' — ' . ($purchase_order->supplier?->name ?? '')
                . ' · ' . number_format((float) $purchase_order->total, 0) . ' ' . config('lvplanning.currency'),
            route('stock-additions.index'), 'info');

        // الحسابات: الترانزاكشن نزلها للمتابعة
        Notifier::broadcastToRole('finance', 'po_payable',
            'مستحق جديد للمتابعة',
            $purchase_order->po_no . ' — ' . ($purchase_order->supplier?->name ?? '')
                . ' · ' . number_format((float) $purchase_order->total, 0) . ' ' . config('lvplanning.currency')
                . ' · ' . ($purchase_order->payment_method ?? ''),
            route('finance.payables'), 'info');

        ActivityLogger::log('sourced', $purchase_order, 'تسكينة ' . $sourcing->seq . ' على طلب الشراء ' . $purchase_order->po_no
            . ' مع ' . ($sourcing->supplier?->name ?? '') . $leftMsg);

        $flash = FlowMessage::flash('po.sourced', $purchase_order);
        if ($leftMsg && isset($flash['flow']['title'])) $flash['flow']['title'] .= $leftMsg;

        // «احفظ» ⇒ رجوع لقايمة المشتريات
        return redirect()->route('purchasing.queue')->with($flash);
    }


    /** صفحة العلم — بند الحسابات بس */
    public function financeForm(PurchaseOrder $purchase_order)
    {
        // الطلب بيوصل الحسابات بمجرد ما يتسعّر — والحسابات بتتفرّج وتسجّل علمها،
        // من غير ما توقّف الاستلام. الطلب اللي لسه ما اتسعّرش ملوش مستحق أصلًا.
        if (! $purchase_order->sourced_at) {
            return redirect()->route('purchase-orders.edit', $purchase_order);
        }

        $purchase_order->load(['lines.color', 'lines.fabricType', 'supplier', 'warehouse', 'requester', 'sourcer']);

        return view('po.finance', [
            'title' => 'علم الحسابات — ' . $purchase_order->po_no,
            'row'   => $purchase_order,
        ]);
    }

    /**
     * ③ الحسابات: علم بس — مفيش اعتماد ولا توقيعات ولا توقيف.
     * الطلب ماشي للمخزن من ساعة التسعير. زرار «علمت» بيشيله من
     * قايمة «مستحقات جديدة» عند الحسابات وخلاص — ما بيغيّرش مرحلة الطلب.
     */
    public function financeAck(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless((bool) $purchase_order->sourced_at, 403, 'الطلب لسه ما اتسعّرش.');
        abort_if((bool) $purchase_order->finance_at, 403, 'الحسابات سجّلت علمها بالطلب ده قبل كده.');

        $data = $request->validate(['finance_note' => ['nullable', 'string']], [], ['finance_note' => 'ملاحظة الحسابات']);

        $purchase_order->forceFill([
            // المرحلة ما بتتغيّرش — الطلب أصلًا ماشي. ده تسجيل علم بس.
            'finance_by'   => auth()->id(),
            'finance_at'   => now(),
            'finance_note' => $data['finance_note'] ?? null,
        ])->save();

        ActivityLogger::log('acknowledged', $purchase_order, 'علم الحسابات بطلب الشراء ' . $purchase_order->po_no);

        // «علمت» ⇒ رجوع لقايمة الحسابات — الطلب خرج منها
        return redirect()->route('finance.payables')->with(FlowMessage::flash('po.finance_ack', $purchase_order));
    }

    public function print(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['lines.color', 'lines.fabricType', 'supplier', 'requester', 'sourcer']);
        return view('print.purchase_order', ['po' => $purchase_order]);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->stage === 'purchasing' && ! $purchase_order->sourced_at,
            403, 'مينفعش تحذف طلب اتسعّر أو دخل الاستلام.');
        $no = $purchase_order->po_no;
        $purchase_order->delete();
        ActivityLogger::log('deleted', null, 'حذف طلب شراء ' . $no);
        return redirect()->route('purchase-orders.index')->with('success', 'تم الحذف.');
    }

    // ── داخلي ────────────────────────────────────────────────────

    /** التحقق من بند التخطيط — التاريخ والموظف أوتوماتيك، مش من الفورم */
    private function validatePlanning(Request $request): array
    {
        $v = $request->validate([
            'planning_note'    => ['nullable', 'string'],
            // الطلب لأكتر من موديل — checkboxes (الميتينج: نفس الخامة لـ 6-7 موديلات)
            'product_model_ids'   => ['nullable', 'array'],
            'product_model_ids.*' => ['integer', 'exists:product_models,id'],
            // يدخل فحص ولا استلام مباشر؟ صاحب الطلب هو اللي بيحدد — مش المخزن
            'inspection_exempt'   => ['nullable', 'boolean'],
            'target_pieces'       => ['nullable', 'integer', 'min:0'],   // حاسبة الكمية: عايز تنتج كام قطعة (الميتينج 37)
            'notes'            => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.color_id'       => ['required', 'exists:colors,id'],
            'lines.*.fabric_type_id' => ['required', 'exists:fabric_types,id'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit'           => ['required', 'in:كجم,متر'],   // الميتينج 37: كيلو أو متر بس — الوحدة بتحكم الدورة كلها
            'lines.*.tolerance_pct'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'lines'                  => 'الأصناف',
            'lines.*.color_id'       => 'كود اللون',
            'lines.*.fabric_type_id' => 'اسم الصنف',
            'lines.*.qty'            => 'الكمية',
        ]);

        $lines = collect($v['lines'])->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values()->all();
        $modelIds = array_values(array_unique(array_map('intval', $v['product_model_ids'] ?? [])));
        unset($v['lines'], $v['product_model_ids']);

        $v['inspection_exempt'] = $request->boolean('inspection_exempt');
        $v['product_model_id']  = $modelIds[0] ?? null;   // للتوافق مع الشاشات القديمة

        return ['header' => $v, 'lines' => $lines, 'model_ids' => $modelIds];
    }

    private function syncLines(PurchaseOrder $po, array $lines, bool $replace = false): void
    {
        if ($replace) $po->lines()->delete();

        foreach ($lines as $i => $l) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_no'           => $i + 1,
                'color_id'          => $l['color_id'],
                'fabric_type_id'    => $l['fabric_type_id'],
                'qty'               => $l['qty'],
                'unit'              => $l['unit'],
                'unit_price'        => 0,
                'line_total'        => 0,
                'tolerance_pct'     => $l['tolerance_pct'] ?? lv_setting('default_po_tolerance_pct', 5),
                'notes'             => $l['notes'] ?? null,
            ]);
        }
    }
}
