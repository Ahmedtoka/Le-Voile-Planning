<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Factory;
use App\Models\Marker;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Services\ActivityLogger;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use App\Services\FlowMessage;
use App\Services\PlanningEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * أمر الشغل — الشاشة اللي بتجمع كل حاجة:
 * حوض معتمد + ماركر + مصنع + كمية ⇒ محرك الحسابات يطلع
 * الرِقّات ووزن الرِقّة واستهلاك القطعة والقطع المتوقعة.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $q = WorkOrder::with(['consignment.color', 'factory', 'marker'])->latest('id');

        if ($s = $request->get('status'))     $q->where('status', $s);
        if ($f = $request->get('factory_id')) $q->where('factory_id', $f);
        if ($request->boolean('late'))        $q->late();
        if ($request->boolean('open'))        $q->open();
        if ($v = $request->get('variance_flag')) $q->where('variance_flag', $v);
        if ($term = trim((string) $request->get('q'))) $q->where('wo_no', 'like', "%{$term}%");

        if ($from = $request->get('from')) $q->whereDate('wo_date', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('wo_date', '<=', $to);

        $out = 'SUM(GREATEST(CAST(cut_pieces AS SIGNED) - CAST(received_pieces AS SIGNED), 0)) as o';

        return view('workorders.index', [
            'title'     => 'أوامر الشغل',
            'rows'      => $q->paginate(25)->withQueryString(),
            'summary'   => [
                ['label' => 'مفتوحة', 'value' => WorkOrder::open()->count(), 'tone' => 'brand',
                 'note' => 'لسه ما اتقفلتش ولا اتلغت.'],
                ['label' => 'متأخرة', 'value' => WorkOrder::late()->count(), 'tone' => 'danger',
                 'note' => 'فات تاريخ تسليمها.'],
                ['label' => 'قطع على المصانع', 'value' => number_format((int) WorkOrder::open()->selectRaw($out)->value('o')),
                 'note' => 'مقصوص ولسه ما اتسلّمش.'],
                ['label' => 'انحراف خارج الحدود', 'value' => WorkOrder::open()->where('variance_flag','danger')->count(),
                 'tone' => 'danger', 'note' => 'الفرق بين المتوقع والمقصوص تعدى الحد المسموح.'],
                ['label' => 'مقفولة', 'value' => WorkOrder::where('status','closed')->count(), 'tone' => 'ok',
                 'note' => 'اتسلّمت بالكامل.'],
            ],
            'statuses'  => WorkOrder::STATUSES,
            'factories' => Factory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        $row = new WorkOrder([
            'wo_date'        => now()->toDateString(),
            'status'         => 'draft',
            'consignment_id' => $request->get('consignment_id'),
        ]);

        return view('workorders.form', $this->formData(['row' => $row, 'mode' => 'create', 'calc' => null]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $wo = DB::transaction(function () use ($data) {
            $consignment = Consignment::findOrFail($data['header']['consignment_id']);
            $marker      = Marker::with('lines')->findOrFail($data['header']['marker_id']);

            $calc = PlanningEngine::forWorkOrder($consignment, $marker, (float) $data['header']['allocated_kg']);

            $wo = WorkOrder::create($data['header'] + [
                'wo_no'                   => DocNumber::next('work_order', 'work_orders', 'wo_no'),
                'status'                  => 'draft',
                'created_by'              => auth()->id(),
                'input_min_width_cm'      => $consignment->min_width_cm,
                'input_avg_gsm'           => $consignment->avg_gsm,
                'input_spread_length_m'   => $marker->spread_length_m,
                'input_pieces_per_spread' => $marker->pieces_per_spread,
                'ply_weight_kg'           => $calc['ply_weight_kg']   ?? null,
                'kg_per_piece'            => $calc['kg_per_piece']    ?? null,
                'expected_plies'          => $calc['expected_plies']  ?? null,
                'expected_pieces'         => $calc['expected_pieces'] ?? null,
            ]);

            $this->buildLines($wo, $marker, (int) ($calc['expected_pieces'] ?? 0));
            $consignment->recalcRemaining();

            return $wo;
        });

        ActivityLogger::log('created', $wo, 'إنشاء أمر شغل ' . $wo->wo_no);
        return redirect()->route('work-orders.show', $wo)->with(FlowMessage::flash('wo.created', $wo));
    }

    public function show(WorkOrder $work_order)
    {
        $work_order->load([
            'consignment.color', 'consignment.fabricType', 'marker.lines.productModel', 'marker.lines.size',
            'factory', 'lines.productModel', 'lines.size',
            'cutDeclarations.lines', 'receipts.lines', 'accessoryRequirements.accessory',
            'approval.steps.user', 'approval.steps.role',
        ]);

        $calc = $work_order->consignment && $work_order->marker
            ? PlanningEngine::forWorkOrder($work_order->consignment, $work_order->marker, (float) $work_order->allocated_kg)
            : null;

        // تأثير فرق طول الفرشة لو المصنع فرش على طول مختلف
        $spreadImpact = null;
        if ($work_order->actual_spread_length_m && $work_order->consignment?->rolls_count) {
            $avgRoll = (float) $work_order->consignment->total_length_m / max(1, $work_order->consignment->rolls_count);
            $spreadImpact = PlanningEngine::spreadImpact(
                $avgRoll,
                (float) $work_order->input_spread_length_m,
                (float) $work_order->actual_spread_length_m,
                (int) $work_order->input_pieces_per_spread
            );
        }

        return view('workorders.show', [
            'title'        => 'أمر الشغل ' . $work_order->wo_no,
            'row'          => $work_order,
            'calc'         => $calc,
            'spreadImpact' => $spreadImpact,
            'accessories'  => PlanningEngine::explodeAccessories($work_order),
        ]);
    }

    public function edit(WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403, 'أمر الشغل مقفول عن التعديل.');
        $work_order->load('lines');
        return view('workorders.form', $this->formData(['row' => $work_order, 'mode' => 'edit', 'calc' => null]));
    }

    public function update(Request $request, WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($work_order, $data) {
            $consignment = Consignment::findOrFail($data['header']['consignment_id']);
            $marker      = Marker::with('lines')->findOrFail($data['header']['marker_id']);
            $calc = PlanningEngine::forWorkOrder($consignment, $marker, (float) $data['header']['allocated_kg']);

            $work_order->update($data['header'] + [
                'input_min_width_cm'      => $consignment->min_width_cm,
                'input_avg_gsm'           => $consignment->avg_gsm,
                'input_spread_length_m'   => $marker->spread_length_m,
                'input_pieces_per_spread' => $marker->pieces_per_spread,
                'ply_weight_kg'           => $calc['ply_weight_kg']   ?? null,
                'kg_per_piece'            => $calc['kg_per_piece']    ?? null,
                'expected_plies'          => $calc['expected_plies']  ?? null,
                'expected_pieces'         => $calc['expected_pieces'] ?? null,
            ]);

            $work_order->lines()->delete();
            $this->buildLines($work_order, $marker, (int) ($calc['expected_pieces'] ?? 0));
            $consignment->recalcRemaining();
        });

        return redirect()->route('work-orders.show', $work_order)->with('success', 'تم الحفظ.');
    }

    public function submit(WorkOrder $work_order)
    {
        abort_unless($work_order->isEditable(), 403);

        $consignment = $work_order->consignment;
        $marker      = $work_order->marker;

        if (!$consignment || !$marker) {
            return back()->withErrors(['msg' => 'لازم تحدد الحوض والماركر.']);
        }

        // منع الأخطاء اللي بتكلّف فلوس
        $blockers = collect(PlanningEngine::warnings($consignment, $marker, (float) $work_order->allocated_kg))
            ->where('level', 'danger');

        if ($blockers->isNotEmpty()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل أمر الشغل: ' . $blockers->pluck('text')->implode(' | ')]);
        }

        ApprovalEngine::submit($work_order);
        return back()->with(FlowMessage::flash('wo.submitted', $work_order));
    }

    /** إرسال للمصنع بعد الاعتماد */
    public function sendToFactory(WorkOrder $work_order)
    {
        abort_unless($work_order->status === 'approved', 403, 'لازم يتعمد الأول.');
        $work_order->update(['status' => 'sent_to_factory']);
        ActivityLogger::log('sent', $work_order, 'إرسال أمر شغل للمصنع ' . $work_order->wo_no);
        return back()->with(FlowMessage::flash('wo.sent', $work_order));
    }

    /** قفل يدوي مع سبب */
    public function close(Request $request, WorkOrder $work_order)
    {
        $data = $request->validate([
            'variance_reason' => ['required_if:force,1', 'nullable', 'string'],
        ], [], ['variance_reason' => 'سبب الفرق']);

        $work_order->update([
            'status'          => 'closed',
            'variance_reason' => $data['variance_reason'] ?? $work_order->variance_reason,
        ]);

        $work_order->consignment?->recalcRemaining();
        ActivityLogger::log('closed', $work_order, 'قفل أمر شغل ' . $work_order->wo_no);

        return back()->with('success', 'تم قفل أمر الشغل.');
    }

    public function print(WorkOrder $work_order)
    {
        $work_order->load(['consignment.color', 'marker', 'factory', 'lines.productModel', 'lines.size']);
        return view('print.work_order', ['wo' => $work_order]);
    }

    public function destroy(WorkOrder $work_order)
    {
        abort_unless($work_order->isDraft(), 403);
        $c = $work_order->consignment;
        $work_order->delete();
        $c?->recalcRemaining();
        return redirect()->route('work-orders.index')->with('success', 'تم الحذف.');
    }

    /** حسبة لحظية (AJAX) — بتظهر للمستخدم قبل ما يحفظ */
    public function calc(Request $request)
    {
        $data = $request->validate([
            'consignment_id' => ['required', 'exists:consignments,id'],
            'marker_id'      => ['required', 'exists:markers,id'],
            'allocated_kg'   => ['required', 'numeric', 'min:0'],
        ]);

        $consignment = Consignment::with(['inspections', 'fabricType'])->findOrFail($data['consignment_id']);
        $marker      = Marker::findOrFail($data['marker_id']);

        return response()->json(
            PlanningEngine::forWorkOrder($consignment, $marker, (float) $data['allocated_kg'])
        );
    }

    // ── داخلي ──

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'أمر شغل',
            'consignments' => Consignment::with('color')->readyForProduction()->latest('id')->get(),
            'markers'      => Marker::where('status', 'approved')->where('is_active', true)
                                ->with('lines.productModel')->latest('id')->get(),
            'factories'    => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'wo_date'        => ['required', 'date'],
            'consignment_id' => ['required', 'exists:consignments,id'],
            'marker_id'      => ['required', 'exists:markers,id'],
            'factory_id'     => ['required', 'exists:factories,id'],
            'due_date'       => ['nullable', 'date'],
            'allocated_kg'   => ['required', 'numeric', 'min:0.001'],
            'allocated_rolls'=> ['nullable', 'integer', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ], [], [
            'consignment_id' => 'الحوض',
            'marker_id'      => 'الماركر',
            'factory_id'     => 'المصنع',
            'allocated_kg'   => 'الكمية المخصصة',
        ]);

        // الأعمدة دي NOT NULL بـ default — الفورم بيبعتها فاضية فلازم نصفّرها
        $v['allocated_rolls'] = $v['allocated_rolls'] ?? 0;

        return ['header' => $v];
    }

    /**
     * توزيع القطع المتوقعة على الموديلات/المقاسات بنسبتها في الماركر.
     * مثال: ماركر فيه 4 موديلات، واحد منهم قطعتين والباقي قطعة —
     * الأول بياخد ضعف الكمية.
     */
    private function buildLines(WorkOrder $wo, Marker $marker, int $expectedPieces): void
    {
        $marker->loadMissing('lines');
        $perSpread = max(1, (int) $marker->pieces_per_spread);
        $spreads   = (int) floor($expectedPieces / $perSpread);

        foreach ($marker->lines as $ml) {
            WorkOrderLine::create([
                'work_order_id'    => $wo->id,
                'product_model_id' => $ml->product_model_id,
                'size_id'          => $ml->size_id,
                'qty_per_spread'   => $ml->qty_per_spread,
                'planned_qty'      => $spreads * (int) $ml->qty_per_spread,
            ]);
        }
    }
}
