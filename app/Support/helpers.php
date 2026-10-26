<?php

/*
|--------------------------------------------------------------------------
| هيلبرز العرض — الأرقام بوحدتها ومن غير أصفار زيادة
|--------------------------------------------------------------------------
| 10000.000 كجم ⇒ «10,000 كجم (10 طن)» · 2.000 طن ⇒ «2 طن» · 1.750 ⇒ «1.75»
*/

if (! function_exists('lv_num')) {
    /** رقم من غير أصفار زيادة بعد العلامة */
    function lv_num(float|int|string|null $v, int $decimals = 3): string
    {
        if ($v === null || $v === '') return '—';
        return rtrim(rtrim(number_format((float) $v, $decimals), '0'), '.');
    }
}

if (! function_exists('lv_qty')) {
    /** كمية بوحدتها: lv_qty(2, 'طن') ⇒ «2 طن» */
    function lv_qty(float|int|string|null $v, ?string $unit = null, int $decimals = 3): string
    {
        if ($v === null || $v === '') return '—';
        return lv_num($v, $decimals) . ($unit ? ' ' . $unit : '');
    }
}

if (! function_exists('lv_kg')) {
    /** وزن بالكيلو — ولو ألف أو أكتر بيوضح بالطن كمان: «10,300 كجم (10.3 طن)» */
    function lv_kg(float|int|string|null $kg, bool $withTon = true): string
    {
        if ($kg === null || $kg === '') return '—';
        $kg = (float) $kg;
        $s  = lv_num($kg, 3) . ' كجم';
        if ($withTon && abs($kg) >= 1000) $s .= ' (' . lv_num($kg / 1000, 3) . ' طن)';
        return $s;
    }
}

if (! function_exists('lv_setting')) {
    /**
     * إعداد قابل للتعديل من شاشة الإعدادات — بيرجع للكونفيج لو ما اتعدّلش.
     * lv_setting('min_inspection_sample_pct', 10)
     */
    function lv_setting(string $key, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $cache = \Illuminate\Support\Facades\Schema::hasTable('app_settings')
                    ? \Illuminate\Support\Facades\DB::table('app_settings')->pluck('value', 'key')->all()
                    : [];
            } catch (\Throwable) { $cache = []; }
        }
        if (array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '') return $cache[$key];
        return config('lvplanning.' . $key, $default);
    }
}

if (! function_exists('lv_packaging_kg')) {
    /** وزن الكيس والماسورة لعدد أتواب: (كيس جم + ماسورة جم) × الأتواب ÷ 1000 */
    function lv_packaging_kg(?float $bagG, ?float $tubeG, int $rolls): float
    {
        return round(((float) $bagG + (float) $tubeG) * max(0, $rolls) / 1000, 3);
    }
}

if (! function_exists('lv_unit')) {
    /** تسمية الوحدة الموحدة: «كجم» أو «م» */
    function lv_unit(?string $unit): string
    {
        return in_array(trim((string) $unit), ['متر', 'م'], true) ? 'م' : 'كجم';
    }
}

if (! function_exists('lv_is_meter')) {
    function lv_is_meter(?string $unit): bool
    {
        return in_array(trim((string) $unit), ['متر', 'م'], true);
    }
}
