<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlowStep extends Model
{
    protected $guarded = [];
    protected $casts = ['is_mandatory' => 'boolean'];

    public function flow() { return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id'); }
    public function role() { return $this->belongsTo(Role::class); }
    public function user() { return $this->belongsTo(User::class); }
}
