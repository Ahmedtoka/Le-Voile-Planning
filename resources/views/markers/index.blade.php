@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'marker'])
<div class="card">
  <div class="card-header d-flex gap-2 align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="ms-auto d-flex gap-2" method="get">
      <input type="number" step="0.01" name="width" value="{{ request('width') }}" class="form-control form-control-sm"
             style="width:170px" placeholder="ينفع على عرض…">
      <select name="status" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach(['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي'] as $k=>$v)
          <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    <a href="{{ route('markers.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> ماركر</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>الكود</th><th>الاسم</th><th>المصنع</th><th>عرض القماش</th><th>طول الفرشة</th>
        <th>قطع الفرشة</th><th>الموديلات</th><th>الباترونست</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold">{{ $r->code }}</td>
          <td>{{ $r->name ?: '—' }}</td>
          <td>{{ $r->factory?->name ?? '—' }}</td>
          <td class="num">{{ $r->fabric_width_cm }}</td>
          <td class="num">{{ $r->spread_length_m }}</td>
          <td class="num fw-bold">{{ $r->pieces_per_spread }}</td>
          <td class="hint">
            @foreach($r->lines as $l)
              {{ $l->productModel?->name }}@if($l->size)/{{ $l->size->name }}@endif ×{{ $l->qty_per_spread }}@if(!$loop->last) · @endif
            @endforeach
          </td>
          <td>{{ $r->patternist?->name ?? '—' }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_label }}</span></td>
          <td><a href="{{ route('markers.edit',$r) }}" class="btn btn-sm btn-outline-plum py-0" aria-label="تعديل" title="تعديل"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
        </tr>
      @empty
        <tr><td colspan="10">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش ماركرات.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
