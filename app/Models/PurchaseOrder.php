<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasApproval, HasDocumentStatus;

    protected $guarded = [];
    protected $casts = ['po_date' => 'date', 'delivery_date' => 'date'];

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

    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function employee()  { return $this->belongsTo(User::class, 'employee_id'); }
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
