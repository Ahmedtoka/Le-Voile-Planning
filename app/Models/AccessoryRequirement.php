<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessoryRequirement extends Model
{
    protected $guarded = [];
    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function accessory() { return $this->belongsTo(Accessory::class); }

    public function getHasShortageAttribute(): bool
    {
        return (float) $this->shortage_qty > 0;
    }
}
