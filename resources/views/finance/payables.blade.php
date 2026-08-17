@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  الشاشة دي <b>متابعة بس — مفيش أي إجراء مطلوب منك</b>. أول ما المشتريات تسعّر طلب،
  الترانزاكشن بتنزل هنا تلقائيًا: المستحق، المورد، طريقة الدفع، وتاريخ التوريد.
  تابع الرصيد وصفّيه دفعات أو كاش حسب الاتفاق.
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="stat">
    <div class="v">{{ $summary['priced_today'] }}</div>
    <div class="l">اتسعّرت النهارده</div></div></div>
  <div class="col-md-4"><div class="stat">
    <div class="v num">{{ number_format($summary['total'], 0) }}</div>
    <div class="l">إجمالي المستحق المتوقع ({{ config('lvplanning.currency') }})</div></div></div>
  <div class="col-md-4"><div class="stat">
    <div class="v num">{{ number_format($summary['due_30'], 0) }}</div>
    <div class="l">توريد خلال 30 يوم</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex gap-2 align-items-center">
        <span>{{ $title }}</span>
        <form class="ms-auto d-flex gap-2" method="get">
          <select name="supplier_id" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
            <option value="">كل الموردين</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
          </select>
          <select name="stage" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
            <option value="">كل المراحل</option>
            @foreach($stages as $k=>$v)<option value="{{ $k }}" @selected(request('stage')===$k)>{{ $v }}</option>@endforeach
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>رقم الطلب</th><th>المورد</th><th>تاريخ التوريد</th><th>الإجمالي</th>
            <th>طريقة الدفع</th><th>المرحلة</th><th></th></tr></thead>
          <tbody>
          @forelse($rows as $r)
            <tr class="{{ $r->stage === 'finance' ? 'table-warning' : '' }}">
              <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
              <td>{{ $r->supplier?->name }}</td>
              <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
              <td class="num fw-bold">{{ number_format((float)$r->total, 2) }}</td>
              <td class="hint">{{ $r->payment_method ?: '—' }}</td>
              <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
              <td>
                <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum py-0"
                   aria-label="عرض" title="عرض"><i class="bi bi-eye" aria-hidden="true"></i></a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">مفيش مستحقات متوقعة.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">{{ $rows->links() }}</div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">حسب المورد</div>
      <table class="table table-sm mb-0">
        <thead><tr><th>المورد</th><th>طلبات</th><th>الإجمالي</th></tr></thead>
        <tbody>
        @forelse($bySupplier as $s)
          <tr>
            <td>{{ $s->supplier?->name ?? '—' }}</td>
            <td class="num">{{ $s->orders }}</td>
            <td class="num fw-bold">{{ number_format((float)$s->total, 0) }}</td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
