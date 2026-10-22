@extends('layouts.app')
@section('purpose', 'الطلب وأصنافه ومرحلته، والكمية اللي اتستلمت منه لحد دلوقتي')
@section('content')
@php $u = auth()->user(); $cur = config('lvplanning.currency'); @endphp

@include('partials.flow_bar', ['flow' => 'buy', 'step' => ''])

@include('partials.po_stepper')

<div class="row g-3 mb-3">
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->stage_name, 'label'=>'حالة الطلب', 'tone'=>'brand'])
  </div>
  <div class="col-md-3">
    @include('partials.kpi', ['value'=>$row->total > 0 ? number_format((float)$row->total, 0) : '—',
      'label'=>'الإجمالي بعد الضريبة', 'sub'=>$cur,
      'note'=>$row->total > 0 ? 'قبل الخصم ' . number_format((float)$row->subtotal, 2) . ' − خصم ' . number_format((float)$row->discount_value, 2) . ' + ضريبة ' . number_format((float)$row->tax_value, 2) : null])
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
  <div class="alert alert-warning d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
    <span>الطلب مستني تسعير منك.</span>
    <a href="{{ route('purchasing.source', $row) }}" class="btn btn-sm btn-plum ms-auto">سعّر الطلب</a>
  </div>
@elseif($row->needsFinanceAck() && $u->can2('po.finance'))
  <div class="alert alert-info d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span>الطلب اتسعّر ونزل للحسابات للمتابعة — الاستلام ماشي عادي، ده تسجيل علم بس.</span>
    <a href="{{ route('finance.ack', $row) }}" class="btn btn-sm btn-plum ms-auto">سجّل علمك</a>
  </div>
@endif

<div class="doc-actions">
  <span class="da-label">إجراءات</span>
  <a href="{{ route('purchase-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-printer" aria-hidden="true"></i> اطبع</a>
  <div class="da-end">
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع للطلبات</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">الأصناف المطلوبة</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>م</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الوحدة</th>
            <th>الزيادة (%)</th><th>سعر الوحدة ({{ $cur }})</th><th>الإجمالي ({{ $cur }})</th><th>مستلم</th><th>باقي</th></tr></thead>
          <tbody>
          @foreach($row->lines as $i => $l)
            @php $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty); @endphp
            <tr>
              <td class="text-center">{{ $i+1 }}</td>
              <td>{{ $l->fabricType?->name }}
                @if($l->notes)<div class="hint">{{ $l->notes }}</div>@endif</td>
              <td>{{ $l->color?->name }}</td>
              <td class="num">{{ lv_num($l->qty, 3) }}</td>
              <td>{{ $l->unit }}</td>
              <td class="num">{{ lv_num($l->tolerance_pct, 2) }}%
                @include('partials.fx', ['t' => 'أقصى كمية مسموح استلامها', 'f' => lv_num($l->qty, 3) . ' × (1 + ' . lv_num($l->tolerance_pct, 2) . '%) = ' . lv_num($l->max_allowed_qty, 3) . ' ' . $l->unit, 'why' => 'الزيادة بتسمح بالأكتر بس — الأقل بيفضل باقي مفتوح'])</td>
              <td class="num">{{ $l->unit_price > 0 ? number_format((float)$l->unit_price,2) : '—' }}</td>
              <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total,2) : '—' }}
                @if($l->line_total > 0)
                  @include('partials.fx', ['t' => 'إجمالي السطر', 'f' => 'الكمية ' . lv_num($l->qty, 3) . ' ' . $l->unit . ' × سعر الوحدة ' . number_format((float)$l->unit_price, 2) . ' = ' . number_format((float)$l->line_total, 2) . ' ' . $cur])
                @endif
              </td>
              <td class="num">{{ $l->received_qty > 0 ? lv_num($l->received_qty, 3) : '—' }}</td>
              <td class="num {{ $left > 0 ? 'text-danger fw-bold' : 'text-success' }}">
                {{ $left > 0 ? lv_num($left, 3) : 'اكتمل' }}
                @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . lv_num($l->min_allowed_qty, 3) . ' − الواصل ' . lv_num($l->received_qty, 3) . ' = ' . lv_num($left, 3) . ' ' . $l->unit])
                @if($l->is_closed)<i class="bi bi-lock-fill" title="البند اتقفل — اكتمل استلامه" aria-label="مقفول"></i>@endif</td>
            </tr>
          @endforeach
          </tbody>
          @if($row->total > 0)
            <tfoot>
              <tr><td colspan="7" class="text-start">قبل الخصم</td><td class="num">{{ number_format((float)$row->subtotal,2) }}</td><td colspan="2"></td></tr>
              <tr><td colspan="7" class="text-start">الخصم {{ lv_num($row->discount_pct, 2) }}%</td>
                <td class="num">{{ number_format((float)$row->discount_value,2) }}
                  @include('partials.fx', ['t' => 'قيمة الخصم', 'f' => number_format((float)$row->subtotal, 2) . ' × ' . lv_num($row->discount_pct, 2) . '% = ' . number_format((float)$row->discount_value, 2) . ' ' . $cur])</td><td colspan="2"></td></tr>
              <tr><td colspan="7" class="text-start">الضريبة {{ lv_num($row->tax_pct, 2) }}%</td>
                <td class="num">{{ number_format((float)$row->tax_value,2) }}
                  @include('partials.fx', ['t' => 'قيمة الضريبة', 'f' => '(' . number_format((float)$row->subtotal, 2) . ' − ' . number_format((float)$row->discount_value, 2) . ') × ' . lv_num($row->tax_pct, 2) . '% = ' . number_format((float)$row->tax_value, 2) . ' ' . $cur])</td><td colspan="2"></td></tr>
              <tr><td colspan="7" class="text-start">الإجمالي بعد الضريبة</td>
                <td class="num">{{ number_format((float)$row->total,2) }}
                  @include('partials.fx', ['t' => 'الإجمالي بعد الضريبة', 'f' => number_format((float)$row->subtotal, 2) . ' − خصم ' . number_format((float)$row->discount_value, 2) . ' + ضريبة ' . number_format((float)$row->tax_value, 2) . ' = ' . number_format((float)$row->total, 2) . ' ' . $cur])</td><td colspan="2"></td></tr>
            </tfoot>
          @endif
        </table>
        @if($row->remainder_eta && false)
          <div class="card-footer bg-white hint">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            الباقي متوقع يوصل <b class="num">{{ $row->remainder_eta->format('Y-m-d') }}</b>
            @if($row->remainder_eta->isBefore(today()))<span class="text-danger fw-bold">— الميعاد فات</span>@endif
            <span>· اتسجّل مع آخر إذن إضافة.</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">مسار الطلب</div>
      <div class="table-responsive">
        <table class="table table-sm kv mb-0">
          <tr><th>طلبه</th>
              <td>{{ $row->requester?->name ?? '—' }} <span class="hint num">{{ $row->requested_at?->format('Y-m-d H:i') }}</span>
                @if($row->planning_note)<div class="hint">«{{ $row->planning_note }}»</div>@endif
                @if($row->models_label)<div class="hint">للموديلات: <b>{{ $row->models_label }}</b></div>@endif
                <div class="hint">نوع الاستلام: <b>{{ $row->inspection_exempt ? 'استلام مباشر بدون فحص' : 'يدخل فحص' }}</b></div></td></tr>
          <tr><th>سعّره</th>
              <td>{{ $row->sourcer?->name ?? '— لسه' }} <span class="hint num">{{ $row->sourced_at?->format('Y-m-d H:i') }}</span></td></tr>
          <tr><th>علمت الحسابات</th>
              <td>{{ $row->financer?->name ?? '— لسه' }} <span class="hint num">{{ $row->finance_at?->format('Y-m-d H:i') }}</span>
                @if($row->finance_note)<div class="hint">«{{ $row->finance_note }}»</div>@endif</td></tr>
          <tr><th>المورد</th><td>{{ $row->supplier?->name ?? '—' }}</td></tr>
          <tr><th>طريقة الدفع</th><td>{{ $row->payment_method ?? '—' }}</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

@if($row->stage !== 'planning' && $row->stage !== 'purchasing')
  @include('partials.po_deliveries', ['po' => $row])
@endif

@endsection
