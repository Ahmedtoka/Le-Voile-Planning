<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;

/**
 * تقارير المخزون — نفس شكل شيت «رصيد القماش» اللي المخازن متعودة عليه:
 *
 *  ① رصيد الرسايل (ON Hand) — رصيد كل رسالة: الكود، الصنف، اللون، العرض،
 *    الأتواب، المتاح والمحجوز. المصدر: الأحواض نفسها، فالتقرير دايمًا
 *    مطابق للشاشات لأنه بيقرا من نفس المكان.
 *
 *  ② حركة المخزون (IN & OUT) — كل حركة داخلة وخارجة بمستندها:
 *    استلام مورد، إفراج، مرفوض للمرتجعات، صرف مصنع، استلام إنتاج.
 */
class StockReportController extends Controller
{
    use FiltersIndex;

    /** ترجمة نوع المستند المصدر لاسم العملية زي الشيت */
    public const OPERATIONS = [
        \App\Models\StockAddition::class     => 'استلام مورد',
        \App\Models\GoodsReceipt::class      => 'إفراج / رفض',
        \App\Models\MaterialIssue::class     => 'صرف مصنع',
        \App\Models\ProductionReceipt::class => 'استلام إنتاج',
    ];

    public function onhand(Request $request)
    {
        $q = Consignment::with(['fabricType', 'color', 'warehouse', 'supplier']);

        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($x) => $x->where('consignment_no', 'like', "%{$term}%")
                ->orWhereHas('fabricType', fn ($f) => $f->where('name', 'like', "%{$term}%"))
                ->orWhereHas('color', fn ($c) => $c->where('code', 'like', "%{$term}%")
                                                    ->orWhere('name', 'like', "%{$term}%")));
        }
        if ($request->filled('fabric_type_id')) $q->where('fabric_type_id', $request->get('fabric_type_id'));
        if ($request->filled('status'))         $q->where('status', $request->get('status'));
        if ($from = $request->get('from'))      $q->whereDate('arrival_date', '>=', $from);
        if ($to = $request->get('to'))          $q->whereDate('arrival_date', '<=', $to);

        // الافتراضي: اللي ليه رصيد بس — زي شيت ON Hand. «الكل» بيوري الصفري كمان
        if (!$request->boolean('all')) {
            $q->where(fn ($x) => $x->where('remaining_kg', '>', 0)->orWhere('hold_kg', '>', 0));
        }

        $base = Consignment::query();

        return view('reports.stock_onhand', [
            'title'   => 'رصيد الرسايل (ON Hand)',
            'rows'    => $this->applySort($q, $request,
                            ['consignment_no', 'arrival_date', 'total_kg', 'remaining_kg',
                             'hold_kg', 'rolls_count', 'min_width_cm', 'status'],
                            'arrival_date')->paginate(50)->withQueryString(),
            'fabrics' => FabricType::orderBy('name')->pluck('name', 'id'),
            'statuses'=> Consignment::STATUSES,
            'summary' => [
                ['label' => 'رسايل ليها رصيد',
                 'value' => (clone $base)->where(fn ($x) => $x->where('remaining_kg', '>', 0)->orWhere('hold_kg', '>', 0))->count(),
                 'tone' => 'brand', 'note' => 'رسالة فيها كمية متاحة أو محجوزة.'],
                ['label' => 'متاح للتشغيل (كجم)',
                 'value' => number_format((float) (clone $base)->sum('remaining_kg'), 0),
                 'tone' => 'ok', 'note' => 'مفرج عنه وغير مخصص لأمر شغل.'],
                ['label' => 'محجوز تحت الفحص (كجم)',
                 'value' => number_format((float) (clone $base)->sum('hold_kg'), 0),
                 'tone' => 'warn', 'note' => 'وصل ولسه ما اتفرجش عنه.'],
                ['label' => 'إجمالي الأتواب',
                 'value' => number_format((int) (clone $base)->where(fn ($x) => $x->where('remaining_kg', '>', 0)->orWhere('hold_kg', '>', 0))->sum('rolls_count')),
                 'note' => 'في الرسايل اللي ليها رصيد.'],
            ],
        ]);
    }

    public function movements(Request $request)
    {
        $q = StockMovement::with(['fabricType', 'color', 'consignment', 'warehouse', 'accessory']);

        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($x) => $x->where('reference', 'like', "%{$term}%")
                ->orWhereHas('consignment', fn ($c) => $c->where('consignment_no', 'like', "%{$term}%")));
        }
        if ($request->filled('direction'))    $q->where('direction', $request->get('direction'));
        if ($request->filled('warehouse_id')) $q->where('warehouse_id', $request->get('warehouse_id'));
        if ($request->filled('source_type'))  $q->where('source_type', $request->get('source_type'));
        if ($from = $request->get('from'))    $q->whereDate('moved_at', '>=', $from);
        if ($to = $request->get('to'))        $q->whereDate('moved_at', '<=', $to);

        $base = StockMovement::query();

        return view('reports.stock_movements', [
            'title'      => 'حركة المخزون (IN & OUT)',
            'rows'       => $this->applySort($q, $request,
                              ['moved_at', 'direction', 'qty', 'quality_state'], 'id')
                              ->paginate(50)->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'operations' => self::OPERATIONS,
            'summary'    => [
                ['label' => 'إجمالي الحركات', 'value' => $base->count(), 'note' => 'كل حركة داخلة وخارجة.'],
                ['label' => 'داخل (كجم)', 'value' => number_format((float) (clone $base)->where('direction', 'in')->where('item_type', 'fabric')->sum('qty'), 0),
                 'tone' => 'ok', 'note' => 'استلامات القماش.'],
                ['label' => 'خارج (كجم)', 'value' => number_format((float) (clone $base)->where('direction', 'out')->where('item_type', 'fabric')->sum('qty'), 0),
                 'tone' => 'warn', 'note' => 'صرف للمصانع والمرتجعات.'],
                ['label' => 'حركات النهارده', 'value' => (clone $base)->whereDate('moved_at', now()->toDateString())->count(),
                 'tone' => 'brand', 'note' => 'اللي اتسجل اليوم.'],
            ],
        ]);
    }
}
