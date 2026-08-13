<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/** تقرير انكماش قماش ومطابقة ألوان — مصدر متوسط البنشر. */
class LabReport extends Model
{
    use HasApproval, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'color_match_ok' => 'boolean'];

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

        $this->forceFill([
            'avg_gsm' => $g->count() ? round($g->avg(), 2) : null,
            'min_gsm' => $g->min(),
            'max_gsm' => $g->max(),
            'avg_shrink_len_pct'   => $this->avgOf($this->s1_shrink_len_pct, $this->s2_shrink_len_pct),
            'avg_shrink_width_pct' => $this->avgOf($this->s1_shrink_width_pct, $this->s2_shrink_width_pct),
        ])->saveQuietly();
    }

    private function avgOf($a, $b): ?float
    {
        $vals = array_values(array_filter([$a, $b], fn ($v) => $v !== null && $v !== ''));
        if (!$vals) return null;
        return round(array_sum(array_map('floatval', $vals)) / count($vals), 2);
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
