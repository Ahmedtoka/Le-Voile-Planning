<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasApproval, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = [
        'po_date'       => 'date',
        'delivery_date' => 'date',
        'requested_at'  => 'datetime',
        'sourced_at'    => 'datetime',
        'finance_at'    => 'datetime',
    ];

    public const DOC_TYPE = 'purchase_order';

    public const STATUSES = [
        'draft'              => 'مسودة',
        'pending'            => 'تحت الاعتماد',
        'approved'           => 'معتمد',
        'rejected'           => 'مرفوض',
        'partially_received' => 'مستلم جزئيًا',
        'received'           => 'مستلم بالكامل',
        'closed'             => 'مقفول',
        'cancelled'          => 'ملغي',
    ];

    /**
     * مراحل الطلب — نفس الورقة، بس بتمر على تلات أيادي.
     * كل مرحلة بتقفل اللي قبلها عن التعديل.
     */
    public const STAGES = [
        'planning'   => 'عند التخطيط',
        'purchasing' => 'عند المشتريات',
        'finance'    => 'عند الحسابات',
        'approval'   => 'تحت الاعتماد',      // موجودة للتوافق — الدورة الحالية بتتخطاها
        'approved'   => 'جاهز — عند المورد',
        'receiving'  => 'جاري الاستلام',
        'closed'     => 'مقفول',
        'cancelled'  => 'ملغي',
    ];

    /** طرق الدفع المتفق عليها — دروب داون مش نص حر */
    public const PAYMENT_METHODS = [
        'نقدي'       => 'نقدي',
        'آجل 30 يوم' => 'آجل 30 يوم',
        'آجل 45 يوم' => 'آجل 45 يوم',
        'آجل 60 يوم' => 'آجل 60 يوم',
        'آجل 90 يوم' => 'آجل 90 يوم',
        'دفعات'      => 'دفعات حسب الاتفاق',
        'شيك'        => 'شيك',
        'تحويل بنكي' => 'تحويل بنكي',
    ];

    public const STAGE_COLORS = [
        'planning'   => 'secondary',
        'purchasing' => 'info',
        'finance'    => 'warning',
        'approval'   => 'warning',
        'approved'   => 'success',
        'receiving'  => 'primary',
        'closed'     => 'dark',
        'cancelled'  => 'danger',
    ];

    public function getStageNameAttribute(): string
    {
        return self::STAGES[$this->stage] ?? (string) $this->stage;
    }

    public function getStageColorAttribute(): string
    {
        return self::STAGE_COLORS[$this->stage] ?? 'secondary';
    }

    /** الأصناف والكميات — التخطيط بس اللي يعدّلها، وقبل ما تنزل للمشتريات */
    public function planningEditable(): bool
    {
        return $this->stage === 'planning';
    }

    /** المورد والأسعار والتوريد — المشتريات */
    public function purchasingEditable(): bool
    {
        return $this->stage === 'purchasing';
    }

    /** جاهز ينزل للمشتريات؟ */
    public function readyForPurchasing(): bool
    {
        return $this->stage === 'planning' && $this->lines()->count() > 0;
    }

    /** جاهز ينزل للحسابات؟ لازم مورد وتاريخ توريد */
    public function readyForFinance(): bool
    {
        return $this->stage === 'purchasing'
            && $this->supplier_id
            && $this->delivery_date;
    }

    /** المستحق المتوقع للمورد من الطلب ده */
    public function getExpectedPayableAttribute(): float
    {
        return (float) $this->total;
    }

    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function employee()  { return $this->belongsTo(User::class, 'employee_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function sourcer()   { return $this->belongsTo(User::class, 'sourced_by'); }
    public function financer()  { return $this->belongsTo(User::class, 'finance_by'); }
    public function stockAdditions() { return $this->hasMany(StockAddition::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no'); }
    public function receipts()  { return $this->hasMany(GoodsReceipt::class); }
    public function consignments() { return $this->hasMany(Consignment::class); }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $subtotal = 0; $qty = 0;
        foreach ($this->lines as $l) {
            $subtotal += (float) $l->line_total;
            $qty      += (float) $l->qty;
        }
        $discount = round($subtotal * ((float) $this->discount_pct / 100), 2);
        $afterDisc = $subtotal - $discount;
        $tax = round($afterDisc * ((float) $this->tax_pct / 100), 2);

        $this->forceFill([
            'subtotal'       => $subtotal,
            'discount_value' => $discount,
            'tax_value'      => $tax,
            'total'          => $afterDisc + $tax,
            'total_qty'      => $qty,
        ])->saveQuietly();
    }
}
