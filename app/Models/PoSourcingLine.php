<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** بند في تسكينة: كام من سطر الطلب اتسكّن مع المورد ده وبكام */
class PoSourcingLine extends Model
{
    protected $guarded = [];

    public function sourcing() { return $this->belongsTo(PoSourcing::class, 'po_sourcing_id'); }
    public function poLine()   { return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id'); }

    public function getOutstandingQtyAttribute(): float
    {
        return max(0, (float) $this->qty - (float) $this->received_qty);
    }
}
