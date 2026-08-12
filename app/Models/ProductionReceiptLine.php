<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionReceiptLine extends Model
{
    protected $guarded = [];
    public function productionReceipt() { return $this->belongsTo(ProductionReceipt::class); }
    public function productModel()      { return $this->belongsTo(ProductModel::class); }
    public function size()              { return $this->belongsTo(Size::class); }
    public function color()             { return $this->belongsTo(Color::class); }
}
