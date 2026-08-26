<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\GoodsReceiptRejection;
use App\Models\MarkerRequest;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\WorkOrder;
use App\Services\CoverageService;

/**
 * ══════════════════════════════════════════════════════════════════
 *  مكاتب الإدارات — شاشة واحدة لكل إدارة
 * ══════════════════════════════════════════════════════════════════
 *
 * كل إدارة بتفتح شاشتها فتلاقي تلات حاجات بالترتيب:
 *   ① اللي مستني منها دلوقتي — بزرار الأكشن المباشر
 *   ② أرقام متابعة سريعة
 *   ③ جداول المتابعة — إيه اللي ماشي وإيه اللي واقف
 *
 * الإنشاء دايمًا في شاشة منفصلة — الشاشة دي للمتابعة والدخول للشغل بس.
 */
class DeskController extends Controller
{
    /** ① التخطيط — بيطلب الخامة وبيعمل أوامر الشغل */
    public function planning()
    {
        return view('desk.planning', [
            'title' => 'مكتب التخطيط',
            'queue' => [
                $this->q('أحواض جاهزة ومستنية أمر شغل',
                    Consignment::readyForProduction()
                        ->whereDoesntHave('workOrderFabrics', fn ($q) =>
                            $q->whereHas('workOrder', fn ($w) => $w->whereNotIn('status', ['draft', 'cancelled', 'superseded'])))
                        ->count(),
                    route('consignments.index', ['ready' => 1]), 'ابدأ أمر شغل', 'brand'),
                $this->q('موديلات في خطر نفاد',
                    collect(CoverageService::overview())->whereIn('flag', ['out', 'danger'])->count(),
                    route('planning.coverage'), 'شوف التغطية', 'danger'),
                $this->q('طلبات ماركر مفتوحة',
                    MarkerRequest::whereIn('status', ['open', 'in_progress'])->count(),
                    route('markers.requests'), 'الطلبات', 'warn'),
                $this->q('أوامر شغل متأخرة', WorkOrder::late()->count(),
                    route('work-orders.index', ['late' => 1]), 'افتحها', 'danger'),
            ],
            'myOrders' => PurchaseOrder::with('supplier')->latest('id')->limit(8)->get(),
            'openWos'  => WorkOrder::open()->with('factory')->latest('id')->limit(8)->get(),
            'create'   => [
                ['طلب شراء جديد', route('purchase-orders.create'), 'bi-file-earmark-plus'],
                ['أمر شغل جديد',  route('work-orders.create'),     'bi-hammer'],
            ],
        ]);
    }

    /** ② المشتريات — بتسعّر وتحدد المورد والتوريد */
    public function purchasing()
    {
        return view('desk.purchasing', [
            'title' => 'مكتب المشتريات',
            'queue' => [
                $this->q('طلبات مستنية تسعير', PurchaseOrder::where('stage', 'purchasing')->count(),
                    route('purchasing.queue'), 'سعّرها', 'warn'),
                $this->q('توريد متأخر عن موعده',
                    PurchaseOrder::whereIn('stage', ['approved', 'receiving'])
                        ->whereNotNull('delivery_date')
                        ->whereDate('delivery_date', '<', now())->count(),
                    route('purchase-orders.index'), 'كلّم الموردين', 'danger'),
                $this->q('مرفوضات مستنية ردّ المورد', GoodsReceiptRejection::open()->count(),
                    route('rejections.index'), 'المرفوضات', 'danger'),
            ],
            'priced' => PurchaseOrder::with('supplier')->whereIn('stage', ['approved', 'receiving'])
                            ->orderBy('delivery_date')->limit(10)->get(),
            'create' => [],
        ]);
    }

    /** ③ الحسابات — متابعة المستحق للموردين */
    public function finance()
    {
        $payable = PurchaseOrder::whereIn('stage', ['finance', 'approved', 'receiving']);

        return view('desk.finance', [
            'title' => 'مكتب الحسابات',
            'queue' => [
                $this->q('مستحقات جديدة ما اتشافتش',
                    PurchaseOrder::whereNotNull('sourced_at')->whereNull('finance_at')
                        ->whereNotIn('stage', ['closed', 'cancelled'])->count(),
                    route('finance.payables', ['unseen' => 1]), 'راجعها', 'warn'),
                $this->q('مستحق خلال 30 يوم',
                    (int) (clone $payable)->whereNotNull('delivery_date')
                        ->whereDate('delivery_date', '<=', now()->addDays(30))->count(),
                    route('finance.payables'), 'افتح', 'brand'),
            ],
            'payables' => PurchaseOrder::with('supplier')->whereIn('stage', ['finance', 'approved', 'receiving'])
                            ->orderBy('delivery_date')->limit(10)->get(),
            'create' => [],
        ]);
    }

    /** ④ المخزن — بيستلم وبيفرج وبيصرف */
    public function store()
    {
        return view('desk.store', [
            'title' => 'مكتب المخزن',
            'queue' => [
                $this->q('طلبات وصلت أو في الطريق',
                    PurchaseOrder::whereIn('stage', ['approved', 'receiving'])->count(),
                    route('stock-additions.index'), 'استلم', 'warn'),
                $this->q('رسايل جاهزة للإفراج',
                    Consignment::where('status', 'lab_done')
                        ->whereDoesntHave('goodsReceipts', fn ($q) => $q->where('status', 'approved'))->count(),
                    route('goods-receipts.index'), 'أفرج', 'brand'),
                $this->q('أوامر شغل لسه عايزة خام',
                    WorkOrder::needsMaterial()->count(),
                    route('material-issues.index'), 'اصرف', 'warn'),
                $this->q('قطع مستنية استلام من المصانع',
                    WorkOrder::open()->whereColumn('cut_pieces', '>', 'received_pieces')->count(),
                    route('production-receipts.index'), 'استلم', 'ok'),
            ],
            'recent' => StockAddition::with(['supplier', 'consignment'])->latest('id')->limit(10)->get(),
            'create' => [
                ['إذن إضافة جديد',  route('stock-additions.create'), 'bi-box-arrow-in-down'],
                ['استلام حاويات',   route('stock-additions.create', ['type' => 'container']), 'bi-box-seam'],
            ],
        ]);
    }

    /** ⑤ الجودة — الفحص والمرفوضات */
    public function quality()
    {
        return view('desk.quality', [
            'title' => 'مكتب الجودة',
            'queue' => [
                $this->q('رسايل وصلت ومستنية فحص',
                    Consignment::where('status', 'under_inspection')
                        ->whereDoesntHave('inspections', fn ($q) => $q->where('status', 'approved'))->count(),
                    route('inspections.index'), 'افحص', 'warn'),
                $this->q('رسايل مستنية المعمل',
                    Consignment::where('status', 'inspected')
                        ->whereDoesntHave('labReports', fn ($q) => $q->where('status', 'approved'))->count(),
                    route('lab-reports.index'), 'سجّل قراءات', 'warn'),
                $this->q('مرفوضات ومعلّق محتاج قرار', GoodsReceiptRejection::open()->count(),
                    route('rejections.index'), 'القرارات', 'danger'),
            ],
            'recent' => Consignment::with(['fabricType', 'color'])
                            ->whereIn('status', ['under_inspection', 'inspected'])
                            ->latest('id')->limit(10)->get(),
            'create' => [],
        ]);
    }

    /** ⑥ المصانع — القص والاستلام */
    public function factory()
    {
        return view('desk.factory', [
            'title' => 'مكتب متابعة المصانع',
            'queue' => [
                $this->q('أوامر عند المصنع مستنية بيان قص',
                    WorkOrder::whereIn('status', ['sent_to_factory', 'cutting'])
                        ->whereDoesntHave('cutDeclarations', fn ($q) => $q->where('status', 'approved'))->count(),
                    route('cut-declarations.index'), 'سجّل القص', 'warn'),
                $this->q('قطع مقصوصة لسه على المصانع',
                    WorkOrder::open()->whereColumn('cut_pieces', '>', 'received_pieces')->count(),
                    route('production-receipts.index'), 'استلم', 'brand'),
                $this->q('أوامر متأخرة', WorkOrder::late()->count(),
                    route('work-orders.index', ['late' => 1]), 'افتحها', 'danger'),
            ],
            'openWos' => WorkOrder::open()->with('factory')->latest('id')->limit(10)->get(),
            'create'  => [],
        ]);
    }

    /** عنصر طابور موحّد */
    private function q(string $label, int $count, string $link, string $action, string $tone): array
    {
        return compact('label', 'count', 'link', 'action', 'tone');
    }
}
