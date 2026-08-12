<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyStock extends Model
{
    protected $guarded = [];
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function updatedBy()    { return $this->belongsTo(User::class, 'updated_by'); }
}
