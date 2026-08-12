<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    protected $guarded = [];
    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function fabricType()   { return $this->belongsTo(FabricType::class); }
    public function color()        { return $this->belongsTo(Color::class); }
}
