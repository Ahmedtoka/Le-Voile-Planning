<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * تقرير فحص القماش.
 *
 * مهم: الفحص عيّنة مش 100% (6-7 أتواب من 40). كل رقم طالع من هنا
 * "متوقّع" — عشان كده بنخزّن sampled_rolls وبنعرضها على أي شاشة
 * بتستخدم أرقام الفحص.
 */
class FabricInspection extends Model
{
    use HasApproval, HasDocumentStatus;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'width_alert' => 'boolean'];

    public const DOC_TYPE = 'fabric_inspection';

    public const RESULTS = [
        'pending'             => 'تحت الفحص',
        'accepted'            => 'مقبول',
        'accepted_with_notes' => 'مقبول بملاحظات',
        'rejected'            => 'مرفوض',
    ];

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function fabricType()  { return $this->belongsTo(FabricType::class); }
    public function color()       { return $this->belongsTo(Color::class); }
    public function supplier()    { return $this->belongsTo(Supplier::class); }
    public function workOrder()   { return $this->belongsTo(WorkOrder::class); }
    public function inspector()   { return $this->belongsTo(User::class, 'inspector_id'); }
    public function rolls()       { return $this->hasMany(InspectionRoll::class); }

    public function getResultNameAttribute(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    /**
     * إعادة حساب ملخص الفحص من سطور الأتواب.
     * ★ أقل عرض هو اللي بيتبني عليه الماركر — مش المتوسط.
     */
    public function recalc(): void
    {
        $rows = $this->rolls()->get();
        $widths  = $rows->pluck('width_cm')->filter(fn ($v) => $v > 0)->map(fn ($v) => (float) $v);
        $lengths = $rows->pluck('length_m')->map(fn ($v) => (float) $v);

        $min = $widths->min();
        $max = $widths->max();
        $spread = ($min !== null && $max !== null) ? round($max - $min, 2) : null;

        $totalLen     = (float) $lengths->sum();
        $totalDefects = (int) $rows->sum('defects_count');

        // نسبة العيوب: عيب لكل 100 متر
        $defectPct = $totalLen > 0 ? round(($totalDefects / $totalLen) * 100, 3) : 0;

        $total   = (int) ($this->total_rolls ?: $this->consignment?->rolls_count ?: $rows->count());
        $sampled = $rows->count();

        $this->forceFill([
            'total_rolls'     => $total,
            'sampled_rolls'   => $sampled,
            'sample_pct'      => $total > 0 ? round(($sampled / $total) * 100, 2) : 0,
            'total_length_m'  => $totalLen,
            'min_width_cm'    => $min,
            'avg_width_cm'    => $widths->count() ? round($widths->avg(), 2) : null,
            'max_width_cm'    => $max,
            'width_spread_cm' => $spread,
            'total_defects'   => $totalDefects,
            'defect_pct'      => $defectPct,
            // فرق العرض الكبير بين أتواب نفس الحوض = القماش نفسه فيه مشكلة
            'width_alert'     => $spread !== null && $spread > (float) config('lvplanning.width_spread_alert_cm', 5),
        ])->saveQuietly();
    }

    /** هل حجم العينة مقبول؟ */
    public function getSampleTooSmallAttribute(): bool
    {
        return (float) $this->sample_pct < (float) config('lvplanning.min_inspection_sample_pct', 15);
    }
}
