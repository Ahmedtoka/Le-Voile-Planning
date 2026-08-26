<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * بيان القص — الرقم الفعلي الأول اللي بيوصلنا من المصنع.
 *
 * أهم حقل هنا: actual_spread_length_m.
 * لو المصنع فرش على 3.05 بدل 3.00، على توب 30 متر بتخسر رِقّة كاملة
 * وعدد القطع بينزل — وده أكبر مصدر فاقد في الدورة كلها.
 */
class CutDeclaration extends Model
{
    use HasDocumentIdentity, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = ['doc_date' => 'date'];

    public const DOC_TYPE = 'cut_declaration';

    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function factory()   { return $this->belongsTo(Factory::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(CutDeclarationLine::class); }

    public function getSpreadDeviationCmAttribute(): ?float
    {
        $planned = (float) ($this->workOrder?->governingFabric()?->effective_spread ?? 0);
        if (!$planned || !$this->actual_spread_length_m) return null;
        return round(((float) $this->actual_spread_length_m - $planned) * 100, 2);
    }
}
