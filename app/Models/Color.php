<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * اللون — أخطر جدول في السيستم.
 *
 * السيستم القديم فيه ~3000 كود لون، لأن كل مرة اللون يترجع من الصباغة
 * بفرق بسيط كان بياخد كود جديد. ده خلّى تتبّع اللون عبر الزمن مستحيل.
 *
 * القاعدة هنا: ممنوع الحذف النهائي. الدمج بيخلّي الكود القديم موجود
 * بحالة merged وبيشاور على الجديد، فالداتا التاريخية تفضل قابلة للقراءة.
 */
class Color extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_basic'  => 'boolean',
        'merged_at' => 'datetime',
    ];

    public const STATUSES = [
        'active'  => 'نشط',
        'merged'  => 'مدموج',
        'retired' => 'موقوف',
    ];

    public function mergedInto() { return $this->belongsTo(Color::class, 'merged_into_id'); }
    public function mergedFrom() { return $this->hasMany(Color::class, 'merged_into_id'); }
    public function merges()     { return $this->hasMany(ColorMerge::class, 'from_color_id'); }

    public function scopeActive($q)  { return $q->where('status', 'active'); }
    public function scopeUsable($q)  { return $q->whereIn('status', ['active']); }
    public function scopeBasic($q)   { return $q->where('is_basic', true); }

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getLabelAttribute(): string
    {
        $label = $this->code . ' — ' . $this->name;
        if ($this->status === 'merged') {
            $label .= ' (مدموج في ' . ($this->mergedInto->code ?? '؟') . ')';
        }
        return $label;
    }

    /** اللون الفعّال — لو ده مدموج، رجّع اللي اندمج فيه (بتتبع السلسلة) */
    public function effective(): Color
    {
        $c = $this;
        $guard = 0;
        while ($c->status === 'merged' && $c->merged_into_id && $guard < 20) {
            $next = static::find($c->merged_into_id);
            if (!$next) break;
            $c = $next;
            $guard++;
        }
        return $c;
    }

    /**
     * دمج لون في لون تاني — من غير ما يتحذف أي حاجة.
     * الكود القديم بيفضل موجود ويشاور على الجديد.
     */
    public static function merge(Color $from, Color $to, ?int $userId = null, ?string $reason = null): void
    {
        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('مينفعش تدمج اللون في نفسه.');
        }
        if ($to->status === 'merged') {
            throw new \InvalidArgumentException('اللون الهدف نفسه مدموج — اختار الكود الفعّال.');
        }

        DB::transaction(function () use ($from, $to, $userId, $reason) {
            $from->update([
                'status'         => 'merged',
                'merged_into_id' => $to->id,
                'merged_at'      => now(),
                'merged_by'      => $userId,
                'merge_note'     => $reason,
            ]);

            ColorMerge::create([
                'from_color_id' => $from->id,
                'to_color_id'   => $to->id,
                'user_id'       => $userId,
                'reason'        => $reason,
            ]);

            // أي لون كان مدموج في القديم يتحوّل للجديد كمان
            static::where('merged_into_id', $from->id)->update(['merged_into_id' => $to->id]);
        });
    }
}
