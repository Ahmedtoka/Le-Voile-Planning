@extends('layouts.app')
@section('content')

@if(!empty($intro))
  <div class="note-box mb-3"><i class="bi bi-info-circle"></i> {!! $intro !!}</div>
@endif

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>

    <form class="d-flex gap-2 flex-wrap ms-auto align-items-center" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:165px"
             placeholder="{{ $searchHint ?? 'بحث برقم المستند…' }}">

      @foreach(($filters ?? []) as $f)
        <select name="{{ $f['name'] }}" class="form-select form-select-sm" style="width:{{ $f['width'] ?? 150 }}px"
                onchange="this.form.submit()">
          <option value="">{{ $f['label'] }}</option>
          @foreach($f['options'] as $k => $v)
            <option value="{{ $k }}" @selected((string) request($f['name']) === (string) $k)>{{ $v }}</option>
          @endforeach
        </select>
      @endforeach

      @if(($dateFilter ?? true))
        <div class="d-flex align-items-center gap-1">
          <span class="hint">من</span>
          <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:135px">
          <span class="hint">إلى</span>
          <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:135px">
        </div>
      @endif

      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
      @if(collect(request()->except('page'))->filter()->isNotEmpty())
        <a href="{{ url()->current() }}" class="btn btn-sm btn-link text-muted p-0" style="font-size:.78rem">مسح الفلاتر</a>
      @endif
    </form>

    @if(!empty($createRoute) && Route::has($createRoute))
      <a href="{{ route($createRoute) }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> {{ $createLabel }}</a>
    @endif
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>@foreach($cols as $c)<th>{{ $c }}</th>@endforeach<th style="width:90px"></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          {!! $rowRenderer($r) !!}
          <td class="text-nowrap">
            <a href="{{ route($editRoute, $r) }}" class="btn btn-sm btn-outline-plum py-0" title="فتح"><i class="bi bi-pencil"></i></a>
            @if(!empty($printRoute))
              <a href="{{ route($printRoute, $r) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="طباعة"><i class="bi bi-printer"></i></a>
            @endif
            @if(method_exists($r, 'commentsCount') && $r->commentsCount())
              <span class="badge bg-light text-dark" title="فيه نقاش على المستند">
                <i class="bi bi-chat-dots"></i> {{ $r->commentsCount() }}
              </span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="20" class="text-center text-muted py-4">
          {{ $emptyText ?? 'مفيش مستندات مطابقة للفلاتر.' }}
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    @if(!empty($footNote))<div class="hint">{{ $footNote }}</div>@endif
  </div>
</div>
@endsection
