<?php

namespace App\Services;

use App\Models\Color;
use App\Models\ColorRatio;
use App\Models\Forecast;
use App\Models\ProductModel;
use App\Models\SalesSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * توليد الفوركاست.
 *
 * الوضع الحالي بصراحة: مفيش Data Set نضيف. المبيعات مش متاحة باللون،
 * والأرقام بتتعدّل بعد تسجيلها. عشان كده الطريقة هنا هي بالظبط اللي
 * بيشتغلوا بيها يدوي على الشيت:
 *
 *   1) خد إجمالي مبيعات الموديل في نفس الفترة السنة اللي فاتت (الأساس)
 *   2) وزّعه على الألوان بنسب مأخوذة من صرف المخزن — أو معدّلة يدوي
 *   3) ضيف نسبة النمو المتوقعة
 *
 * لما الداتا تبقى نضيفة، المحرك ده يتبدّل بواحد إحصائي حقيقي.
 */
class ForecastService
{
    /** نسب الألوان المستنتجة من صرف المخزن الرئيسي */
    public static function deriveColorRatios(int $modelId, int $year): array
    {
        $rows = DB::table('stock_movements')
            ->where('item_type', 'finished')
            ->where('direction', 'out')
            ->where('product_model_id', $modelId)
            ->whereYear('moved_at', $year)
            ->whereNotNull('color_id')
            ->groupBy('color_id')
            ->select('color_id', DB::raw('SUM(qty) as total'))
            ->pluck('total', 'color_id')->all();

        $sum = array_sum($rows);
        if ($sum <= 0) return [];

        $out = [];
        foreach ($rows as $colorId => $qty) {
            $out[$colorId] = round(($qty / $sum) * 100, 3);
        }
        return $out;
    }

    /**
     * توليد فوركاست سنة كاملة لموديل.
     *
     * @param  float $growthPct نسبة الزيادة المتوقعة على السنة الأساس
     * @param  array $ratios    [color_id => pct] — لو فاضية بيقرأها من color_ratios
     */
    public static function generate(int $modelId, int $targetYear, float $growthPct = 0, array $ratios = [], ?int $userId = null): int
    {
        $baseYear = $targetYear - 1;

        if (!$ratios) {
            $ratios = ColorRatio::where('product_model_id', $modelId)
                ->where('year', $baseYear)
                ->whereNull('month')
                ->pluck('ratio_pct', 'color_id')->all();
        }
        if (!$ratios) {
            $ratios = self::deriveColorRatios($modelId, $baseYear);
        }

        $created = 0;

        for ($month = 1; $month <= 12; $month++) {
            // الأساس: مبيعات نفس الشهر السنة اللي فاتت
            $base = (float) SalesSnapshot::where('product_model_id', $modelId)
                ->whereYear('period_from', $baseYear)
                ->whereMonth('period_from', $month)
                ->sum('qty_pcs');

            $target = $base * (1 + $growthPct / 100);

            if (!$ratios) {
                // مفيش نسب ألوان ⇒ سطر واحد بدون لون
                Forecast::updateOrCreate(
                    ['year' => $targetYear, 'month' => $month, 'product_model_id' => $modelId, 'color_id' => null],
                    ['base_qty' => $base, 'growth_pct' => $growthPct, 'forecast_qty' => round($target, 2),
                     'source' => 'generated', 'created_by' => $userId]
                );
                $created++;
                continue;
            }

            foreach ($ratios as $colorId => $pct) {
                Forecast::updateOrCreate(
                    ['year' => $targetYear, 'month' => $month, 'product_model_id' => $modelId, 'color_id' => $colorId],
                    [
                        'base_qty'     => round($base * ($pct / 100), 2),
                        'growth_pct'   => $growthPct,
                        'forecast_qty' => round($target * ($pct / 100), 2),
                        'source'       => 'generated',
                        'created_by'   => $userId,
                    ]
                );
                $created++;
            }
        }

        return $created;
    }

    /** تحديث الفعلي على الفوركاست من لقطات المبيعات المقفولة */
    public static function syncActuals(int $year): int
    {
        $updated = 0;

        foreach (Forecast::where('year', $year)->cursor() as $f) {
            $actual = (float) SalesSnapshot::where('product_model_id', $f->product_model_id)
                ->when($f->color_id, fn ($q) => $q->where('color_id', $f->color_id))
                ->whereYear('period_from', $year)
                ->whereMonth('period_from', $f->month)
                ->where('is_locked', true)
                ->sum('qty_pcs');

            $f->forceFill(['actual_qty' => $actual])->saveQuietly();
            $f->recalcAchievement();
            $updated++;
        }

        return $updated;
    }
}
