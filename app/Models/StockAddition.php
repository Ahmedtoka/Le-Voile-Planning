<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * إذن الإضافة — أول مستند لما القماش يوصل.
 *
 * ده اللي بيولّد الحوض (الرسالة) وبيدخّله المخزن **محجوز تحت الفحص**.
 * القماش مش متاح للتشغيل من هنا — الإفراج بيحصل بإذن الاستلام الخام
 * بعد ما الفحص والمعمل يخلّصوا.
 */
class StockAddition extends Model
{
    use HasApproval, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'remainder_eta' => 'date'];

    public const DOC_TYPE = 'stock_addition';

    /** عادي = يدخل دورة الفحص · حاويات = الإذن ده هو الاستلام النهائي */
    public const RECEIPT_TYPES = [
        'normal'    => 'عادي — يدخل الفحص',
        'container' => 'حاويات — بدون فحص',
    ];

    public function getReceiptTypeNameAttribute(): string
    {
        return self::RECEIPT_TYPES[$this->receipt_type] ?? 'عادي';
    }

    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function consignment()   { return $this->belongsTo(Consignment::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()         { return $this->hasMany(StockAdditionLine::class); }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $this->forceFill([
            // الإجمالي بالكيلو — السطور ممكن تكون بوحدة الطلب (طن)
            'total_qty'   => (float) $this->lines->sum(fn ($l) =>
                                 $l->unit === 'طن' ? (float) $l->qty * 1000 : (float) $l->qty),
            'total_rolls' => (int) $this->lines->sum('rolls_count'),
        ])->saveQuietly();
    }
}
