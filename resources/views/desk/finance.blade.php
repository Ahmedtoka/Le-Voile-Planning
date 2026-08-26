@extends('partials.desk')
@php
  $flow='buy'; $flowStep='finance';
  $intro = 'دورك <b>متابعة</b> — مفيش اعتماد. بتشوف المستحق المتوقع لكل مورد وتاريخ التوريد، وتتابع الرصيد وتصفّيه دفعات أو كاش.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>المستحق المتوقع للموردين</span>
    <a href="{{ route('finance.payables') }}" class="btn btn-sm btn-outline-plum py-0">الشاشة الكاملة</a>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>الطلب</th><th>المورد</th><th>الإجمالي</th><th>طريقة الدفع</th><th>توريد متوقع</th><th>الحالة</th></tr></thead>
    <tbody>
    @forelse($payables as $r)
      <tr>
        <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
        <td>{{ $r->supplier?->name ?? '—' }}</td>
        <td class="num fw-bold">{{ $r->total > 0 ? number_format((float)$r->total,0) : '—' }}</td>
        <td>{{ $r->payment_method ?? '—' }}</td>
        <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
        <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
      </tr>
    @empty
      <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش مستحقات دلوقتي.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
