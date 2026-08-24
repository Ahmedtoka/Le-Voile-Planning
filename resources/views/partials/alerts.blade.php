@if(session('flow'))
  @php $f = session('flow'); @endphp
  <div class="card mb-3" role="status" aria-live="polite"
       style="border-color:var(--lv-ok-line);background:var(--lv-ok-bg)">
    <div class="card-body py-3">
      <div class="d-flex align-items-start gap-3">
        <div style="width:36px;height:36px;flex:0 0 36px;border-radius:50%;background:var(--lv-ok);color:#fff;
             text-align:center;line-height:36px;font-size:1.1rem"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold" style="color:#14603E">{{ $f['title'] }}</div>

          @if($f['next'])
            <div class="mt-2" style="font-size:.84rem;color:#2F5544">
              <b>الخطوة الجاية</b>
              @if($f['who']) — المسؤول: <b>{{ $f['who'] }}</b> @endif
              <div class="mt-1">{!! $f['next'] !!}</div>
            </div>
          @endif

          @if($f['link'])
            <a href="{{ $f['link'] }}" class="btn btn-sm mt-2"
               style="background:var(--lv-ok);color:#fff">
              <i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $f['link_label'] }}
            </a>
          @endif
        </div>
        <button class="btn-close" aria-label="إغلاق" onclick="this.closest('.card').remove()"></button>
      </div>
    </div>
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
