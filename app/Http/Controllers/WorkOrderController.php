<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\Factory;
use App\Models\Marker;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderFabric;
use App\Models\WorkOrderLine;
use App\Services\ActivityLogger;
use App\Services\DocNumber;
use App\Services\FlowEngine;
use App\Services\FlowMessage;
use App\Services\PlanningEngine;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * أمر الشغل — ورقة المصنع.
 *
 * المنتج بيتعمل من **أكتر من خامة** مع بعض (طرحة تل + بونيه رباط مياي).
 * كل خامة ليها رسالتها ولونها وطول فرشتها وعرضها وعدد قطعها في الفرشة،
 * وبتتحسب بطريقة مختلفة: بالوزن (كجم) أو بالطول (متر).
 *
 * الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج — والفرق بينها وبين
 * باقي الخامات هو أهم رقم في الشاشة دي، لأنه على الورق بيبقى مخفي.
 */
class WorkOrderController extends Controller
{
    use FiltersIndex;

    private const OUTSTANDING = 'SUM(GREATEST(CAST(cut_pieces AS SIGNED) - CAST(received_pieces AS SIGNED), 0)) as o';

    public function index(Request $request)
    {
        $q = WorkOrder::with(['factory', 'fabrics.consignment', 'fabrics.color']);

        // مستخدم المصنع بيشوف أوامر مصنعه بس
        $fid = auth()->user()?->factory_id;
        if ($fid) $q->where('factory_id', $fid);
        $onF = fn ($x) => $fid ? $x->where('factory_id', $fid) : $x;   // الأرقام فوق كمان على مصنعه بس

        $this->applyFilters($q, $request, ['wo_no', 'product_title', 'product_code', 'qb_code'], 'wo_date',
            ['status' => 'status', 'factory_id' => 'factory_id', 'variance_flag' => 'variance_flag']);

        if ($request->boolean('late')) $q->late();
        if ($request->boolean('open')) $q->open();

        return view('workorders.index', [
            'title'     => 'أوامر الشغل',
            'rows'      => $this->applySort($q, $request, ['wo_no','wo_date','due_date','cut_pieces','received_pieces','status','variance_pct'])->paginate(25)->withQueryString(),
            'statuses'  => WorkOrder::STATUSES,
            'factories' => Factory::orderBy('name')->pluck('name', 'id'),
            'summary'   => [
                ['label' => 'مفتوحة', 'value' => $onF(WorkOrder::open())->count(), 'tone' => 'brand',
                 'note' => 'لسه ما اتقفلتش ولا اتلغت.'],
                ['label' => 'متأخرة', 'value' => $onF(WorkOrder::late())->count(), 'tone' => 'danger',
                 'note' => 'فات تاريخ تسليمها.'],
                ['label' => 'قطع على المصانع',
                 'value' => number_format((int) $onF(WorkOrder::open())->selectRaw(self::OUTSTANDING)->value('o')),
                 'note' => 'مقصوص ولسه ما اتسلّمش.'],
                ['label' => 'خامات مش متوازنة',
                 'value' => $onF(WorkOrder::open())->whereHas('fabrics', fn ($x) => $x->where('is_governing', true))
                                ->get()->filter(fn ($w) => $w->fabric_gap > 0)->count(),
                 'tone' => 'warn', 'note' => 'فيه خامة هتخلص قبل التانية وتوقف الإنتاج.'],
                ['label' => 'مقفولة', 'value' => $onF(WorkOrder::where('status', 'closed'))->count(), 'tone' => 'ok',
                 'note' => 'اتسلّمت بالكامل.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $row = new WorkOrder([
            'wo_date'       => now()->toDateString(),
            'status'        => 'draft',
            'marker_copies' => 2,
            'planner_id'    => auth()->id(),
            // التسليم الافتراضي بعد 15 يوم — المخطط يعدّله عادي
            'due_date'      => now()->addDays(15)->toDateString(),
            'receive_date'  => now()->addDays(15)->toDateString(),
        ]);

        /* أمر الشغل بيتبني على طلب تحضير اتوزن + ماركر معتمد (الميتينج 36: «مفيش أرقام تتكتب يدوي»).
           من غير طلب محدد ⇒ شاشة البداية: الطلبات الجاهزة لأمر شغل واللي لسه مستنية وزن أو ماركر. */
        $pr = null;
        if ($pid = $request->get('preparation_request_id')) {
            $pr = \App\Models\PreparationRequest::find($pid);
        } elseif ($cid = $request->get('consignment_id')) {
            $ready = $this->readyRequests()->filter(fn ($r) => (int) $r->consignment_id === (int) $cid && $this->markerOptions($r)->isNotEmpty());
            if ($ready->count() === 1) $pr = $ready->first();
        }

        if (!$pr || !$this->isReady($pr)) {
            return view('workorders.pick', [
                'title'    => 'أمر شغل جديد — اختار طلب التحضير',
                // الجاهزية بتتحسب هنا (نفس قاعدة الحفظ) — الشاشة بتعرضها بس
                'requests' => $this->readyRequests(includeWaiting: true)->each(function ($r) {
                    $opts = $r->status === 'weighed' ? $this->markerOptions($r) : collect();
                    $r->setRelation('readyMarker', $opts->first());
                    $r->setAttribute('is_ready', $opts->isNotEmpty());
                }),
                'focus'    => $request->get('consignment_id'),
                'notReady' => $pr && !$this->isReady($pr) ? $pr : null,
            ]);
        }

        $fabric = $this->fabricFromRequest($pr);
        $fabrics = [$fabric];
        $products = $this->defaultProducts($pr, $fabric['_marker']);
        $row->factory_id = $pr->factory_id;

        return view('workorders.form', $this->formData([
            'row'      => $row,
            'mode'     => 'create',
            'fabrics'  => $fabrics,
            'products' => $products,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $wo = DB::transaction(function () use ($data) {
            $wo = WorkOrder::create($data['header'] + [
                'wo_no'      => DocNumber::next('work_order', 'work_orders', 'wo_no'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->syncFabrics($wo, $data['fabrics']);
            $this->syncProducts($wo, $data['products'], $data['header_model']);
            $wo->refresh()->recalc();
            $this->touchConsignments($wo);

            return $wo;
        });

        ActivityLogger::log('created', $wo, 'إنشاء أمر شغل ' . $wo->wo_no);
        return redirect()->route('work-orders.show', $wo)->with(FlowMessage::flash('wo.created', $wo));
    }

    public function show(WorkOrder $work_order)
    {
        if (($fid = auth()->user()?->factory_id) && (int) $work_order->factory_id !== (int) $fid) {
            abort(403, 'أمر الشغل ده مش على مصنعك.');
        }

        $work_order->load([
            'factory', 'planner',
            'fabrics.consignment.color', 'fabrics.fabricType', 'fabrics.color', 'fabrics.marker', 'fabrics.preparationRequest',
            'lines.productModel', 'lines.size',
            'cutDeclarations' => fn ($q) => $q->orderBy('id', 'desc'),
            'cutDeclarations.lines',
            'receipts' => fn ($q) => $q->orderBy('id', 'desc'),
            'receipts.lines',
            'accessoryRequirements.accessory',
            'materialIssueLines' => fn ($q) => $q->orderBy('id', 'desc'),
            'materialIssueLines.materialIssue',
            ]);

        $work_order->load(['revisedFrom', 'revisions']);

        return view('workorders.show', [
            'title'       => 'أمر الشغل ' . $work_order->wo_no,
            'row'         => $work_order,
            'calc'        => $this->recompute($work_order),
            'accessories' => PlanningEngine::explodeAccessories($work_order),
            'issued'      => $work_order->materialIssueLines
                                ->groupBy('work_order_fabric_id'),
            'history'     => \App\Models\ActivityLog::with('user')
                                ->where('subject_type', $work_order->getMorphClass())
                                ->where('subject_id', $work_order->id)
                                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function edit(WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403, 'أمر الشغل مقفول عن التعديل.');
        $work_order->load(['fabrics', 'lines']);

        $fabrics = $work_order->fabrics->map(function ($f) {
            $pr = $f->preparation_request_id ? \App\Models\PreparationRequest::with(['consignment', 'models', 'markerRequest.marker'])->find($f->preparation_request_id) : null;
            return $pr && $pr->consignment
                ? $this->fabricFromRequest($pr, $f->marker_id, $f->width_basis ?: 'usable')
                : $f->toArray() + ['_legacy' => true];   // أمر قديم اتعمل قبل الربط بطلب التحضير
        })->all();

        return view('workorders.form', $this->formData([
            'row'     => $work_order,
            'mode'    => 'edit',
            'fabrics' => $fabrics,
        ]));
    }

    public function update(Request $request, WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($work_order, $data) {
            $work_order->update($data['header']);
            $old = $work_order->fabrics->pluck('consignment_id')->filter()->all();

            // طلبات التحضير اللي كانت على الأمر ترجع «اتوزنت» لحد ما تتربط تاني
            \App\Models\PreparationRequest::where('work_order_id', $work_order->id)
                ->update(['work_order_id' => null, 'status' => 'weighed']);

            $work_order->fabrics()->delete();
            $work_order->lines()->delete();
            $this->syncFabrics($work_order, $data['fabrics']);
            $this->syncProducts($work_order, $data['products'], $data['header_model']);
            $work_order->refresh()->recalc();

            // الأحواض اللي اتشالت لازم يتعاد حساب رصيدها كمان
            $this->touchConsignments($work_order, $old);
        });

        return redirect()->route('work-orders.show', $work_order)->with('success', 'تم الحفظ.');
    }

    public function submit(WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403);
        $work_order->load('fabrics.consignment', 'fabrics.preparationRequest', 'fabrics.marker');

        if ($work_order->fabrics->isEmpty()) {
            return back()->withErrors(['msg' => 'أمر الشغل لازم يكون فيه خامة واحدة على الأقل.']);
        }

        /* كل مشكلة على سطر الخامة بتاعها — بتتعلّم بالأحمر جنب الحقل نفسه */
        $blockers = [];
        foreach ($work_order->fabrics->values() as $i => $f) {
            $c = $f->consignment;
            if (!$c) { $blockers["fabrics.$i.preparation_request_id"][] = 'اختار طلب التحضير اللي الخامة دي هتتصرف منه.'; continue; }
            if (!$f->preparationRequest || $f->preparationRequest->status !== 'weighed') {
                $blockers["fabrics.$i.preparation_request_id"][] = $c->consignment_no . ': لازم طلب تحضير المخزن وزنه — الوزن الفعلي هو اللي بيدخل الأمر.';
            }
            if (!$f->marker || $f->marker->status !== 'approved') {
                $blockers["fabrics.$i.marker_id"][] = $c->consignment_no . ': لازم ماركر معتمد من الباترونيست.';
            }

            foreach (PlanningEngine::warnings($c, $f->marker, (float) $f->planned_qty) as $w) {
                if ($w['level'] === 'danger') $blockers["fabrics.$i.planned_qty"][] = $c->consignment_no . ': ' . $w['text'];
            }
            if ((int) $f->expected_pieces <= 0) {
                $blockers["fabrics.$i.expected_pieces"][] = ($f->fabricType?->name ?? 'خامة') . ': القص المتوقع صفر — راجع الحسبة.';
            }
        }

        if ($blockers) {
            return back()->withErrors(array_map(fn ($m) => array_values(array_unique($m)), $blockers));
        }

        FlowEngine::complete($work_order, 'أمر الشغل ' . $work_order->wo_no . ' اتسجّل وخلص');
        return back()->with(FlowMessage::flash('wo.done', $work_order));
    }

    public function sendToFactory(WorkOrder $work_order)
    {
        abort_unless($work_order->status === 'approved', 403, 'لازم يتعمد الأول.');
        $work_order->update(['status' => 'sent_to_factory']);
        ActivityLogger::log('sent', $work_order, 'إرسال أمر شغل للمصنع ' . $work_order->wo_no);
        return back()->with(FlowMessage::flash('wo.sent', $work_order));
    }

    public function close(Request $request, WorkOrder $work_order)
    {
        $data = $request->validate([
            'variance_reason' => ['required', 'string'],
        ], [], ['variance_reason' => 'سبب القفل']);

        // أمر مقفول أو ملغي أو اتستبدل بنسخة أحدث ما يتقفلش تاني
        abort_unless(in_array($work_order->status, WorkOrder::CLOSABLE_STATUSES, true), 403,
            'أمر الشغل ده مش مفتوح علشان يتقفل.');

        $work_order->update(['status' => 'closed', 'variance_reason' => $data['variance_reason']]);
        $this->touchConsignments($work_order);
        ActivityLogger::log('closed', $work_order, 'قفل أمر شغل ' . $work_order->wo_no);

        return back()->with('success', 'تم قفل أمر الشغل.');
    }

    public function print(WorkOrder $work_order)
    {
        $work_order->load([
            'factory', 'planner',
            'fabrics.consignment', 'fabrics.fabricType', 'fabrics.color',
            'lines.productModel', 'lines.size',
            'accessoryRequirements.accessory',
        ]);

        return view('print.work_order', ['wo' => $work_order]);
    }

    /**
     * نسخة معدلة (Revision) — الأمر المعتمد مش بيتعدل مباشرة.
     * بننسخه بالكامل (خامات وموديلات) كمسودة جديدة بترجع للتخطيط
     * والاعتماد، والأصل بيتعلم «استُبدل بنسخة أحدث» ويفضل محفوظ بسجله.
     */
    public function revise(Request $request, WorkOrder $work_order)
    {
        abort_unless(in_array($work_order->status, ['approved', 'sent_to_factory'], true), 403,
            'النسخ المعدلة بتتعمل من أمر معتمد أو مُرسل للمصنع بس.');

        $reason = trim((string) $request->input('revision_reason'));
        if ($reason === '') {
            return back()->withErrors(['msg' => 'لازم تكتب سبب التعديل — بيتسجل في تاريخ الأمر.']);
        }

        $rev = DB::transaction(function () use ($work_order, $reason) {
            $work_order->load('fabrics', 'lines');

            $base  = preg_replace('/-R\d+$/', '', $work_order->wo_no);
            $revNo = (int) $work_order->revision_no + 1;

            $rev = $work_order->replicate([
                'wo_no', 'status', 'revision_no', 'revised_from_id', 'revision_reason',
                'cut_pieces', 'received_pieces', 'variance_pct', 'variance_flag', 'variance_reason',
                'actual_spread_length_m', 'actual_plies',
            ]);
            $rev->forceFill([
                'wo_no'           => $base . '-R' . $revNo,
                'status'          => 'draft',
                'revision_no'     => $revNo,
                'revised_from_id' => $work_order->id,
                'revision_reason' => $reason,
                'created_by'      => auth()->id(),
            ])->save();

            foreach ($work_order->fabrics as $f) {
                $rev->fabrics()->create(collect($f->getAttributes())
                    ->except(['id', 'work_order_id', 'created_at', 'updated_at'])->all());
            }
            foreach ($work_order->lines as $l) {
                $rev->lines()->create(collect($l->getAttributes())
                    ->except(['id', 'work_order_id', 'created_at', 'updated_at',
                              'cut_qty', 'received_qty', 'remaining_qty'])->all());
            }

            $work_order->forceFill(['status' => 'superseded'])->save();
            $this->touchConsignments($work_order);

            ActivityLogger::log('revised', $work_order,
                'اتعمل منه نسخة معدلة ' . $rev->wo_no . ' — السبب: ' . $reason);
            ActivityLogger::log('created', $rev,
                'نسخة معدلة من ' . $work_order->wo_no . ' — السبب: ' . $reason);

            return $rev;
        });

        return redirect()->route('work-orders.edit', $rev)
            ->with('success', 'اتعملت النسخة ' . $rev->wo_no . ' — عدّل اللي محتاجه وابعتها للاعتماد. النسخة القديمة محفوظة زي ما هي.');
    }

    /**
     * طلب شراء من عجز الإكسسوارات — سطر لكل صنف ناقص، بينزل للمشتريات فورًا.
     */
    public function shortagePo(WorkOrder $work_order)
    {
        $shortages = collect(PlanningEngine::explodeAccessories($work_order))
            ->filter(fn ($a) => $a['shortage'] > 0)->values();

        if ($shortages->isEmpty()) {
            return back()->withErrors(['msg' => 'مفيش عجز إكسسوارات على الأمر ده.']);
        }

        $po = DB::transaction(function () use ($work_order, $shortages) {
            $po = \App\Models\PurchaseOrder::create([
                'po_no'         => \App\Services\DocNumber::next('purchase_order', 'purchase_orders', 'po_no'),
                'po_date'       => now()->toDateString(),
                'stage'         => 'purchasing',
                'status'        => 'draft',
                'planning_note' => 'عجز إكسسوارات أمر الشغل ' . $work_order->wo_no,
                'employee_id'   => auth()->id(),
                'created_by'    => auth()->id(),
                'requested_by'  => auth()->id(),
                'requested_at'  => now(),
            ]);

            foreach ($shortages as $i => $a) {
                \App\Models\PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'line_no'   => $i + 1,
                    'item_name' => $a['accessory']->name . ' (' . $a['accessory']->code . ')',
                    'qty'       => $a['shortage'],
                    'unit'      => $a['accessory']->unit,
                ]);
            }
            $po->recalcTotals();
            return $po;
        });

        \App\Services\Notifier::broadcastToRole('purchasing', 'po_sourcing',
            'طلب شراء لعجز إكسسوارات',
            $po->po_no . ' — ' . $shortages->count() . ' صنف ناقص لأمر الشغل ' . $work_order->wo_no,
            route('purchase-orders.edit', $po), 'warning');

        return redirect()->route('purchase-orders.edit', $po)
            ->with('success', 'اتعمل الطلب ' . $po->po_no . ' بأصناف العجز ونزل للمشتريات.');
    }

    public function destroy(WorkOrder $work_order)
    {
        abort_unless($work_order->isDraft(), 403);

        $ids = $work_order->fabrics->pluck('consignment_id')->filter()->all();
        \App\Models\PreparationRequest::where('work_order_id', $work_order->id)
            ->update(['work_order_id' => null, 'status' => 'weighed']);
        $work_order->delete();

        foreach (Consignment::whereIn('id', $ids)->get() as $c) {
            $c->recalcRemaining();
        }

        return redirect()->route('work-orders.index')->with('success', 'تم الحذف.');
    }

    /** حسبة لحظية (AJAX) — بتشتغل وإنت بتكتب قبل ما تحفظ */
    public function calc(Request $request)
    {
        $data = $request->validate([
            'fabrics'                        => ['required', 'array', 'min:1'],
            'fabrics.*.label'                => ['nullable', 'string'],
            'fabrics.*.calc_mode'            => ['required', 'in:weight,length'],
            'fabrics.*.spread_length_m'      => ['nullable', 'numeric'],
            'fabrics.*.spread_length_safe_m' => ['nullable', 'numeric'],
            'fabrics.*.fabric_width_m'       => ['nullable', 'numeric'],
            'fabrics.*.gsm_kg_m2'            => ['nullable', 'numeric'],
            'fabrics.*.pieces_per_spread'    => ['nullable', 'integer'],
            'fabrics.*.available'            => ['nullable', 'numeric'],
        ]);

        $out = PlanningEngine::computeWorkOrder($data['fabrics']);

        // الـ closure مش بيتسلسل في JSON
        foreach ($out['fabrics'] as $k => $f) unset($out['fabrics'][$k]['needed_for']);

        return response()->json($out);
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * طلبات التحضير اللي ينفع أمر شغل يتبني عليها: اتوزنت ولسه مادخلتش أمر (ولا محجوزة لأمر مسودة تاني).
     * includeWaiting: كمان اللي لسه مستنية وزن المخزن — بتظهر في شاشة البداية عشان المخطط يعرف هي فين.
     */
    private function readyRequests(?WorkOrder $except = null, bool $includeWaiting = false)
    {
        $taken = WorkOrderFabric::whereNotNull('preparation_request_id')
            ->whereHas('workOrder', fn ($q) => $q->whereNotIn('status', ['cancelled', 'rejected', 'superseded']))
            ->when($except, fn ($q) => $q->where('work_order_id', '!=', $except->id))
            ->pluck('preparation_request_id');

        return \App\Models\PreparationRequest::with([
                'consignment.fabricType', 'consignment.color', 'consignment.purchaseOrder.models',
                'models', 'factory', 'markerRequest.marker',
            ])
            ->whereIn('status', $includeWaiting ? ['open', 'weighed'] : ['weighed'])
            ->whereNull('work_order_id')
            ->whereNotIn('id', $taken)
            ->orderBy('id')
            ->get()
            ->filter(fn ($r) => $r->consignment);
    }

    /** ماركرات معتمدة تنفع على عرض الرسالة — ماركر طلب الماركر الموازي الأول */
    private function markerOptions(\App\Models\PreparationRequest $pr)
    {
        $c     = $pr->consignment;
        $width = (float) ($c->usable_width ?: $c->min_width_cm);
        $own   = $pr->markerRequest?->marker;

        $list = Marker::with(['lines.productModel', 'lines.size'])
            ->where('status', 'approved')->where('is_active', true)->latest('id')->get()
            ->filter(fn ($m) => $m->fitsWidth($width));

        /* الترتيب: ماركر الطلب نفسه، بعده الماركرات اللي فيها موديلات الطلب (مكتبة الماركرات)، وبعدها الباقي */
        $want = $pr->models->pluck('id');
        $list = $list->sortByDesc(fn ($m) => $m->lines->pluck('product_model_id')->intersect($want)->count())->values();
        if ($own && $own->status === 'approved') {
            $own->loadMissing(['lines.productModel', 'lines.size']);
            $list = collect([$own])->merge($list->where('id', '!=', $own->id));
        }
        return $list->values();
    }

    /** جاهز لأمر شغل = اتوزن + فيه ماركر معتمد ينفع على عرضه */
    private function isReady(\App\Models\PreparationRequest $pr): bool
    {
        return $pr->status === 'weighed' && !$pr->work_order_id && $pr->consignment && $this->markerOptions($pr)->isNotEmpty();
    }

    /**
     * سطر الخامة من طلب التحضير والماركر — السيرفر هو اللي بيحدد الأرقام:
     * الكمية = الوزن الفعلي من المخزن · الطول والقطع = الماركر · العرض والبنشر = الجودة.
     */
    private function fabricFromRequest(\App\Models\PreparationRequest $pr, ?int $markerId = null, string $basis = 'usable'): array
    {
        $c       = $pr->consignment;
        $markers = $this->markerOptions($pr);
        $mk      = ($markerId ? $markers->firstWhere('id', $markerId) : null) ?? $markers->first();
        $meter   = (bool) $c->is_meter;
        $wCm     = $basis === 'full' ? $c->min_width_cm : ($c->usable_width ?: $c->min_width_cm);

        return [
            'preparation_request_id' => $pr->id,
            'consignment_id'         => $c->id,
            'marker_id'              => $mk?->id,
            'width_basis'            => $basis,
            'calc_mode'              => $meter ? 'length' : 'weight',
            'unit'                   => $meter ? 'متر' : 'كجم',
            'planned_qty'            => round($pr->qty_for_work_order, 3),
            'spread_length_m'        => $mk?->spread_length_m,
            'pieces_per_spread'      => $mk?->pieces_per_spread,
            'fabric_width_m'         => $wCm ? round((float) $wCm / 100, 3) : null,
            'gsm_kg_m2'              => $c->avg_gsm ? round((float) $c->avg_gsm / 1000, 4) : null,
            '_pr'                    => $pr,
            '_markers'               => $markers,
            '_marker'                => $mk,
        ];
    }

    /**
     * المنتجات والمقاسات الافتراضية: من سطور الماركر (الموديل والمقاس وقطعه في الفرشة)،
     * وإلا موديلات طلب التحضير، وإلا موديلات طلب الشراء اللي الرسالة جت عليه.
     */
    private function defaultProducts(\App\Models\PreparationRequest $pr, ?Marker $mk): array
    {
        $models = $pr->models->isNotEmpty() ? $pr->models : ($pr->consignment?->purchaseOrder?->models ?? collect());
        $lines  = $mk?->lines ?? collect();

        // سطور الماركر اللي موديلاتها هي موديلات الطلب (أو الماركر كله لو الطلب من غير موديلات)
        $match = $models->isEmpty() ? $lines : $lines->whereIn('product_model_id', $models->pluck('id'));
        if ($match->isNotEmpty()) {
            return $match->map(fn ($l) => [
                'product_model_id' => $l->product_model_id, 'size_id' => $l->size_id, 'qty_per_spread' => $l->qty_per_spread,
            ])->values()->all();
        }
        // الماركر لموديلات تانية (ماركر قديم بنفس العرض) ⇒ موديلات الطلب، والمخطط يحدد المقاسات
        return $models->map(fn ($m) => ['product_model_id' => $m->id])->values()->all();
    }

    /** سطر خامة تانية (منتج من أكتر من خامة) — بيتبني على السيرفر من طلب التحضير ويتحط في الجدول */
    public function fabricRow(Request $request)
    {
        $pr = \App\Models\PreparationRequest::with(['consignment.fabricType', 'consignment.color', 'models', 'markerRequest.marker'])
            ->find($request->integer('preparation_request_id'));
        if (!$pr) return redirect()->route('work-orders.create');   // اتفتح مباشر من غير طلب ⇒ شاشة البداية
        abort_unless($this->isReady($pr), 422, 'طلب التحضير ده مش جاهز — لازم يتوزن ويكون له ماركر معتمد.');

        return view('workorders.fabric_row', [
            'i' => (string) $request->get('i', 'x' . time()),
            'l' => $this->fabricFromRequest($pr),
        ]);
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'أمر شغل',
            // الأقدم أولًا (FIFO) — القديم يخرج الأول (الميتينج)
            'consignments' => Consignment::with(['color', 'fabricType'])->readyForProduction()
                                ->orderBy('arrival_date')->orderBy('id')->get(),
            'safetyCm'     => (float) lv_setting('safety_margin_cm', 5),
            'products'     => [],
            // طلبات جاهزة تتضاف كخامة تانية على نفس الأمر
            'readyMore'    => $this->readyRequests($extra['row'] ?? null)->filter(fn ($r) => $this->isReady($r))->values(),
            'factories'    => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'models'       => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'sizes'        => Size::ordered()->where('is_active', true)->pluck('name', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'planners'     => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'wo_date'        => ['required', 'date'],
            'factory_id'     => ['required', 'exists:factories,id'],
            'due_date'       => ['nullable', 'date'],
            'receive_date'   => ['nullable', 'date'],
            'product_title'  => ['nullable', 'string', 'max:191'],   // بيتكوّن من الموديلات لو فاضي
            'product_model_id' => ['nullable', 'exists:product_models,id'],
            'product_code'   => ['nullable', 'string', 'max:60'],
            'qb_code'        => ['nullable', 'string', 'max:60'],
            'marker_copies'  => ['nullable', 'integer', 'min:0'],
            'planner_id'     => ['nullable', 'exists:users,id'],
            'cutting_notes'  => ['nullable', 'string'],
            'barcode'        => ['nullable', 'string', 'max:80'],
            'approved_qty'   => ['nullable', 'integer', 'min:0'],
            'approved_qty_reason' => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],

            // ── الخامات ──
            'fabrics'                        => ['required', 'array', 'min:1'],
            /* الخامة = طلب تحضير اتوزن + ماركر معتمد. الأرقام كلها بتتحسب على السيرفر منهم —
               اللي جاي من الفورم في الخانات دي بيتجاهل (الميتينج: «مفيش أرقام تتكتب يدوي») */
            'fabrics.*.preparation_request_id' => ['required', 'exists:preparation_requests,id'],
            'fabrics.*.marker_id'            => ['required', 'exists:markers,id'],
            'fabrics.*.width_basis'          => ['nullable', 'in:usable,full'],
            'fabrics.*.notes'                => ['nullable', 'string'],

            // ── المنتجات ──
            'products'                    => ['nullable', 'array'],
            'products.*.product_model_id' => ['required', 'exists:product_models,id'],
            'picked_models'               => ['nullable', 'array'],
            'products.*.size_id'          => ['nullable', 'exists:sizes,id'],
            'products.*.qty_per_spread'   => ['nullable', 'integer', 'min:0'],
            'products.*.planned_qty'      => ['nullable', 'integer', 'min:0'],
        ], [], [
            'factory_id'    => 'المصنع',
            'product_title' => 'اسم المنتج',
            'fabrics'       => 'الخامات',
            'fabrics.*.preparation_request_id' => 'طلب التحضير',
            'fabrics.*.marker_id'         => 'الماركر',
        ]);

        /* كل خامة: الطلب اتوزن ولسه مادخلش أمر تاني، والماركر معتمد وينفع على عرض الرسالة */
        $except = $request->route('work_order');
        $readyIds = $this->readyRequests($except instanceof WorkOrder ? $except : null)->pluck('id')->all();
        $errors = [];
        foreach ($v['fabrics'] as $k => $f) {
            $pr = \App\Models\PreparationRequest::with(['consignment', 'markerRequest.marker'])->find($f['preparation_request_id']);
            if (!$pr || !in_array($pr->id, $readyIds, true)) {
                $errors["fabrics.$k.preparation_request_id"] = 'طلب التحضير ده مش متاح — لازم يكون اتوزن ومادخلش أمر شغل تاني.';
                continue;
            }
            if (!$this->markerOptions($pr)->firstWhere('id', (int) $f['marker_id'])) {
                $errors["fabrics.$k.marker_id"] = 'اختار ماركر معتمد عرضه ينفع على الرسالة دي.';
                continue;
            }
            $v['fabrics'][$k] = $this->fabricFromRequest($pr, (int) $f['marker_id'], $f['width_basis'] ?? 'usable') + ['notes' => $f['notes'] ?? null];
        }
        if ($errors) throw \Illuminate\Validation\ValidationException::withMessages($errors);

        // اسم المنتج = الموديلات اللي هتتشغل (مفيش خانة تانية تتكتب باليد)
        if (empty($v['product_title'])) {
            $ids = collect($v['products'] ?? [])->pluck('product_model_id')->filter()->unique();
            $v['product_title'] = ProductModel::whereIn('id', $ids)->pluck('name')->implode(' + ') ?: 'منتج';
        }

        $fabrics  = $v['fabrics'];
        $products = $v['products'] ?? [];
        $model    = $v['product_model_id'] ?? null;
        unset($v['fabrics'], $v['products'], $v['product_model_id'], $v['picked_models']);   // picked_models من البيكر بس — مش عمود

        $v['marker_copies'] = $v['marker_copies'] ?? 1;

        return ['header' => $v, 'fabrics' => $fabrics, 'products' => $products, 'header_model' => $model];
    }

    /** إنشاء سطور الخامات مع حسبة كل واحدة */
    private function syncFabrics(WorkOrder $wo, array $fabrics): void
    {
        foreach (array_values($fabrics) as $i => $f) {
            $c = Consignment::find($f['consignment_id']);

            // طول الأمان: 5 سم ثابتة بتتضاف تلقائيًا على طول الفرشة لو المخطط ما كتبش (الميتينج)
            if (empty($f['spread_length_safe_m']) && !empty($f['spread_length_m'])) {
                $f['spread_length_safe_m'] = round((float) $f['spread_length_m'] + (float) lv_setting('safety_margin_cm', 5) / 100, 3);
            }

            /* طلب التحضير بيفضل حاجز الكمية على الرسالة طول ما الأمر مسودة —
               بيتعلّم «دخل أمر شغل» وحجزه بيتحول لتخصيص الأمر لحظة الاعتماد (DocumentEffects::workOrder).
               قبل كده كان بيتحرر وقت الحفظ والأمر لسه مسودة، فالكمية كانت بتبان متاحة لحد تاني. */

            $calc = PlanningEngine::computeFabric([
                'calc_mode'            => $f['calc_mode'],
                'spread_length_m'      => $f['spread_length_m'],
                'spread_length_safe_m' => $f['spread_length_safe_m'] ?? null,
                'fabric_width_m'       => $f['fabric_width_m'] ?? null,
                'gsm_kg_m2'            => $f['gsm_kg_m2'] ?? null,
                'pieces_per_spread'    => $f['pieces_per_spread'],
                'available'            => $f['planned_qty'],
            ]);

            WorkOrderFabric::create([
                'work_order_id'        => $wo->id,
                'line_no'              => $i + 1,
                'width_basis'          => $f['width_basis'] ?? 'usable',
                'consignment_id'       => $c?->id,
                'preparation_request_id' => $f['preparation_request_id'] ?? null,
                'fabric_type_id'       => $c?->fabric_type_id,
                'color_id'             => $c?->color_id,
                'marker_id'            => $f['marker_id'] ?? null,
                'role'                 => $f['role'] ?? ($i === 0 ? 'main' : 'secondary'),
                'calc_mode'            => $f['calc_mode'],
                'unit'                 => $f['unit'],
                'planned_qty'          => $f['planned_qty'],
                'spread_length_m'      => $f['spread_length_m'],
                'spread_length_safe_m' => $f['spread_length_safe_m'] ?? null,
                'fabric_width_m'       => $f['fabric_width_m'] ?? null,
                'gsm_kg_m2'            => $f['gsm_kg_m2'] ?? null,
                'pieces_per_spread'    => $f['pieces_per_spread'],
                'ply_weight_kg'        => $calc['ply_weight_kg'] ?? null,
                'consumption_per_piece'=> $calc['consumption_per_piece'] ?? null,
                'calc_plies'           => $calc['plies'] ?? null,
                'calc_pieces'          => $calc['expected_pieces'] ?? null,
                // اللي بيروح للمصنع = حسبة السيستم من الوزن الفعلي والماركر
                'plies'                => $calc['plies'] ?? null,
                'expected_pieces'      => $calc['expected_pieces'] ?? null,
                'notes'                => $f['notes'] ?? null,
            ]);
        }
    }

    /**
     * الموديلات والمقاسات.
     *
     * لازم يبقى فيه سطر واحد على الأقل — من غيره مينفعش تعمل بيان قص
     * ولا استلام إنتاج، ولا هتطلع احتياجات الإكسسوارات.
     * لو المستخدم ما فصّلش مقاسات، بنعمل سطر واحد بالكمية المستهدفة.
     */
    /**
     * الموديلات المشتركة في الفرشة + توزيع الاستهلاك بالمتوسطات.
     *
     * لو المستخدم حدّد «قطع الموديل في الفرشة» (6 تلبيسة + 6 كويتي مثلًا)،
     * كل موديل بياخد: قطعه المتوقعة = الرِقّات × قطعه في الفرشة،
     * واستهلاكه = نصيبه من الاستهلاك الفعلي بنسبة متوسطه التاريخي —
     * بدل ما الورقة القديمة كانت بتعمّم رقم واحد على الكل.
     */
    private function syncProducts(WorkOrder $wo, array $products, ?int $fallbackModel = null): void
    {
        $wo->refresh()->load('fabrics');
        $target = $wo->approved_qty ?: $wo->computed_governing_qty;

        $rows = array_values(array_filter($products, fn ($p) => !empty($p['product_model_id'])));

        if (!$rows && $fallbackModel) {
            $rows = [['product_model_id' => $fallbackModel, 'size_id' => null, 'planned_qty' => $target]];
        }
        if (!$rows) return;

        // الخامة الرئيسية = اللي الماركر المشترك متفصّل عليها
        $main  = $wo->fabrics->firstWhere('role', 'main') ?: $wo->fabrics->first();
        $plies = (int) ($main?->plies ?: $main?->calc_plies ?: 0);
        $per   = (float) ($main?->consumption_per_piece ?: 0);

        $modelIds = array_column($rows, 'product_model_id');
        $avgs = ProductModel::whereIn('id', $modelIds)->pluck('std_consumption_kg', 'id');
        $names = ProductModel::whereIn('id', $modelIds)->pluck('name', 'id');

        $split = null;
        /* التوزيع بيشتغل بس لما المخطط يكتب قطع الفرشة فعلًا (قيمة > 1) —
           السطور القديمة كلها متسجلة بـ 1 افتراضيًا، ومينفعش إعادة حفظها
           تفعّل التوزيع من غير ما حد يطلبه. */
        $hasPps = collect($rows)->contains(fn ($p) => (int) ($p['qty_per_spread'] ?? 0) > 1);
        if ($hasPps && $per > 0) {
            $split = PlanningEngine::splitConsumption(array_map(fn ($p) => [
                'product_model_id' => $p['product_model_id'],
                'label'            => $names[$p['product_model_id']] ?? '',
                'pieces_in_spread' => (int) ($p['qty_per_spread'] ?? 0),
                'avg_kg'           => (float) ($avgs[$p['product_model_id']] ?? 0),
            ], $rows), $per, $plies);
        }

        $sum = array_sum(array_map(fn ($p) => (int) ($p['planned_qty'] ?? 0), $rows));

        foreach ($rows as $p) {
            $pps = (int) ($p['qty_per_spread'] ?? 0);
            $sp  = $split ? collect($split['rows'])->firstWhere('product_model_id', $p['product_model_id']) : null;

            $qty = (int) ($p['planned_qty'] ?? 0);
            if ($qty === 0 && $sp) {
                $qty = (int) $sp['expected_pieces'];          // الرِقّات × قطعه في الفرشة
            } elseif ($qty === 0 && $sum === 0) {
                $qty = count($rows) === 1 ? $target : (int) floor($target / max(1, count($rows)));
            }

            WorkOrderLine::create([
                'work_order_id'         => $wo->id,
                'product_model_id'      => $p['product_model_id'],
                'size_id'               => $p['size_id'] ?? null,
                'qty_per_spread'        => max(1, $pps),
                'planned_qty'           => $qty,
                'avg_consumption_kg'    => (float) ($avgs[$p['product_model_id']] ?? 0) ?: null,
                'consumption_per_piece' => $sp['per_piece'] ?? ($per ?: null),
                // الكيلوهات دايمًا = الكمية الفعلية × نصيب القطعة — حتى لو المخطط كتب كمية مختلفة
                'planned_kg'            => $sp
                    ? round($sp['per_piece'] * $qty, 3)
                    : ($per > 0 ? round($per * $qty, 3) : null),
            ]);
        }
    }

    private function recompute(WorkOrder $wo): array
    {
        $in = $wo->fabrics->map(fn ($f) => [
            'label'                => ($f->fabricType?->name ?? 'خامة') . ' — ' . ($f->color?->code ?? ''),
            'calc_mode'            => $f->calc_mode,
            'spread_length_m'      => $f->spread_length_m,
            'spread_length_safe_m' => $f->spread_length_safe_m,
            'fabric_width_m'       => $f->fabric_width_m,
            'gsm_kg_m2'            => $f->gsm_kg_m2,
            'pieces_per_spread'    => $f->pieces_per_spread,
            'available'            => $f->planned_qty,
        ])->all();

        return $in ? PlanningEngine::computeWorkOrder($in) : ['ok' => false, 'fabrics' => [], 'warnings' => []];
    }

    /** إعادة حساب رصيد الأحواض — الحالية والقديمة اللي اتشالت */
    private function touchConsignments(WorkOrder $wo, array $alsoIds = []): void
    {
        $ids = collect($wo->fabrics->pluck('consignment_id'))->merge($alsoIds)->filter()->unique();

        foreach (Consignment::whereIn('id', $ids)->get() as $c) {
            $c->recalcRemaining();
        }
    }
}
