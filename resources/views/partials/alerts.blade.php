{{-- رسايل أعلى الشاشة: «تم» بالخطوة الجاية، نجاح، معلومة، وأخطاء التحقق --}}
@if(session('flow'))
  @php $f = session('flow'); @endphp
  <div class="flow-toast" role="status" aria-live="polite">
    <div class="ft-ico"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
    <div class="flex-grow-1 min-w-0">
      <div class="ft-title">{{ $f['title'] }}</div>

      @if($f['next'])
        <div class="ft-next">
          <b><i class="bi bi-arrow-left-short" aria-hidden="true"></i> الخطوة الجاية</b>
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
  <div class="alert alert-success alert-dismissible fade show" role="status" aria-live="polite">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <b>تم.</b> {{ session('success') }}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if(session('info'))
  <div class="alert alert-info alert-dismissible fade show" role="status" aria-live="polite">
    <i class="bi bi-info-circle-fill" aria-hidden="true"></i> {!! session('info') !!}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible fade show" role="status" aria-live="polite">
    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {!! session('warning') !!}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="assertive">
    <i class="bi bi-x-circle-fill" aria-hidden="true"></i> {!! session('error') !!}
    <button class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
@endif

@if($errors->any())
  @php $errBag = $errors->toArray(); $errCount = count($errors->all()); @endphp
  <div class="lv-errors" role="alert" aria-live="assertive" id="lv-errors">
    <div class="le-ico"><i class="bi bi-exclamation-lg" aria-hidden="true"></i></div>
    <div class="flex-grow-1 min-w-0">
      <div class="le-title">{{ $errCount === 1 ? 'فيه حاجة واحدة محتاجة تتظبط قبل الحفظ' : 'فيه ' . $errCount . ' حاجات محتاجة تتظبط قبل الحفظ' }}</div>
      <div class="le-sub">{{ session('draft_saved')
          ? 'المستند اتحفظ كمسودة بس — مش هينزل الخطوة اللي بعدها لحد ما تصلّح ده وتحفظ تاني.'
          : 'مفيش حاجة اتحفظت.' }} الحقول اللي فيها مشكلة متعلّمة بالأحمر — دوس على الرسالة يوديك عليها.</div>
      <ul>
        @foreach($errBag as $field => $msgs)
          {{-- lines.2.qty ⇒ «السطر 3:» عشان المستخدم يعرف أي سطر من غير ما يدوّر --}}
          @php $rowNo = preg_match('/^(?:lines|rolls|fabrics|products|readings)\.(\d+)\./', $field, $m) ? ((int) $m[1] + 1) : null; @endphp
          @foreach($msgs as $e)<li data-field="{{ $field }}">@if($rowNo)<b>السطر {{ $rowNo }}:</b> @endif{{ $e }}</li>@endforeach
        @endforeach
      </ul>
    </div>
    <button class="btn-close" aria-label="إغلاق" onclick="this.closest('.lv-errors').remove()"></button>
  </div>
@endif
