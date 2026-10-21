{{-- رقم إذن المورد كلينك يفتح إذن الإضافة. الاستخدام: @include('partials.supplier_doc', ['sa' => $sa]) --}}
@php $sdSa = $sa ?? null; @endphp
@if($sdSa)
  <a href="{{ route('stock-additions.edit', $sdSa) }}" class="badge bg-light text-dark border text-decoration-none num"
     title="إذن الإضافة {{ $sdSa->doc_no }} — اضغط لفتح التفاصيل" style="font-size:.8rem">
    <i class="bi bi-receipt" aria-hidden="true"></i> إذن المورد {{ $sdSa->supplier_doc_no ?: '—' }}
    <span class="hint">· {{ $sdSa->doc_no }}</span>
  </a>
@else
  <span class="hint">إذن المورد —</span>
@endif
