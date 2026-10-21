<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    protected $guarded = [];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function color()         { return $this->belongsTo(Color::class); }
    public function fabricType()    { return $this->belongsTo(FabricType::class); }

    /** الحد الأقصى المسموح استلامه = الكمية + نسبة الزيادة المسموح بها */
    public function getMaxAllowedQtyAttribute(): float
    {
        return (float) $this->qty * (1 + ((float) $this->tolerance_pct / 100));
    }

    /**
     * الحد الأدنى للإقفال = الكمية المطلوبة نفسها.
     * نسبة الزيادة اسمها زيادة: بتسمح بالزيادة بس — لو وصل أقل من المطلوب يفضل باقي مفتوح.
     */
    public function getMinAllowedQtyAttribute(): float
    {
        return (float) $this->qty;
    }

    public function getOutstandingQtyAttribute(): float
    {
        return max(0, (float) $this->qty - (float) $this->received_qty);
    }

    /** هل الاستلام خرج عن حدود نسبة الزيادة؟ */
    public function getToleranceBreachAttribute(): bool
    {
        return (float) $this->received_qty > $this->max_allowed_qty;
    }
}
