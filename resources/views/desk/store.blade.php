@extends('partials.desk')
@php
  $flow='fabric'; $flowStep='addition';
  $intro = 'إنت بتستلم القماش وبتفرج عنه وبتصرفه. أول ما تستلم، بتحدد يروح <b>فحص</b> ولا <b>المخزن على طول</b> (حاويات) — والباقي بيمشي لوحده.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>آخر أذون الإضافة</span>
    <a href="{{ route('stock-additions.index') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>الإذن</th><th>التاريخ</th><th>المورد</th><th>الرسالة</th><th>الكمية</th><th>الحالة</th></tr></thead>
    <tbody>
    @forelse($recent as $r)
      <tr>
        <td class="num fw-bold"><a href="{{ route('stock-additions.edit',$r) }}">{{ $r->doc_no }}</a></td>
        <td class="num">{{ $r->doc_date?->format('Y-m-d') }}</td>
        <td>{{ $r->supplier?->name ?? '—' }}</td>
        <td class="num hint">{{ $r->consignment_no ?: '—' }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$r->total_qty,2),'0'),'.') }}</td>
        <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_label }}</span></td>
      </tr>
    @empty
      <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش أذون لسه.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
