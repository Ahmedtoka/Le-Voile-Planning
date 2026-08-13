<?php

namespace App\Models;

use App\Support\HasApproval;
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
    use HasApproval, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['wo_date' => 'date', 'due_date' => 'date'];

    public const DOC_TYPE = 'work_order';

    public const STATUSES = [
        'draft'              => 'مسودة',
        'pending'            => 'تحت الاعتماد',
        'approved'           => 'معتمد',
        'rejected'           => 'مرفوض',
        'sent_to_factory'    => 'مُرسل للمصنع',
        'cutting'            => 'تحت القص',
        'cut_declared'       => 'بيان قص وارد',
        'in_production'      => 'تحت الإنتاج',
        'partially_received' => 'مستلم جزئيًا',
        'closed'             => 'مقفول',
        'cancelled'          => 'ملغي',
    ];

    public const VARIANCE_FLAGS = [
        'ok'     => 'داخل الحدود',
        'warn'   => 'تحذير',
        'danger' => 'خارج الحدود',
    ];

    public function consignment()   { return $this->belongsTo(Consignment::class); }
    public function marker()        { return $this->belongsTo(Marker::class); }
    public function factory()       { return $this->belongsTo(Factory::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()         { return $this->hasMany(WorkOrderLine::class); }
    public function rolls()         { return $this->hasMany(FabricRoll::class); }
    public function cutDeclarations(){ return $this->hasMany(CutDeclaration::class); }
    public function receipts()      { return $this->hasMany(ProductionReceipt::class); }
    public function accessoryRequirements() { return $this->hasMany(AccessoryRequirement::class); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', ['closed', 'cancelled', 'draft']);
    }

    public function scopeLate($q)
    {
        return $q->open()->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
    }

    public function getIsLateAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast()
            && !in_array($this->status, ['closed', 'cancelled'], true);
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

    /** إعادة حساب الإجماليات من السطور والاستلامات */
    public function recalc(): void
    {
        $this->load('lines');
        $cut      = (int) $this->lines->sum('cut_qty');
        $received = (int) $this->lines->sum('received_qty');

        $this->forceFill([
            'cut_pieces'      => $cut,
            'received_pieces' => $received,
        ])->saveQuietly();
    }
}
