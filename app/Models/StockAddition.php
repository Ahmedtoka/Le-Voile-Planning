<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

class StockAddition extends Model
{
    use HasApproval, HasDocumentStatus;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date'];

    public const DOC_TYPE = 'stock_addition';

    public function supplier()     { return $this->belongsTo(Supplier::class); }
    public function warehouse()    { return $this->belongsTo(Warehouse::class); }
    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function consignment()  { return $this->belongsTo(Consignment::class); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()        { return $this->hasMany(StockAdditionLine::class); }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $this->forceFill(['total_qty' => (float) $this->lines->sum('qty')])->saveQuietly();
    }
}
