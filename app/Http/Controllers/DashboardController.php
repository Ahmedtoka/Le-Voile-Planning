<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Factory;
use App\Models\PurchaseOrder;
use App\Models\WorkOrder;
use App\Services\ApprovalEngine;
use App\Services\CoverageService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // ── الحوض والتشغيل ──
        $consignments = [
            // تحت الفحص = وصل بإذن إضافة ولسه ما اتفحصش
            'awaiting_inspection' => Consignment::where('status', 'under_inspection')->count(),
            // مستني معمل = محجوز ومالوش تقرير معمل معتمد
            'awaiting_lab'        => Consignment::onHold()
                                        ->whereDoesntHave('labReports', fn ($q) => $q->where('status', 'approved'))
                                        ->count(),
            // مستني إفراج = اتفحص وخلّص معمل ومالوش إذن استلام
            'awaiting_release'    => Consignment::whereIn('status', ['inspected', 'lab_done'])
                                        ->whereDoesntHave('goodsReceipts', fn ($q) => $q->where('status', 'approved'))
                                        ->count(),
            'hold_kg'             => (float) Consignment::onHold()->sum('total_kg'),
            'ready'               => Consignment::readyForProduction()->count(),
            'ready_kg'            => (float) Consignment::readyForProduction()->sum('remaining_kg'),
        ];

        $workOrders = [
            'open'   => WorkOrder::open()->count(),
            'late'   => WorkOrder::late()->count(),
            'danger' => WorkOrder::open()->where('variance_flag', 'danger')->count(),
            // cut_pieces و received_pieces أعمدة unsigned — الطرح المباشر بيرمي
            // خطأ في MySQL لو الاستلام زاد. CAST + GREATEST بيأمّنوا الحسبة.
            'outstanding' => (int) WorkOrder::open()
                ->selectRaw('SUM(GREATEST(CAST(cut_pieces AS SIGNED) - CAST(received_pieces AS SIGNED), 0)) as o')
                ->value('o'),
        ];

        // ── تحميل المصانع ──
        $factoryLoad = Factory::where('is_active', true)->get()->map(function ($f) {
            $open = WorkOrder::open()->where('factory_id', $f->id);
            return [
                'factory'     => $f,
                'open'        => (clone $open)->count(),
                'late'        => (clone $open)->whereNotNull('due_date')->whereDate('due_date','<',now())->count(),
                'outstanding' => (int) (clone $open)
                    ->selectRaw('SUM(GREATEST(CAST(cut_pieces AS SIGNED) - CAST(received_pieces AS SIGNED), 0)) as o')
                    ->value('o'),
            ];
        })->filter(fn ($r) => $r['open'] > 0)->values();

        // ── أخطر 10 موديلات في التغطية ──
        $coverage = array_slice(CoverageService::overview(), 0, 10);

        return view('dashboard', [
            'title'        => 'لوحة التحكم',
            'consignments' => $consignments,
            'workOrders'   => $workOrders,
            'factoryLoad'  => $factoryLoad,
            'coverage'     => $coverage,
            'poStages'     => PurchaseOrder::query()->selectRaw('stage, COUNT(*) c')
                                  ->whereIn('stage', ['planning','purchasing','finance','approval'])
                                  ->groupBy('stage')->pluck('c', 'stage'),
            'myApprovals'  => $user ? ApprovalEngine::pendingFor($user)->limit(8)->get() : collect(),
            'lateOrders'   => WorkOrder::late()->with(['factory','consignment'])->limit(8)->get(),
        ]);
    }
}
