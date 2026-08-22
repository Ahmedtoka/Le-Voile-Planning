<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\ModelBom;
use App\Models\ProductModel;

/**
 * الماستر داتا الحقيقية من شيت «Automation Job Order»:
 * 49 موديل مكملات بأكوادهم (110A…143A) وF.P codes، والـBOM بتاع كل موديل
 * (تكت/أكياس/استيكر/كباسين… باستهلاك القطعة)، و74 صنف إكسسوار بأكوادهم
 * وأرصدتهم، وشجرة الألوان (ابيض_001، بيج_003، بيج_004…).
 *
 * متوسطات الاستهلاك مبدئية بأمثلة الميتينج (بنيه 60 جم، معصم 30 جم…) —
 * التخطيط يعدّلها من شاشة الموديلات وهي اللي بتتوزّع بيها الفرشة المشتركة.
 */
class JobOrderMaster
{
    public static function seed(?callable $say = null): array
    {
        $say = $say ?: fn ($m) => null;

        $path = database_path('data/job_order_master.json');
        if (!is_file($path)) {
            $say('ملف الماستر داتا مش موجود: ' . $path);
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!$data) return [];

        // ── الإكسسوارات ──
        $accIds = [];
        foreach (($data['accessories'] ?? []) as $a) {
            $row = Accessory::updateOrCreate(['code' => $a['code']], [
                'name'      => $a['name'],
                'type'      => self::accessoryType($a['name']),
                'unit'      => $a['unit'] ?: 'قطعة',
                'stock_qty' => (float) ($a['stock_qty'] ?? 0),
                'is_active' => true,
            ]);
            $accIds[$a['name']] = $row->id;
        }
        $say('إكسسوارات حقيقية: ' . count($accIds));

        // ── الألوان (بنكمّل الشجرة — مش بنلمس الموجود) ──
        $newColors = 0;
        foreach (($data['colors'] ?? []) as $c) {
            $color = Color::firstOrCreate(['code' => $c['code']], [
                'name'   => $c['name'] . '_' . $c['code'],
                'family' => $c['name'],
                'status' => 'active',
            ]);
            if ($color->wasRecentlyCreated) $newColors++;
        }
        $say('ألوان جديدة من الشجرة: ' . $newColors);

        // ── الموديلات + الـBOM ──
        $bomRows = 0;
        foreach (($data['models'] ?? []) as $m) {
            $model = ProductModel::updateOrCreate(['code' => $m['code']], [
                'name'               => $m['name'],
                'category'           => 'مكملات',
                'fp_code'            => $m['fp_code'] ?? null,
                'photo_url'          => $m['photo_url'] ?? null,
                'std_consumption_kg' => $m['avg_kg'] ?? null,
                'is_active'          => true,
            ]);

            foreach (($m['bom'] ?? []) as $b) {
                $aid = $accIds[$b['accessory']] ?? null;
                if (!$aid) continue;
                ModelBom::updateOrCreate(
                    ['product_model_id' => $model->id, 'accessory_id' => $aid, 'size_id' => null],
                    ['qty_per_piece' => (float) $b['qty_per_piece']]
                );
                $bomRows++;
            }
        }
        $say('موديلات حقيقية: ' . count($data['models'] ?? []) . ' · سطور BOM: ' . $bomRows);

        return [
            'إكسسوارات حقيقية' => count($accIds),
            'موديلات حقيقية'   => count($data['models'] ?? []),
            'سطور BOM'         => $bomRows,
            'ألوان جديدة'      => $newColors,
        ];
    }

    private static function accessoryType(string $name): string
    {
        return match (true) {
            str_contains($name, 'استيكر') || str_contains($name, 'ترانسفير') => 'sticker',
            str_contains($name, 'كيس') || str_contains($name, 'اكياس')       => 'bag',
            str_contains($name, 'تكت') || str_contains($name, 'كارت')
                || str_contains($name, 'هانج') || str_contains($name, 'باركود') => 'label',
            str_contains($name, 'زراير') || str_contains($name, 'كبسول')
                || str_contains($name, 'كباسين')                              => 'button',
            str_contains($name, 'كرتون')                                      => 'carton',
            default                                                           => 'other',
        };
    }
}
