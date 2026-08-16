<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * خامة داخل أمر الشغل.
 *
 * المنتج الواحد ممكن يتعمل من أكتر من خامة (طرحة تل + بونيه رباط مياي).
 * كل خامة ليها رسالتها ولونها وماركرها وحسبتها المستقلة تمامًا —
 * ومن غير ما تحسب كل واحدة لوحدها، مش هتعرف مين فيهم اللي هيوقف الإنتاج.
 */
class WorkOrderFabric extends Model
{
    protected $guarded = [];
    protected $casts = ['is_governing' => 'boolean'];

    public const MODES = [
        'weight' => 'بالوزن (كجم)',
        'length' => 'بالطول (متر)',
    ];

    public function workOrder()   { return $this->belongsTo(WorkOrder::class); }
    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function fabricType()  { return $this->belongsTo(FabricType::class); }
    public function color()       { return $this->belongsTo(Color::class); }
    public function marker()      { return $this->belongsTo(Marker::class); }
    public function issueLines()  { return $this->hasMany(MaterialIssueLine::class); }

    public function getModeNameAttribute(): string
    {
        return self::MODES[$this->calc_mode] ?? $this->calc_mode;
    }

    /** طول الفرشة اللي بتتحسب بيه فعلًا — الأمان لو موجود */
    public function getEffectiveSpreadAttribute(): ?float
    {
        return (float) ($this->spread_length_safe_m ?: $this->spread_length_m) ?: null;
    }

    /** المنصرف فعليًا من إذون صرف الخام المعتمدة */
    public function getIssuedActualAttribute(): float
    {
        return (float) $this->issueLines()
            ->whereHas('materialIssue', fn ($q) => $q->where('status', 'approved'))
            ->sum('qty');
    }

    public function getShortageAttribute(): float
    {
        return max(0, (float) $this->planned_qty - $this->issued_actual);
    }
}
