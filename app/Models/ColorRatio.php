<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نسب الألوان.
 *
 * المبيعات مش متاحة باللون من المصدر، فبنستنتج النسب من صرف المخزن
 * الرئيسي — "مش أدق حاجة، بس دي اللي في إيدنا".
 * النسب قابلة للتعديل اليدوي، وكل تعديل بيتسجّل بصاحبه.
 */
class ColorRatio extends Model
{
    protected $guarded = [];

    public const SOURCES = [
        'issues' => 'صرف المخزن',
        'manual' => 'يدوي',
        'sales'  => 'مبيعات',
    ];

    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function updatedBy()    { return $this->belongsTo(User::class, 'updated_by'); }

    public function getSourceNameAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
