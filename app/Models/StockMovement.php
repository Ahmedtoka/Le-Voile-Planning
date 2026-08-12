<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = [];
    protected $casts = ['moved_at' => 'date'];

    public function warehouse()    { return $this->belongsTo(Warehouse::class); }
    public function fabricType()   { return $this->belongsTo(FabricType::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function accessory()    { return $this->belongsTo(Accessory::class); }
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }
    public function consignment()  { return $this->belongsTo(Consignment::class); }

    public function scopeIn($q)  { return $q->where('direction', 'in'); }
    public function scopeOut($q) { return $q->where('direction', 'out'); }
}
