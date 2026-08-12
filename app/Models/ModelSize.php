<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelSize extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }
}
