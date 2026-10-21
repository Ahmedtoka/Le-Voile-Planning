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
