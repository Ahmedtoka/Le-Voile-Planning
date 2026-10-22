@extends('partials.desk')
@section('purpose', 'بيانات القص الفعلية واستلامات الإنتاج لحد ما أمر الشغل يقفل')
@php
  $flow='prod'; $flowStep='cut';
  $intro = 'بتتابع الشغل جوه المصانع: بيان القص الفعلي واستلامات الإنتاج على دفعات لحد ما أمر الشغل يقفل.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header">
    <span>أوامر الشغل عند المصانع</span>
    <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-plum">الكل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr>
      <th>الأمر</th><th>المنتج</th><th>المصنع</th>
      <th class="num">متوقع</th><th class="num">مقصوص</th><th class="num">مستلم</th><th class="num">متبقي</th>
      <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
    </tr></thead>
    <tbody>
    @forelse($openWos as $w)
      <tr class="{{ $w->is_late ? 'table-warning' : '' }}">
        <td class="num fw-bold">
          <a href="{{ route('work-orders.show',$w) }}">{{ $w->wo_no }}</a>
          @if($w->is_late)<div class="status is-danger">متأخر</div>@endif
        </td>
        <td>{{ Str::limit($w->product_title, 22) }}</td>
        <td>{{ $w->factory?->name ?? '—' }}</td>
        <td class="num">{{ number_format($w->target_qty) }}</td>
        <td class="num">{{ number_format((int)$w->cut_pieces) }}</td>
        <td class="num">{{ number_format((int)$w->received_pieces) }}</td>
        <td class="num fw-bold">{{ number_format($w->outstanding_pieces) }}</td>
        <td class="text-end">
          <div class="row-actions">
            @if(in_array($w->status,['sent_to_factory','cutting']))
              <a href="{{ route('cut-declarations.create',['work_order_id'=>$w->id]) }}" class="btn btn-sm btn-outline-plum btn-icon"
                 title="سجّل بيان القص" aria-label="سجّل بيان القص للأمر {{ $w->wo_no }}"><i class="bi bi-scissors" aria-hidden="true"></i></a>
            @endif
            @if($w->outstanding_pieces > 0)
              <a href="{{ route('production-receipts.create',['work_order_id'=>$w->id]) }}" class="btn btn-sm btn-outline-plum btn-icon"
                 title="استلم إنتاج" aria-label="استلم إنتاج من الأمر {{ $w->wo_no }}"><i class="bi bi-inboxes" aria-hidden="true"></i></a>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش أوامر عند المصانع.</div>
        <div class="hint">أول ما أمر شغل يتبعت لمصنع هيظهر هنا بالمقصوص والمستلم.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
