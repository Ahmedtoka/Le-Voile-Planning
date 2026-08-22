@extends('layouts.app')
@section('content')
@php $u = auth()->user(); @endphp

@include('partials.po_stepper')

<div class="row g-3 mb-3">
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->stage_name, 'label'=>'حالة الطلب', 'tone'=>'brand'])
  </div>
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->total > 0 ? number_format((float)$row->total, 0) : '—',
      'label'=>'الإجمالي بعد الضريبة', 'sub'=>config('lvplanning.currency')])
  </div>
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->delivery_date?->format('Y-m-d') ?? '—', 'label'=>'متوقع وصوله',
      'tone'=>($row->delivery_date && $row->delivery_date->isPast() && in_array($row->stage,['approved','receiving'],true)) ? 'danger' : 'ink'])
  </div>
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->requester?->name ?? '—', 'label'=>'طلبه',
      'note'=>$row->po_date?->format('Y-m-d')])
  </div>
</div>

{{-- زرار الدور اللي عليه الدور --}}
@if($row->stage === 'purchasing' && $u->can2('po.source'))
  <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
    <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
    الطلب مستني تسعير منك.
    <a href="{{ route('purchasing.source', $row) }}" class="btn btn-sm btn-plum ms-auto">سعّر الطلب</a>
  </div>
@elseif($row->stage === 'finance' && $u->can2('po.finance'))
  <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
    <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
    الطلب مستني علم الحسابات.
    <a href="{{ route('finance.ack', $row) }}" class="btn btn-sm btn-plum ms-auto">افتح صفحة العلم</a>
  </div>
@endif

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">الأصناف المطلوبة</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>م</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الوحدة</th>
            <th>نسبة الزيادة</th><th>سعر الوحدة</th><th>الإجمالي</th><th>مستلم</th></tr></thead>
          <tbody>
          @foreach($row->lines as $i => $l)
            <tr>
              <td class="text-center">{{ $i+1 }}</td>
              <td>{{ $l->fabricType?->name }}
                @if($l->notes)<div class="hint">{{ $l->notes }}</div>@endif</td>
              <td>{{ $l->color?->name }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty,3),'0'),'.') }}</td>
              <td>{{ $l->unit }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$l->tolerance_pct,2),'0'),'.') }}%</td>
              <td class="num">{{ $l->unit_price > 0 ? number_format((float)$l->unit_price,2) : '—' }}</td>
              <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total,2) : '—' }}</td>
              <td class="num">{{ $l->received_qty > 0 ? rtrim(rtrim(number_format((float)$l->received_qty,3),'0'),'.') : '—' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">مسار الطلب</div>
      <table class="table table-sm mb-0">
        <tr><th style="width:130px">طلبه</th>
            <td>{{ $row->requester?->name ?? '—' }} <span class="hint">{{ $row->requested_at?->format('Y-m-d H:i') }}</span>
              @if($row->planning_note)<div class="hint">«{{ $row->planning_note }}»</div>@endif
              @if($row->productModel)<div class="hint">للموديل: <b>{{ $row->productModel->label }}</b></div>@endif</td></tr>
        <tr><th>سعّره</th>
            <td>{{ $row->sourcer?->name ?? '— لسه' }} <span class="hint">{{ $row->sourced_at?->format('Y-m-d H:i') }}</span></td></tr>
        <tr><th>علمت الحسابات</th>
            <td>{{ $row->financer?->name ?? '— لسه' }} <span class="hint">{{ $row->finance_at?->format('Y-m-d H:i') }}</span>
              @if($row->finance_note)<div class="hint">«{{ $row->finance_note }}»</div>@endif</td></tr>
        <tr><th>المورد</th><td>{{ $row->supplier?->name ?? '—' }}</td></tr>
        <tr><th>طريقة الدفع</th><td>{{ $row->payment_method ?? '—' }}</td></tr>
        <tr><th>مكان التسليم</th><td>{{ $row->delivery_place ?? '—' }}</td></tr>
      </table>
      <div class="card-footer bg-white d-flex gap-2">
        <a href="{{ route('purchase-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-printer" aria-hidden="true"></i> اطبع</a>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-plum">رجوع للطلبات</a>
      </div>
    </div>
  </div>
</div>

@include('partials.comments')
@endsection
