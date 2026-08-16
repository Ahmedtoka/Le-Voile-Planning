<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * توليد أرقام المستندات.
 *
 * كل مستند بياخد رقم من السيستم، والمسلسل الورقي (اللي مطبوع على
 * الدفتر) بيتخزّن جنبه في paper_serial عشان نقدر نرجع للورقة الأصلية.
 */
class DocNumber
{
    /** البادئات المعتمدة لكل نوع مستند */
    public const PREFIXES = [
        'purchase_order'     => 'PO',
        'goods_receipt'      => 'GR',
        'stock_addition'     => 'SA',
        'consignment'        => 'CN',
        'fabric_inspection'  => 'FI',
        'lab_report'         => 'LB',
        'marker_request'     => 'MR',
        'marker'             => 'MK',
        'work_order'         => 'WO',
        'cut_declaration'    => 'CD',
        'production_receipt' => 'PR',
        'material_issue'     => 'MI',
    ];

    /**
     * رقم تسلسلي في نطاق السنة: PO-2026-00001
     */
    public static function next(string $docType, string $table, string $column = 'doc_no'): string
    {
        $prefix = self::PREFIXES[$docType] ?? strtoupper(substr($docType, 0, 2));
        $year   = now()->year;
        $like   = "{$prefix}-{$year}-%";

        return DB::transaction(function () use ($table, $column, $prefix, $year, $like) {
            $last = DB::table($table)
                ->where($column, 'like', $like)
                ->orderByDesc($column)
                ->lockForUpdate()
                ->value($column);

            $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

            return sprintf('%s-%d-%05d', $prefix, $year, $seq);
        });
    }

    /**
     * رقم الرسالة بنمط الشركة: SL30-090826-196-00
     *   SL30   = بادئة المورد/الخامة
     *   090826 = تاريخ الوصول (يوم/شهر/سنة)
     *   196    = رقم أمر المشتريات
     *   00     = مسلسل داخل نفس اليوم
     */
    public static function consignmentNo(string $supplierPrefix, \DateTimeInterface $date, ?string $poRef, int $seq = 0): string
    {
        $d = $date->format('dmy');
        $po = $poRef ? preg_replace('/\D+/', '', $poRef) : '00';
        $po = $po !== '' ? $po : '00';

        return sprintf('%s-%s-%s-%02d', strtoupper($supplierPrefix), $d, $po, $seq);
    }
}
