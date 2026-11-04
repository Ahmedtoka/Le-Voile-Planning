@extends('layouts.app')
@section('purpose', 'أمر الشغل بيتبني على طلب تحضير اتوزن وماركر معتمد — اختار الطلب والباقي كله بينزل لوحده')
@section('content')
@php
  /* جاهز = اتوزن وله ماركر معتمد ينفع على عرض الرسالة. الباقي بيظهر تحت بسبب وقوفه. */
  $markerOf = fn ($r) => $r->markerRequest?->marker;
  $isReady  = fn ($r) => (bool) $r->is_ready;
  $ready    = $requests->filter($isReady);
  $waiting  = $requests->reject($isReady);
  $models   = fn ($r) => $r->models->isNotEmpty() ? $r->models->pluck('name')->implode('، ') : ($r->consignment?->purchaseOrder?->models_label ?: '—');
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الترتيب زي ما اتفقنا: <b>طلب تحضير</b> (والماركر معاه) ⇐ <b>المخزن يوزن</b> ⇐ <b>الباترونيست يسلّم الماركر</b> ⇐ <b>أمر الشغل</b>.
  اختار الطلب والأمر بيتملى لوحده: الوزن الفعلي من المخزن، وطول الفرشة والقطع من الماركر، والعرض والبنشر من الجودة، والموديلات والمقاسات من الماركر.
  إنت بتختار <b>المصنع وتاريخ التسليم</b> بس.</span>
</div>

@if($notReady)
  <div class="alert alert-warning">
    طلب التحضير <b class="num">{{ $notReady->doc_no }}</b> لسه مش جاهز لأمر شغل —
    {{ $notReady->status !== 'weighed' ? 'المخزن لسه ما وزنهوش.' : 'مفيش ماركر معتمد ينفع على عرض الرسالة.' }}
  </div>
@endif

<div class="card mb-3">
  <div class="card-header">
    <span>جاهز لأمر شغل <span class="pill pill-ok num">{{ $ready->count() }}</span>
      <span class="hint">— اتوزن وله ماركر معتمد</span></span>
    <a href="{{ route('preparation-requests.create') }}" class="btn btn-sm btn-outline-plum ms-auto">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> طلب تحضير جديد</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm doc-table mb-0">
      <thead><tr><th>طلب التحضير</th><th>الرسالة</th><th>الوزن الفعلي</th><th>الماركر</th><th>الموديلات</th><th>المصنع</th><th></th></tr></thead>
      <tbody>
      @forelse($ready as $r)
        @php $c = $r->consignment; $mk = $r->readyMarker; @endphp
        <tr class="{{ (string) $focus === (string) $c->id ? 'table-active' : '' }}">
          <td class="num fw-bold">{{ $r->doc_no }}<div class="hint">{{ $r->weighed_at?->format('Y-m-d') }}</div></td>
          <td><span class="num fw-bold">{{ $c->consignment_no }}</span>
            <div class="hint">{{ $c->fabricType?->name }} · {{ $c->color?->code }}</div></td>
          <td class="num fw-bold text-success">{{ $c->is_meter ? lv_num($r->qty_for_work_order, 2) . ' م' : lv_kg($r->qty_for_work_order) }}</td>
          <td><span class="num">{{ $mk->code }}</span>
            <div class="hint">طول {{ lv_num($mk->spread_length_m, 2) }} م · {{ (int) $mk->pieces_per_spread }} قطعة في الفرشة</div></td>
          <td class="hint">{{ $models($r) }}</td>
          <td class="hint">{{ $r->factory?->name ?? 'هيتحدد في الأمر' }}</td>
          <td class="text-end"><a href="{{ route('work-orders.create', ['preparation_request_id' => $r->id]) }}" class="btn btn-sm btn-plum">
            <i class="bi bi-hammer" aria-hidden="true"></i> افتح أمر شغل</a></td>
        </tr>
      @empty
        <tr><td colspan="7">
          <div class="empty-state">
            <i class="bi bi-hourglass-split ico" aria-hidden="true"></i>
            <div class="t">مفيش طلب تحضير جاهز لأمر شغل دلوقتي.</div>
            <div class="hint">ابدأ بطلب تحضير على الرسالة (وطلب الماركر معاه)، والمخزن يوزن والباترونيست يسلّم الماركر.</div>
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($waiting->isNotEmpty())
<div class="card">
  <div class="card-header"><span>لسه مستني <span class="pill pill-warn num">{{ $waiting->count() }}</span>
    <span class="hint">— أمر الشغل بيتفتح أول ما الاتنين يخلصوا</span></span></div>
  <div class="table-responsive">
    <table class="table table-sm doc-table mb-0">
      <thead><tr><th>طلب التحضير</th><th>الرسالة</th><th>الكمية</th><th>وزن المخزن</th><th>الماركر</th><th>الموديلات</th></tr></thead>
      <tbody>
      @foreach($waiting as $r)
        @php $c = $r->consignment; $mr = $r->markerRequest; $mk = $markerOf($r); @endphp
        <tr>
          <td class="num fw-bold"><a href="{{ route('preparation-requests.index') }}">{{ $r->doc_no }}</a></td>
          <td><span class="num">{{ $c->consignment_no }}</span><div class="hint">{{ $c->fabricType?->name }} · {{ $c->color?->code }}</div></td>
          <td class="num">{{ lv_num($r->status === 'weighed' ? $r->qty_for_work_order : $r->requested_qty) }}
            <div class="hint">{{ $r->status === 'weighed' ? 'فعلي' : 'تقريبي' }}</div></td>
          <td>@if($r->status === 'weighed')<span class="pill pill-ok">اتوزن</span>@else<span class="pill pill-warn">مستني المخزن</span>@endif</td>
          <td>
            @if($mk?->status === 'approved')<span class="pill pill-ok">{{ $mk->code }}</span>
            @elseif($mk)<span class="pill pill-warn">{{ $mk->code }} — لسه ما اتعتمدش</span>
            @elseif($mr)<span class="pill pill-warn">مستني الباترونيست</span>
            @else<span class="pill pill-muted">مفيش طلب ماركر</span>@endif
          </td>
          <td class="hint">{{ $models($r) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
