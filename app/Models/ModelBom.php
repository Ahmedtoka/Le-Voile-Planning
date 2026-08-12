<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelBom extends Model
{
    protected $guarded = [];
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }
    public function accessory()    { return $this->belongsTo(Accessory::class); }
}
