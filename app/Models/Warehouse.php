<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'last_stock_count_at' => 'date'];

    public const TYPES = [
        'fabric'      => 'قماش',
        'accessories' => 'إكسسوارات',
        'finished'    => 'منتج تام',
        'other'       => 'أخرى',
    ];

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** الرصيد موثوق قد إيه؟ لو الجرد بقى له كتير، حذّر */
    public function getStockAgeDaysAttribute(): ?int
    {
        // absolute = true — Carbon 3 بيرجّع الفرق بإشارة
        return $this->last_stock_count_at?->diffInDays(now(), true);
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
