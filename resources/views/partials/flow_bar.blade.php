{{--
  شريط الفلو — إنت واقف فين في الدورة، وإيه اللي قبلك واللي بعدك.
  الاستخدام: @include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'inspection'])
  الخطوة اللي فاتت بتبقى خضرا، اللي إنت فيها بلون البراند، واللي جاية رمادي —
  وكلهم لينكات، فالمستخدم بيمشي الدورة بالترتيب من غير ما يدور في المنيو.
--}}
@php
  $flows = [
    'buy' => ['label' => 'دورة الشراء', 'steps' => [
      ['k'=>'request',   't'=>'طلب شراء',        'r'=>'purchase-orders.index', 'i'=>'bi-file-earmark-text'],
      ['k'=>'sourcing',  't'=>'التسعير',          'r'=>'purchasing.queue',      'i'=>'bi-cart3'],
      ['k'=>'finance',   't'=>'الحسابات',         'r'=>'finance.payables',      'i'=>'bi-cash-coin'],
      ['k'=>'addition',  't'=>'إذن الإضافة',      'r'=>'stock-additions.index', 'i'=>'bi-box-arrow-in-down'],
    ]],
    'fabric' => ['label' => 'دورة القماش', 'steps' => [
      ['k'=>'addition',   't'=>'إذن الإضافة',     'r'=>'stock-additions.index', 'i'=>'bi-box-arrow-in-down'],
      ['k'=>'inspection', 't'=>'الفحص',           'r'=>'inspections.index',     'i'=>'bi-search'],
      ['k'=>'lab',        't'=>'المعمل',          'r'=>'lab-reports.index',     'i'=>'bi-thermometer-half'],
      ['k'=>'receipt',    't'=>'استلام الخام',     'r'=>'goods-receipts.index',  'i'=>'bi-box-arrow-in-down'],
      ['k'=>'consign',    't'=>'الأحواض',          'r'=>'consignments.index',    'i'=>'bi-box-seam'],
    ]],
    'prod' => ['label' => 'دورة التشغيل', 'steps' => [
      ['k'=>'consign',   't'=>'حوض مُفرَج عنه',   'r'=>'consignments.index',        'i'=>'bi-box-seam'],
      ['k'=>'prep',      't'=>'طلب تحضير',        'r'=>'preparation-requests.index','i'=>'bi-speedometer2'],
      ['k'=>'mreq',      't'=>'طلب الماركر',      'r'=>'markers.requests',          'i'=>'bi-envelope-paper'],
      ['k'=>'marker',    't'=>'الماركر',          'r'=>'markers.index',             'i'=>'bi-grid-3x3'],
      ['k'=>'wo',        't'=>'أمر الشغل',        'r'=>'work-orders.index',         'i'=>'bi-hammer'],
      ['k'=>'issue',     't'=>'صرف الخام',        'r'=>'material-issues.index',     'i'=>'bi-box-arrow-up'],
      ['k'=>'cut',       't'=>'بيان القص',        'r'=>'cut-declarations.index',    'i'=>'bi-scissors'],
      ['k'=>'receive',   't'=>'استلام الإنتاج',   'r'=>'production-receipts.index', 'i'=>'bi-inboxes'],
    ]],
  ];

  $f = $flows[$flow] ?? null;

  /* رقم الخطوة = نفس رقمها في القايمة الجانبية (1 ← 13)، عشان المستخدم
     يشوف نفس الرقم في المكانين. الخطوة اللي ملهاش رقم (الأحواض) بتاخد أيقونة. */
  $cycleNo = [
    'purchase-orders.index' => 1, 'purchasing.queue' => 2, 'finance.payables' => 3,
    'stock-additions.index' => 4, 'inspections.index' => 5, 'lab-reports.index' => 6,
    'goods-receipts.index' => 7, 'markers.requests' => 8, 'markers.index' => 9,
    'work-orders.index' => 10, 'material-issues.index' => 11, 'cut-declarations.index' => 12,
    'production-receipts.index' => 13,
  ];
@endphp

@if($f)
  @php
    $steps = array_values(array_filter($f['steps'], fn ($s) => Route::has($s['r'])));
    $at = null;
    foreach ($steps as $i => $s) if ($s['k'] === ($step ?? '')) $at = $i;
    $next = ($at !== null && isset($steps[$at + 1])) ? $steps[$at + 1] : null;
    $prev = ($at !== null && $at > 0) ? $steps[$at - 1] : null;
  @endphp

  <nav class="flow-bar" aria-label="{{ $f['label'] }}">
    <div class="steps">
      @foreach($steps as $i => $s)
        @php $state = $at === null ? '' : ($i < $at ? 'done' : ($i === $at ? 'now' : '')); @endphp
        @if($state === 'now')
          {{-- الخطوة اللي إنت فيها مش لينك — عشان ما تودّيش لصفحة تانية --}}
          <span class="flow-step now" aria-current="step">
            @if(isset($cycleNo[$s['r']]))<span class="n">{{ $cycleNo[$s['r']] }}</span>@else<span class="n"><i class="bi {{ $s['i'] }}" style="display:inline;font-size:.68rem" aria-hidden="true"></i></span>@endif
            <i class="bi {{ $s['i'] }}" aria-hidden="true"></i>
            <span>{{ $s['t'] }}</span>
            <span class="visually-hidden">(إنت هنا)</span>
          </span>
        @else
          <a class="flow-step {{ $state }}" href="{{ route($s['r']) }}">
            @if(isset($cycleNo[$s['r']]))<span class="n">{{ $cycleNo[$s['r']] }}</span>@else<span class="n"><i class="bi {{ $s['i'] }}" style="display:inline;font-size:.68rem" aria-hidden="true"></i></span>@endif
            <i class="bi {{ $s['i'] }}" aria-hidden="true"></i>
            <span>{{ $s['t'] }}</span>
            @if($state === 'done')
              <i class="bi bi-check2" aria-hidden="true"></i><span class="visually-hidden">(خطوة سابقة)</span>
            @endif
          </a>
        @endif
      @endforeach
    </div>

    @if($next || $prev)
      <div class="flow-next">
        <span class="hint">{{ $f['label'] }}:</span>
        @if($prev)
          <a href="{{ route($prev['r']) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-right" aria-hidden="true"></i> اللي قبله: {{ $prev['t'] }}
          </a>
        @endif
        @if($next)
          <a href="{{ route($next['r']) }}" class="btn btn-sm btn-outline-plum ms-auto">
            الخطوة اللي بعدها: {{ $next['t'] }} <i class="bi bi-chevron-left" aria-hidden="true"></i>
          </a>
        @endif
      </div>
    @endif
  </nav>
@endif
