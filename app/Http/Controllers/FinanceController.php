<?php

namespace App\Http\Controllers;

use App\Models\PoSourcing;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * شاشة الحسابات — المستحقات المتوقعة للموردين.
 *
 * الحسابات مش بتوقف الطلب، بس بتتابع: إيه اللي جاي عليها فلوس،
 * ولمين، وإمتى تاريخ التوريد.
 *
 * الصف = أمر شراء (تسكينة)، مش طلب: طلب اتشرى من موردين بيظهر صفين،
 * كل واحد بمورده وإجماليه وعلمه — و«حسب المورد» بيجمع إجمالي كل مورد الحقيقي.
 */
class FinanceController extends Controller
{
    private const OPEN_STAGES = ['finance', 'approved', 'receiving'];

    public function payables(Request $request)
    {
        $open = fn ($q) => $q->whereHas('purchaseOrder', fn ($p) => $p->whereIn('stage', self::OPEN_STAGES));

        $q = PoSourcing::with(['purchaseOrder' => fn ($p) => $p->withCount('sourcings'), 'supplier', 'sourcer'])
            ->tap($open)
            // الأحدث فوق زي كل جداول السيستم.
            // عايز اللي ما اتشافش بس؟ الكارت في مكتب الحسابات بيفتح ?unseen=1
            ->orderByDesc('sourced_at')->orderByDesc('id');

        if ($s = $request->get('supplier_id')) $q->where('supplier_id', $s);
        if ($st = $request->get('stage'))      $q->whereHas('purchaseOrder', fn ($p) => $p->where('stage', $st));
        if ($request->get('unseen'))           $q->whereNull('finance_at');

        $rows = $q->paginate(30)->withQueryString();

        $base = PoSourcing::query()->tap($open);

        return view('finance.payables', [
            'title'     => 'المستحقات المتوقعة للموردين',
            'rows'      => $rows,
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'stages'    => PurchaseOrder::STAGES,
            'summary'   => [
                'priced_today' => PoSourcing::whereDate('sourced_at', now()->toDateString())->count(),
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
                ->orderByDesc('total')
                ->get(),
        ]);
    }
}
