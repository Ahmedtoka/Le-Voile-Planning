<?php

namespace App\Imports;

use App\Models\Color;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * استيراد الألوان من إكسيل.
 *
 * الأعمدة المتوقعة (بالعربي أو الإنجليزي):
 *   code / كود   |  name / اللون  |  family / العائلة  |  hex
 *   is_basic / اساسي   |  legacy_code / الكود القديم
 *   merged_into / مدموج_في   ← لو اللون ده مدموج، حط كود الهدف
 *
 * ملاحظة: مفيش حذف. أي كود موجود بيتحدّث، وأي كود جديد بيتضاف.
 */
class ColorsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $merged  = 0;
    public array $errors = [];

    public function collection($rows): void
    {
        $pendingMerges = [];

        foreach ($rows as $i => $r) {
            $code = trim((string) $this->pick($r, ['code', 'كود', 'كود_اللون', 'كود اللون']));
            $name = trim((string) $this->pick($r, ['name', 'اللون', 'الاسم', 'اسم_اللون']));

            if ($code === '') continue;
            if ($name === '') $name = $code;

            $payload = [
                'name'        => $name,
                'family'      => $this->pick($r, ['family', 'العائلة', 'المجموعة']) ?: null,
                'hex'         => $this->pick($r, ['hex', 'لون_العرض']) ?: null,
                'is_basic'    => $this->bool($this->pick($r, ['is_basic', 'اساسي', 'أساسي'])),
                'legacy_code' => $this->pick($r, ['legacy_code', 'الكود_القديم', 'كود قديم']) ?: null,
            ];

            $existing = Color::where('code', $code)->first();
            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                Color::create($payload + ['code' => $code, 'status' => 'active']);
                $this->created++;
            }

            $mergeTarget = trim((string) $this->pick($r, ['merged_into', 'مدموج_في', 'مدموج في']));
            if ($mergeTarget !== '') {
                $pendingMerges[$code] = $mergeTarget;
            }
        }

        // الدمج بعد ما كل الأكواد تبقى موجودة
        foreach ($pendingMerges as $fromCode => $toCode) {
            $from = Color::where('code', $fromCode)->first();
            $to   = Color::where('code', $toCode)->first();

            if (!$from || !$to || $from->id === $to->id) {
                $this->errors[] = "مش قادر أدمج {$fromCode} في {$toCode} — كود مش موجود.";
                continue;
            }

            try {
                Color::merge($from, $to, auth()->id(), 'استيراد من إكسيل');
                $this->merged++;
            } catch (\Throwable $e) {
                $this->errors[] = "{$fromCode} → {$toCode}: " . $e->getMessage();
            }
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

    private function bool($v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'نعم', 'اساسي', 'أساسي'], true);
    }
}
