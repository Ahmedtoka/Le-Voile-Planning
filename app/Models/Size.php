<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('id'); }

    public function getLabelAttribute(): string { return $this->name; }
}
