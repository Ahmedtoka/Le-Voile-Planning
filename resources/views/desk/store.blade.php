@extends('partials.desk')
@section('purpose', 'استلام القماش والإفراج عنه، صرف الخام، واستلام الإنتاج')
@php
  $flow='fabric'; $flowStep='addition';
  $intro = 'إنت بتستلم القماش وبتفرج عنه وبتصرفه. أول ما تستلم، بتحدد يروح <b>فحص</b> ولا <b>المخزن على طول</b> (حاويات) — والباقي بيمشي لوحده.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header">
    <span>آخر أذون الإضافة</span>
    <a href="{{ route('stock-additions.index') }}" class="btn btn-sm btn-outline-plum">الكل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr>
      <th>الإذن</th><th class="num">التاريخ</th><th>المورد</th><th>الرسالة</th><th class="num">الكمية</th><th>الحالة</th>
      <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
    </tr></thead>
    <tbody>
    @forelse($recent as $r)
      <tr>
        <td class="num fw-bold"><a href="{{ route('stock-additions.edit',$r) }}">{{ $r->doc_no }}</a></td>
        <td class="num">{{ $r->doc_date?->format('Y-m-d') }}</td>
        <td>{{ $r->supplier?->name ?? '—' }}</td>
        <td class="num hint">{{ $r->consignment_no ?: '—' }}</td>
        <td class="num">{{ lv_num($r->total_qty, 2) }}</td>
        <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_label }}</span></td>
        <td class="text-end">
          <div class="row-actions">
            <a href="{{ route('stock-additions.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="افتح الإذن" aria-label="افتح الإذن {{ $r->doc_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
        <div class="t">مفيش أذون لسه.</div>
        <div class="hint">ابدأ بـ«إذن إضافة جديد» أو «استلام حاويات» من فوق.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
