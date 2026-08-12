<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    protected $table = 'accessories';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'is_shared' => 'boolean'];

    public const TYPES = [
        'bag'     => 'كيس',
        'sticker' => 'استيكر',
        'label'   => 'تيكت',
        'button'  => 'زرار',
        'zipper'  => 'سوستة',
        'thread'  => 'خيط',
        'carton'  => 'كرتونة',
        'other'   => 'أخرى',
    ];

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }

    public function getIsBelowReorderAttribute(): bool
    {
        return $this->reorder_point > 0 && $this->stock_qty <= $this->reorder_point;
    }
}
