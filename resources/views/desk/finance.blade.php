@extends('partials.desk')
@section('purpose', 'متابعة المستحق المتوقع للموردين — متابعة مش اعتماد')
@php
  $flow='buy'; $flowStep='finance';
  $intro = 'دورك <b>متابعة</b> — مفيش اعتماد. بتشوف المستحق المتوقع لكل مورد وتاريخ التوريد، وتتابع الرصيد وتصفّيه دفعات أو كاش.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header">
    <span>المستحق المتوقع للموردين</span>
    <a href="{{ route('finance.payables') }}" class="btn btn-sm btn-outline-plum">الكل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr>
      <th>الطلب</th><th>المورد</th><th class="num">الإجمالي ({{ config('lvplanning.currency') }})</th>
      <th>طريقة الدفع</th><th class="num">توريد متوقع</th><th>الحالة</th>
      <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
    </tr></thead>
    <tbody>
    @forelse($payables as $r)
      <tr>
        <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
        <td>{{ $r->supplier?->name ?? '—' }}</td>
        <td class="num fw-bold">{{ $r->total > 0 ? number_format((float)$r->total,0) : '—' }}</td>
        <td>{{ $r->payment_method ?? '—' }}</td>
        <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
        <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
        <td class="text-end">
          <div class="row-actions">
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="افتح الطلب" aria-label="افتح الطلب {{ $r->po_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش مستحقات دلوقتي.</div>
        <div class="hint">أول ما المشتريات تسعّر طلب هيظهر هنا بالمبلغ وتاريخ التوريد.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
