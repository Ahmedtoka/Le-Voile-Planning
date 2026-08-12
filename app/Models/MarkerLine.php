<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkerLine extends Model
{
    protected $guarded = [];
    public function marker()       { return $this->belongsTo(Marker::class); }
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }
}
