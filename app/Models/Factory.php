<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factory extends Model
{
    protected $table = 'factories';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function workOrders() { return $this->hasMany(WorkOrder::class); }
    public function markers()    { return $this->hasMany(Marker::class); }

    public function getLabelAttribute(): string
    {
        return $this->name;
    }
}
