@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex gap-2 align-items-center">
    <span>{{ $title }}</span>
    <form class="ms-auto d-flex gap-2" method="get">
      <select name="user_id" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
        <option value="">كل المستخدمين</option>
        @foreach($users as $k=>$v)<option value="{{ $k }}" @selected(request('user_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="action" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الأحداث</option>
        @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
      </select>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>التاريخ</th><th>المستخدم</th><th>الحدث</th><th>الوصف</th><th>IP</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num hint">{{ $r->created_at->format('Y-m-d H:i') }}</td>
          <td>{{ $r->user?->name ?? '—' }}</td>
          <td><span class="badge bg-light text-dark">{{ $r->action }}</span></td>
          <td>{{ $r->title }}</td>
          <td class="num hint">{{ $r->ip }}</td>
        </tr>
      @empty
        <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش حركة مسجلة.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
