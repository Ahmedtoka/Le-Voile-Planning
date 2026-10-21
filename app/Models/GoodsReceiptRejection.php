<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * رفض جزئي أو تعليق لون عند الاستلام.
 *
 * الاستلام مش «قبول كله أو رفض كله». الورق الحقيقي بيسجّل:
 *   • رفض أتواب بعينها بوزنها وسببها — قرار الجودة
 *   • تعليق لون لحين رد التخطيط والمشتريات (لون غير مطابق)
 *
 * المرفوض ما بيدخلش المخزون وما بيتحسبش على أمر الشراء، وبيفضل
 * مطالبة على المورد لحد ما يتقفل.
 */
class GoodsReceiptRejection extends Model
{
    protected $guarded = [];
    protected $casts = ['resolved_at' => 'datetime'];

    public const KINDS = [
        'rejected' => 'مرفوض',
        'on_hold'  => 'معلّق لحين الرد',
    ];

    public const PARTIES = [
        'quality'    => 'مصلحة الجودة',
        'planning'   => 'إدارة التخطيط',
        'purchasing' => 'إدارة المشتريات',
    ];

    public const RESOLUTIONS = [
        'open'                   => 'مفتوح',
        'accepted'               => 'اتقبل بعد المراجعة',
        'accepted_with_discount' => 'اتقبل بخصم من المورد',
        'rejected'               => 'اترفض نهائي',
        'returned'               => 'رجع للمورد',
    ];

    /**
     * تصنيف سبب الرفض (الميتينج): التخطيط والمشتريات يقدروا يتفاوضوا في تلات عوامل
     * (عرض/بنشر/انكماش مختلف ⇒ خصم من المورد)، واختلاف اللون/الفواصل مش قابل للتفاوض.
     */
    public const CATEGORIES = [
        'width'     => 'عرض مختلف عن المطلوب',
        'gsm'       => 'بنشر مختلف عن المواصفة',
        'shrinkage' => 'انكماش مختلف عن المواصفة',
        'color'     => 'اختلاف لون / فواصل',
        'other'     => 'عيب آخر',
    ];
    public const NEGOTIABLE = ['width', 'gsm', 'shrinkage'];

    public function getCategoryNameAttribute(): string { return self::CATEGORIES[$this->reason_category] ?? (string) $this->reason_category; }
    public function getIsNegotiableAttribute(): bool   { return in_array($this->reason_category, self::NEGOTIABLE, true); }

    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function inspection()   { return $this->belongsTo(FabricInspection::class, 'fabric_inspection_id'); }
    public function consignment()  { return $this->belongsTo(Consignment::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function resolver()     { return $this->belongsTo(User::class, 'resolved_by'); }

    public function scopeOpen($q)   { return $q->where('resolution', 'open'); }
    public function scopeOnHold($q) { return $q->where('kind', 'on_hold')->where('resolution', 'open'); }

    public function getKindNameAttribute(): string       { return self::KINDS[$this->kind] ?? $this->kind; }
    public function getPartyNameAttribute(): string      { return self::PARTIES[$this->party] ?? $this->party; }
    public function getResolutionNameAttribute(): string { return self::RESOLUTIONS[$this->resolution] ?? $this->resolution; }

    public function getLabelAttribute(): string
    {
        $what = $this->lot_label ?: ('كود ' . ($this->color_code ?: $this->color?->code ?: '—'));
        return $what . ' — ' . $this->rolls_count . ' توب بوزن '
             . rtrim(rtrim(number_format((float) $this->qty, 3), '0'), '.') . ' ' . $this->unit;
    }
}
