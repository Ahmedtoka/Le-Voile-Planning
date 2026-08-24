<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdditionLine extends Model
{
    protected $guarded = [];
    public function stockAddition() { return $this->belongsTo(StockAddition::class); }
    public function fabricType()    { return $this->belongsTo(FabricType::class); }
    public function color()         { return $this->belongsTo(Color::class); }
    public function poColor()       { return $this->belongsTo(Color::class, 'po_color_id'); }
    public function poLine()        { return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id'); }
    public function accessory()     { return $this->belongsTo(Accessory::class); }
}
