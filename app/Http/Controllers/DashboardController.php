<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Forecast;
use App\Models\SalesSnapshot;
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

            // ── الأناليتكس ──
            'trend'       => $this->trend(),
            'statusMix'   => $this->statusMix(),
            'coverageMix' => $this->coverageMix(),
            'upcoming'    => $this->upcoming(),
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

    /**
     * اتجاه 12 شهر: المبيعات مقابل الإنتاج المستلم مقابل الفوركاست.
     * الشارت ده بيجاوب على السؤال الوحيد المهم: بننتج بقدر ما بنبيع ولا لأ.
     */
    private function trend(): array
    {
        $months = collect(range(11, 0))->map(fn ($k) => now()->copy()->subMonths($k)->startOfMonth());

        $sales = SalesSnapshot::query()
            ->selectRaw("DATE_FORMAT(period_from, '%Y-%m') ym, SUM(qty_pcs) q")
            ->where('period_from', '>=', $months->first()->toDateString())
            /*
             | اللقطات الشهرية بس. لقطة «آخر 30 يوم» بتتشال لأنها بتتداخل
             | مع الشهر الحالي — والفلتر على LAST_DAY أدق من DAY()=1 لأن
             | يوم 31 في شهر 31 يوم بيخلي الاتنين متساويين.
            */
            ->whereRaw('DAY(period_from) = 1 AND period_to = LAST_DAY(period_from)')
            // الاستيراد المتكرر بيسجّل مراجعة جديدة — بناخد الأحدث بس
            ->whereRaw('revision = (SELECT MAX(s2.revision) FROM sales_snapshots s2
                        WHERE s2.product_model_id <=> sales_snapshots.product_model_id
                          AND s2.period_from = sales_snapshots.period_from)')
            ->groupBy('ym')->pluck('q', 'ym');

        $prod = ProductionReceipt::query()
            ->selectRaw("DATE_FORMAT(doc_date, '%Y-%m') ym, SUM(total_pieces) q")
            ->where('status', 'approved')
            ->where('doc_date', '>=', $months->first()->toDateString())
            ->groupBy('ym')->pluck('q', 'ym');

        $fc = Forecast::query()
            ->selectRaw("CONCAT(year, '-', LPAD(month, 2, '0')) ym, SUM(forecast_qty) q")
            ->groupBy('ym')->pluck('q', 'ym');

        $names = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',
                  7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];

        return [
            'labels'   => $months->map(fn ($m) => $names[$m->month] . ' ' . substr((string) $m->year, 2))->all(),
            'sales'    => $months->map(fn ($m) => (int) ($sales[$m->format('Y-m')] ?? 0))->all(),
            'produced' => $months->map(fn ($m) => (int) ($prod[$m->format('Y-m')] ?? 0))->all(),
            'forecast' => $months->map(fn ($m) => (int) ($fc[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    /** توزيع الأحواض على الحالات — بيوريك القماش واقف فين */
    private function statusMix(): array
    {
        $rows = Consignment::query()->selectRaw('status, COUNT(*) c, SUM(total_kg) kg')
            ->groupBy('status')->get();

        $order = ['under_inspection', 'inspected', 'lab_done', 'released', 'in_production', 'closed', 'rejected'];
        $colors = [
            'under_inspection' => '#D9A114', 'inspected' => '#2E86AB', 'lab_done' => '#5B7FA6',
            'released' => '#1B7A50', 'in_production' => '#9D197E', 'closed' => '#6B606A',
            'rejected' => '#B5342B',
        ];

        $out = ['labels' => [], 'data' => [], 'kg' => [], 'colors' => []];
        foreach ($order as $st) {
            $r = $rows->firstWhere('status', $st);
            if (!$r || $r->c == 0) continue;
            $out['labels'][] = Consignment::STATUSES[$st] ?? $st;
            $out['data'][]   = (int) $r->c;
            $out['kg'][]     = round((float) $r->kg);
            $out['colors'][] = $colors[$st];
        }
        return $out;
    }

    /** توزيع الموديلات على شرايح التغطية */
    private function coverageMix(): array
    {
        $cov = collect(CoverageService::overview());
        $map = [
            'out'     => ['خلص',        '#3B092F'],
            'danger'  => ['خطر',        '#B5342B'],
            'watch'   => ['مراقبة',     '#D9A114'],
            'ok'      => ['تمام',       '#1B7A50'],
            'high'    => ['مخزون عالي', '#2E86AB'],
            'unknown' => ['مفيش مبيعات','#9A8E96'],
        ];

        $out = ['labels' => [], 'data' => [], 'colors' => []];
        foreach ($map as $flag => [$label, $color]) {
            $n = $cov->where('flag', $flag)->count();
            if (!$n) continue;
            $out['labels'][] = $label;
            $out['data'][]   = $n;
            $out['colors'][] = $color;
        }
        return $out;
    }

    /**
     * المواعيد القادمة — توريدات من الموردين وتسليمات من المصانع.
     * المتأخر بيظهر الأول لأنه اللي محتاج تحرّك.
     */
    private function upcoming(): array
    {
        $rows = [];

        foreach (PurchaseOrder::with('supplier')
            ->whereIn('stage', ['approved', 'receiving'])
            ->whereNotNull('delivery_date')
            ->whereDate('delivery_date', '<=', now()->addDays(45))
            ->orderBy('delivery_date')->limit(12)->get() as $po) {
            $rows[] = [
                'date'  => $po->delivery_date,
                'kind'  => 'توريد',
                'icon'  => 'bi-truck',
                'no'    => $po->po_no,
                'who'   => $po->supplier?->name ?? '—',
                'note'  => number_format((float) $po->total_qty, 0) . ' وحدة',
                'link'  => route('purchase-orders.edit', $po),
            ];
        }

        foreach (WorkOrder::with('factory')->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDays(45))
            ->orderBy('due_date')->limit(12)->get() as $wo) {
            $rows[] = [
                'date'  => $wo->due_date,
                'kind'  => 'تسليم',
                'icon'  => 'bi-hammer',
                'no'    => $wo->wo_no,
                'who'   => $wo->factory?->name ?? '—',
                'note'  => number_format($wo->outstanding_pieces) . ' قطعة متبقية',
                'link'  => route('work-orders.show', $wo),
            ];
        }

        usort($rows, fn ($a, $b) => $a['date'] <=> $b['date']);

        return array_slice($rows, 0, 14);
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
