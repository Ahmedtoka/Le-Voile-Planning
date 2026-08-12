<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * الماركر (التعشيقة).
 *
 * قاعدة حاكمة: عرض الماركر لازم يكون <= أقل عرض قماش في الحوض.
 * لو أكبر، هنحرق الجنب ونرميه — وده فلوس بتترمي.
 * الماركر ممكن يشيل أكتر من موديل ومقاس، لأن المكملات بتتقص
 * خامة واحدة لموديلات كتير في نفس الفرشة.
 */
class Marker extends Model
{
    use HasApproval, HasDocumentStatus;

    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public const DOC_TYPE = 'marker';

    public function request()    { return $this->belongsTo(MarkerRequest::class, 'marker_request_id'); }
    public function factory()    { return $this->belongsTo(Factory::class); }
    public function patternist() { return $this->belongsTo(User::class, 'created_by_patternist'); }
    public function lines()      { return $this->hasMany(MarkerLine::class); }
    public function workOrders() { return $this->hasMany(WorkOrder::class); }

    /** إجمالي القطع في الفرشة من السطور */
    public function recalcPieces(): void
    {
        $total = (int) $this->lines()->sum('qty_per_spread');
        if ($total > 0) {
            $this->forceFill(['pieces_per_spread' => $total])->saveQuietly();
        }
    }

    /** هل الماركر ينفع على العرض ده؟ */
    public function fitsWidth(?float $availableWidthCm): bool
    {
        if ($availableWidthCm === null) return false;
        $need = (float) ($this->marker_width_cm ?: $this->fabric_width_cm);
        return $need <= $availableWidthCm + 0.001;
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' (عرض ' . rtrim(rtrim(number_format((float) $this->fabric_width_cm, 2), '0'), '.') . ' سم)';
    }
}
