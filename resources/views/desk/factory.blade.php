@extends('partials.desk')
@php
  $flow='prod'; $flowStep='cut';
  $intro = 'بتتابع الشغل جوه المصانع: بيان القص الفعلي واستلامات الإنتاج على دفعات لحد ما أمر الشغل يقفل.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>أوامر الشغل عند المصانع</span>
    <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>الأمر</th><th>المنتج</th><th>المصنع</th><th>متوقع</th><th>مقصوص</th><th>مستلم</th><th>متبقي</th><th></th></tr></thead>
    <tbody>
    @forelse($openWos as $w)
      <tr class="{{ $w->is_late ? 'table-warning' : '' }}">
        <td class="num fw-bold"><a href="{{ route('work-orders.show',$w) }}">{{ $w->wo_no }}</a></td>
        <td>{{ Str::limit($w->product_title, 22) }}</td>
        <td>{{ $w->factory?->name ?? '—' }}</td>
        <td class="num">{{ number_format($w->target_qty) }}</td>
        <td class="num">{{ number_format((int)$w->cut_pieces) }}</td>
        <td class="num">{{ number_format((int)$w->received_pieces) }}</td>
        <td class="num fw-bold">{{ number_format($w->outstanding_pieces) }}</td>
        <td class="text-nowrap">
          @if(in_array($w->status,['sent_to_factory','cutting']))
            <a href="{{ route('cut-declarations.create',['work_order_id'=>$w->id]) }}" class="btn btn-sm btn-outline-plum py-0">قص</a>
          @endif
          @if($w->outstanding_pieces > 0)
            <a href="{{ route('production-receipts.create',['work_order_id'=>$w->id]) }}" class="btn btn-sm btn-plum py-0">استلم</a>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش أوامر عند المصانع.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
