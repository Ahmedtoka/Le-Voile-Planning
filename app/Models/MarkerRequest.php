<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkerRequest extends Model
{
    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'needed_by' => 'date'];

    public const STATUSES = [
        'open'        => 'مفتوح',
        'in_progress' => 'جاري التنفيذ',
        'delivered'   => 'تم التسليم',
        'cancelled'   => 'ملغي',
    ];

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function factory()     { return $this->belongsTo(Factory::class); }
    public function patternist()  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function marker()      { return $this->belongsTo(Marker::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
