<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** خامات الموديل — الموديل بيتعمل من أنهي خامة وبكام للقطعة (الميتينج 37: طلب الشراء يطلّعها لوحده) */
class ModelFabric extends Model
{
    protected $guarded = [];

    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function fabricType()   { return $this->belongsTo(FabricType::class); }
    public function color()        { return $this->belongsTo(Color::class); }
}
