<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * فلاتر الجداول الموحّدة: بحث + مدى تاريخ + فلاتر أعمدة.
 * كل شاشة بتستخدم نفس السلوك، فالمستخدم بيتعلمه مرة واحدة.
 */
trait FiltersIndex
{
    protected function applyFilters(Builder $q, Request $request, array $searchable, string $dateColumn = 'doc_date', array $exact = []): Builder
    {
        if ($term = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($searchable, $term) {
                foreach ($searchable as $col) {
                    if (str_contains($col, '.')) {
                        [$rel, $c] = explode('.', $col, 2);
                        $qq->orWhereHas($rel, fn ($r) => $r->where($c, 'like', "%{$term}%"));
                    } else {
                        $qq->orWhere($col, 'like', "%{$term}%");
                    }
                }
            });
        }

        if ($from = $request->get('from')) $q->whereDate($dateColumn, '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate($dateColumn, '<=', $to);

        foreach ($exact as $param => $column) {
            if ($request->filled($param)) $q->where($column, $request->get($param));
        }

        return $q;
    }

    /**
     * ترتيب الأعمدة.
     *
     * العمود لازم يكون في القايمة المسموح بيها — عشان محدش يبعت اسم عمود
     * من عنده. ولو مفيش ترتيب مطلوب، بنرجع للافتراضي (الأحدث الأول).
     *
     * @param array $allowed أسماء أعمدة الجدول المسموح الترتيب بيها
     */
    protected function applySort(Builder $q, Request $request, array $allowed, string $default = 'id', string $defaultDir = 'desc'): Builder
    {
        $col = (string) $request->get('sort');
        $dir = strtolower((string) $request->get('dir')) === 'asc' ? 'asc' : 'desc';

        if ($col !== '' && in_array($col, $allowed, true)) {
            return $q->orderBy($col, $dir)->orderBy('id', 'desc');
        }

        return $q->orderBy($default, $defaultDir);
    }
}
