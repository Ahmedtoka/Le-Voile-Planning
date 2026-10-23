<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function fabricType() { return $this->belongsTo(FabricType::class); }
    public function sizes()      { return $this->belongsToMany(Size::class, 'model_sizes')->withPivot('size_factor'); }
    public function boms()       { return $this->hasMany(ModelBom::class); }
    /** خامات الموديل (الميتينج 37) — طلب الشراء بيطلّعها لوحده لما تختار الموديل */
    public function fabrics()    { return $this->hasMany(ModelFabric::class); }
    public function forecasts()  { return $this->hasMany(Forecast::class); }
    public function colorRatios(){ return $this->hasMany(ColorRatio::class); }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
