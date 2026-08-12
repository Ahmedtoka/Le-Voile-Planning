<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderLine extends Model
{
    protected $guarded = [];

    public function workOrder()    { return $this->belongsTo(WorkOrder::class); }
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }

    public function syncRemaining(): void
    {
        $this->forceFill([
            'remaining_qty' => max(0, (int) $this->cut_qty - (int) $this->received_qty),
        ])->saveQuietly();
    }
}
