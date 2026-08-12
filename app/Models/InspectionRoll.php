<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionRoll extends Model
{
    protected $guarded = [];

    public function inspection() { return $this->belongsTo(FabricInspection::class, 'fabric_inspection_id'); }
    public function fabricRoll() { return $this->belongsTo(FabricRoll::class); }
}
