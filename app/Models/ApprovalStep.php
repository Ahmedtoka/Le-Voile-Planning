<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $guarded = [];
    protected $casts = ['acted_at' => 'datetime'];

    public const STATUSES = [
        'waiting'  => 'في الانتظار',
        'pending'  => 'مطلوب الآن',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'skipped'  => 'متخطى',
    ];

    public function approval() { return $this->belongsTo(Approval::class); }
    public function role()     { return $this->belongsTo(Role::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function actor()    { return $this->belongsTo(User::class, 'acted_by'); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
