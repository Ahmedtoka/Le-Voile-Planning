<?php

namespace App\Http\Controllers;

use App\Models\FabricType;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تقرير أسعار الشراء — بنجيب الخامة دي من مين وبكام.
 *
 * المصدر: سطور أوامر الشراء (التسكينات) — كل سطر = خامة اتشرت من مورد بكمية وسعر وتاريخ.
 * لكل (خامة + مورد + وحدة): آخر سعر، المتوسط المرجّح بالكمية، أقل وأعلى سعر، عدد المرات، والكمية.
 * الأرخص لكل خامة بيتعلّم — عشان المشتريات تعرف تفاوض وتختار.
 */
class PurchasePriceController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('po_sourcing_lines as sl')
            ->join('po_sourcings as s', 's.id', '=', 'sl.po_sourcing_id')
            ->join('purchase_order_lines as pl', 'pl.id', '=', 'sl.purchase_order_line_id')
            ->where('sl.qty', '>', 0)->where('sl.unit_price', '>', 0);

        if ($f = $request->get('fabric_type_id')) $q->where('pl.fabric_type_id', $f);
        if ($s = $request->get('supplier_id'))    $q->where('s.supplier_id', $s);
        if ($from = $request->get('from'))        $q->whereDate('s.sourced_at', '>=', $from);
        if ($to = $request->get('to'))            $q->whereDate('s.sourced_at', '<=', $to);

        $rows = (clone $q)
            ->selectRaw('pl.fabric_type_id, s.supplier_id, sl.unit,
                COUNT(*) times, SUM(sl.qty) qty, SUM(sl.line_total) total,
                MIN(sl.unit_price) min_price, MAX(sl.unit_price) max_price, MAX(s.sourced_at) last_at')
            ->groupBy('pl.fabric_type_id', 's.supplier_id', 'sl.unit')
            ->get();

        // آخر سعر لكل (خامة + مورد + وحدة) — سطر آخر تسكينة
        $last = (clone $q)
            ->select('pl.fabric_type_id', 's.supplier_id', 'sl.unit', 'sl.unit_price', 's.sourced_at', 'sl.id')
            ->orderBy('s.sourced_at')->orderBy('sl.id')
            ->get()
            ->keyBy(fn ($r) => $r->fabric_type_id . '|' . $r->supplier_id . '|' . $r->unit);

        $fabrics   = FabricType::orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::orderBy('name')->pluck('name', 'id');

        $groups = $rows->map(function ($r) use ($last) {
                $r->avg_price  = $r->qty > 0 ? $r->total / $r->qty : 0;
                $r->last_price = $last->get($r->fabric_type_id . '|' . $r->supplier_id . '|' . $r->unit)?->unit_price;
                return $r;
            })
            ->groupBy(fn ($r) => $r->fabric_type_id . '|' . $r->unit)
            ->map(fn ($g) => $g->sortBy('avg_price')->values())
            ->sortBy(fn ($g) => $fabrics[$g->first()->fabric_type_id] ?? '');

        return view('reports.purchase_prices', [
            'title'     => 'أسعار الشراء — الخامة من مين وبكام',
            'groups'    => $groups,
            'fabrics'   => $fabrics,
            'suppliers' => $suppliers,
            'summary'   => [
                ['label' => 'خامات اتشرت', 'value' => $rows->pluck('fabric_type_id')->unique()->count(),
                 'note' => 'خامات ليها سعر شراء في الفترة دي.'],
                ['label' => 'موردين', 'value' => $rows->pluck('supplier_id')->unique()->count(), 'tone' => 'brand',
                 'note' => 'موردين اتشرى منهم.'],
                ['label' => 'أوامر شراء', 'value' => (clone $q)->distinct('s.id')->count('s.id'), 'tone' => 'ok',
                 'note' => 'تسكينات بأسعار — كل مورد بأمره.'],
                ['label' => 'خامة بأكتر من مورد', 'value' => $groups->filter(fn ($g) => $g->count() > 1)->count(), 'tone' => 'warn',
                 'note' => 'فيها مقارنة أسعار — الأرخص متعلّم في الجدول.'],
            ],
        ]);
    }
}
