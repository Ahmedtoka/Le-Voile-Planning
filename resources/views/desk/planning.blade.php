@extends('partials.desk')
@section('purpose', 'تطلب الخامة، تحوّل الأحواض المفرج عنها لأوامر شغل، وتتابع التغطية')
@php
  $flow='buy'; $flowStep='request';
  $intro = 'إنت بتبدأ الدورة: تطلب الخامة، وتحوّل الرسايل المفرَج عنها لأوامر شغل. الطلب بينزل للمشتريات <b>أول ما تحفظه</b> — مفيش اعتماد.';
@endphp

@section('tracking')

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <span>آخر طلبات الشراء</span>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-plum">الكل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0 no-min">
        <thead><tr>
          <th>الطلب</th><th>الحالة</th><th>المورد</th><th class="num">توريد متوقع</th>
          <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
        </tr></thead>
        <tbody>
        @forelse($myOrders as $r)
          <tr>
            <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
            <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
            <td>{{ $r->supplier?->name ?? '— لسه' }}</td>
            <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
            <td class="text-end">
              <div class="row-actions">
                <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
                   title="افتح الطلب" aria-label="افتح الطلب {{ $r->po_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش طلبات لسه.</div>
            <div class="hint">ابدأ بـ«طلب شراء جديد» من فوق.</div></div></td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <span>أوامر الشغل المفتوحة</span>
        <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-plum">الكل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0 no-min">
        <thead><tr>
          <th>الأمر</th><th>المنتج</th><th>المصنع</th><th class="num">متبقي</th>
          <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
        </tr></thead>
        <tbody>
        @forelse($openWos as $w)
          <tr class="{{ $w->is_late ? 'table-warning' : '' }}">
            <td class="num fw-bold">
              <a href="{{ route('work-orders.show',$w) }}">{{ $w->wo_no }}</a>
              @if($w->is_late)<div class="status is-danger">متأخر</div>@endif
            </td>
            <td>{{ Str::limit($w->product_title, 24) }}</td>
            <td>{{ $w->factory?->name ?? '—' }}</td>
            <td class="num">{{ number_format($w->outstanding_pieces) }}</td>
            <td class="text-end">
              <div class="row-actions">
                <a href="{{ route('work-orders.show',$w) }}" class="btn btn-sm btn-outline-plum btn-icon"
                   title="افتح أمر الشغل" aria-label="افتح أمر الشغل {{ $w->wo_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش أوامر مفتوحة.</div>
            <div class="hint">لما حوض يتفرج عنه، اعمل عليه «أمر شغل جديد».</div></div></td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
@endsection
