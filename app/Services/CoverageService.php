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
 *   متوسط البيع اليومي = مبيعات أحدث فترة كاملة (شهر/30 يوم) ÷ عدد أيامها
 *   أيام التغطية       = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي
 */
class CoverageService
{
    public static function forModel(int $modelId, ?int $colorId = null, ?Carbon $asOf = null): array
    {
        $asOf   = $asOf ?: now();

        /* متوسط المبيعات اليومي — من أحدث فترة مبيعات كاملة (شهر أو 30 يوم)
           خلصت قبل النهارده، بأحدث مراجعة ليها، مقسومة على عدد أيامها.
           ليه مش جمع كل اللقطات في آخر 30 يوم؟ لأن اللقطة الشهرية ولقطة
           «آخر 30 يوم» بيتداخلوا، والاستيراد المتكرر بيعمل مراجعات —
           الجمع كان بيكرر المبيعات ويطلّع التغطية أقل من الحقيقة. */
        $base = SalesSnapshot::query()
            ->where('product_model_id', $modelId)
            ->when($colorId, fn ($q) => $q->where('color_id', $colorId))
            ->latestRevision()
            ->whereDate('period_to', '<=', $asOf->toDateString())
            ->whereRaw('DATEDIFF(period_to, period_from) >= 27');

        $period = (clone $base)->orderByDesc('period_to')->orderBy('period_from')
            ->first(['period_from', 'period_to']);

        $soldQty = 0.0; $periodDays = null;
        if ($period) {
            $soldQty = (float) (clone $base)
                ->whereDate('period_from', $period->period_from)
                ->whereDate('period_to', $period->period_to)
                ->sum('qty_pcs');
            $periodDays = $period->period_from->diffInDays($period->period_to) + 1;
        }

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

        return self::build($modelId, $colorId, $soldQty, $periodDays, $stock, $safety, $lastPull);
    }

    /**
     * تركيب صف التغطية من الأرقام الخام — نفس الحسبة لـ forModel() و overview()
     * عشان الاتنين يطلّعوا نفس النتيجة بالظبط مهما اختلفت طريقة جلب الأرقام.
     */
    private static function build(int $modelId, ?int $colorId, float $soldQty, ?int $periodDays, float $stock, float $safety, $lastPull): array
    {
        $periodDays ??= (int) config('lvplanning.avg_sales_window_days', 30);
        $avgDaily = $periodDays > 0 ? $soldQty / $periodDays : 0;

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

    /** النتيجة بتتحفظ في الميموري لطول الريكوست — بتتطلب من المنيو والداشبورد أكتر من مرة */
    private static ?array $memo = null;

    /**
     * جدول التغطية لكل الموديلات النشطة.
     *
     * بتتنادى من الداشبورد أكتر من مرة ومن كاونتر المنيو في كل صفحة،
     * فبنحفظ النتيجة في الميموري لطول الريكوست. أي كتابة على المخزون
     * في نفس الريكوست نادرة، ولو حصلت الصفحة اللي بعدها بتحسب من أول.
     */
    public static function overview(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $models = ProductModel::where('is_active', true)->orderBy('name')->get();
        $today  = now()->toDateString();

        /* المبيعات: مجموع كل فترة كاملة لكل موديل في استعلام واحد، مرتّب
           بنفس ترتيب forModel() (أحدث period_to ثم أقدم period_from) —
           فأول صف يقابل كل موديل هو «أحدث فترة كاملة» بتاعته. */
        $sales = [];
        $periods = SalesSnapshot::query()
            ->selectRaw('product_model_id, period_from, period_to, SUM(qty_pcs) AS q')
            ->latestRevision()
            ->whereDate('period_to', '<=', $today)
            ->whereRaw('DATEDIFF(period_to, period_from) >= 27')
            ->groupBy('product_model_id', 'period_from', 'period_to')
            ->orderByDesc('period_to')->orderBy('period_from')
            ->get();
        foreach ($periods as $p) {
            if (isset($sales[$p->product_model_id])) continue;
            $sales[$p->product_model_id] = [
                (float) $p->q,
                $p->period_from->diffInDays($p->period_to) + 1,
            ];
        }

        /* الرصيد: مجموع كل سحبة لكل موديل، وأحدث سحبة هي اللي بتتحسب
           (نفس MAX(pulled_at) ثم SUM عند التاريخ ده في forModel). */
        $stocks = [];
        $pulls = StockSnapshot::query()->toBase()
            ->selectRaw('product_model_id, pulled_at, SUM(qty_pcs) AS q')
            ->groupBy('product_model_id', 'pulled_at')
            ->orderByDesc('pulled_at')
            ->get();
        foreach ($pulls as $s) {
            if (isset($stocks[$s->product_model_id])) continue;
            $stocks[$s->product_model_id] = [(float) $s->q, $s->pulled_at];
        }

        // مخزون الأمان على مستوى الموديل (من غير لون) — أول سجل لكل موديل
        $safeties = [];
        foreach (SafetyStock::query()->toBase()->whereNull('color_id')->orderBy('id')
                     ->get(['product_model_id', 'qty_pcs']) as $s) {
            $safeties[$s->product_model_id] ??= (float) $s->qty_pcs;
        }

        $rows = [];
        foreach ($models as $m) {
            [$soldQty, $periodDays] = $sales[$m->id]  ?? [0.0, null];
            [$stock, $lastPull]     = $stocks[$m->id] ?? [0.0, null];

            $row = self::build($m->id, null, $soldQty, $periodDays, $stock, $safeties[$m->id] ?? 0.0, $lastPull);
            $row['model'] = $m;
            $rows[] = $row;
        }

        // الأخطر الأول
        usort($rows, fn ($a, $b) => ($a['cover_days'] ?? PHP_INT_MAX) <=> ($b['cover_days'] ?? PHP_INT_MAX));

        return self::$memo = $rows;
    }
}
