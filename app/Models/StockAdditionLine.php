<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdditionLine extends Model
{
    protected $guarded = [];
    public function stockAddition() { return $this->belongsTo(StockAddition::class); }
    public function fabricType()    { return $this->belongsTo(FabricType::class); }
    public function color()         { return $this->belongsTo(Color::class); }
    public function accessory()     { return $this->belongsTo(Accessory::class); }
}
