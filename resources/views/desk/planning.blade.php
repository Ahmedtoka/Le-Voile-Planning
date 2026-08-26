@extends('partials.desk')
@php
  $flow='buy'; $flowStep='request';
  $intro = 'إنت بتبدأ الدورة: تطلب الخامة، وتحوّل الرسايل المفرَج عنها لأوامر شغل. الطلب بينزل للمشتريات <b>أول ما تحفظه</b> — مفيش اعتماد.';
@endphp

@section('tracking')

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>آخر طلبات الشراء</span>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الطلب</th><th>الحالة</th><th>المورد</th><th>توريد متوقع</th></tr></thead>
        <tbody>
        @forelse($myOrders as $r)
          <tr>
            <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
            <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
            <td>{{ $r->supplier?->name ?? '— لسه' }}</td>
            <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش طلبات لسه.</div></div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>أوامر الشغل المفتوحة</span>
        <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الأمر</th><th>المنتج</th><th>المصنع</th><th>متبقي</th></tr></thead>
        <tbody>
        @forelse($openWos as $w)
          <tr class="{{ $w->is_late ? 'table-warning' : '' }}">
            <td class="num fw-bold"><a href="{{ route('work-orders.show',$w) }}">{{ $w->wo_no }}</a></td>
            <td>{{ Str::limit($w->product_title, 24) }}</td>
            <td>{{ $w->factory?->name ?? '—' }}</td>
            <td class="num">{{ number_format($w->outstanding_pieces) }}</td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش أوامر مفتوحة.</div></div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
