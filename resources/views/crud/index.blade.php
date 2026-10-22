@extends('layouts.app')
@section('content')

@php
  $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty();
  $listCols   = collect($fields)->filter(fn ($f) => !empty($f['list']))->count();
@endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route($routeName.'.create') }}" class="btn btn-sm btn-plum">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ $singular }} جديد
    </a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة {{ $title }}">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="بحث…">
      </div>
      @foreach($fields as $f)
        @if(!empty($f['filter']))
          <select name="{{ $f['name'] }}" class="form-select" aria-label="{{ $f['label'] }}" onchange="this.form.submit()">
            <option value="">كل {{ $f['label'] }}</option>
            @foreach(($f['options'] ?? []) as $k => $v)
              <option value="{{ $k }}" @selected(request($f['name']) == $k)>{{ $v }}</option>
            @endforeach
          </select>
        @endif
      @endforeach
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead>
        <tr>
          <th style="width:40px" class="num">#</th>
          @foreach($fields as $f)
            @if(!empty($f['list']))
              @if(empty($f['relation']) && ($f['type'] ?? '') !== 'checkbox')
                @include('partials.th_sort', ['label' => $f['label'], 'col' => $f['name']])
              @else
                <th>{{ $f['label'] }}</th>
              @endif
            @endif
          @endforeach
          <th style="width:110px"><span class="visually-hidden">إجراءات</span></th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $i => $row)
        <tr>
          <td class="text-muted num">{{ $rows->firstItem() + $i }}</td>
          @foreach($fields as $f)
            @if(!empty($f['list']))
              @php $val = $row->{$f['name']}; @endphp
              <td @class(['num' => in_array($f['type'] ?? '', ['number', 'date'], true)])>
                @if(($f['type'] ?? '') === 'checkbox')
                  <span class="status {{ $val ? 'is-ok' : '' }}">{{ $val ? 'نعم' : 'لا' }}</span>
                @elseif(!empty($f['relation']))
                  {{ $row->{$f['relation']}->name ?? '—' }}
                @elseif(($f['type'] ?? '') === 'select')
                  {{ ($f['options'][$val] ?? $val) ?: '—' }}
                @elseif(($f['type'] ?? '') === 'date')
                  {{ $val?->format('Y-m-d') ?? '—' }}
                @elseif(($f['type'] ?? '') === 'number')
                  {{ $val !== null ? lv_num($val) : '—' }}
                @else
                  {{ $val ?: '—' }}
                @endif
              </td>
            @endif
          @endforeach
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route($routeName.'.edit', $row->id) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح / تعديل" aria-label="فتح {{ $row->name ?? $row->id }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            @if($routeName === 'product-models')
              <a href="{{ route('product-models.sizes', $row->id) }}" class="btn btn-sm btn-outline-secondary btn-icon"
                 title="المقاسات والإكسسوارات" aria-label="المقاسات والإكسسوارات لـ{{ $row->name ?? $row->id }}"><i class="bi bi-list-check" aria-hidden="true"></i></a>
            @endif
            @if($canDelete)
              <form method="post" action="{{ route($routeName.'.destroy', $row->id) }}"
                    onsubmit="return confirm('متأكد من الحذف؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger btn-icon" title="حذف" aria-label="حذف {{ $row->name ?? $row->id }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
              </form>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="{{ $listCols + 2 }}">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش نتايج مطابقة للبحث.' : 'مفيش بيانات لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب كلمة تانية أو شيل الفلاتر.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح البحث واعرض الكل</a>
            @else
              <a href="{{ route($routeName.'.create') }}" class="btn btn-sm btn-plum mt-2">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف {{ $singular }}</a>
            @endif
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    @if($rows->total() > 0)
      <div class="hint num">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} من {{ number_format($rows->total()) }}</div>
    @endif
  </div>
</div>
@endsection
