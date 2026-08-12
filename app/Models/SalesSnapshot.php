<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * لقطة مبيعات.
 *
 * QuickBooks بيتعدّل فيه طول الشهر — فاتورة تتسجّل 200 قطعة وهي 200 دستة
 * وبعدين تتصحّح. عشان كده كل سحب لقطة مستقلة برقم مراجعة، والشهر
 * ما يتقفلش (locked) غير يوم 5 من الشهر التالي.
 */
class SalesSnapshot extends Model
{
    protected $guarded = [];
    protected $casts = [
        'pulled_at'   => 'date',
        'period_from' => 'date',
        'period_to'   => 'date',
        'is_locked'   => 'boolean',
        'unit_warning'=> 'boolean',
    ];

    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function importer()     { return $this->belongsTo(User::class, 'imported_by'); }

    public function scopeLocked($q) { return $q->where('is_locked', true); }

    /** هل الشهر ده بقى مسموح يتقفل؟ (يوم 5 من الشهر التالي) */
    public static function monthIsLockable(int $year, int $month): bool
    {
        $lockDay = (int) config('lvplanning.sales_lock_day_next_month', 5);
        $lockDate = now()->setDate($year, $month, 1)->addMonthNoOverflow()->setDay($lockDay);
        return now()->greaterThanOrEqualTo($lockDate);
    }
}
