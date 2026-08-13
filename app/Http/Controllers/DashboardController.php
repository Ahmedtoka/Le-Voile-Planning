<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\DocumentComment;
use App\Models\Factory;
use App\Models\GoodsReceipt;
use App\Models\LabReport;
use App\Models\Marker;
use App\Models\MarkerRequest;
use App\Models\ProductionReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\FabricInspection;
use App\Models\WorkOrder;
use App\Services\ApprovalEngine;
use App\Services\CoverageService;
use App\Services\MenuCounters;

/**
 * لوحة التحكم — صورة السيستم كله في شاشة واحدة.
 *
 * مقسومة بترتيب الفلو: دورة الشراء ⇒ وصول القماش ⇒ التشغيل ⇒ التخطيط.
 * كل بوكس تحته سطر بيشرح الرقم معناه إيه وبيروح فين.
 */
class DashboardController extends Controller
{
    /** الطرح على أعمدة unsigned لازم CAST — الاستلام ممكن يزيد عن المقصوص */
    private const OUTSTANDING = 'SUM(GREATEST(CAST(cut_pieces AS SIGNED) - CAST(received_pieces AS SIGNED), 0)) as o';

    public function index()
    {
        $user = auth()->user();

        return view('dashboard', [
            'title'     => 'لوحة التحكم',
            'counters'  => MenuCounters::for($user),

            'purchase'  => $this->purchase(),
            'fabric'    => $this->fabric(),
            'production'=> $this->production(),
            'planning'  => $this->planning(),

            'factoryLoad' => $this->factoryLoad(),
            'coverage'    => array_slice(CoverageService::overview(), 0, 8),
            'myApprovals' => $user ? ApprovalEngine::pendingFor($user)->limit(6)->get() : collect(),
            'lateOrders'  => WorkOrder::late()->with(['factory','consignment'])->limit(6)->get(),
            'talk'        => DocumentComment::with('user')->where('kind', '!=', 'system')
                                ->latest('id')->limit(6)->get(),
        ]);
    }

    // ── ① دورة الشراء ───────────────────────────────────────────
    private function purchase(): array
    {
        $byStage = PurchaseOrder::query()->selectRaw('stage, COUNT(*) c')->groupBy('stage')->pluck('c', 'stage');

        return [
            'planning'   => (int) ($byStage['planning'] ?? 0),
            'purchasing' => (int) ($byStage['purchasing'] ?? 0),
            'finance'    => (int) ($byStage['finance'] ?? 0),
            'approval'   => (int) ($byStage['approval'] ?? 0),
            'open'       => PurchaseOrder::whereNotIn('stage', ['closed','cancelled'])->count(),
            'payable'    => (float) PurchaseOrder::whereIn('stage', ['finance','approval','approved','receiving'])
                                ->sum('total'),
            'due_30'     => (float) PurchaseOrder::whereIn('stage', ['approved','receiving'])
                                ->whereNotNull('delivery_date')
                                ->whereDate('delivery_date', '<=', now()->addDays(30))->sum('total'),
            'late_supply'=> PurchaseOrder::whereIn('stage', ['approved','receiving'])
                                ->whereNotNull('delivery_date')
                                ->whereDate('delivery_date', '<', now())->count(),
        ];
    }

    // ── ② وصول القماش ───────────────────────────────────────────
    private function fabric(): array
    {
        return [
            'awaiting_inspection' => Consignment::where('status', 'under_inspection')
                ->whereDoesntHave('inspections', fn ($q) => $q->where('status', 'approved'))->count(),
            'awaiting_lab' => Consignment::onHold()
                ->whereDoesntHave('labReports', fn ($q) => $q->where('status', 'approved'))->count(),
            'awaiting_release' => Consignment::whereIn('status', ['inspected','lab_done'])
                ->whereHas('inspections', fn ($q) => $q->where('status', 'approved'))
                ->whereHas('labReports',  fn ($q) => $q->where('status', 'approved'))
                ->whereDoesntHave('goodsReceipts', fn ($q) => $q->where('status', 'approved'))->count(),
            'hold_kg'     => (float) Consignment::onHold()->sum('total_kg'),
            'ready'       => Consignment::readyForProduction()->count(),
            'ready_kg'    => (float) Consignment::readyForProduction()->sum('remaining_kg'),
            'rejected'    => Consignment::where('status', 'rejected')->count(),
            'roll_variance' => FabricInspection::where('rolls_variance', '!=', 0)->count(),
            'width_alerts'  => FabricInspection::where('width_alert', true)->count(),
            'docs' => [
                'additions'   => StockAddition::count(),
                'inspections' => FabricInspection::count(),
                'labs'        => LabReport::count(),
                'receipts'    => GoodsReceipt::count(),
            ],
        ];
    }

    // ── ③ التشغيل ───────────────────────────────────────────────
    private function production(): array
    {
        return [
            'marker_requests' => MarkerRequest::whereIn('status', ['open','in_progress'])->count(),
            'markers_ready'   => Marker::where('status', 'approved')->where('is_active', true)->count(),
            'open'            => WorkOrder::open()->count(),
            'late'            => WorkOrder::late()->count(),
            'danger'          => WorkOrder::open()->where('variance_flag', 'danger')->count(),
            'outstanding'     => (int) WorkOrder::open()->selectRaw(self::OUTSTANDING)->value('o'),
            'cut_pending'     => WorkOrder::whereIn('status', ['sent_to_factory','cutting'])
                                    ->whereDoesntHave('cutDeclarations', fn ($q) => $q->where('status','approved'))
                                    ->count(),
            'closed_month'    => WorkOrder::where('status', 'closed')
                                    ->whereMonth('updated_at', now()->month)->count(),
            'received_month'  => (int) ProductionReceipt::where('status', 'approved')
                                    ->whereMonth('doc_date', now()->month)->sum('total_pieces'),
            'cut_docs'        => CutDeclaration::count(),
        ];
    }

    // ── ④ التخطيط ───────────────────────────────────────────────
    private function planning(): array
    {
        $cov = collect(CoverageService::overview());

        return [
            'danger'  => $cov->whereIn('flag', ['out','danger'])->count(),
            'watch'   => $cov->where('flag', 'watch')->count(),
            'ok'      => $cov->whereIn('flag', ['ok','high'])->count(),
            'unknown' => $cov->where('flag', 'unknown')->count(),
            'models'  => $cov->count(),
        ];
    }

    private function factoryLoad()
    {
        return Factory::where('is_active', true)->get()->map(function ($f) {
            $open = WorkOrder::open()->where('factory_id', $f->id);
            return [
                'factory'     => $f,
                'open'        => (clone $open)->count(),
                'late'        => (clone $open)->whereNotNull('due_date')->whereDate('due_date','<',now())->count(),
                'outstanding' => (int) (clone $open)->selectRaw(self::OUTSTANDING)->value('o'),
                'capacity'    => (int) $f->daily_capacity_pcs,
            ];
        })->filter(fn ($r) => $r['open'] > 0)->sortByDesc('outstanding')->values();
    }
}
