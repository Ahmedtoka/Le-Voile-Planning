@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle"></i>
  أمر الشغل بيربط <b>حوض واحد + ماركر واحد + مصنع واحد</b>. الأرقام المتوقعة فيه محسوبة من أقل عرض
  ومتوسط البنشر وطول الفرشة. الفعلي بييجي من بيان القص، والفرق الطبيعي 2-4%.
</div>

@include('partials.summary')
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:140px" placeholder="رقم الأمر…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="factory_id" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">كل المصانع</option>
        @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(request('factory_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="late" value="1" id="lt" @checked(request('late')) onchange="this.form.submit()">
        <label class="form-check-label small" for="lt">المتأخر بس</label>
      </div>
            <div class="d-flex align-items-center gap-1">
        <span class="hint">من</span>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:132px">
        <span class="hint">إلى</span>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:132px">
      </div>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    <a href="{{ route('io.export.work-orders') }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i></a>
    <a href="{{ route('work-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> أمر شغل</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>رقم الأمر</th><th>التاريخ</th><th>الحوض</th><th>اللون</th><th>المصنع</th><th>كجم</th>
        <th>متوقع</th><th>مقصوص</th><th>مستلم</th><th>متبقي</th><th>الانحراف</th><th>التسليم</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr class="{{ $r->is_late ? 'table-warning' : '' }}">
          <td class="num fw-bold"><a href="{{ route('work-orders.show',$r) }}">{{ $r->wo_no }}</a></td>
          <td class="num">{{ $r->wo_date?->format('Y-m-d') }}</td>
          <td class="num">{{ $r->consignment?->consignment_no ?? '—' }}</td>
          <td>{{ $r->consignment?->color?->code ?? '—' }}</td>
          <td>{{ $r->factory?->name ?? '—' }}</td>
          <td class="num">{{ number_format((float)$r->allocated_kg,1) }}</td>
          <td class="num">{{ number_format((int)$r->expected_pieces) }}</td>
          <td class="num">{{ number_format((int)$r->cut_pieces) }}</td>
          <td class="num">{{ number_format((int)$r->received_pieces) }}</td>
          <td class="num">{{ number_format($r->outstanding_pieces) }}</td>
          <td class="num">
            @if($r->variance_pct !== null)
              <span class="badge bg-{{ ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$r->variance_flag] ?? 'secondary' }}">
                {{ $r->variance_pct }}%
              </span>
            @else — @endif
          </td>
          <td class="num {{ $r->is_late ? 'text-danger fw-bold' : '' }}">{{ $r->due_date?->format('Y-m-d') ?? '—' }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td><a href="{{ route('work-orders.show',$r) }}" class="btn btn-sm btn-outline-plum py-0" aria-label="عرض" title="عرض"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
        </tr>
      @empty
        <tr><td colspan="14" class="text-center text-muted py-4">مفيش أوامر شغل.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
