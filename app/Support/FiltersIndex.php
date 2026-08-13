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
}
