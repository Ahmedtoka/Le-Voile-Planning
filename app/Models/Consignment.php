<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
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
    use HasDocumentIdentity, HasComments;

    protected $guarded = [];
    protected $casts = ['arrival_date' => 'date', 'released_at' => 'date', 'color_match_ok' => 'boolean', 'has_color_break' => 'boolean'];

    public const DOC_TYPE = 'consignment';

    /**
     * حالة الحوض بتمشي مع دورة الورق بالظبط:
     *   إذن إضافة ⇒ under_inspection (محجوز، ممنوع التشغيل)
     *   تقرير فحص ⇒ inspected  ·  تقرير معمل ⇒ lab_done
     *   إذن استلام خام ⇒ released (الإفراج الفعلي)
     */
    public const STATUSES = [
        'under_inspection' => 'تحت الفحص (محجوز)',
        'inspected'        => 'تم الفحص',
        'lab_done'         => 'تم المعمل',
        'released'         => 'مفرج عنه — جاهز للتشغيل',
        'rejected'         => 'مرفوض',
        'in_production'    => 'في التشغيل',
        'closed'           => 'مقفول',
    ];

    public const STATUS_COLORS = [
        'under_inspection' => 'warning',
        'inspected'        => 'info',
        'lab_done'         => 'info',
        'released'         => 'success',
        'rejected'         => 'danger',
        'in_production'    => 'primary',
        'closed'           => 'dark',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function fabricType()    { return $this->belongsTo(FabricType::class); }
    public function color()         { return $this->belongsTo(Color::class); }
    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function rolls()         { return $this->hasMany(FabricRoll::class); }
    public function stockAdditions(){ return $this->hasMany(StockAddition::class); }
    public function inspections()   { return $this->hasMany(FabricInspection::class); }
    public function labReports()    { return $this->hasMany(LabReport::class); }
    public function workOrders()      { return $this->hasMany(WorkOrder::class); }
    public function workOrderFabrics(){ return $this->hasMany(WorkOrderFabric::class); }
    public function issueLines()      { return $this->hasMany(MaterialIssueLine::class); }
    public function rejections()      { return $this->hasMany(GoodsReceiptRejection::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
    public function preparationRequests() { return $this->hasMany(PreparationRequest::class); }

    /** سطر طلب الشراء اللي الرسالة دي جاية منه (نفس الصنف واللون) */
    public function poLine(): ?PurchaseOrderLine
    {
        $po = $this->purchaseOrder;
        if (!$po) return null;
        $po->loadMissing('lines');
        return $po->lines->first(fn ($l) => $l->fabric_type_id == $this->fabric_type_id && $l->color_id == $this->color_id);
    }

    /**
     * الرسالة دي من صنف كان مطلوب منه كام، وصل منه كام، وباقي كام —
     * عشان دفعة الباقي لما توصل تتربط بنفس السطر صح.
     * @return array{ordered:float,received:float,left:float,unit:string,eta:?string,closed:bool,line:PurchaseOrderLine}|null
     */
    public function orderLineStatus(): ?array
    {
        $l = $this->poLine();
        if (!$l) return null;
        $eta = StockAdditionLine::where('po_line_id', $l->id)->whereNotNull('remainder_eta')
            ->orderByDesc('id')->value('remainder_eta');
        return [
            'ordered'  => (float) $l->qty,
            'received' => (float) $l->received_qty,
            'left'     => max(0, (float) $l->min_allowed_qty - (float) $l->received_qty),
            'unit'     => $l->unit,
            'eta'      => $eta ? \Illuminate\Support\Carbon::parse($eta)->format('Y-m-d') : $this->purchaseOrder?->remainder_eta?->format('Y-m-d'),
            'closed'   => (bool) $l->is_closed,
            'line'     => $l,
        ];
    }

    /** إذن الإضافة اللي الرسالة دي اتولّدت منه — عشان رقم إذن المورد يبقى لينك في كل الشاشات */
    public function sourceAddition(): ?StockAddition
    {
        if ($this->relationLoaded('stockAdditions') && $this->stockAdditions->first()) return $this->stockAdditions->first();
        $sa = $this->stockAdditions()->latest('id')->first();
        if ($sa) return $sa;
        $id = StockAdditionLine::where('consignment_id', $this->id)->value('stock_addition_id');
        return $id ? StockAddition::find($id) : null;
    }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** الرسالة بالمتر؟ (الوحدة من بند طلب الشراء — الميتينج 37) */
    public function getIsMeterAttribute(): bool { return lv_is_meter($this->unit); }

    /** تسمية الوحدة للعرض: كجم أو م */
    public function getUnitLabelAttribute(): string { return lv_unit($this->unit); }

    /** وزن الكيس والماسورة لعدد أتواب من الرسالة دي — للصرف ووزن التحضير */
    public function packagingFor(int $rolls): float
    {
        if ($this->is_meter) return 0.0;
        return lv_packaging_kg($this->bag_g, $this->tube_g, $rolls);
    }

    /** عرض التشغيل = أقل عرض − هامش من كل جانب (تأمين العروض) */
    public function getUsableWidthAttribute(): ?float
    {
        if ($this->usable_width_cm) return (float) $this->usable_width_cm;
        if (!$this->min_width_cm) return null;
        return round((float) $this->min_width_cm - 2 * (float) lv_setting('width_margin_cm_per_side', 2.5), 2);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /** الجاهز للتشغيل = المفرج عنه بإذن الاستلام بس */
    public function scopeReadyForProduction($q)
    {
        return $q->whereIn('status', ['released', 'in_production'])->where('remaining_kg', '>', 0);
    }

    /** لسه محجوز تحت الفحص */
    /**
     * رسايل خلّصت فحص ومعمل ومستنية إذن الاستلام الخام (الإفراج).
     * التعريف الوحيد في السيستم — المنيو والداشبورد والمكتب وشاشة الاستلام كلهم بيستخدموه.
     */
    public function scopeAwaitingRelease($q)
    {
        return $q->whereIn('status', ['inspected', 'lab_done'])
            ->whereHas('inspections', fn ($i) => $i->where('status', 'approved'))
            ->whereHas('labReports',  fn ($l) => $l->where('status', 'approved'))
            ->whereDoesntHave('goodsReceipts', fn ($g) => $g->where('status', 'approved'));
    }

    public function scopeOnHold($q)
    {
        return $q->whereIn('status', ['under_inspection', 'inspected', 'lab_done']);
    }

    /**
     * جاهز للتشغيل؟
     * لازم يكون اتفرج عنه بإذن استلام + عنده أقل عرض من الفحص + بنشر من المعمل.
     */
    public function getIsReadyAttribute(): bool
    {
        return in_array($this->status, ['released', 'in_production'], true)
            && $this->min_width_cm > 0
            && $this->avg_gsm > 0
            && $this->remaining_kg > 0;
    }

    /** الخطوة الجاية المطلوبة على الحوض ده — بتغذّي كاونترات المنيو */
    public function nextStep(): ?string
    {
        return match (true) {
            $this->status === 'under_inspection' && !$this->inspections()->where('status','approved')->exists()
                => 'inspection',
            in_array($this->status, ['under_inspection','inspected'], true)
                && !$this->labReports()->where('status','approved')->exists()
                => 'lab',
            in_array($this->status, ['inspected','lab_done'], true)
                => 'receipt',
            default => null,
        };
    }

    /** نسبة العينة المفحوصة — كل الأرقام اللي بعديها متوقّعة مش مؤكدة */
    public function getSamplePctAttribute(): float
    {
        $insp = $this->inspections->first();
        return $insp ? (float) $insp->sample_pct : 0;
    }

    /**
     * إعادة حساب الأرصدة.
     * المتاح للتشغيل = المفرج عنه − المخصص لأوامر الشغل.
     * طول ما الحوض تحت الفحص، المفرج عنه = صفر ⇒ مفيش تشغيل.
     */
    public function recalcRemaining(): void
    {
        /* المخصص بيتجمع من خامات أوامر الشغل — لأن أمر الشغل الواحد
           بياخد من أكتر من حوض، وكل حوض بكميته. */
        $open = (float) $this->workOrderFabrics()
            ->whereHas('workOrder', fn ($q) => $q->whereNotIn('status', ['cancelled', 'draft', 'superseded', 'closed']))
            ->sum('planned_qty');

        /* الأمر المقفول بيحجز اللي اتصرف فعلًا بس — الباقي من المخطط
           بيرجع متاح للرسالة بدل ما يفضل محجوز على أمر خلص. */
        $closed = (float) $this->workOrderFabrics()
            ->whereHas('workOrder', fn ($q) => $q->where('status', 'closed'))
            ->sum('issued_qty');

        /* طلبات التحضير (تقسيم الرسالة) بتحجز فورًا لحد ما تبقى أمر شغل —
           بالفعلي لو المخزن وزن، وبالتقريبي لو لسه (الميتينج). */
        $reserved = (float) $this->preparationRequests()->reserving()->get()->sum('reserved_qty');

        $allocated = $open + $closed;

        /* المفرج عنه بيتاخد زي ما هو من إذن الاستلام.
           ملحوظة مهمة: مينفعش نستخدم ‎?:‎ هنا — لأن رسالة اترفضت بالكامل
           بيبقى المفرج عنه فيها = 0.0، و ‎0.0 ?: total_kg‎ هترجّع الوزن كله
           فيبان قماش مرفوض كأنه جاهز للتشغيل. الفولباك للـ null بس
           (صفوف قديمة قبل ما العمود يتملّي). */
        $released = in_array($this->status, ['released', 'in_production', 'closed'], true)
            ? (float) ($this->released_kg ?? $this->total_kg)
            : 0.0;

        $hold = in_array($this->status, ['under_inspection', 'inspected', 'lab_done'], true)
            ? (float) $this->total_kg
            : 0.0;

        $this->forceFill([
            'hold_kg'      => $hold,
            'released_kg'  => $released,
            'allocated_kg' => $allocated,
            'reserved_kg'  => $reserved,
            'remaining_kg' => max(0, $released - $allocated - $reserved),
        ])->saveQuietly();
    }
}
