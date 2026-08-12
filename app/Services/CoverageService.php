<?php

namespace App\Services;

use App\Models\ProductModel;
use App\Models\SafetyStock;
use App\Models\SalesSnapshot;
use App\Models\StockSnapshot;
use Illuminate\Support\Carbon;

/**
 * أيام التغطية.
 *
 * الطريقة القديمة كانت "نواقص" — تفتح المخزن، تلاقي لون ناقص، تعمله.
 * إطفاء حرايق. الطريقة دي بتبص على متوسط مبيعات آخر 30 يوم وتقول
 * الرصيد الحالي يكفي كام يوم، وتصنّف: خطر / مراقبة / تمام.
 *
 *   متوسط البيع اليومي = مبيعات آخر 30 يوم ÷ 30
 *   أيام التغطية       = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي
 */
class CoverageService
{
    public static function forModel(int $modelId, ?int $colorId = null, ?Carbon $asOf = null): array
    {
        $asOf   = $asOf ?: now();
        $window = (int) config('lvplanning.avg_sales_window_days', 30);
        $from   = $asOf->copy()->subDays($window);

        // متوسط المبيعات اليومي
        $soldQty = (float) SalesSnapshot::query()
            ->where('product_model_id', $modelId)
            ->when($colorId, fn ($q) => $q->where('color_id', $colorId))
            ->whereBetween('period_from', [$from->toDateString(), $asOf->toDateString()])
            ->sum('qty_pcs');

        $avgDaily = $window > 0 ? $soldQty / $window : 0;

        // آخر رصيد
        $lastPull = StockSnapshot::query()
            ->where('product_model_id', $modelId)
            ->when($colorId, fn ($q) => $q->where('color_id', $colorId))
            ->max('pulled_at');

        $stock = (float) StockSnapshot::query()
            ->where('product_model_id', $modelId)
            ->when($colorId, fn ($q) => $q->where('color_id', $colorId))
            ->when($lastPull, fn ($q) => $q->whereDate('pulled_at', $lastPull))
            ->sum('qty_pcs');

        // مخزون الأمان — بيتخصم قبل حساب التغطية
        $safety = (float) SafetyStock::query()
            ->where('product_model_id', $modelId)
            ->when($colorId, fn ($q) => $q->where('color_id', $colorId), fn ($q) => $q->whereNull('color_id'))
            ->value('qty_pcs');

        $usable = $stock - $safety;
        $days   = $avgDaily > 0 ? round($usable / $avgDaily, 1) : null;

        return [
            'model_id'    => $modelId,
            'color_id'    => $colorId,
            'sold_window' => round($soldQty, 1),
            'avg_daily'   => round($avgDaily, 2),
            'stock'       => round($stock, 1),
            'safety'      => round($safety, 1),
            'usable'      => round($usable, 1),
            'cover_days'  => $days,
            'flag'        => self::flag($days),
            'flag_label'  => self::flagLabel(self::flag($days)),
            'last_pull'   => $lastPull,
        ];
    }

    public static function flag(?float $days): string
    {
        if ($days === null)   return 'unknown';
        $c = config('lvplanning.coverage');
        if ($days <= 0)                    return 'out';
        if ($days <= $c['danger_days'])    return 'danger';
        if ($days <= $c['watch_days'])     return 'watch';
        if ($days <= $c['ok_days'])        return 'ok';
        return 'high';
    }

    public static function flagLabel(string $flag): string
    {
        return [
            'out'     => 'خلص',
            'danger'  => 'خطر',
            'watch'   => 'مراقبة',
            'ok'      => 'تمام',
            'high'    => 'مخزون عالي',
            'unknown' => 'مفيش مبيعات',
        ][$flag] ?? $flag;
    }

    public static function flagColor(string $flag): string
    {
        return [
            'out'     => 'dark',
            'danger'  => 'danger',
            'watch'   => 'warning',
            'ok'      => 'success',
            'high'    => 'info',
            'unknown' => 'secondary',
        ][$flag] ?? 'secondary';
    }

    /** جدول التغطية لكل الموديلات النشطة */
    public static function overview(): array
    {
        $rows = [];
        foreach (ProductModel::where('is_active', true)->orderBy('name')->get() as $m) {
            $row = self::forModel($m->id);
            $row['model'] = $m;
            $rows[] = $row;
        }

        // الأخطر الأول
        usort($rows, fn ($a, $b) => ($a['cover_days'] ?? PHP_INT_MAX) <=> ($b['cover_days'] ?? PHP_INT_MAX));

        return $rows;
    }
}
