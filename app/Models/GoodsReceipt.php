<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * إذن استلام خام — آخر خطوة في دورة وصول القماش.
 *
 * بييجي **بعد** إذن الإضافة والفحص والمعمل. اعتماده هو اللي بيفرج عن
 * الحوض من الحجز ويخلّيه متاح لأوامر الشغل.
 */
class GoodsReceipt extends Model
{
    use HasApproval, HasDocumentStatus;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date'];

    public const DOC_TYPE = 'goods_receipt';

    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function consignment()   { return $this->belongsTo(Consignment::class); }
    public function stockAddition() { return $this->belongsTo(StockAddition::class); }
    public function inspection()    { return $this->belongsTo(FabricInspection::class, 'fabric_inspection_id'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()         { return $this->hasMany(GoodsReceiptLine::class); }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $this->forceFill([
            'total_qty'   => (float) $this->lines->sum('qty'),
            'total_rolls' => (int) $this->lines->sum('rolls_count'),
        ])->saveQuietly();
    }
}
