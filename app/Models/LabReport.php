<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/** تقرير انكماش قماش ومطابقة ألوان — مصدر متوسط البنشر. */
class LabReport extends Model
{
    use HasDocumentIdentity, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'color_match_ok' => 'boolean', 'has_color_break' => 'boolean', 'gsm_out_of_spec' => 'boolean'];

    public const DOC_TYPE = 'lab_report';

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function supplier()    { return $this->belongsTo(Supplier::class); }
    public function fabricType()  { return $this->belongsTo(FabricType::class); }
    public function color()       { return $this->belongsTo(Color::class); }
    public function technician()  { return $this->belongsTo(User::class, 'technician_id'); }
    public function readings()    { return $this->hasMany(LabGsmReading::class); }

    public function recalc(): void
    {
        $g = $this->readings()->pluck('gsm')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);

        $avg = $g->count() ? round($g->avg(), 2) : null;
        $ft  = $this->fabricType;
        $out = false;
        if ($avg !== null && $ft) {
            if ($ft->spec_gsm_min !== null && $avg < (float) $ft->spec_gsm_min) $out = true;
            if ($ft->spec_gsm_max !== null && $avg > (float) $ft->spec_gsm_max) $out = true;
        }

        $this->forceFill([
            'avg_gsm' => $avg,
            'min_gsm' => $g->min(),
            'max_gsm' => $g->max(),
            // لحد 4 عينات انكماش (الميتينج) — المتوسط على اللي اتسجل منهم
            'avg_shrink_len_pct'   => $this->avgOf($this->s1_shrink_len_pct, $this->s2_shrink_len_pct, $this->s3_shrink_len_pct, $this->s4_shrink_len_pct),
            'avg_shrink_width_pct' => $this->avgOf($this->s1_shrink_width_pct, $this->s2_shrink_width_pct, $this->s3_shrink_width_pct, $this->s4_shrink_width_pct),
            'gsm_out_of_spec'      => $out,
        ])->saveQuietly();
    }

    private function avgOf(...$vals): ?float
    {
        $vals = array_values(array_filter($vals, fn ($v) => $v !== null && $v !== ''));
        if (!$vals) return null;
        return round(array_sum(array_map('floatval', $vals)) / count($vals), 2);
    }

    /** مقارنة القراءات بمواصفة الخامة — بتظهر في التقرير نفسه */
    public function specChecks(): array
    {
        $ft = $this->fabricType;
        if (!$ft) return [];
        $rows = [];
        $gsm = $this->avg_gsm;
        $rows[] = ['label' => 'البنشر (جم/م²)', 'spec' => ($ft->spec_gsm_min ?? '—') . ' – ' . ($ft->spec_gsm_max ?? '—'),
                   'actual' => $gsm ?? '—', 'ok' => $gsm === null ? null : !$this->gsm_out_of_spec];
        $w = $this->consignment?->min_width_cm;
        $rows[] = ['label' => 'أقل عرض (سم)', 'spec' => $ft->spec_width_min_cm ? '≥ ' . $ft->spec_width_min_cm : ($ft->spec_width_cm ?: '—'),
                   'actual' => $w ?? '—', 'ok' => ($w === null || $ft->spec_width_min_cm === null) ? null : (float) $w >= (float) $ft->spec_width_min_cm];
        $rows[] = ['label' => 'انكماش الطول %', 'spec' => $ft->max_shrink_len_pct !== null ? '≤ ' . $ft->max_shrink_len_pct : '—',
                   'actual' => $this->avg_shrink_len_pct ?? '—',
                   'ok' => ($this->avg_shrink_len_pct === null || $ft->max_shrink_len_pct === null) ? null : (float) $this->avg_shrink_len_pct <= (float) $ft->max_shrink_len_pct];
        $rows[] = ['label' => 'انكماش العرض %', 'spec' => $ft->max_shrink_width_pct !== null ? '≤ ' . $ft->max_shrink_width_pct : '—',
                   'actual' => $this->avg_shrink_width_pct ?? '—',
                   'ok' => ($this->avg_shrink_width_pct === null || $ft->max_shrink_width_pct === null) ? null : (float) $this->avg_shrink_width_pct <= (float) $ft->max_shrink_width_pct];
        return $rows;
    }

    /** هل الانكماش خارج المواصفة؟ */
    public function getShrinkOutOfSpecAttribute(): bool
    {
        $ft = $this->fabricType;
        if (!$ft) return false;
        if ($ft->max_shrink_len_pct !== null && (float) $this->avg_shrink_len_pct > (float) $ft->max_shrink_len_pct) return true;
        if ($ft->max_shrink_width_pct !== null && (float) $this->avg_shrink_width_pct > (float) $ft->max_shrink_width_pct) return true;
        return false;
    }
}
