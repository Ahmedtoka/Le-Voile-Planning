@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:150px" placeholder="رقم الطلب…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="supplier_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> طلب شراء</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الطلب</th><th>التاريخ</th><th>المورد</th><th>الكمية</th><th>الإجمالي</th><th>الحالة</th><th></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold">{{ $r->po_no }}</td>
          <td class="num">{{ $r->po_date?->format('Y-m-d') }}</td>
          <td>{{ $r->supplier?->name ?? '—' }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float)$r->total_qty,3),'0'),'.') }}</td>
          <td class="num">{{ number_format((float)$r->total, 2) }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_label }}</span></td>
          <td class="text-nowrap">
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-pencil"></i></a>
            <a href="{{ route('purchase-orders.print',$r) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i></a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">مفيش طلبات شراء.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
