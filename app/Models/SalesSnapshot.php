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

    /**
     * أحدث مراجعة بس لكل (موديل + فترة).
     * الاستيراد المتكرر لنفس الفترة بيسجّل مراجعة جديدة ومش بيمسح القديمة —
     * فلو جمعنا من غير الفلتر ده، الشهر اللي اتسحب 3 مرات يتحسب 3 مرات.
     */
    public function scopeLatestRevision($q)
    {
        return $q->whereRaw('sales_snapshots.revision = (SELECT MAX(s2.revision) FROM sales_snapshots s2
            WHERE s2.product_model_id <=> sales_snapshots.product_model_id
              AND s2.period_from = sales_snapshots.period_from
              AND s2.period_to = sales_snapshots.period_to)');
    }

    /**
     * اللقطات الشهرية الكاملة بس (من أول الشهر لآخره).
     * لقطة «آخر 30 يوم» بتتداخل مع الشهر — جمعهم مع بعض بيكرر المبيعات.
     */
    public function scopeMonthly($q)
    {
        return $q->whereRaw('DAY(sales_snapshots.period_from) = 1
            AND sales_snapshots.period_to = LAST_DAY(sales_snapshots.period_from)');
    }

    /** هل الشهر ده بقى مسموح يتقفل؟ (يوم 5 من الشهر التالي) */
    public static function monthIsLockable(int $year, int $month): bool
    {
        $lockDay = (int) config('lvplanning.sales_lock_day_next_month', 5);
        $lockDate = now()->setDate($year, $month, 1)->addMonthNoOverflow()->setDay($lockDay);
        return now()->greaterThanOrEqualTo($lockDate);
    }
}
