<?php

namespace App\Models;

use App\Support\HasApproval;
use Illuminate\Database\Eloquent\Model;

/**
 * الحوض / الرسالة — وحدة الشغل الأساسية.
 *
 * مجموعة أتواب اتنسجت واتصبغت مع بعض ⇒ نفس اللون بالضبط ونفس البنشر.
 * القاعدة الذهبية: ممنوع خلط حوضين في قطعة واحدة، لأن الصدر هيطلع
 * لون والظهر لون تاني حتى لو الاتنين "أسود".
 */
class Consignment extends Model
{
    use HasApproval;

    protected $guarded = [];
    protected $casts = ['arrival_date' => 'date', 'color_match_ok' => 'boolean'];

    public const DOC_TYPE = 'consignment';

    public const STATUSES = [
        'received'      => 'مستلم',
        'inspecting'    => 'تحت الفحص',
        'inspected'     => 'تم الفحص',
        'lab_pending'   => 'مستني المعمل',
        'approved'      => 'معتمد للتشغيل',
        'rejected'      => 'مرفوض',
        'in_production' => 'في التشغيل',
        'closed'        => 'مقفول',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function fabricType()    { return $this->belongsTo(FabricType::class); }
    public function color()         { return $this->belongsTo(Color::class); }
    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function rolls()         { return $this->hasMany(FabricRoll::class); }
    public function inspections()   { return $this->hasMany(FabricInspection::class); }
    public function labReports()    { return $this->hasMany(LabReport::class); }
    public function workOrders()    { return $this->hasMany(WorkOrder::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeReadyForProduction($q)
    {
        return $q->whereIn('status', ['approved', 'in_production'])->where('remaining_kg', '>', 0);
    }

    /** جاهز للتشغيل؟ لازم يكون متفحص ومعتمد وفيه رصيد */
    public function getIsReadyAttribute(): bool
    {
        return in_array($this->status, ['approved', 'in_production'], true)
            && $this->min_width_cm > 0
            && $this->avg_gsm > 0
            && $this->remaining_kg > 0;
    }

    /** نسبة العينة المفحوصة — كل الأرقام اللي بعديها متوقّعة مش مؤكدة */
    public function getSamplePctAttribute(): float
    {
        $insp = $this->inspections->first();
        return $insp ? (float) $insp->sample_pct : 0;
    }

    public function recalcRemaining(): void
    {
        $allocated = (float) $this->workOrders()
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->sum('allocated_kg');

        $this->forceFill([
            'allocated_kg' => $allocated,
            'remaining_kg' => max(0, (float) $this->total_kg - $allocated),
        ])->saveQuietly();
    }
}
