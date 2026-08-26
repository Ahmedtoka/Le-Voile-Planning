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
 * ده الرقم الوحيد في المنيو، ومعناه واحد بس: <b>فيه حاجة محتاجة أكشن منك
 * هنا دلوقتي</b>. مش عدد السجلات، ومش إحصائية.
 *
 * القاعدة: الرقم لازم يوصل صفر لما تخلّص شغلك. لو رقم مش بيقل، يبقى فيه
 * حاجة واقفة ومحتاجة تتفتح.
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

        // ── المشتريات: الطلبات اللي نزلت من التخطيط ومستنية تسعير ──
        if ($can('po.source')) {
            $c['purchasing.queue'] = PurchaseOrder::where('stage', 'purchasing')->count();
        }

        // الحسابات متابعة بس — مفيش أكشن مطلوب فمفيش كاونتر.


        // ── إذن إضافة: مسوداتي ──
        if ($can('receipt.manage')) {
            $c['stock-additions.index'] = StockAddition::whereIn('status', ['draft', 'rejected'])
                ->where('created_by', $user->id)->count();
        }

        // ── الفحص: أحواض وصلت ولسه ما اتفحصتش ──
        if ($can('qc.manage')) {
            $c['inspections.index'] = Consignment::where('status', 'under_inspection')
                ->whereDoesntHave('inspections', fn ($q) => $q->where('status', 'approved'))
                ->count();

            // ── المعمل: أحواض ملهاش تقرير معمل ──
            $c['lab-reports.index'] = Consignment::where('status', 'inspected')
                ->whereDoesntHave('labReports', fn ($q) => $q->where('status', 'approved'))
                ->count();
        }

        // ── إذن الاستلام: أحواض متفحصة ومستنية إفراج ──
        if ($can('receipt.manage')) {
            $c['goods-receipts.index'] = Consignment::whereIn('status', ['inspected', 'lab_done'])
                ->whereHas('inspections', fn ($q) => $q->where('status', 'approved'))
                ->whereHas('labReports', fn ($q) => $q->where('status', 'approved'))
                ->whereDoesntHave('goodsReceipts', fn ($q) => $q->where('status', 'approved'))
                ->count();
        }

        // ── الأحواض: مفرج عنها ولسه ما اتعملهاش أمر شغل ⇒ محتاجة أكشن ──
        if ($can('wo.manage')) {
            $c['consignments.index'] = Consignment::readyForProduction()
                ->whereDoesntHave('workOrderFabrics', fn ($q) =>
                    $q->whereHas('workOrder', fn ($w) => $w->whereNotIn('status', ['draft', 'cancelled', 'superseded'])))
                ->count();
        }

        // ── طلبات الماركر: المسند ليّا ──
        if ($can('marker.manage')) {
            $c['markers.requests'] = MarkerRequest::whereIn('status', ['open', 'in_progress'])
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'))
                ->count();

            // مسوداتي أنا بس — مش مسودات الناس
            $c['markers.index'] = Marker::whereIn('status', ['draft', 'rejected'])
                ->where('created_by_patternist', $user->id)->count();
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
                ->whereDoesntHave('cutDeclarations', fn ($q) => $q->where('status', 'approved'))
                ->count();
        }

        // ── استلام الإنتاج: أوامر عليها متبقي ──
        if ($can('prod.manage')) {
            $c['production-receipts.index'] = WorkOrder::open()
                ->whereColumn('cut_pieces', '>', 'received_pieces')->count();
        }

        // ── إذن صرف خام: أوامر معتمدة ولسه ما اتصرفلهاش خامة ──
        if ($can('receipt.manage')) {
            $c['material-issues.index'] = WorkOrder::needsMaterial()->count();
        }

        // ── المرفوضات: البنود المفتوحة اللي محتاجة قرار ──
        if ($can('wo.manage') || $can('po.source')) {
            $c['rejections.index'] = \App\Models\GoodsReceiptRejection::open()->count();
        }

        // ── التغطية: موديلات في الخطر ──
        if ($can('forecast.view')) {
            $c['planning.coverage'] = collect(CoverageService::overview())
                ->whereIn('flag', ['out', 'danger'])->count();
        }

        return $cache[$user->id] = array_filter($c, fn ($n) => $n > 0);
    }
}
