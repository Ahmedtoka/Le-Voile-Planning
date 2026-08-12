<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricRoll extends Model
{
    protected $guarded = [];
    protected $casts = ['is_inspected' => 'boolean'];

    public const STATUSES = [
        'in_stock'  => 'في المخزن',
        'allocated' => 'مخصص',
        'issued'    => 'منصرف',
        'returned'  => 'مرتجع',
        'scrapped'  => 'هالك',
    ];

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function workOrder()   { return $this->belongsTo(WorkOrder::class); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
