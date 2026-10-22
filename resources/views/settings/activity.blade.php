@extends('layouts.app')
@section('content')
@php $hasFilter = request()->filled('user_id') || request()->filled('action'); @endphp
<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="hint">({{ number_format($rows->total()) }})</span></span>
    <form class="filter-bar" method="get" role="search" aria-label="فلترة سجل الحركة">
      <label class="visually-hidden" for="act-user">المستخدم</label>
      <select id="act-user" name="user_id" class="form-select" onchange="this.form.submit()">
        <option value="">كل المستخدمين</option>
        @foreach($users as $k=>$v)<option value="{{ $k }}" @selected(request('user_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <label class="visually-hidden" for="act-action">الحدث</label>
      <select id="act-action" name="action" class="form-select" onchange="this.form.submit()">
        <option value="">كل الأحداث</option>
        @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilter)
        <a class="btn btn-sm btn-ghost" href="{{ url()->current() }}" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th class="num">التاريخ</th><th>المستخدم</th><th>الحدث</th><th>الوصف</th><th class="num">IP</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num hint text-nowrap">{{ $r->created_at->format('Y-m-d H:i') }}</td>
          <td>{{ $r->user?->name ?? '—' }}</td>
          <td><span class="badge bg-secondary">{{ $r->action }}</span></td>
          <td>{{ $r->title }}</td>
          <td class="num hint">{{ $r->ip }}</td>
        </tr>
      @empty
        <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-journal-text ico" aria-hidden="true"></i>
              <div class="t">مفيش حركة مسجلة{{ $hasFilter ? ' بالفلتر ده' : '' }}.</div>
              @if($hasFilter)<div class="hint"><a href="{{ url()->current() }}">شيل الفلاتر</a> وشوف كل الحركة.</div>@endif
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->withQueryString()->links() }}</div>
</div>
@endsection
