<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = [];
    protected $casts = ['completed_at' => 'datetime'];

    public const STATUSES = [
        'pending'   => 'تحت الاعتماد',
        'approved'  => 'معتمد',
        'rejected'  => 'مرفوض',
        'cancelled' => 'ملغي',
    ];

    public function subject()     { return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id'); }
    public function steps()       { return $this->hasMany(ApprovalStep::class)->orderBy('step_no'); }
    public function requester()   { return $this->belongsTo(User::class, 'requested_by'); }

    public function currentStepRow(): ?ApprovalStep
    {
        return $this->steps()->where('step_no', $this->current_step)->first();
    }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
