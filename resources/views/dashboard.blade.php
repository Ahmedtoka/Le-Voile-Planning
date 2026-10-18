@extends('layouts.app')
@section('purpose', 'إيه اللي مستنيك، الدورة واقفة فين، وإيه محتاج انتباه — كل ده في شاشة واحدة')
@section('content')

<h1 class="visually-hidden">لوحة تحكم نظام تخطيط الإنتاج</h1>

@php
  $cov = fn ($f) => \App\Services\CoverageService::flagColor($f);
  $money = fn ($n) => number_format((float) $n, 0);

  /* ── اللي مستني منك: الطابور الشخصي + أي كاونتر من المنيو مالوش وصف في الطابور ── */
  $queueLinks = array_column($myQueue, 'link');
  $queueExtra = [];
  foreach ($counters as $route => $n) {
    if ($n < 1 || !Route::has($route) || in_array(route($route), $queueLinks, true)) continue;
    $queueExtra[$route] = $n;
  }
  $queueTotal = array_sum(array_column($myQueue, 'count')) + array_sum($queueExtra);

  /* ── الدورة دلوقتي: نفس أرقام الكروت القديمة بالظبط، مترتبة بترتيب الدورة.
       [المفتاح, رقم الخطوة في المنيو, العنوان, الرقم, سطر تحت الرقم, الشرح, اللينك, نوعه]
       النوع: wait = شغل واقف (بيدخل في حساب «فين الزحمة») · ready = حالة كويسة · volume = حجم */
  $phases = [
    ['n' => '①', 'title' => 'دورة الشراء', 'all' => [route('purchase-orders.index'), 'كل الطلبات'], 'steps' => [
      ['purchasing', '2', 'عند المشتريات', $purchase['purchasing'], 'طلب',
        'مستنية تحديد مورد وسعر وتاريخ توريد.',
        route('purchase-orders.index', ['stage' => 'purchasing']), 'wait'],
      ['receiving', '4', 'جاري الاستلام', $purchase['receiving'], 'طلب',
        'وصل جزء منها والباقي مستني.',
        route('stock-additions.index'), 'wait'],
    ]],
    ['n' => '②', 'title' => 'وصول القماش', 'all' => [route('consignments.index'), 'كل الأحواض'], 'steps' => [
      ['awaiting_inspection', '5', 'مستنية فحص', $fabric['awaiting_inspection'], 'حوض',
        'وصلت بإذن إضافة ومحجوزة. الفاحص هيجرد الأتواب ويقيس العرض.',
        route('inspections.index'), 'wait'],
      ['awaiting_lab', '6', 'مستنية المعمل', $fabric['awaiting_lab'], 'حوض',
        'من غير تقرير معمل مفيش بنشر، ومن غير بنشر الحسبة مش هتطلع.',
        route('lab-reports.index'), 'wait'],
      ['awaiting_release', '7', 'مستنية الإفراج', $fabric['awaiting_release'], 'حوض',
        'اتفحصت وخلّصت معمل — ناقصها إذن استلام خام عشان تتشغّل.',
        route('goods-receipts.index'), 'wait'],
      ['ready', null, 'جاهزة للتشغيل', $fabric['ready'], $money($fabric['ready_kg']) . ' كجم متاحة',
        'مفرج عنها ولسه فيها رصيد متاح — تقدر تعمل عليها أوامر شغل.',
        route('consignments.index', ['ready' => 1]), 'ready'],
    ]],
    ['n' => '③', 'title' => 'التشغيل', 'all' => [route('work-orders.index'), 'أوامر الشغل'], 'steps' => [
      ['marker_requests', '8', 'طلبات ماركر مفتوحة', $production['marker_requests'], 'طلب',
        'الباترونست لسه ما رفعش الماركر.',
        route('markers.requests'), 'wait'],
      ['open', '10', 'أوامر شغل مفتوحة', $production['open'], 'أمر',
        'لسه على المصانع ولا اتقفلتش.',
        route('work-orders.index'), 'volume'],
      ['cut_pending', '12', 'مستنية بيان قص', $production['cut_pending'], 'أمر',
        'اتبعتت للمصنع ولسه ما جاش منها بيان قص.',
        route('cut-declarations.index'), 'wait'],
      ['outstanding', '13', 'قطع على المصانع', $money($production['outstanding']), 'قطعة',
        'مجموع (المقصوص − المستلم) لكل أمر شغل مفتوح.',
        route('production-receipts.index'), 'volume'],
    ]],
  ];

  // فين الزحمة: أكبر رقم بين مراحل «شغل واقف» (لو كلها صفر مفيش تمييز)
  $hotKey = null; $hotMax = 0; $hotLabel = null;
  foreach ($phases as $ph) foreach ($ph['steps'] as $s) {
    if ($s[7] === 'wait' && (int) $s[3] > $hotMax) { $hotMax = (int) $s[3]; $hotKey = $s[0]; $hotLabel = $s[2]; }
  }

  /* ── التنبيهات: اللي محتاج انتباه بس ── */
  $alerts = [
    ['n' => $purchase['late_supply'], 'label' => 'توريد متأخر', 'icon' => 'bi-truck',
     'note' => 'طلبات معتمدة فات تاريخ توريدها وما وصلتش كاملة.', 'link' => null],
    ['n' => $production['late'], 'label' => 'أوامر متأخرة', 'icon' => 'bi-hourglass-bottom',
     'note' => 'فات تاريخ تسليمها ولسه مفتوحة.', 'link' => [route('work-orders.index', ['late' => 1]), 'افتح']],
    ['n' => $production['danger'], 'label' => 'انحراف خارج الحدود', 'icon' => 'bi-rulers',
     'note' => 'الفرق بين المتوقع والمقصوص تعدى ' . config('lvplanning.variance.warn_pct') . '% — محتاج سبب.', 'link' => null],
    ['n' => $fabric['roll_variance'], 'label' => 'فروق في جرد الأتواب', 'icon' => 'bi-list-ol',
     'note' => 'تقارير فحص لقت عدد أتواب مختلف عن اللي المورد قال عليه.', 'link' => [route('inspections.index'), 'راجعها']],
    ['n' => $fabric['width_alerts'], 'label' => 'تنبيهات فرق العرض', 'icon' => 'bi-arrows-expand-vertical',
     'note' => 'فرق عرض كبير بين أتواب حوض واحد — مؤشر إن القماش نفسه فيه مشكلة.', 'link' => null],
    ['n' => $planning['danger'], 'label' => 'موديلات في الخطر', 'icon' => 'bi-clock-history',
     'note' => 'الرصيد يكفي ' . config('lvplanning.coverage.danger_days') . ' يوم أو أقل — لازم تشغيل دلوقتي.',
     'link' => [route('planning.coverage'), 'شوف التغطية']],
  ];
  $alertsOn  = array_values(array_filter($alerts, fn ($a) => (int) $a['n'] > 0));
  $alertsOff = array_values(array_filter($alerts, fn ($a) => (int) $a['n'] === 0));
@endphp

<style>
/* ══ لوحة التحكم — مكونات خاصة بالشاشة دي بس ══ */
.dash-section{margin-bottom:var(--sp-6)}
.dash-section > .section-head{margin-top:0}
/* ② الدورة دلوقتي — البايبلاين */
.pipe{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-lg);box-shadow:var(--e-1);overflow:hidden}
.pipe-hot{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 18px;border-bottom:1px solid var(--lv-line-soft);
  background:var(--lv-surface-2);font-size:var(--fs-sm);color:var(--lv-ink-2)}
.pipe-hot b{color:var(--lv-brand-ink)}
.pipe-body{display:grid;grid-template-columns:2fr 4fr 4fr}
.pipe-phase{padding:14px 10px 12px;min-width:0}
.pipe-phase + .pipe-phase{border-inline-start:1px dashed var(--lv-line)}
.pipe-ph-head{display:flex;align-items:center;gap:8px;padding:0 8px 12px;min-height:30px}
.pipe-ph-head .ph-n{font-size:1.05rem;color:var(--lv-brand);line-height:1}
.pipe-ph-head .ph-t{font-weight:700;font-size:var(--fs-sm);color:var(--lv-brand-ink)}
.pipe-ph-head a{margin-inline-start:auto;font-size:var(--fs-xs);font-weight:600;color:var(--lv-muted);white-space:nowrap}
.pipe-ph-head a:hover{color:var(--lv-brand)}
.pipe-steps{display:grid;grid-auto-columns:1fr;grid-auto-flow:column}
.pipe-node{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;gap:3px;
  padding:6px 6px 10px;border-radius:var(--r-md);border:1px solid transparent;color:var(--lv-ink);min-width:0;
  transition:background var(--dur) var(--ease),border-color var(--dur) var(--ease)}
.pipe-node:hover{background:var(--lv-hover);text-decoration:none;color:var(--lv-ink)}
.pipe-node:focus-visible{outline:0;box-shadow:var(--ring)}
/* الخط اللي بيوصّل الدواير */
.pipe-steps > li{display:flex;min-width:0}
.pipe-steps > li > .pipe-node{flex:1 1 auto}
.pipe-steps > li:not(:last-child) > .pipe-node::after{content:"";position:absolute;top:19px;inset-inline-start:calc(50% + 17px);
  width:calc(100% - 34px);height:2px;background:var(--lv-line);border-radius:2px}
.pipe-node .sn{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
  border:1px solid var(--lv-field);background:var(--lv-white);color:var(--lv-muted);font-size:.72rem;font-weight:700;
  font-variant-numeric:tabular-nums;margin-bottom:4px;position:relative;z-index:1}
.pipe-node .pv{font-size:var(--fs-xl);font-weight:700;line-height:1.15;color:var(--lv-brand-ink);font-variant-numeric:tabular-nums}
.pipe-node .pl{font-size:var(--fs-sm);font-weight:600;color:var(--lv-ink-2);line-height:1.35}
.pipe-node .ps{font-size:var(--fs-xs);color:var(--lv-muted);font-variant-numeric:tabular-nums;line-height:1.4}
.pipe-node.t-wait .sn{border-color:var(--lv-warn-line);background:var(--lv-warn-bg);color:var(--lv-warn)}
.pipe-node.t-ready .sn{border-color:var(--lv-ok-line);background:var(--lv-ok-bg);color:var(--lv-ok)}
.pipe-node.t-ready .pv{color:var(--lv-ok)}
.pipe-node.is-zero .pv{color:#A79BA4}
.pipe-node.is-zero .sn{border-color:var(--lv-line);background:var(--lv-white);color:#A79BA4}
.pipe-node.is-hot{background:var(--lv-tint);border-color:var(--lv-soft)}
.pipe-node.is-hot .sn{background:var(--lv-brand);border-color:var(--lv-brand);color:#fff;box-shadow:0 0 0 4px rgba(157,25,126,.12)}
.pipe-node.is-hot .pv{color:var(--lv-brand)}
.pipe-node .hot-tag{font-size:.68rem;font-weight:700;color:var(--lv-brand-dark);background:var(--lv-white);
  border:1px solid var(--lv-soft);border-radius:var(--r-pill);padding:0 8px;margin-top:2px}
.pipe-side{margin-inline-start:auto;font-size:var(--fs-xs);color:var(--lv-muted);display:inline-flex;align-items:center;gap:5px;flex-wrap:wrap}
.pipe-side a{font-weight:600}
.pipe-side b{color:var(--lv-ink-2);font-variant-numeric:tabular-nums}

@media (max-width:1199.98px){
  .pipe-body{grid-template-columns:1fr}
  .pipe-phase + .pipe-phase{border-inline-start:0;border-top:1px dashed var(--lv-line)}
}
/* موبايل: البايبلاين يبقى ليستة رأسية */
@media (max-width:767.98px){
  .pipe-steps{grid-auto-flow:row}
  .pipe-node{flex-direction:row;flex-wrap:wrap;text-align:start;align-items:center;gap:2px 10px;padding:8px 8px 8px}
  .pipe-node .sn{margin:0;order:0}
  .pipe-node .pl{order:1;flex:1 1 auto}
  .pipe-node .pv{order:2;font-size:var(--fs-lg)}
  .pipe-node .ps{order:3;flex-basis:100%;padding-inline-start:38px;margin-top:-2px}
  .pipe-node .hot-tag{order:4;margin-inline-start:38px}
  .pipe-steps > li:not(:last-child) > .pipe-node::after{top:36px;inset-inline-start:21px;width:2px;height:calc(100% - 30px)}
  .pipe-hot{padding:10px 14px}
}

/* ③ التنبيهات */
.al-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,165px),1fr));gap:var(--sp-3)}
.al-tile{position:relative;display:flex;align-items:flex-start;gap:12px;padding:12px 14px;background:var(--lv-white);
  border:1px solid var(--lv-danger-line);border-inline-start:3px solid var(--lv-danger-dot);border-radius:var(--r-md);color:var(--lv-ink)}
a.al-tile:hover{box-shadow:var(--e-2);text-decoration:none;color:var(--lv-ink)}
a.al-tile:focus-visible{outline:0;box-shadow:var(--ring)}
.al-tile .ai{width:32px;height:32px;flex:0 0 32px;border-radius:8px;background:var(--lv-danger-bg);color:var(--lv-danger);
  display:inline-flex;align-items:center;justify-content:center;font-size:1rem}
.al-tile .an{font-size:var(--fs-lg);font-weight:700;color:var(--lv-danger);line-height:1.1;font-variant-numeric:tabular-nums}
.al-tile .al{font-size:var(--fs-sm);font-weight:600;color:var(--lv-ink-2);line-height:1.35}
.al-tile .ago{font-size:var(--fs-xs);font-weight:600;color:var(--lv-brand);margin-top:2px;display:inline-flex;gap:4px;align-items:center}
.ok-line{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:var(--sp-3);font-size:var(--fs-xs);color:var(--lv-muted)}
.ok-line .lead{color:var(--lv-ok);font-weight:700;display:inline-flex;align-items:center;gap:4px}

/* ④ شريط الأرقام الهادي */
.vol-strip{display:grid;grid-template-columns:repeat(4,1fr);background:var(--lv-white);border:1px solid var(--lv-line);
  border-radius:var(--r-md)}
.vol-cell{padding:12px 18px;min-width:0}
.vol-cell + .vol-cell{border-inline-start:1px solid var(--lv-line-soft)}
.vol-cell .vl{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:500;display:flex;align-items:center;gap:6px}
.vol-cell .vv{font-size:var(--fs-lg);font-weight:700;color:var(--lv-ink);font-variant-numeric:tabular-nums;line-height:1.3}
.vol-cell .vu{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:500;margin-inline-start:4px}
@media (max-width:991.98px){
  .vol-strip{grid-template-columns:repeat(2,1fr)}
  .vol-cell:nth-child(3){border-inline-start:0}
  .vol-cell:nth-child(n+3){border-top:1px solid var(--lv-line-soft)}
}

/* ⑤ التحليلات: رأس كارت موحد */
.dash-card{height:100%;display:flex;flex-direction:column;margin-bottom:0}
.dash-card > .card-header{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-height:52px}
.dash-card > .card-header .bi{color:var(--lv-brand)}
.dash-card > .card-header .ch-side{margin-inline-start:auto;font-weight:400}
.dash-card > .card-header .ch-side.btn{font-weight:600}
.dash-card > .card-footer{margin-top:auto}
.dash-card .table-responsive{flex:1 1 auto}
.mini-legend{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-top:1px solid var(--lv-line-soft)}
.mini-legend > div{padding:10px 12px;text-align:center;min-width:0}
.mini-legend > div + div{border-inline-start:1px solid var(--lv-line-soft)}
.mini-legend .ml-v{font-size:var(--fs-md);font-weight:700;font-variant-numeric:tabular-nums;color:var(--lv-ink)}
.mini-legend .ml-l{font-size:var(--fs-xs);color:var(--lv-muted);display:flex;align-items:center;justify-content:center;gap:5px}
.dash-list .list-group-item{padding:.7rem 1.1rem}
.dash-scroll{max-height:440px;overflow-y:auto}
.dash-scroll thead th{position:sticky;top:0;z-index:1}
</style>

{{-- ══ ١ · اللي مستني منك ══ --}}
<section class="dash-section" aria-labelledby="h-queue">
  <div class="section-head">
    <h2 id="h-queue">اللي مستني منك</h2>
    @if($queueTotal > 0)
      <span class="count-badge" aria-label="{{ $queueTotal }} مستني منك">{{ $queueTotal }}</span>
    @endif
    <span class="hint">شغل عليك إنت بس — مفيش اعتمادات، كل خطوة بتنزل لصاحبها على طول</span>
  </div>

  @if(count($myQueue) || count($queueExtra))
    <div class="q-grid">
      @foreach($myQueue as $q)
        <a href="{{ $q['link'] }}" class="q-tile" title="{{ $q['note'] }}">
          <div class="q-top">
            <span class="q-n">{{ number_format($q['count']) }}</span>
            <span class="q-l">{{ $q['label'] }}</span>
          </div>
          <span class="q-go">{{ $q['note'] }} <i class="bi bi-arrow-left" aria-hidden="true"></i></span>
        </a>
      @endforeach
      @foreach($queueExtra as $route => $n)
        <a href="{{ route($route) }}" class="q-tile">
          <div class="q-top">
            <span class="q-n">{{ number_format($n) }}</span>
            <span class="q-l">{{ __('nav.' . $route) }}</span>
          </div>
          <span class="q-go">افتح <i class="bi bi-arrow-left" aria-hidden="true"></i></span>
        </a>
      @endforeach
    </div>
  @else
    <div class="calm-line">
      <span class="ic"><i class="bi bi-check2" aria-hidden="true"></i></span>
      <span><b>مفيش حاجة مستنية منك.</b> كل اللي عليك خلص.</span>
      <span class="hint">أي شغل جديد هيظهر هنا فورًا</span>
    </div>
  @endif
</section>

{{-- ══ ٢ · الدورة دلوقتي ══ --}}
<section class="dash-section" aria-labelledby="h-pipe">
  <div class="section-head">
    <h2 id="h-pipe">الدورة دلوقتي</h2>
    <span class="hint">كل مرحلة بعدد اللي واقف فيها — دوس على المرحلة تدخل على شاشتها</span>
  </div>

  <div class="pipe">
    <div class="pipe-hot">
      @if($hotKey)
        <i class="bi bi-geo-alt-fill" style="color:var(--lv-brand)" aria-hidden="true"></i>
        أكتر شغل واقف دلوقتي عند: <b>{{ $hotLabel }}</b> <span class="num">({{ $hotMax }})</span>
      @else
        <i class="bi bi-check2-circle" style="color:var(--lv-ok)" aria-hidden="true"></i>
        مفيش شغل واقف في أي مرحلة.
      @endif
      <span class="pipe-side" title="اتسعّر ونزل للحسابات، ولسه ما سجّلتش علمها بيه. مش بيوقّف الاستلام.">
        <i class="bi bi-cash-coin" aria-hidden="true"></i>
        للحسابات: <b>{{ $purchase['finance'] }}</b> مستحق ما اتشافش (مش بيوقّف الاستلام)
        <a href="{{ route('finance.payables', ['unseen' => 1]) }}">راجعها</a>
      </span>
    </div>

    <div class="pipe-body">
      @foreach($phases as $ph)
        <div class="pipe-phase">
          <div class="pipe-ph-head">
            <span class="ph-n" aria-hidden="true">{{ $ph['n'] }}</span>
            <span class="ph-t">{{ $ph['title'] }}</span>
            <a href="{{ $ph['all'][0] }}">{{ $ph['all'][1] }} <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
          </div>
          <ol class="pipe-steps list-unstyled mb-0">
            @foreach($ph['steps'] as [$key, $stepNo, $label, $value, $sub, $note, $link, $kind])
              @php
                $isZero = (string) $value === '0';
                $cls = 't-' . $kind . ($isZero ? ' is-zero' : '') . ($key === $hotKey ? ' is-hot' : '');
              @endphp
              <li>
                <a href="{{ $link }}" class="pipe-node {{ $cls }}" title="{{ $note }}">
                  <span class="sn" aria-hidden="true">
                    @if($stepNo){{ $stepNo }}@else<i class="bi bi-check2"></i>@endif
                  </span>
                  <span class="pv num">{{ $value }}</span>
                  <span class="pl">{{ $label }}</span>
                  <span class="ps">{{ $sub }}</span>
                  @if($key === $hotKey)<span class="hot-tag">هنا الزحمة</span>@endif
                </a>
              </li>
            @endforeach
          </ol>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══ ٣ · محتاج انتباه ══ --}}
<section class="dash-section" aria-labelledby="h-alerts">
  <div class="section-head">
    <h2 id="h-alerts">محتاج انتباه</h2>
    <span class="hint">بيظهر هنا اللي فيه مشكلة بس</span>
  </div>

  @if(count($alertsOn))
    <div class="al-grid">
      @foreach($alertsOn as $a)
        @if($a['link'])
          <a href="{{ $a['link'][0] }}" class="al-tile" title="{{ $a['note'] }}">
        @else
          <div class="al-tile" title="{{ $a['note'] }}">
        @endif
            <span class="ai"><i class="bi {{ $a['icon'] }}" aria-hidden="true"></i></span>
            <span class="d-flex flex-column">
              <span class="an num text-start">{{ $a['n'] }}</span>
              <span class="al">{{ $a['label'] }}</span>
              @if($a['link'])
                <span class="ago">{{ $a['link'][1] }} <i class="bi bi-arrow-left" aria-hidden="true"></i></span>
              @endif
            </span>
        @if($a['link'])</a>@else</div>@endif
      @endforeach
    </div>
  @endif

  @if(count($alertsOff))
    <div class="ok-line {{ count($alertsOn) ? '' : 'calm-line mt-0' }}">
      <span class="lead"><i class="bi bi-check2-circle" aria-hidden="true"></i> كله تمام</span>
      @if(count($alertsOn))
        <span>في:</span>
      @else
        <span>— مفيش تنبيهات في:</span>
      @endif
      @foreach($alertsOff as $a)
        <span class="pill pill-muted" title="{{ $a['note'] }}">{{ $a['label'] }}</span>
      @endforeach
    </div>
  @endif
</section>

{{-- ══ ٤ · الحجم والفلوس ══ --}}
<section class="dash-section" aria-label="أرقام الحجم والقيمة">
  <div class="vol-strip">
    <div class="vol-cell" title="إجمالي قيمة الطلبات اللي اتحدد لها مورد وسعر ولسه ما اتقفلتش.">
      <div class="vl"><span class="dot dot-brand" aria-hidden="true"></span> مستحق متوقع للموردين</div>
      <div class="vv"><span class="num">{{ $money($purchase['payable']) }}</span><span class="vu">{{ config('lvplanning.currency') }}</span></div>
    </div>
    <div class="vol-cell" title="قماش في المخزن ممنوع تشغيله لحد ما يتفرج عنه.">
      <div class="vl"><span class="dot dot-warn" aria-hidden="true"></span> كجم محجوزة تحت الفحص</div>
      <div class="vv"><span class="num">{{ $money($fabric['hold_kg']) }}</span><span class="vu">كجم</span></div>
    </div>
    <div class="vol-cell" title="الرصيد المتاح في الأحواض الجاهزة للتشغيل.">
      <div class="vl"><span class="dot dot-ok" aria-hidden="true"></span> كجم متاحة للتشغيل</div>
      <div class="vv"><span class="num">{{ $money($fabric['ready_kg']) }}</span><span class="vu">كجم · {{ $fabric['ready'] }} حوض</span></div>
    </div>
    <div class="vol-cell" title="إجمالي المنتج التام اللي دخل المخزن الشهر ده.">
      <div class="vl"><span class="dot dot-ok" aria-hidden="true"></span> قطع مستلمة الشهر ده</div>
      <div class="vv"><span class="num">{{ $money($production['received_month']) }}</span><span class="vu">قطعة · {{ $production['closed_month'] }} أمر مقفول</span></div>
    </div>
  </div>
</section>

{{-- ══ ٥ · التحليلات ══ --}}
<section aria-labelledby="h-analytics">
<div class="section-head">
  <h2 id="h-analytics">التحليلات</h2>
  <span class="hint">الاتجاه العام، القماش واقف فين، التغطية، والمواعيد</span>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-graph-up" aria-hidden="true"></i> المبيعات مقابل الإنتاج — آخر 12 شهر
        <span class="hint ch-side">بننتج بقدر ما بنبيع ولا لأ؟</span>
      </div>
      <div class="card-body">
        <div style="position:relative;height:340px">
          <canvas id="chTrend" role="img"
                  aria-label="رسم بياني يقارن المبيعات الشهرية بالإنتاج المستلم والفوركاست خلال آخر 12 شهر"></canvas>
        </div>
      </div>
      <div class="card-footer hint">
        الأعمدة = المبيعات الفعلية · الخط الكامل = الإنتاج المستلم من المصانع ·
        الخط المتقطع = الفوركاست. لو الإنتاج تحت المبيعات باستمرار، المخزون بيتآكل.
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-pie-chart" aria-hidden="true"></i> القماش واقف فين
        <a href="{{ route('consignments.index') }}" class="btn btn-sm btn-outline-plum py-0 ch-side">كل الأحواض</a>
      </div>
      <div class="card-body">
        <div style="position:relative;height:200px">
          <canvas id="chStatus" role="img" aria-label="توزيع الأحواض على حالات الدورة"></canvas>
        </div>
        <table class="table table-sm mt-3 mb-0">
          <thead><tr><th>الحالة</th><th class="num">أحواض</th><th class="num">كجم</th></tr></thead>
          <tbody>
          @forelse($statusMix['labels'] as $i => $lbl)
            <tr>
              <td>
                <span style="display:inline-block;width:9px;height:9px;border-radius:2px;
                      background:{{ $statusMix['colors'][$i] }}" aria-hidden="true"></span>
                {{ $lbl }}
              </td>
              <td class="num">{{ $statusMix['data'][$i] }}</td>
              <td class="num">{{ number_format($statusMix['kg'][$i]) }}</td>
            </tr>
          @empty
            <tr><td colspan="3">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش أحواض لسه.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer hint">
        مستندات الدورة: <span class="num">{{ $fabric['docs']['additions'] }}</span> إذن إضافة ·
        <span class="num">{{ $fabric['docs']['inspections'] }}</span> فحص ·
        <span class="num">{{ $fabric['docs']['labs'] }}</span> معمل ·
        <span class="num">{{ $fabric['docs']['receipts'] }}</span> استلام
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-bar-chart-steps" aria-hidden="true"></i> شرايح التغطية
        <a href="{{ route('planning.coverage') }}" class="btn btn-sm btn-outline-plum py-0 ch-side">كل التغطية</a>
      </div>
      <div class="card-body">
        <div style="position:relative;height:200px">
          <canvas id="chCoverage" role="img" aria-label="عدد الموديلات في كل شريحة تغطية"></canvas>
        </div>
      </div>
      <div class="mini-legend">
        <div title="الرصيد يكفي {{ config('lvplanning.coverage.danger_days') }} يوم أو أقل — لازم تشغيل دلوقتي.">
          <div class="ml-v num" style="color:var(--lv-danger)">{{ $planning['danger'] }}</div>
          <div class="ml-l"><span class="dot dot-danger" aria-hidden="true"></span> في الخطر</div>
        </div>
        <div title="تغطية بين {{ config('lvplanning.coverage.danger_days') }} و {{ config('lvplanning.coverage.watch_days') }} يوم.">
          <div class="ml-v num" style="color:var(--lv-warn)">{{ $planning['watch'] }}</div>
          <div class="ml-l"><span class="dot dot-warn" aria-hidden="true"></span> تحت المراقبة</div>
        </div>
        <div title="مفيش قلق على الموديلات دي حاليًا.">
          <div class="ml-v num" style="color:var(--lv-ok)">{{ $planning['ok'] }}</div>
          <div class="ml-l"><span class="dot dot-ok" aria-hidden="true"></span> تغطية مريحة</div>
        </div>
        <div title="موديلات مالهاش حركة بيع مسجلة — التغطية مش محسوبة ليها.">
          <div class="ml-v num" style="color:var(--lv-muted)">{{ $planning['unknown'] }}</div>
          <div class="ml-l"><span class="dot dot-muted" aria-hidden="true"></span> مفيش مبيعات</div>
        </div>
      </div>
      <div class="card-footer hint d-flex flex-wrap gap-2 align-items-center">
        <span>
          خطر = تغطية {{ config('lvplanning.coverage.danger_days') }} يوم أو أقل ·
          مراقبة = لحد {{ config('lvplanning.coverage.watch_days') }} يوم.
        </span>
        <a href="{{ route('io.index') }}" class="ms-auto fw-semibold">ارفع المبيعات</a>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-calendar-event" aria-hidden="true"></i> المواعيد القادمة
        <span class="hint ch-side">45 يوم جايين — المتأخر الأول</span>
      </div>
      <div class="table-responsive dash-scroll">
        <table class="table table-sm mb-0">
          <thead><tr><th>التاريخ</th><th>النوع</th><th>المستند</th><th>الطرف</th><th>المتبقي</th></tr></thead>
          <tbody>
          @forelse($upcoming as $u)
            @php
              // Carbon 3 بيرجّع الفرق بإشارة — من غير true التواريخ الجاية بتطلع بالسالب
              $late = $u['date']->isPast();
              $days = (int) $u['date']->diffInDays(now(), true);
            @endphp
            <tr class="{{ $late ? 'table-warning' : '' }}">
              <td class="num text-nowrap">
                {{ $u['date']->format('Y-m-d') }}
                <div class="hint">
                  @if($late)
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> متأخر {{ $days }} يوم
                  @else
                    باقي {{ $days }} يوم
                  @endif
                </div>
              </td>
              <td><i class="bi {{ $u['icon'] }}" aria-hidden="true"></i> {{ $u['kind'] }}</td>
              <td class="num"><a href="{{ $u['link'] }}">{{ $u['no'] }}</a></td>
              <td>{{ $u['who'] }}</td>
              <td class="hint">{{ $u['note'] }}</td>
            </tr>
          @empty
            <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش مواعيد قريبة.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer hint">
        توريدات من الموردين + تسليمات من المصانع في مكان واحد.
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-clock-history" aria-hidden="true"></i> أخطر الموديلات في التغطية
        <a href="{{ route('planning.coverage') }}" class="btn btn-sm btn-outline-plum py-0 ch-side">الكل</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الموديل</th><th>متوسط يومي</th><th>الرصيد</th><th>أيام التغطية</th><th>الحالة</th></tr></thead>
          <tbody>
          @forelse($coverage as $c)
            <tr>
              <td>{{ $c['model']->name }}<div class="hint">{{ $c['model']->code }}</div></td>
              <td class="num">{{ $c['avg_daily'] }}</td>
              <td class="num">{{ number_format($c['stock']) }}</td>
              <td class="num fw-bold">{{ $c['cover_days'] ?? '—' }}</td>
              <td>
                <span class="badge bg-{{ $cov($c['flag']) }}">
                  <i class="bi bi-{{ in_array($c['flag'],['out','danger']) ? 'exclamation-triangle-fill' : ($c['flag']==='watch' ? 'exclamation-circle' : 'check-circle') }}" aria-hidden="true"></i>
                  {{ $c['flag_label'] }}
                </span>
              </td>
            </tr>
          @empty
            <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش داتا مبيعات أو أرصدة — ارفعها من شاشة الاستيراد أو ولّد داتا ديمو.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer hint">
        أيام التغطية = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي لآخر
        {{ config('lvplanning.avg_sales_window_days') }} يوم.
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> أوامر شغل متأخرة
        <a href="{{ route('work-orders.index', ['late' => 1]) }}" class="btn btn-sm btn-outline-plum py-0 ch-side">الكل</a>
      </div>
      <ul class="list-group list-group-flush dash-list">
        @forelse($lateOrders as $w)
          <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-center gap-2">
              <a href="{{ route('work-orders.show', $w) }}" class="fw-bold num">{{ $w->wo_no }}</a>
              <span class="status is-danger">متأخر {{ (int) $w->due_date->diffInDays(now(), true) }} يوم</span>
            </div>
            <div class="hint">
              {{ $w->factory?->name }} · متبقي {{ number_format($w->outstanding_pieces) }} قطعة
            </div>
          </li>
        @empty
          <li class="list-group-item">
            <div class="empty-state py-4">
              <i class="bi bi-check2-circle ico" aria-hidden="true"></i>
              <div class="t">مفيش تأخير. تمام.</div>
            </div>
          </li>
        @endforelse
      </ul>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-building" aria-hidden="true"></i> تحميل المصانع
        <span class="hint ch-side">مين عليه شغل أكتر</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>المصنع</th><th>أوامر مفتوحة</th><th>متأخرة</th><th>قطع عليه</th><th>أيام تقديرية</th></tr></thead>
          <tbody>
          @forelse($factoryLoad as $f)
            <tr>
              <td>{{ $f['factory']->name }}</td>
              <td class="num">{{ $f['open'] }}</td>
              <td class="num {{ $f['late'] ? 'text-danger fw-bold' : '' }}">{{ $f['late'] }}</td>
              <td class="num">{{ number_format($f['outstanding']) }}</td>
              <td class="num hint">
                {{ $f['capacity'] > 0 ? ceil($f['outstanding'] / $f['capacity']) . ' يوم' : '—' }}
              </td>
            </tr>
          @empty
            <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش أوامر شغل مفتوحة.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer hint">
        الأيام التقديرية = القطع المتبقية ÷ الطاقة اليومية للمصنع.
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card dash-card">
      <div class="card-header">
        <i class="bi bi-chat-dots" aria-hidden="true"></i> آخر النقاشات
        <span class="hint ch-side">على كل المستندات</span>
      </div>
      <ul class="list-group list-group-flush dash-list">
        @forelse($talk as $t)
          <li class="list-group-item">
            <div class="d-flex justify-content-between">
              <b style="font-size:.82rem">{{ $t->user?->name }}</b>
              <span class="hint">{{ $t->created_at->diffForHumans() }}</span>
            </div>
            <div class="hint">{{ Str::limit($t->body, 90) }}</div>
          </li>
        @empty
          <li class="list-group-item">
            <div class="empty-state py-4">
              <i class="bi bi-chat ico" aria-hidden="true"></i>
              <div class="t">مفيش نقاشات لسه.</div>
            </div>
          </li>
        @endforelse
      </ul>
      <div class="card-footer hint">
        كل مستند فيه خيط نقاش زي التيكيت — استفسارات وردود وصور إثبات.
      </div>
    </div>
  </div>
</div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;

  var FF = "'IBM Plex Sans Arabic', system-ui, sans-serif";
  Chart.defaults.font.family = FF;
  Chart.defaults.font.size   = 11;
  Chart.defaults.color       = '#6B606A';
  Chart.defaults.plugins.legend.rtl = true;
  Chart.defaults.plugins.tooltip.rtl = true;
  Chart.defaults.plugins.tooltip.titleFont = {family: FF};
  Chart.defaults.plugins.tooltip.bodyFont  = {family: FF};
  Chart.defaults.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ? false : {duration: 260};

  const nf = new Intl.NumberFormat('en-US');
  const grid = {color: '#EFE7EC', drawBorder: false};

  const trend = @json($trend);
  const t = document.getElementById('chTrend');
  if (t) new Chart(t, {
    data: {
      labels: trend.labels,
      datasets: [
        {type: 'bar',  label: 'مبيعات', data: trend.sales,
         backgroundColor: '#ECD1E5', borderColor: '#9D197E', borderWidth: 1, order: 3},
        {type: 'line', label: 'إنتاج مستلم', data: trend.produced,
         borderColor: '#1B7A50', backgroundColor: '#1B7A50', tension: .35,
         pointRadius: 3, pointHoverRadius: 6, borderWidth: 2, order: 1},
        {type: 'line', label: 'فوركاست', data: trend.forecast,
         borderColor: '#9A6410', borderDash: [5, 4], tension: .35,
         pointRadius: 0, pointHoverRadius: 5, borderWidth: 2, order: 2}
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
      plugins: {
        legend: {position: 'bottom', labels: {boxWidth: 12, padding: 14, usePointStyle: true}},
        tooltip: {callbacks: {label: c => c.dataset.label + ': ' + nf.format(c.parsed.y) + ' قطعة'}}
      },
      scales: {
        y: {beginAtZero: true, grid: grid, ticks: {callback: v => nf.format(v)}},
        x: {grid: {display: false}}
      }
    }
  });

  const st = @json($statusMix);
  const sEl = document.getElementById('chStatus');
  if (sEl && st.data.length) new Chart(sEl, {
    type: 'doughnut',
    data: {labels: st.labels, datasets: [{data: st.data, backgroundColor: st.colors,
           borderColor: '#fff', borderWidth: 2}]},
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '58%',
      plugins: {
        legend: {position: 'bottom', labels: {boxWidth: 10, padding: 10, usePointStyle: true, font: {size: 10}}},
        tooltip: {callbacks: {label: c => c.label + ': ' + nf.format(c.parsed) + ' حوض'}}
      }
    }
  });

  const cv = @json($coverageMix);
  const cEl = document.getElementById('chCoverage');
  if (cEl && cv.data.length) new Chart(cEl, {
    type: 'bar',
    data: {labels: cv.labels, datasets: [{label: 'موديلات', data: cv.data,
           backgroundColor: cv.colors, borderRadius: 5, maxBarThickness: 34}]},
    options: {
      responsive: true, maintainAspectRatio: false, indexAxis: 'y',
      plugins: {
        legend: {display: false},
        tooltip: {callbacks: {label: c => nf.format(c.parsed.x) + ' موديل'}}
      },
      scales: {x: {beginAtZero: true, grid: grid, ticks: {precision: 0}}, y: {grid: {display: false}}}
    }
  });
})();
</script>
@endpush
