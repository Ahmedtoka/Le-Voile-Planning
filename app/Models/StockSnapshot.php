<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSnapshot extends Model
{
    protected $guarded = [];
    protected $casts = ['pulled_at' => 'date'];

    public const RELIABILITY = [
        'counted'   => 'مجرود',
        'book'      => 'دفتري',
        'estimated' => 'تقديري',
    ];

    public function warehouse()    { return $this->belongsTo(Warehouse::class); }
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function size()         { return $this->belongsTo(Size::class); }

    public function getReliabilityNameAttribute(): string
    {
        return self::RELIABILITY[$this->reliability] ?? $this->reliability;
    }
}
