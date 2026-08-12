<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'allow_skip_if_same_user' => 'boolean'];

    public function steps() { return $this->hasMany(ApprovalFlowStep::class)->orderBy('step_no'); }
}
