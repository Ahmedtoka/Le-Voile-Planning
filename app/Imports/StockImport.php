<?php

namespace App\Imports;

use App\Models\Color;
use App\Models\ProductModel;
use App\Models\StockSnapshot;
use App\Models\Warehouse;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public array $errors = [];

    public function __construct(
        private string $pulledAt,
        private string $reliability = 'book',
        private ?int $userId = null,
    ) {}

    public function collection($rows): void
    {
        foreach ($rows as $r) {
            $code = trim((string) $this->pick($r, ['model_code', 'كود_الموديل', 'الكود', 'code']));
            if ($code === '') continue;

            $model = ProductModel::where('code', $code)->first();
            if (!$model) { $this->errors[] = "موديل غير معروف: {$code}"; continue; }

            $colorCode = trim((string) $this->pick($r, ['color_code', 'كود_اللون', 'اللون']));
            $color = $colorCode !== '' ? Color::where('code', $colorCode)->first() : null;
            // لو اللون مدموج، نسجّل على الكود الفعّال
            $color = $color?->effective();

            $whCode = trim((string) $this->pick($r, ['warehouse_code', 'كود_المخزن', 'المخزن']));
            $wh = $whCode !== '' ? Warehouse::where('code', $whCode)->first() : null;

            StockSnapshot::create([
                'pulled_at'        => $this->pulledAt,
                'warehouse_id'     => $wh?->id,
                'product_model_id' => $model->id,
                'color_id'         => $color?->id,
                'qty_pcs'          => (float) $this->pick($r, ['qty', 'الكمية', 'الرصيد']),
                'reliability'      => $this->reliability,
                'source'           => 'excel',
                'imported_by'      => $this->userId,
            ]);

            $this->imported++;
        }
    }

    /**
     * قراءة قيمة العمود بأي صيغة من صيغ العنوان.
     * الأعمدة العربية بتيجي من الإكسيل بمسافات، والإنجليزية بـ underscore —
     * فبنجرّب الشكلين + النسخة الصغيرة.
     */
    private function pick($row, array $keys)
    {
        foreach ($keys as $k) {
            foreach (array_unique([$k, str_replace(' ', '_', $k), str_replace('_', ' ', $k), mb_strtolower($k)]) as $variant) {
                if (isset($row[$variant]) && $row[$variant] !== null && $row[$variant] !== '') {
                    return $row[$variant];
                }
            }
        }
        return null;
    }
}
