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
            'awaiting_inspection' => Consignment::whereIn('status', ['received','inspecting'])->count(),
            'awaiting_lab'        => Consignment::where('status', 'lab_pending')->count(),
            'ready'               => Consignment::readyForProduction()->count(),
            'ready_kg'            => (float) Consignment::readyForProduction()->sum('remaining_kg'),
        ];

        $workOrders = [
            'open'   => WorkOrder::open()->count(),
            'late'   => WorkOrder::late()->count(),
            'danger' => WorkOrder::open()->where('variance_flag', 'danger')->count(),
            'outstanding' => (int) WorkOrder::open()->selectRaw('SUM(cut_pieces - received_pieces) as o')->value('o'),
        ];

        // ── تحميل المصانع ──
        $factoryLoad = Factory::where('is_active', true)->get()->map(function ($f) {
            $open = WorkOrder::open()->where('factory_id', $f->id);
            return [
                'factory'     => $f,
                'open'        => (clone $open)->count(),
                'late'        => (clone $open)->whereNotNull('due_date')->whereDate('due_date','<',now())->count(),
                'outstanding' => (int) (clone $open)->selectRaw('SUM(cut_pieces - received_pieces) as o')->value('o'),
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
            'pendingPOs'   => PurchaseOrder::where('status', 'pending')->count(),
            'myApprovals'  => $user ? ApprovalEngine::pendingFor($user)->limit(8)->get() : collect(),
            'lateOrders'   => WorkOrder::late()->with(['factory','consignment'])->limit(8)->get(),
        ]);
    }
}
