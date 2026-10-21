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
    public function models()      { return $this->belongsToMany(ProductModel::class, 'marker_request_models'); }
    public function suggestedMarker()    { return $this->belongsTo(Marker::class, 'suggested_marker_id'); }
    public function preparationRequest() { return $this->belongsTo(PreparationRequest::class); }

    public function getModelsLabelAttribute(): string
    {
        $n = $this->models->pluck('label')->all();
        return $n ? implode(' + ', $n) . ($this->sizes_note ? ' — ' . $this->sizes_note : '') : (string) $this->requested_models;
    }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
