<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CutDeclarationLine extends Model
{
    protected $guarded = [];
    public function cutDeclaration() { return $this->belongsTo(CutDeclaration::class); }
    public function productModel()   { return $this->belongsTo(ProductModel::class); }
    public function size()           { return $this->belongsTo(Size::class); }
}
