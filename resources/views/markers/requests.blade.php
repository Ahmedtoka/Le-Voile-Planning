@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex gap-2 align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="ms-auto d-flex gap-2" method="get">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
    </form>
    <a href="{{ route('markers.requests.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> طلب ماركر</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>رقم الطلب</th><th>التاريخ</th><th>الحوض</th><th>المصنع</th><th>عرض القماش</th>
        <th>المطلوب</th><th>الباترونست</th><th>مطلوب في</th><th>الماركر</th><th>الحالة</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold">{{ $r->doc_no }}</td>
          <td class="num">{{ $r->doc_date?->format('Y-m-d') }}</td>
          <td class="num">{{ $r->consignment?->consignment_no ?? '—' }}</td>
          <td>{{ $r->factory?->name ?? '—' }}</td>
          <td class="num fw-bold">{{ $r->fabric_width_cm }}</td>
          <td class="hint">{{ Str::limit($r->requested_models, 60) }}</td>
          <td>{{ $r->patternist?->name ?? '—' }}</td>
          <td class="num">{{ $r->needed_by?->format('Y-m-d') ?? '—' }}</td>
          <td class="num">
            @if($r->marker)<a href="{{ route('markers.edit',$r->marker) }}">{{ $r->marker->code }}</a>
            @else <a href="{{ route('markers.create', ['request_id'=>$r->id]) }}" class="btn btn-sm btn-outline-plum py-0">ارفع الماركر</a>@endif
          </td>
          <td><span class="badge bg-{{ $r->status==='delivered'?'success':($r->status==='cancelled'?'secondary':'warning') }}">{{ $r->status_name }}</span></td>
        </tr>
      @empty
        <tr><td colspan="10" class="text-center text-muted py-4">مفيش طلبات ماركر.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
