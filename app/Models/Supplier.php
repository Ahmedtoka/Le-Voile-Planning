<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
    public function consignments()   { return $this->hasMany(Consignment::class); }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
