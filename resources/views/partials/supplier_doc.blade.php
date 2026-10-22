{{-- رقم إذن المورد كلينك يفتح إذن الإضافة. الاستخدام: @include('partials.supplier_doc', ['sa' => $sa]) --}}
@php $sdSa = $sa ?? null; @endphp
@if($sdSa)
  <a href="{{ route('stock-additions.edit', $sdSa) }}" class="pill pill-muted num text-decoration-none"
     title="إذن الإضافة {{ $sdSa->doc_no }} — اضغط لفتح التفاصيل">
    <i class="bi bi-receipt" aria-hidden="true"></i> إذن المورد {{ $sdSa->supplier_doc_no ?: '—' }}
    <span class="hint">· {{ $sdSa->doc_no }}</span>
  </a>
@else
  <span class="hint">إذن المورد —</span>
@endif
