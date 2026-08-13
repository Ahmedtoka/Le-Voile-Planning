<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\FabricInspection;
use App\Models\GoodsReceipt;
use App\Models\LabReport;
use App\Models\Marker;
use App\Models\MarkerRequest;
use App\Models\ProductionReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * كاونترات المنيو — «اللي مستني مني أنا».
 *
 * الرقم اللي جنب كل شاشة معناه: فيه شغل عليك دلوقتي في المكان ده.
 * كل دور بيشوف أرقامه هو — المشتريات بتشوف الطلبات المستنية تسعير،
 * والفاحص يشوف الأحواض المستنية فحص، وهكذا.
 *
 * القاعدة: الرقم لازم يوصل صفر لما تخلّص شغلك. لو رقم مش بيقل،
 * يبقى فيه حاجة واقفة.
 */
class MenuCounters
{
    /** @return array<string,int> مفتاحه اسم الراوت */
    public static function for(?User $user): array
    {
        if (!$user) return [];

        static $cache = [];
        if (isset($cache[$user->id])) return $cache[$user->id];

        $can = fn (string $p) => $user->can2($p);
        $c   = [];

        // ── الاعتمادات: أي حاجة مستنية توقيعي ──
        $c['approvals.index'] = ApprovalEngine::pendingFor($user)->count();

        // ── طلبات الشراء: كل دور والمرحلة بتاعته ──
        $po = 0;
        if ($can('po.request')) {
            $po += PurchaseOrder::where('stage', 'planning')
                     ->where('requested_by', $user->id)->count();
        }
        if ($can('po.source'))  $po += PurchaseOrder::where('stage', 'purchasing')->count();
        if ($can('po.finance')) $po += PurchaseOrder::where('stage', 'finance')->count();
        $c['purchase-orders.index'] = $po;

        // ── الحسابات: الطلبات المستنية علم ──
        if ($can('po.finance')) {
            $c['finance.payables'] = PurchaseOrder::where('stage', 'finance')->count();
        }

        // ── إذن إضافة: مسوداتي ──
        if ($can('receipt.manage')) {
            $c['stock-additions.index'] = StockAddition::whereIn('status', ['draft', 'rejected'])
                ->where('created_by', $user->id)->count();
        }

        // ── الفحص: أحواض وصلت ولسه ما اتفحصتش ──
        if ($can('qc.manage')) {
            $c['inspections.index'] = Consignment::where('status', 'under_inspection')
                ->whereDoesntHave('inspections', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
                ->count();

            // ── المعمل: أحواض ملهاش تقرير معمل ──
            $c['lab-reports.index'] = Consignment::onHold()
                ->whereDoesntHave('labReports', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
                ->count();
        }

        // ── إذن الاستلام: أحواض متفحصة ومستنية إفراج ──
        if ($can('receipt.manage')) {
            $c['goods-receipts.index'] = Consignment::whereIn('status', ['inspected', 'lab_done'])
                ->whereHas('inspections', fn ($q) => $q->where('status', 'approved'))
                ->whereHas('labReports', fn ($q) => $q->where('status', 'approved'))
                ->whereDoesntHave('goodsReceipts', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
                ->count();
        }

        // ── الأحواض: الجاهز للتشغيل ──
        if ($can('wo.manage')) {
            $c['consignments.index'] = Consignment::readyForProduction()->count();
        }

        // ── طلبات الماركر: المسند ليّا ──
        if ($can('marker.manage')) {
            $c['markers.requests'] = MarkerRequest::whereIn('status', ['open', 'in_progress'])
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'))
                ->count();

            $c['markers.index'] = Marker::whereIn('status', ['draft', 'rejected'])->count();
        }

        // ── أوامر الشغل: مسودات ومتأخرات ──
        if ($can('wo.manage') || $can('wo.view')) {
            $c['work-orders.index'] = WorkOrder::where(function ($q) use ($user) {
                $q->where(fn ($qq) => $qq->whereIn('status', ['draft', 'rejected'])->where('created_by', $user->id))
                  ->orWhere(fn ($qq) => $qq->open()->whereNotNull('due_date')
                                            ->whereDate('due_date', '<', now()->toDateString()));
            })->count();
        }

        // ── بيان القص: أوامر عند المصنع من غير بيان ──
        if ($can('cut.manage')) {
            $c['cut-declarations.index'] = WorkOrder::whereIn('status', ['sent_to_factory', 'cutting'])
                ->whereDoesntHave('cutDeclarations', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
                ->count();
        }

        // ── استلام الإنتاج: أوامر عليها متبقي ──
        if ($can('prod.manage')) {
            $c['production-receipts.index'] = WorkOrder::open()
                ->whereColumn('cut_pieces', '>', 'received_pieces')->count();
        }

        // ── التغطية: موديلات في الخطر ──
        if ($can('forecast.view')) {
            $c['planning.coverage'] = collect(CoverageService::overview())
                ->whereIn('flag', ['out', 'danger'])->count();
        }

        return $cache[$user->id] = array_filter($c, fn ($n) => $n > 0);
    }
}
