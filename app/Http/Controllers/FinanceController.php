<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * شاشة الحسابات — المستحقات المتوقعة للموردين.
 *
 * الحسابات مش بتوقف الطلب، بس بتتابع: إيه اللي جاي عليها فلوس،
 * ولمين، وإمتى تاريخ التوريد.
 */
class FinanceController extends Controller
{
    public function payables(Request $request)
    {
        $q = PurchaseOrder::with(['supplier', 'sourcer', 'lines'])
            ->whereNotNull('supplier_id')
            ->whereIn('stage', ['finance', 'approval', 'approved', 'receiving'])
            ->orderByRaw("FIELD(stage,'finance','approval','approved','receiving')")
            ->orderBy('delivery_date');

        if ($s = $request->get('supplier_id')) $q->where('supplier_id', $s);
        if ($st = $request->get('stage'))      $q->where('stage', $st);

        $rows = $q->paginate(30)->withQueryString();

        $base = PurchaseOrder::whereNotNull('supplier_id')
            ->whereIn('stage', ['finance', 'approval', 'approved', 'receiving']);

        return view('finance.payables', [
            'title'     => 'المستحقات المتوقعة للموردين',
            'rows'      => $rows,
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'stages'    => PurchaseOrder::STAGES,
            'summary'   => [
                'awaiting_ack' => (clone $base)->where('stage', 'finance')->count(),
                'total'        => (float) (clone $base)->sum('total'),
                'due_30'       => (float) (clone $base)
                                    ->whereNotNull('delivery_date')
                                    ->whereDate('delivery_date', '<=', now()->addDays(30))
                                    ->sum('total'),
            ],
            'bySupplier' => (clone $base)
                ->selectRaw('supplier_id, COUNT(*) orders, SUM(total) total')
                ->groupBy('supplier_id')
                ->with('supplier')
                ->get(),
        ]);
    }
}
