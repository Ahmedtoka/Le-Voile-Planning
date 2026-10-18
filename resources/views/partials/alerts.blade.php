@if(session('flow'))
  @php $f = session('flow'); @endphp
  <div class="flow-toast" role="status" aria-live="polite">
    <div class="ft-ico"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
    <div class="flex-grow-1">
      <div class="ft-title">{{ $f['title'] }}</div>

      @if($f['next'])
        <div class="ft-next">
          <b>الخطوة الجاية</b>
          @if($f['who']) — المسؤول: <b>{{ $f['who'] }}</b> @endif
          <div class="mt-1">{!! $f['next'] !!}</div>
        </div>
      @endif

      @if($f['link'])
        <a href="{{ $f['link'] }}" class="btn btn-sm btn-plum mt-2">
          {{ $f['link_label'] }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
        </a>
      @endif
    </div>
    <button class="btn-close" aria-label="إغلاق" onclick="this.closest('.flow-toast').remove()"></button>
  </div>
@endif

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show py-2" role="status" aria-live="polite">
    <i class="bi bi-check-circle" aria-hidden="true"></i> {{ session('success') }}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if(session('info'))
  <div class="alert alert-info alert-dismissible fade show py-2" role="status" aria-live="polite">
    <i class="bi bi-info-circle" aria-hidden="true"></i> {!! session('info') !!}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show py-2" role="alert" aria-live="assertive">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif
