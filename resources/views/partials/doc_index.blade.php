@extends('layouts.app')
@section('content')

@if(!empty($flow))
  @include('partials.flow_bar', ['flow' => $flow, 'step' => $flowStep ?? ''])
@endif

@if(!empty($intro))
  <div class="note-box mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{!! $intro !!}</span></div>
@endif

@include('partials.summary')

{{-- جدول علوي اختياري — زي «طلبات مستنية استلام» في أذون الإضافة --}}
@if(!empty($topTable))
  <div class="card mb-3">
    <div class="card-header"><span><span class="dot dot-warn" aria-hidden="true"></span>{{ $topTable['title'] }}</span></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr>@foreach($topTable['cols'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
          @forelse($topTable['rows'] as $tr)
            <tr>{!! $tr !!}</tr>
          @empty
            <tr><td colspan="{{ count($topTable['cols']) }}" class="text-center text-muted py-3">{{ $topTable['empty'] ?? '—' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    {{-- الفلاتر: سطر واحد، كل حقل على قد محتواه، بحث بالإنتر، وزرار مسح --}}
    <form class="filter-bar" method="get" role="search" aria-label="فلترة القايمة">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث"
               placeholder="{{ $searchHint ?? 'بحث برقم المستند…' }}">
      </div>

      @foreach(($filters ?? []) as $f)
        <select name="{{ $f['name'] }}" class="form-select" aria-label="{{ $f['label'] }}"
                @if(!empty($f['width'])) style="width:{{ $f['width'] }}px" @endif
                onchange="this.form.submit()">
          <option value="">{{ $f['label'] }}</option>
          @foreach($f['options'] as $k => $v)
            <option value="{{ $k }}" @selected((string) request($f['name']) === (string) $k)>{{ $v }}</option>
          @endforeach
        </select>
      @endforeach

      @if(($dateFilter ?? true))
        <div class="fb-range">
          <label class="hint" for="f-from">من</label>
          <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="form-control">
          <label class="hint" for="f-to">إلى</label>
          <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="form-control">
        </div>
      @endif

      {{-- الترتيب بيتحفظ مع الفلاتر --}}
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif

      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>

    @if(!empty($extraActions)){!! $extraActions !!}@endif
    @if(!empty($createRoute) && Route::has($createRoute))
      <a href="{{ route($createRoute) }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ $createLabel }}</a>
    @endif
  </div>

  {{-- شرايح التاريخ السريعة --}}
  @if(($dateFilter ?? true))
    @include('partials.date_chips')
  @endif

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        @php
          $sortMap = $sortable ?? [];
          $curSort = request('sort'); $curDir = request('dir') === 'asc' ? 'asc' : 'desc';
        @endphp
        @foreach($cols as $c)
          @php $col = $sortMap[$c] ?? null; @endphp
          @if($col)
            @php
              $on   = $curSort === $col;
              $next = $on && $curDir === 'asc' ? 'desc' : 'asc';
              $url  = url()->current() . '?' . http_build_query(array_merge(request()->except(['page','sort','dir']),
                        ['sort' => $col, 'dir' => $next]));
            @endphp
            <th @if($on) aria-sort="{{ $curDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
              <a href="{{ $url }}" class="th-sort {{ $on ? 'on' : '' }}"
                 title="ترتيب بـ{{ $c }} {{ $next === 'asc' ? 'تصاعدي' : 'تنازلي' }}">
                {{ $c }}
                <i class="bi {{ $on ? ($curDir === 'asc' ? 'bi-sort-up-alt' : 'bi-sort-down') : 'bi-arrow-down-up' }}"
                   aria-hidden="true"></i>
              </a>
            </th>
          @else
            <th>{{ $c }}</th>
          @endif
        @endforeach
        <th style="width:84px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          {!! $rowRenderer($r) !!}
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route($editRoute, $r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح المستند" aria-label="فتح المستند"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            @if(!empty($printRoute))
              <a href="{{ route($printRoute, $r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary btn-icon"
                 title="طباعة" aria-label="طباعة المستند"><i class="bi bi-printer" aria-hidden="true"></i></a>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="{{ count($cols) + 1 }}">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $emptyText ?? 'مفيش مستندات مطابقة للفلاتر.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو توسّع مدى التاريخ.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @elseif(!empty($createRoute) && Route::has($createRoute))
              <a href="{{ route($createRoute) }}" class="btn btn-sm btn-plum mt-2">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ $createLabel ?? 'ابدأ واحد جديد' }}</a>
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
    @if(!empty($footNote))<div class="hint">{{ $footNote }}</div>@endif
  </div>
</div>
@endsection
