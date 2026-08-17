@extends('layouts.app')
@section('content')

<h1 class="visually-hidden">لوحة تحكم نظام تخطيط الإنتاج</h1>

@php
  $cov = fn ($f) => \App\Services\CoverageService::flagColor($f);
  $money = fn ($n) => number_format((float) $n, 0);
@endphp

{{-- ══ اللي مستني مني ══ --}}
@if(count($counters))
  <div class="card mb-3" style="border-color:#F0C8A0;background:#FFF9F2">
    <div class="card-body py-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-hand-index-thumb" style="color:#B7791F"></i>
        <b style="color:#8A5A12">مستني منك دلوقتي</b>
        <span class="hint">الأرقام دي شغل عليك إنت — كل واحد بيشوف بتاعه</span>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        @foreach($counters as $route => $n)
          @continue(!Route::has($route))
          <a href="{{ route($route) }}" class="btn btn-sm btn-outline-plum">
            {{ __('nav.' . $route) }}
            <span class="badge rounded-pill" style="background:#E8506B;color:#fff">{{ $n }}</span>
          </a>
        @endforeach
      </div>
    </div>
  </div>
@endif

{{-- ══ ① دورة الشراء ══ --}}
<div class="section-head">
  <h2>① دورة الشراء</h2>
  <span class="hint">من طلب التخطيط لحد ما القماش يتورّد</span>
  <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-plum py-0 ms-auto">كل الطلبات</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$purchase['planning'], 'label'=>'عند التخطيط', 'tone'=>'muted',
      'note'=>'طلبات لسه بتتكتب، ما نزلتش للمشتريات.',
      'link'=>[route('purchase-orders.index',['stage'=>'planning']),'افتح']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$purchase['purchasing'], 'label'=>'عند المشتريات', 'tone'=>'warn',
      'note'=>'مستنية تحديد مورد وسعر وتاريخ توريد.',
      'link'=>[route('purchase-orders.index',['stage'=>'purchasing']),'افتح']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$purchase['finance'], 'label'=>'عند الحسابات', 'tone'=>'warn',
      'note'=>'مستنية علم الحسابات بالمستحق.',
      'link'=>[route('finance.payables'),'المستحقات']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$purchase['approval'], 'label'=>'تحت الاعتماد', 'tone'=>'warn',
      'note'=>'مستنية توقيع مدير المشتريات والمدير العام.',
      'link'=>[route('approvals.index'),'الاعتمادات']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$money($purchase['payable']), 'label'=>'مستحق متوقع للموردين',
      'sub'=>config('lvplanning.currency'), 'tone'=>'brand',
      'note'=>'إجمالي قيمة الطلبات اللي اتحدد لها مورد وسعر ولسه ما اتقفلتش.'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$purchase['late_supply'], 'label'=>'توريد متأخر',
      'tone'=>$purchase['late_supply'] ? 'danger' : 'ok',
      'note'=>'طلبات معتمدة فات تاريخ توريدها وما وصلتش كاملة.'])
  </div>
</div>

{{-- ══ ② وصول القماش ══ --}}
<div class="section-head">
  <h2>② وصول القماش</h2>
  <span class="hint">إذن إضافة ← فحص ← معمل ← إذن استلام (الإفراج)</span>
  <a href="{{ route('consignments.index') }}" class="btn btn-sm btn-outline-plum py-0 ms-auto">كل الأحواض</a>
</div>
<div class="row g-3 mb-2">
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['awaiting_inspection'], 'label'=>'أحواض مستنية فحص',
      'tone'=>$fabric['awaiting_inspection'] ? 'warn' : 'ok',
      'note'=>'وصلت بإذن إضافة ومحجوزة. الفاحص هيجرد الأتواب ويقيس العرض.',
      'link'=>[route('inspections.index'),'تقارير الفحص']])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['awaiting_lab'], 'label'=>'مستنية المعمل',
      'tone'=>$fabric['awaiting_lab'] ? 'warn' : 'ok',
      'note'=>'من غير تقرير معمل مفيش بنشر، ومن غير بنشر الحسبة مش هتطلع.',
      'link'=>[route('lab-reports.index'),'تقارير المعمل']])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['awaiting_release'], 'label'=>'مستنية الإفراج',
      'tone'=>$fabric['awaiting_release'] ? 'warn' : 'ok',
      'note'=>'اتفحصت وخلّصت معمل — ناقصها إذن استلام خام عشان تتشغّل.',
      'link'=>[route('goods-receipts.index'),'أذون الاستلام']])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['ready'], 'label'=>'أحواض جاهزة للتشغيل', 'tone'=>'ok',
      'sub'=>$money($fabric['ready_kg']).' كجم متاحة',
      'note'=>'مفرج عنها وعندها أقل عرض وبنشر — تقدر تعمل عليها أوامر شغل.',
      'link'=>[route('consignments.index',['ready'=>1]),'افتح']])
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$money($fabric['hold_kg']), 'label'=>'كجم محجوزة تحت الفحص', 'tone'=>'warn',
      'note'=>'قماش في المخزن ممنوع تشغيله لحد ما يتفرج عنه.'])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['roll_variance'], 'label'=>'فروق في جرد الأتواب',
      'tone'=>$fabric['roll_variance'] ? 'danger' : 'ok',
      'note'=>'تقارير فحص لقت عدد أتواب مختلف عن اللي المورد قال عليه.',
      'link'=>[route('inspections.index'),'راجعها']])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['width_alerts'], 'label'=>'تنبيهات فرق العرض',
      'tone'=>$fabric['width_alerts'] ? 'danger' : 'ok',
      'note'=>'فرق عرض كبير بين أتواب حوض واحد — مؤشر إن القماش نفسه فيه مشكلة.'])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$fabric['docs']['additions'], 'label'=>'مستندات الدورة', 'tone'=>'muted',
      'sub'=>'إذن إضافة',
      'note'=>$fabric['docs']['inspections'].' فحص · '.$fabric['docs']['labs'].' معمل · '.$fabric['docs']['receipts'].' استلام'])
  </div>
</div>

{{-- ══ ③ التشغيل ══ --}}
<div class="section-head">
  <h2>③ التشغيل</h2>
  <span class="hint">ماركر ← أمر شغل ← قص ← استلام</span>
  <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-plum py-0 ms-auto">أوامر الشغل</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$production['marker_requests'], 'label'=>'طلبات ماركر مفتوحة',
      'tone'=>$production['marker_requests'] ? 'warn' : 'ok',
      'note'=>'الباترونست لسه ما رفعش الماركر.',
      'link'=>[route('markers.requests'),'افتح']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$production['open'], 'label'=>'أوامر شغل مفتوحة', 'tone'=>'brand',
      'sub'=>$money($production['outstanding']).' قطعة',
      'note'=>'لسه على المصانع ولا اتقفلتش.'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$production['late'], 'label'=>'أوامر متأخرة',
      'tone'=>$production['late'] ? 'danger' : 'ok',
      'note'=>'فات تاريخ تسليمها ولسه مفتوحة.',
      'link'=>[route('work-orders.index',['late'=>1]),'افتح']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$production['cut_pending'], 'label'=>'مستنية بيان قص',
      'tone'=>$production['cut_pending'] ? 'warn' : 'ok',
      'note'=>'اتبعتت للمصنع ولسه ما جاش منها بيان قص.',
      'link'=>[route('cut-declarations.index'),'افتح']])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$production['danger'], 'label'=>'انحراف خارج الحدود',
      'tone'=>$production['danger'] ? 'danger' : 'ok',
      'note'=>'الفرق بين المتوقع والمقصوص تعدى '.config('lvplanning.variance.warn_pct').'% — محتاج سبب.'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>$money($production['received_month']), 'label'=>'قطع مستلمة الشهر ده', 'tone'=>'ok',
      'sub'=>$production['closed_month'].' أمر مقفول',
      'note'=>'إجمالي المنتج التام اللي دخل المخزن.'])
  </div>
</div>

{{-- ══ ④ التخطيط ══ --}}
<div class="section-head">
  <h2>④ التخطيط والتغطية</h2>
  <span class="hint">الرصيد يكفي كام يوم — بديل شغل «النواقص»</span>
  <a href="{{ route('planning.coverage') }}" class="btn btn-sm btn-outline-plum py-0 ms-auto">كل التغطية</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$planning['danger'], 'label'=>'موديلات في الخطر',
      'tone'=>$planning['danger'] ? 'danger' : 'ok',
      'note'=>'الرصيد يكفي '.config('lvplanning.coverage.danger_days').' يوم أو أقل — لازم تشغيل دلوقتي.'])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$planning['watch'], 'label'=>'تحت المراقبة', 'tone'=>'warn',
      'note'=>'تغطية بين '.config('lvplanning.coverage.danger_days').' و '.config('lvplanning.coverage.watch_days').' يوم.'])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$planning['ok'], 'label'=>'تغطية مريحة', 'tone'=>'ok',
      'note'=>'مفيش قلق على الموديلات دي حاليًا.'])
  </div>
  <div class="col-6 col-lg-3">
    @include('partials.kpi', ['value'=>$planning['unknown'], 'label'=>'مفيش مبيعات', 'tone'=>'muted',
      'note'=>'موديلات مالهاش حركة بيع مسجلة — التغطية مش محسوبة ليها.',
      'link'=>[route('io.index'),'ارفع المبيعات']])
  </div>
</div>


{{-- ══ تحليلات ══ --}}
<div class="section-head">
  <h2>التحليلات</h2>
  <span class="hint">الاتجاه العام، توزيع القماش، وشرايح التغطية</span>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-graph-up" aria-hidden="true"></i> المبيعات مقابل الإنتاج — آخر 12 شهر</span>
        <span class="hint">بننتج بقدر ما بنبيع ولا لأ؟</span>
      </div>
      <div class="card-body">
        <div style="position:relative;height:280px">
          <canvas id="chTrend" role="img"
                  aria-label="رسم بياني يقارن المبيعات الشهرية بالإنتاج المستلم والفوركاست خلال آخر 12 شهر"></canvas>
        </div>
      </div>
      <div class="card-footer bg-white hint">
        الأعمدة = المبيعات الفعلية · الخط الكامل = الإنتاج المستلم من المصانع ·
        الخط المتقطع = الفوركاست. لو الإنتاج تحت المبيعات باستمرار، المخزون بيتآكل.
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart" aria-hidden="true"></i> القماش واقف فين</div>
      <div class="card-body">
        <div style="position:relative;height:230px">
          <canvas id="chStatus" role="img" aria-label="توزيع الأحواض على حالات الدورة"></canvas>
        </div>
        <table class="table table-sm mt-3 mb-0">
          <thead><tr><th>الحالة</th><th>أحواض</th><th>كجم</th></tr></thead>
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
            <tr><td colspan="3" class="text-center text-muted py-2">مفيش أحواض لسه.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i> شرايح التغطية</div>
      <div class="card-body">
        <div style="position:relative;height:220px">
          <canvas id="chCoverage" role="img" aria-label="عدد الموديلات في كل شريحة تغطية"></canvas>
        </div>
      </div>
      <div class="card-footer bg-white hint">
        خطر = تغطية {{ config('lvplanning.coverage.danger_days') }} يوم أو أقل ·
        مراقبة = لحد {{ config('lvplanning.coverage.watch_days') }} يوم.
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar-event" aria-hidden="true"></i> المواعيد القادمة</span>
        <span class="hint">45 يوم جايين — المتأخر الأول</span>
      </div>
      <div class="table-responsive">
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
            <tr><td colspan="5" class="text-center text-muted py-4">مفيش مواعيد قريبة.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white hint">
        توريدات من الموردين + تسليمات من المصانع في مكان واحد.
      </div>
    </div>
  </div>
</div>

{{-- ══ الجداول ══ --}}
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> أخطر الموديلات في التغطية</span>
        <a href="{{ route('planning.coverage') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
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
            <tr><td colspan="5" class="text-center text-muted py-3">
              مفيش داتا مبيعات أو أرصدة — ارفعها من شاشة الاستيراد أو ولّد داتا ديمو.
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white hint">
        أيام التغطية = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي لآخر
        {{ config('lvplanning.avg_sales_window_days') }} يوم.
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-building"></i> تحميل المصانع</div>
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
            <tr><td colspan="5" class="text-center text-muted py-3">مفيش أوامر شغل مفتوحة.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white hint">
        الأيام التقديرية = القطع المتبقية ÷ الطاقة اليومية للمصنع.
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-square"></i> مستني اعتمادك</span>
        <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-outline-plum py-0">الكل</a>
      </div>
      <ul class="list-group list-group-flush">
        @forelse($myApprovals as $a)
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <div class="fw-bold num">{{ $a->subject_no }}</div>
              <div class="hint">{{ __('doc.'.$a->doc_type) }} — {{ $a->currentStepRow()?->title }}</div>
            </div>
            <span class="hint">{{ $a->created_at->diffForHumans() }}</span>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-3">مفيش حاجة مستنية اعتمادك.</li>
        @endforelse
      </ul>
      <div class="card-footer bg-white hint">دورة الاعتماد بتتظبط من الإعدادات ← دورات الاعتماد.</div>
    </div>

    <div class="card mb-3">
      <div class="card-header text-danger"><i class="bi bi-exclamation-triangle"></i> أوامر شغل متأخرة</div>
      <ul class="list-group list-group-flush">
        @forelse($lateOrders as $w)
          <li class="list-group-item py-2">
            <a href="{{ route('work-orders.show', $w) }}" class="fw-bold num">{{ $w->wo_no }}</a>
            <div class="hint">
              {{ $w->factory?->name }} · متأخر {{ (int) $w->due_date->diffInDays(now(), true) }} يوم ·
              متبقي {{ number_format($w->outstanding_pieces) }} قطعة
            </div>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-3">مفيش تأخير. تمام.</li>
        @endforelse
      </ul>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-chat-dots"></i> آخر النقاشات</div>
      <ul class="list-group list-group-flush">
        @forelse($talk as $t)
          <li class="list-group-item py-2">
            <div class="d-flex justify-content-between">
              <b style="font-size:.82rem">{{ $t->user?->name }}</b>
              <span class="hint">{{ $t->created_at->diffForHumans() }}</span>
            </div>
            <div class="hint">{{ Str::limit($t->body, 90) }}</div>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-3">مفيش نقاشات لسه.</li>
        @endforelse
      </ul>
      <div class="card-footer bg-white hint">
        كل مستند فيه خيط نقاش زي التيكيت — استفسارات وردود وصور إثبات.
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font.family = "'Cairo', sans-serif";
  Chart.defaults.font.size   = 11;
  Chart.defaults.color       = '#6B606A';
  Chart.defaults.plugins.legend.rtl = true;
  Chart.defaults.plugins.tooltip.rtl = true;
  Chart.defaults.plugins.tooltip.titleFont = {family: "'Cairo', sans-serif"};
  Chart.defaults.plugins.tooltip.bodyFont  = {family: "'Cairo', sans-serif"};
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
