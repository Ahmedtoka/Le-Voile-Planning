<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تسكينة على طلب شراء = أمر شراء لمورد — مورد واحد بكمية وسعر وتاريخ توريد.
 * طلب اتشرى من موردين = أمرين (PO-…/1 و PO-…/2): كل واحد بيتطبع لمورده،
 * والحسابات بتسجّل علمها بكل واحد لوحده.
 * الطلب الواحد ممكن يتسكّن على أكتر من مورد وأكتر من مرة (الميتينج 37):
 * طلبت 100 طن ⇐ 50 مع مورد دلوقتي، والباقي يتسعّر بعدين مع نفس المورد أو غيره.
 */
class PoSourcing extends Model
{
    protected $guarded = [];
    protected $casts = ['delivery_date' => 'date', 'sourced_at' => 'datetime', 'finance_at' => 'datetime'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function sourcer()       { return $this->belongsTo(User::class, 'sourced_by'); }
    public function financer()      { return $this->belongsTo(User::class, 'finance_by'); }
    public function lines()         { return $this->hasMany(PoSourcingLine::class); }
    public function stockAdditions(){ return $this->hasMany(StockAddition::class); }

    /** رقم أمر الشراء زي ما بيظهر على الشاشات والورق: PO-2026-00016 / 2 */
    public function getLabelAttribute(): string
    {
        return ($this->purchaseOrder?->po_no ?? '') . ' / ' . (int) $this->seq;
    }

    /** الباقي للاستلام على التسكينة دي (بوحدة كل بند) */
    public function getOutstandingQtyAttribute(): float
    {
        return (float) $this->lines->sum(fn ($l) => max(0, (float) $l->qty - (float) $l->received_qty));
    }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $subtotal = (float) $this->lines->sum('line_total');
        $disc  = round($subtotal * ((float) $this->discount_pct / 100), 2);
        $tax   = round(($subtotal - $disc) * ((float) $this->tax_pct / 100), 2);
        $this->forceFill(['subtotal' => $subtotal, 'total' => $subtotal - $disc + $tax])->saveQuietly();
    }
}
