<?php

namespace App\Models;

use App\Support\HasApproval;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * إذن صرف خام — الحلقة بين أمر الشغل والمصنع.
 *
 * بيصرف الخامات من المخزن للمصنع مقابل أمر شغل. الورقة الواحدة ممكن
 * تغطي أكتر من أمر شغل وأكتر من خامة (زي 1303774 اللي فيه KB106 و KB107).
 *
 * اعتماده بيخصم فعليًا من رصيد الحوض.
 */
class MaterialIssue extends Model
{
    use HasApproval, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date'];

    public const DOC_TYPE = 'material_issue';

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function factory()   { return $this->belongsTo(Factory::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(MaterialIssueLine::class); }

    /** أوامر الشغل اللي الورقة دي بتخدمها */
    public function workOrders()
    {
        return WorkOrder::whereIn('id', $this->lines()->pluck('work_order_id')->filter()->unique())->get();
    }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');
        $this->forceFill([
            'total_qty'   => (float) $this->lines->sum('qty'),
            'total_rolls' => (int) $this->lines->sum('rolls_count'),
        ])->saveQuietly();
    }
}
