<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/** استلام منتج تام — جزئي ومتكرر لحد ما أمر الشغل يتقفل. */
class ProductionReceipt extends Model
{
    use HasDocumentIdentity, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date'];

    public const DOC_TYPE = 'production_receipt';

    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function factory()   { return $this->belongsTo(Factory::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(ProductionReceiptLine::class); }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $this->forceFill(['total_pieces' => (int) $this->lines->sum('qty')])->saveQuietly();
    }
}
