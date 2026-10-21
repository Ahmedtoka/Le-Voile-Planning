<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use Illuminate\Database\Eloquent\Model;

/**
 * طلب تحضير (تقسيم رسالة) — بين التخطيط والمخزن.
 *
 * من الميتينج: التخطيط يختار الرسالة ويكتب كمية **تقريبية** (200 كيلو مثلًا) للموديلات
 * اللي عايز يشغّلها، والمخزن يوزن الأتواب فعليًا ويرد بالوزن الفعلي (195 أو 210).
 * الرقم الفعلي هو اللي بيدخل أمر الشغل — لأنه بيفرق في عدد القطع.
 *
 * القواعد:
 *   • الكمية المطلوبة بتتحجز على الرسالة فورًا، وبتتعدّل للفعلي بعد رد المخزن.
 *   • الرسالة ممكن تتقسم لأكتر من طلب (2-5 مصانع) — مجموعهم ما يتعداش رصيدها.
 *   • آخر تقسيمة «الباقي» بتاخد اللي فاضل من غير وزن، والفرق بالزيادة أو النقص على الرسالة.
 */
class PreparationRequest extends Model
{
    use HasDocumentIdentity, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'weighed_at' => 'datetime', 'is_remainder' => 'boolean'];

    public const DOC_TYPE = 'preparation_request';

    public const STATUSES = [
        'open'      => 'مستني وزن المخزن',
        'weighed'   => 'اتوزن — جاهز لأمر الشغل',
        'used'      => 'دخل أمر شغل',
        'cancelled' => 'ملغي',
    ];

    public const STATUS_COLORS = [
        'open'      => 'warning',
        'weighed'   => 'success',
        'used'      => 'primary',
        'cancelled' => 'secondary',
    ];

    public function consignment()   { return $this->belongsTo(Consignment::class); }
    public function factory()       { return $this->belongsTo(Factory::class); }
    public function workOrder()     { return $this->belongsTo(WorkOrder::class); }
    public function markerRequest() { return $this->belongsTo(MarkerRequest::class); }
    public function requester()     { return $this->belongsTo(User::class, 'requested_by'); }
    public function weigher()       { return $this->belongsTo(User::class, 'weighed_by'); }
    public function models()        { return $this->belongsToMany(ProductModel::class, 'preparation_request_models'); }

    public function getStatusNameAttribute(): string  { return self::STATUSES[$this->status] ?? $this->status; }
    public function getStatusColorAttribute(): string { return self::STATUS_COLORS[$this->status] ?? 'secondary'; }

    /** لسه بيحجز على الرسالة؟ (ما بقاش أمر شغل ولا اتلغى) */
    public function scopeReserving($q)
    {
        return $q->whereIn('status', ['open', 'weighed'])->whereNull('work_order_id');
    }

    /** الكمية اللي بتتحجز دلوقتي: الفعلي لو اتوزن، وإلا التقريبي */
    public function getReservedQtyAttribute(): float
    {
        return (float) ($this->status === 'weighed' && $this->actual_qty !== null ? $this->actual_qty : $this->requested_qty);
    }

    /** الكمية اللي تدخل أمر الشغل */
    public function getQtyForWorkOrderAttribute(): float
    {
        return (float) ($this->actual_qty ?? $this->requested_qty);
    }

    public function getModelsLabelAttribute(): string
    {
        $n = $this->models->pluck('name')->all();
        return $n ? implode('، ', $n) : (string) $this->models_note;
    }
}
