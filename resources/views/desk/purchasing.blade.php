@extends('partials.desk')
@php
  $flow='buy'; $flowStep='sourcing';
  $intro = 'الطلبات بتنزل عندك <b>أول ما التخطيط يحفظها</b>. تحدد المورد والسعر وتاريخ التوريد وتحفظ — الحسابات والمخزن بيشوفوها فورًا.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>طلبات اتسعّرت ومستنية التوريد</span>
    <a href="{{ route('purchasing.queue') }}" class="btn btn-sm btn-outline-plum py-0">طابور التسعير</a>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>الطلب</th><th>المورد</th><th>الإجمالي</th><th>توريد متوقع</th><th>الحالة</th></tr></thead>
    <tbody>
    @forelse($priced as $r)
      @php $late = $r->delivery_date && $r->delivery_date->isBefore(today()); @endphp
      <tr class="{{ $late ? 'table-warning' : '' }}">
        <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
        <td>{{ $r->supplier?->name ?? '—' }}</td>
        <td class="num">{{ $r->total > 0 ? number_format((float)$r->total,0) : '—' }}</td>
        <td class="num {{ $late ? 'text-danger fw-bold' : '' }}">
          {{ $r->delivery_date?->format('Y-m-d') ?? '—' }}{{ $late ? ' — متأخر' : '' }}</td>
        <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
      </tr>
    @empty
      <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش طلبات مستنية توريد.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
