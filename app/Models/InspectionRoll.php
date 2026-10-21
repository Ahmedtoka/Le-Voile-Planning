<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionRoll extends Model
{
    protected $guarded = [];

    /** أنواع العيوب الشائعة — بتتحفظ في أول وصف العيب «بوجرسو: التفاصيل» */
    public const DEFECT_TYPES = ['بوجرسو', 'برادة', 'تناسخ', 'ثقوب', 'بقع', 'شد / تجعيد', 'اختلاف لون', 'أخرى'];

    public function inspection() { return $this->belongsTo(FabricInspection::class, 'fabric_inspection_id'); }
    public function fabricRoll() { return $this->belongsTo(FabricRoll::class); }
}
