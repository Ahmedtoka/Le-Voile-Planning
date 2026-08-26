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
                ['label' => 'مفتوحة', 'value' => WorkOrder::open()->count(), 'tone' => 'brand',
                 'note' => 'لسه ما اتقفلتش ولا اتلغت.'],
                ['label' => 'متأخرة', 'value' => WorkOrder::late()->count(), 'tone' => 'danger',
                 'note' => 'فات تاريخ تسليمها.'],
                ['label' => 'قطع على المصانع',
                 'value' => number_format((int) WorkOrder::open()->selectRaw(self::OUTSTANDING)->value('o')),
                 'note' => 'مقصوص ولسه ما اتسلّمش.'],
                ['label' => 'خامات مش متوازنة',
                 'value' => WorkOrder::open()->whereHas('fabrics', fn ($x) => $x->where('is_governing', true))
                                ->get()->filter(fn ($w) => $w->fabric_gap > 0)->count(),
                 'tone' => 'warn', 'note' => 'فيه خامة هتخلص قبل التانية وتوقف الإنتاج.'],
                ['label' => 'مقفولة', 'value' => WorkOrder::where('status', 'closed')->count(), 'tone' => 'ok',
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

        // لو جاي من شاشة الحوض، نبدأ بسطر خامة جاهز عليه
        $fabrics = [];
        if ($cid = $request->get('consignment_id')) {
            if ($c = Consignment::find($cid)) {
                $fabrics[] = [
                    'consignment_id'  => $c->id,
                    'calc_mode'       => 'weight',
                    'unit'            => 'كجم',
                    'planned_qty'     => $c->remaining_kg,
                    'fabric_width_m'  => $c->min_width_cm ? round((float) $c->min_width_cm / 100, 3) : null,
                    'gsm_kg_m2'       => $c->avg_gsm ? round((float) $c->avg_gsm / 1000, 4) : null,
                ];
            }
        }

        return view('workorders.form', $this->formData([
            'row'     => $row,
            'mode'    => 'create',
            'fabrics' => $fabrics,
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
        $work_order->load([
            'factory', 'planner',
            'fabrics.consignment.color', 'fabrics.fabricType', 'fabrics.color', 'fabrics.marker',
            'lines.productModel', 'lines.size',
            'cutDeclarations.lines', 'receipts.lines',
            'accessoryRequirements.accessory',
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

        return view('workorders.form', $this->formData([
            'row'     => $work_order,
            'mode'    => 'edit',
            'fabrics' => $work_order->fabrics->toArray(),
        ]));
    }

    public function update(Request $request, WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($work_order, $data) {
            $work_order->update($data['header']);
            $old = $work_order->fabrics->pluck('consignment_id')->filter()->all();

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
        $work_order->load('fabrics.consignment');

        if ($work_order->fabrics->isEmpty()) {
            return back()->withErrors(['msg' => 'أمر الشغل لازم يكون فيه خامة واحدة على الأقل.']);
        }

        $blockers = [];
        foreach ($work_order->fabrics as $f) {
            $c = $f->consignment;
            if (!$c) { $blockers[] = 'فيه خامة من غير رسالة محددة.'; continue; }

            foreach (PlanningEngine::warnings($c, $f->marker, (float) $f->planned_qty) as $w) {
                if ($w['level'] === 'danger') $blockers[] = ($c->consignment_no . ': ' . $w['text']);
            }
            if ((int) $f->expected_pieces <= 0) {
                $blockers[] = ($f->fabricType?->name ?? 'خامة') . ': القص المتوقع صفر — راجع الحسبة.';
            }
        }

        if ($blockers) {
            return back()->withErrors(['msg' => implode(' | ', array_unique($blockers))]);
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

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'أمر شغل',
            'consignments' => Consignment::with(['color', 'fabricType'])->readyForProduction()->latest('id')->get(),
            'markers'      => Marker::where('status', 'approved')->where('is_active', true)->latest('id')->get(),
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
            'product_title'  => ['required', 'string', 'max:191'],
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
            'fabrics.*.consignment_id'       => ['required', 'exists:consignments,id'],
            'fabrics.*.marker_id'            => ['nullable', 'exists:markers,id'],
            'fabrics.*.role'                 => ['nullable', 'string', 'max:20'],
            'fabrics.*.calc_mode'            => ['required', 'in:weight,length'],
            'fabrics.*.unit'                 => ['required', 'string', 'max:20'],
            'fabrics.*.planned_qty'          => ['required', 'numeric', 'min:0.001'],
            'fabrics.*.spread_length_m'      => ['required', 'numeric', 'min:0.01'],
            'fabrics.*.spread_length_safe_m' => ['nullable', 'numeric', 'min:0'],
            'fabrics.*.fabric_width_m'       => ['nullable', 'numeric', 'min:0'],
            'fabrics.*.gsm_kg_m2'            => ['nullable', 'numeric', 'min:0'],
            'fabrics.*.pieces_per_spread'    => ['required', 'integer', 'min:1'],
            'fabrics.*.plies'                => ['nullable', 'integer', 'min:0'],
            'fabrics.*.expected_pieces'      => ['nullable', 'integer', 'min:0'],
            'fabrics.*.notes'                => ['nullable', 'string'],

            // ── المنتجات ──
            'products'                    => ['nullable', 'array'],
            'products.*.product_model_id' => ['required_with:products', 'exists:product_models,id'],
            'products.*.size_id'          => ['nullable', 'exists:sizes,id'],
            'products.*.qty_per_spread'   => ['nullable', 'integer', 'min:0'],
            'products.*.planned_qty'      => ['nullable', 'integer', 'min:0'],
        ], [], [
            'factory_id'    => 'المصنع',
            'product_title' => 'اسم المنتج',
            'fabrics'       => 'الخامات',
            'fabrics.*.consignment_id'    => 'رقم الرسالة',
            'fabrics.*.planned_qty'       => 'الكمية',
            'fabrics.*.spread_length_m'   => 'طول الفرشة',
            'fabrics.*.pieces_per_spread' => 'عدد القطع في الفرشة',
        ]);

        $fabrics  = $v['fabrics'];
        $products = $v['products'] ?? [];
        $model    = $v['product_model_id'] ?? null;
        unset($v['fabrics'], $v['products'], $v['product_model_id']);

        $v['marker_copies'] = $v['marker_copies'] ?? 1;

        return ['header' => $v, 'fabrics' => $fabrics, 'products' => $products, 'header_model' => $model];
    }

    /** إنشاء سطور الخامات مع حسبة كل واحدة */
    private function syncFabrics(WorkOrder $wo, array $fabrics): void
    {
        foreach (array_values($fabrics) as $i => $f) {
            $c = Consignment::find($f['consignment_id']);

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
                'consignment_id'       => $c?->id,
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
                // اللي بيروح للمصنع: اعتماد المخطط، وافتراضيًا حسبة السيستم
                'plies'                => $f['plies'] ?? ($calc['plies'] ?? null),
                'expected_pieces'      => $f['expected_pieces'] ?? ($calc['expected_pieces'] ?? null),
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
