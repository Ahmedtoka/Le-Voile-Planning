<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabGsmReading extends Model
{
    protected $guarded = [];
    public function labReport() { return $this->belongsTo(LabReport::class); }
}
