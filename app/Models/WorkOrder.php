<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * أمر الشغل — يربط حوض واحد + ماركر واحد + مصنع واحد.
 *
 * كل الأرقام المحسوبة هنا "متوقّعة" لأنها مبنية على متوسطات فحص
 * عيّنة. الفعلي بيظهر في بيان القص. الفرق المقبول 2-4%؛ فوق كده
 * لازم سبب مكتوب.
 */
class WorkOrder extends Model
{
    use HasDocumentIdentity, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['wo_date' => 'date', 'due_date' => 'date', 'receive_date' => 'date'];

    public const DOC_TYPE = 'work_order';

    public const STATUSES = [
        'draft'              => 'مسودة',
        'pending'            => 'قديم — اتنقل',   // داتا قديمة قبل شيل الاعتمادات
        'approved'           => 'معتمد',
        'rejected'           => 'مرفوض',
        'sent_to_factory'    => 'مُرسل للمصنع',
        'cutting'            => 'تحت القص',
        'cut_declared'       => 'بيان قص وارد',
        'in_production'      => 'تحت الإنتاج',
        'partially_received' => 'مستلم جزئيًا',
        'closed'             => 'مقفول',
        'cancelled'          => 'ملغي',
        'superseded'         => 'استُبدل بنسخة أحدث',
    ];

    public const VARIANCE_FLAGS = [
        'ok'     => 'داخل الحدود',
        'warn'   => 'تحذير',
        'danger' => 'خارج الحدود',
    ];

    /** @deprecated أمر الشغل بقى بأكتر من خامة — استخدم fabrics() */
    public function consignment()   { return $this->belongsTo(Consignment::class); }
    /** @deprecated الماركر بقى على مستوى الخامة — استخدم fabrics()->marker */
    public function marker()        { return $this->belongsTo(Marker::class); }
    public function factory()       { return $this->belongsTo(Factory::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()         { return $this->hasMany(WorkOrderLine::class); }
    public function fabrics()       { return $this->hasMany(WorkOrderFabric::class)->orderBy('line_no'); }
    public function materialIssueLines() { return $this->hasMany(MaterialIssueLine::class); }
    public function planner()       { return $this->belongsTo(User::class, 'planner_id'); }
    public function rolls()         { return $this->hasMany(FabricRoll::class); }
    public function cutDeclarations(){ return $this->hasMany(CutDeclaration::class); }
    public function receipts()      { return $this->hasMany(ProductionReceipt::class); }
    public function accessoryRequirements() { return $this->hasMany(AccessoryRequirement::class); }
    public function revisedFrom() { return $this->belongsTo(self::class, 'revised_from_id'); }
    public function revisions()   { return $this->hasMany(self::class, 'revised_from_id'); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', ['closed', 'cancelled', 'draft', 'superseded']);
    }

    /**
     * أوامر لسه عايزة خام من المخزن.
     *
     * التعريف الوحيد للطابور ده في السيستم كله — الكاونتر والكارت والجدول
     * كلهم بينادوه، علشان الرقم اللي فوق يبقى هو نفسه اللي تحت.
     * لاحظ `cutting`: الصرف الجزئي بينقل الأمر للحالة دي، وبرغم كده
     * لسه فيه باقي مطلوب صرفه — فلازم يفضل في الطابور.
     */
    public function scopeNeedsMaterial($q)
    {
        return $q->whereIn('status', ['approved', 'sent_to_factory', 'cutting'])
            ->whereHas('fabrics', fn ($f) => $f->whereColumn('issued_qty', '<', 'planned_qty'));
    }

    public function scopeLate($q)
    {
        return $q->open()->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
    }

    public function getIsLateAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast()
            && !in_array($this->status, ['closed', 'cancelled', 'superseded'], true);
    }

    /** المتبقي على المصنع = المقصوص - المستلم */
    public function getOutstandingPiecesAttribute(): int
    {
        return max(0, (int) $this->cut_pieces - (int) $this->received_pieces);
    }

    public function getCompletionPctAttribute(): float
    {
        $base = (int) ($this->cut_pieces ?: $this->expected_pieces);
        return $base > 0 ? round(((int) $this->received_pieces / $base) * 100, 1) : 0;
    }

    /**
     * الخامة الحاكمة — اللي بتدي أقل قطع، وبالتالي بتوقف الإنتاج.
     * على الورق الفرق ده بيبقى مخفي تمامًا.
     */
    public function governingFabric(): ?WorkOrderFabric
    {
        return $this->fabrics->sortBy(fn ($f) => $f->expected_pieces ?? PHP_INT_MAX)->first();
    }

    /** الكمية اللي الخامات فعلًا تسمح بيها */
    public function getComputedGoverningQtyAttribute(): int
    {
        $vals = $this->fabrics->pluck('expected_pieces')->filter()->all();
        return $vals ? (int) min($vals) : 0;
    }

    /** الكمية اللي هتتنفذ فعلًا: اعتماد المخطط لو موجود، وإلا الحاكمة */
    public function getTargetQtyAttribute(): int
    {
        return (int) ($this->approved_qty ?: $this->governing_qty ?: $this->computed_governing_qty);
    }

    /** فرق بين الخامات — رقم بيوضّح النقص أو الركود */
    public function getFabricGapAttribute(): int
    {
        $vals = $this->fabrics->pluck('expected_pieces')->filter()->all();
        return count($vals) > 1 ? (int) (max($vals) - min($vals)) : 0;
    }

    /** إعادة حساب الإجماليات من السطور والاستلامات */
    public function recalc(): void
    {
        $this->load('lines');
        $cut      = (int) $this->lines->sum('cut_qty');
        $received = (int) $this->lines->sum('received_qty');

        $this->load('fabrics');

        $this->forceFill([
            'cut_pieces'      => $cut,
            'received_pieces' => $received,
            'governing_qty'   => $this->computed_governing_qty ?: null,
        ])->saveQuietly();

        // علّم الخامة الحاكمة
        $min = $this->computed_governing_qty;
        foreach ($this->fabrics as $f) {
            $f->forceFill(['is_governing' => $min > 0 && (int) $f->expected_pieces === $min])->saveQuietly();
        }
    }
}
