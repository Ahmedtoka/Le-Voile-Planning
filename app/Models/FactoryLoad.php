<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactoryLoad extends Model
{
    protected $guarded = [];
    protected $casts = ['as_of' => 'date'];
    public function factory() { return $this->belongsTo(Factory::class); }
}
