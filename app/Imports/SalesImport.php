<?php

namespace App\Imports;

use App\Models\ProductModel;
use App\Models\SalesSnapshot;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * استيراد المبيعات من إكسبورت QuickBooks.
 *
 * حارسين مهمين هنا:
 *   1) الدستة/القطعة — لو الوحدة "دستة" بنضرب في قطع الدستة، ولو الرقم
 *      شاذ جدًا عن متوسط الموديل بنعلّم unit_warning عشان المخطط يراجع.
 *      (حصل فعلًا: فاتورة 200 دستة اتسجلت 200 قطعة.)
 *   2) القفل — الشهر ما يتقفلش غير يوم 5 من الشهر التالي، لأن الأرقام
 *      بتتعدّل طول الشهر.
 */
class SalesImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public int $warnings = 0;
    public array $errors = [];

    public function __construct(
        private string $periodFrom,
        private string $periodTo,
        private ?int $userId = null,
    ) {}

    public function collection($rows): void
    {
        $from = Carbon::parse($this->periodFrom);
        $to   = Carbon::parse($this->periodTo);

        $lockable = SalesSnapshot::monthIsLockable((int) $from->year, (int) $from->month);

        foreach ($rows as $r) {
            $code = trim((string) $this->pick($r, ['model_code', 'كود_الموديل', 'الكود', 'code']));
            if ($code === '') continue;

            $model = ProductModel::where('code', $code)->first();
            if (!$model) {
                $this->errors[] = "موديل غير معروف: {$code}";
                continue;
            }

            $rawQty  = (float) $this->pick($r, ['qty', 'الكمية', 'كمية']);
            $rawUnit = trim((string) ($this->pick($r, ['unit', 'الوحدة']) ?: 'قطعة'));

            // تحويل الدستة لقطعة
            $qtyPcs = str_contains($rawUnit, 'دست') || strtolower($rawUnit) === 'dozen'
                ? $rawQty * (int) $model->pcs_per_dozen
                : $rawQty;

            // كشف الأرقام الشاذة — مؤشر على خطأ دستة/قطعة
            $avg = (float) SalesSnapshot::where('product_model_id', $model->id)->avg('qty_pcs');
            $warn = $avg > 0 && ($qtyPcs > $avg * 8 || ($qtyPcs > 0 && $qtyPcs < $avg / 8));
            if ($warn) $this->warnings++;

            SalesSnapshot::create([
                'pulled_at'        => now()->toDateString(),
                'period_from'      => $from->toDateString(),
                'period_to'        => $to->toDateString(),
                'product_model_id' => $model->id,
                'color_id'         => null,   // المصدر مش بيدي لون
                'qty_pcs'          => $qtyPcs,
                'raw_qty'          => $rawQty,
                'raw_unit'         => $rawUnit,
                'source'           => 'quickbooks_excel',
                'revision'         => 1 + (int) SalesSnapshot::where('product_model_id', $model->id)
                                            ->whereDate('period_from', $from)->max('revision'),
                'is_locked'        => $lockable,
                'unit_warning'     => $warn,
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
