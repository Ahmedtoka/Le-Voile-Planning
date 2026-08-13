@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle"></i>
  مستند واحد بيمر على تلات أيادي: <b>التخطيط</b> يكتب الأصناف والكميات، <b>المشتريات</b> تحدد المورد
  والسعر وتاريخ التوريد، <b>الحسابات</b> تعلم بالمستحق. وبعدها الاعتماد.
  كل مرحلة بتقفل اللي قبلها عن التعديل.
</div>

@include('partials.summary')

<div class="d-flex gap-2 flex-wrap mb-3">
  <a href="{{ route('purchase-orders.index') }}"
     class="btn btn-sm {{ !request('stage') ? 'btn-plum' : 'btn-outline-plum' }}">
    الكل <span class="badge bg-light text-dark">{{ $counts->sum() }}</span>
  </a>
  @foreach($stages as $k => $v)
    @continue(($counts[$k] ?? 0) === 0 && !in_array($k, ['planning','purchasing','finance'], true))
    <a href="{{ route('purchase-orders.index', ['stage' => $k]) }}"
       class="btn btn-sm {{ request('stage') === $k ? 'btn-plum' : 'btn-outline-plum' }}">
      {{ $v }} <span class="badge bg-light text-dark">{{ $counts[$k] ?? 0 }}</span>
    </a>
  @endforeach
</div>

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input type="hidden" name="stage" value="{{ request('stage') }}">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:150px" placeholder="رقم الطلب…">
      <select name="supplier_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine')) onchange="this.form.submit()">
        <label class="form-check-label small" for="mine">طلباتي</label>
      </div>
            <div class="d-flex align-items-center gap-1">
        <span class="hint">من</span>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:132px">
        <span class="hint">إلى</span>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:132px">
      </div>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    @if(auth()->user()->can2('po.request'))
      <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> طلب شراء</a>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الطلب</th><th>التاريخ</th><th>الأصناف</th><th>الكمية</th>
        <th>المورد</th><th>تاريخ التوريد</th><th>الإجمالي</th><th>المرحلة</th><th>طلبه</th><th></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
          <td class="num">{{ $r->po_date?->format('Y-m-d') }}</td>
          <td class="num">{{ $r->lines()->count() }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float)$r->total_qty,3),'0'),'.') }}</td>
          <td>{{ $r->supplier?->name ?? '— لسه' }}</td>
          <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
          <td class="num">{{ $r->total > 0 ? number_format((float)$r->total, 2) : '—' }}</td>
          <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
          <td class="hint">{{ $r->requester?->name }}</td>
          <td class="text-nowrap">
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum py-0" aria-label="تعديل" title="تعديل"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            <a href="{{ route('purchase-orders.print',$r) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
          </td>
        </tr>
      @empty
        <tr><td colspan="10" class="text-center text-muted py-4">مفيش طلبات في المرحلة دي.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
