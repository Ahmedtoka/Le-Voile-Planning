{{-- زرارين الرجوع المشتركين. بياخد اختياريًا: $primary (HTML لزرار أساسي بديل يتحط الأول). --}}
<div class="actions">
  {!! $primary ?? '' !!}
  <a class="{{ isset($primary) ? 'btn btn-outline' : 'btn btn-plum' }}" href="{{ url('/') }}">
    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5ZM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5 5 5Z"/></svg>
    ارجع للوحة التحكم
  </a>
  <button type="button" class="btn btn-outline" onclick="history.back()">
    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/></svg>
    ارجع للصفحة اللي قبلها
  </button>
</div>
