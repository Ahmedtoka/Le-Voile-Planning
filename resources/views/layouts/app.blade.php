<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'تخطيط الإنتاج' }} — Le Voile</title>
<link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('assets/favicon-180.png') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════
   هوية Le Voile — #9D197E + أبيض + أوف وايت
   الألوان كلها متحققة على تباين 4.5:1 على الأقل للنص العادي.
   ═══════════════════════════════════════════════════════════════ */
:root{
  --lv-brand:      #9D197E;
  --lv-brand-dark: #7A1362;
  --lv-brand-deep: #560D45;
  --lv-brand-ink:  #3B092F;
  --lv-tint:       #F7E8F3;
  --lv-soft:       #ECD1E5;
  --lv-white:      #FFFFFF;
  --lv-offwhite:   #F8F4F1;
  --lv-ink:        #2B2028;   /* 14.8:1 على الأبيض */
  --lv-muted:      #6B606A;   /* 5.9:1 — كان #857A81 وكان تحت الحد */
  --lv-line:       #E3D9E0;

  /* حالات دلالية — كل واحدة معاها نص أو أيقونة، مش لون بس */
  --lv-ok:     #1B7A50;
  --lv-warn:   #9A6410;
  --lv-danger: #B5342B;
  --lv-info:   #1F5E8C;

  /* إيقاع المسافات 4/8 */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:24px; --sp-6:32px;
  --radius:10px;
}

*{font-family:'Cairo',system-ui,sans-serif}
body{background:var(--lv-offwhite);color:var(--lv-ink);font-size:.9rem;line-height:1.65}
a{text-decoration:none;color:var(--lv-brand)}
a:hover{color:var(--lv-brand-dark);text-decoration:underline}

/* ── تركيز واضح على كل عنصر تفاعلي (مطلب وصولية) ── */
a:focus-visible, button:focus-visible, input:focus-visible,
select:focus-visible, textarea:focus-visible, [tabindex]:focus-visible{
  outline:3px solid var(--lv-soft); outline-offset:2px; border-radius:6px;
}
.sidebar a:focus-visible, .sidebar button:focus-visible{
  outline:3px solid #E8C9DE; outline-offset:-3px;
}

/* ── تخطي للمحتوى — للكيبورد وقارئ الشاشة ── */
.skip-link{position:absolute;inset-inline-start:-9999px;top:8px;z-index:2000;
  background:var(--lv-brand);color:#fff;padding:10px 18px;border-radius:8px}
.skip-link:focus{inset-inline-start:8px;color:#fff}

/* ── القايمة الجانبية ── */
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:248px;
  background:linear-gradient(180deg,var(--lv-brand-deep),var(--lv-brand-ink));
  color:#F0E3EC;overflow-y:auto;z-index:1030}
.sidebar .brand{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.14);text-align:center}
.sidebar .brand img{width:150px;display:block;margin:0 auto 8px}
.sidebar .brand small{color:#DDB9CF;font-size:.72rem;letter-spacing:.5px}
.sidebar .nav-group-btn{display:flex;align-items:center;gap:9px;width:100%;border:0;background:none;
  color:#D2AAC4;font-size:.76rem;letter-spacing:.3px;padding:13px 16px 8px;text-align:start;min-height:40px}
.sidebar .nav-group-btn:hover{color:#fff}
.sidebar .nav-group-btn .caret{margin-inline-start:auto;font-size:.65rem;transition:transform .18s ease-out}
.sidebar .nav-group-btn[aria-expanded="true"]{color:#F2DCEA}
.sidebar .nav-group-btn[aria-expanded="true"] .caret{transform:rotate(180deg)}
.sidebar a.nav-item{display:flex;align-items:center;gap:9px;padding:9px 16px;color:#EEDFE8;
  font-size:.85rem;min-height:40px}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.09);color:#fff;text-decoration:none}
.sidebar a.nav-item.active{background:var(--lv-brand);color:#fff;font-weight:600;
  box-shadow:inset 3px 0 0 #F0D6E6}
.sidebar a.nav-item i{width:18px;text-align:center;opacity:.9}
.sidebar .step-no{margin-inline-start:auto;font-size:.62rem;color:#C49BB6;
  border:1px solid rgba(255,255,255,.22);border-radius:4px;padding:0 5px;line-height:15px}
.sidebar a.nav-item.active .step-no{color:#F5E3EE;border-color:rgba(255,255,255,.45)}
.sidebar .badge-count{margin-inline-start:6px;background:#D8394F;color:#fff;font-size:.68rem;
  min-width:20px;padding:2px 6px}
.sidebar .nav-group-btn .badge-count{margin-inline-start:auto}

/* ── الهيكل ── */
.main{margin-inline-start:248px}
.topbar{background:var(--lv-white);border-bottom:1px solid var(--lv-line);padding:10px 20px;
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020}
.topbar h2{font-size:1.05rem;margin:0;font-weight:700;color:var(--lv-brand-ink)}
.content{padding:20px 20px 48px}

/* ── عناوين الأقسام ── */
.section-head{display:flex;align-items:center;gap:10px;margin:var(--sp-6) 0 var(--sp-3)}
.section-head:first-child{margin-top:0}
.section-head h2{font-size:.98rem;margin:0;font-weight:700;color:var(--lv-brand-ink);
  padding-inline-start:10px;border-inline-start:4px solid var(--lv-brand);line-height:1.3}

/* ── الكروت والجداول ── */
.card{border:1px solid var(--lv-line);border-radius:var(--radius);
  box-shadow:0 1px 2px rgba(59,9,47,.04);background:var(--lv-white)}
.card-header{background:var(--lv-white);border-bottom:1px solid var(--lv-line);font-weight:600;
  border-radius:var(--radius) var(--radius) 0 0 !important;padding:.7rem 1rem;color:var(--lv-brand-ink)}
.table{font-size:.85rem;margin-bottom:0}
.table>thead>tr>th{background:var(--lv-tint);color:var(--lv-brand-ink);font-weight:600;
  white-space:nowrap;border-bottom:1px solid var(--lv-soft);font-size:.8rem}
.table>tbody>tr>td{vertical-align:middle}
.table-hover>tbody>tr:hover>*{background:var(--lv-tint)}

/* ── الأزرار: مساحة لمس كافية ── */
.btn{min-height:34px}
.btn-sm{min-height:31px}
.table .btn-sm{min-height:28px;min-width:28px;padding-inline:.45rem}
.btn-plum{background:var(--lv-brand);color:#fff;border:0}
.btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);color:#fff}
.btn-outline-plum{border:1px solid var(--lv-brand);color:var(--lv-brand);background:var(--lv-white)}
.btn-outline-plum:hover{background:var(--lv-brand);color:#fff}
.btn:disabled,.btn.disabled{opacity:.45;cursor:not-allowed}
.form-control:focus,.form-select:focus{border-color:var(--lv-soft);
  box-shadow:0 0 0 .2rem rgba(157,25,126,.16)}

/* ── العناصر ── */
.stat{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--radius);
  padding:14px 16px;height:100%}
.stat .v{font-size:1.55rem;font-weight:700;line-height:1.15;color:var(--lv-brand-ink);
  font-variant-numeric:tabular-nums}
.stat .l{color:var(--lv-muted);font-size:.79rem;line-height:1.45}
.form-label{font-size:.8rem;font-weight:600;margin-bottom:.2rem;color:#4C4150}
.form-control,.form-select{font-size:.85rem}
.form-control-sm,.form-select-sm{font-size:.8rem;min-height:31px}
.req::after{content:' *';color:var(--lv-danger)}
.badge{font-weight:600;font-size:.72rem;padding:.3em .55em}
.hint{font-size:.77rem;color:var(--lv-muted);line-height:1.6}
.note-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:var(--radius);
  padding:11px 13px;font-size:.81rem;color:#453040;line-height:1.75}
.calc-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:var(--radius);padding:14px}
.calc-box .kv{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--lv-soft)}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:1rem;color:var(--lv-brand);font-variant-numeric:tabular-nums}
.line-table input,.line-table select{border:1px solid #DDD2DA;border-radius:6px;padding:4px 6px;
  font-size:.8rem;width:100%;background:var(--lv-white);min-height:30px}
.line-table td{padding:.25rem}
.num{font-variant-numeric:tabular-nums;direction:ltr;text-align:center;display:inline-block}
.page-item.active .page-link{background:var(--lv-brand);border-color:var(--lv-brand)}
.page-link{color:var(--lv-brand);min-width:34px;min-height:34px}
.accordion-button:not(.collapsed){background:var(--lv-tint);color:var(--lv-brand-ink)}
.accordion-button:focus{box-shadow:0 0 0 .2rem rgba(157,25,126,.16)}
.list-group-item{border-color:var(--lv-line)}

/* ── حالة تحميل الأزرار: تمنع الدبل كليك ── */
.btn .spin{display:none;width:13px;height:13px;border:2px solid currentColor;border-top-color:transparent;
  border-radius:50%;animation:lvspin .6s linear infinite;margin-inline-end:6px;vertical-align:-2px}
.btn.is-loading .spin{display:inline-block}
.btn.is-loading{pointer-events:none;opacity:.75}
@keyframes lvspin{to{transform:rotate(360deg)}}

/* ── موبايل ── */
@media (max-width:900px){
  .sidebar{display:none}
  .main{margin-inline-start:0}
  .content{padding:14px 12px 40px}
  /* 16px بيمنع iOS من عمل zoom تلقائي عند التركيز */
  .form-control,.form-select,.form-control-sm,.form-select-sm,
  .line-table input,.line-table select{font-size:16px}
  .btn,.btn-sm{min-height:44px}
  .table .btn-sm{min-height:36px;min-width:36px}
}

/* ── احترام تقليل الحركة ── */
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms !important;animation-iteration-count:1 !important;
    transition-duration:.01ms !important;scroll-behavior:auto !important}
}

@media print{.sidebar,.topbar,.btn,.card-footer{display:none !important}.main{margin:0}}
</style>
</head>
<body>

@php
  $u   = auth()->user();
  $cnt = $navCounters ?? [];

  /* المنيو مرتّب بترتيب الفلو الحقيقي. كل مجموعة تاب بيتفتح ويتقفل،
     والتاب اللي فيه الشاشة المفتوحة بيفتح لوحده.
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, رقم الخطوة, الشرح] */
  $nav = [
    ['main', '', 'bi-grid', [
      ['dashboard', 'لوحة التحكم', 'bi-speedometer2', null, null,
       'صورة السيستم كله: الشراء، القماش، التشغيل، التغطية'],
    ]],

    ['buy', '① دورة الشراء', 'bi-cart3', [
      ['purchase-orders.index', 'طلبات الشراء', 'bi-file-earmark-text',
       'po.view|po.request|po.source|po.finance', null,
       'مستند واحد بيمر على التخطيط ثم المشتريات ثم الحسابات ثم الاعتماد'],
      ['finance.payables', 'المستحقات المتوقعة', 'bi-cash-coin', 'po.finance', null,
       'إيه اللي جاي على الحسابات فلوس، لمين، وإمتى'],
    ]],

    ['fabric', '② وصول القماش', 'bi-box-arrow-in-down', [
      ['stock-additions.index', 'إذن إضافة', 'bi-box-arrow-in-down', 'receipt.view', '1',
       'أول مستند: بيولّد الحوض ويحجز الكمية تحت الفحص'],
      ['inspections.index', 'تقرير فحص قماش', 'bi-search', 'qc.view', '2',
       'جرد الأتواب وقياس عرض وطول كل توب ⇐ أقل عرض'],
      ['lab-reports.index', 'تقرير المعمل', 'bi-thermometer-half', 'qc.view', '3',
       'وزن البنشر والانكماش ومطابقة اللون'],
      ['goods-receipts.index', 'إذن استلام خام', 'bi-truck', 'receipt.view', '4',
       'الإفراج — دلوقتي بس القماش يبقى متاح للتشغيل'],
      ['consignments.index', 'الأحواض', 'bi-box-seam', 'receipt.view', null,
       'كل رسالة قماش وحالتها ورصيدها المحجوز والمتاح'],
    ]],

    ['prod', '③ التشغيل', 'bi-hammer', [
      ['markers.requests', 'طلبات الماركر', 'bi-envelope-paper', 'marker.view', '1',
       'المخطط بيطلب تعشيقة على أقل عرض'],
      ['markers.index', 'الماركرات', 'bi-grid-3x3', 'marker.view', '2',
       'طول الفرشة وعدد القطع فيها والموديلات'],
      ['work-orders.index', 'أوامر الشغل', 'bi-hammer', 'wo.view', '3',
       'حوض + ماركر + مصنع ⇐ الحسبة والكميات المتوقعة'],
      ['cut-declarations.index', 'بيانات القص', 'bi-scissors', 'cut.view', '4',
       'الفعلي من المصنع — وأهم رقم فيه طول الفرشة الفعلي'],
      ['production-receipts.index', 'استلامات الإنتاج', 'bi-inboxes', 'prod.manage|wo.view', '5',
       'استلام على دفعات لحد ما أمر الشغل يتقفل'],
    ]],

    ['plan', '④ التخطيط', 'bi-graph-up-arrow', [
      ['planning.calculator', 'حاسبة التخطيط', 'bi-calculator', null, null,
       'جرّب الأرقام من غير ما تعمل مستند'],
      ['planning.coverage', 'أيام التغطية', 'bi-clock-history', 'forecast.view', null,
       'الرصيد يكفي كام يوم — بديل شغل النواقص'],
      ['planning.forecast', 'الفوركاست', 'bi-graph-up-arrow', 'forecast.view', null,
       'المتوقع شهريًا لكل موديل ولون، والفعلي مقابله'],
      ['planning.color-ratios', 'نسب الألوان', 'bi-pie-chart', 'forecast.view', null,
       'توزيع الموديل على الألوان — مستنتج من الصرف وقابل للتعديل'],
      ['planning.safety-stock', 'مخزون الأمان', 'bi-shield-check', 'forecast.view', null,
       'الكمية اللي بتتخصم قبل حساب التغطية'],
    ]],

    ['master', 'البيانات الأساسية', 'bi-database', [
      ['product-models.index', 'الموديلات', 'bi-tags', 'master.view', null, 'المنتجات ومقاساتها وقائمة إكسسواراتها'],
      ['colors.index', 'الألوان', 'bi-palette', 'master.view', null, 'أكواد الألوان — دمج وإيقاف، مفيش حذف'],
      ['fabric-types.index', 'الخامات', 'bi-layers', 'master.view', null, 'الخامات ومواصفاتها المعتمدة'],
      ['accessories.index', 'الإكسسوارات', 'bi-paperclip', 'master.view', null, 'الكياس والاستيكرات والزراير والسوست'],
      ['sizes.index', 'المقاسات', 'bi-rulers', 'master.view', null, 'المقاسات المتاحة'],
      ['suppliers.index', 'الموردين', 'bi-shop', 'master.view', null, 'الموردين وبياناتهم وشروط الدفع'],
      ['factories.index', 'المصانع', 'bi-building', 'master.view', null, 'المصانع وطاقتها اليومية ودورة تشغيلها'],
      ['warehouses.index', 'المخازن', 'bi-house-gear', 'master.view', null, 'المخازن وتاريخ آخر جرد'],
    ]],

    ['sys', 'النظام', 'bi-gear', [
      ['approvals.index', 'الاعتمادات', 'bi-check2-square', null, null, 'كل حاجة مستنية توقيعك'],
      ['io.index', 'استيراد وتصدير', 'bi-file-earmark-excel', 'import.manage', null, 'إكسيل: ألوان ومبيعات وأرصدة وتقارير'],
      ['settings.users', 'المستخدمين', 'bi-people', 'settings.users', null, 'إضافة مستخدمين وتحديد أدوارهم'],
      ['settings.roles', 'الأدوار والصلاحيات', 'bi-key', 'settings.roles', null, 'مين يشوف إيه ومين يعمل إيه'],
      ['settings.flows', 'دورات الاعتماد', 'bi-diagram-3', 'settings.flows', null, 'مين يعتمد إيه — بيتغيّر من غير كود'],
      ['settings.activity', 'سجل الحركة', 'bi-journal-text', 'settings.audit', null, 'مين عمل إيه وإمتى'],
      ['settings.data', 'أدوات الداتا', 'bi-database-gear', 'settings.data', null, 'مسح بيانات الشغل أو توليد داتا ديمو'],
    ]],
  ];

  $allowed = function (?string $perm) use ($u) {
      if (!$perm || !$u) return true;
      foreach (explode('|', $perm) as $p) if ($u->can2($p)) return true;
      return false;
  };
@endphp

<aside class="sidebar">
  <div class="brand">
    <a href="{{ route('dashboard') }}">
      <img src="{{ asset('assets/logo-white.png') }}" alt="Le Voile">
    </a>
    <small>نظام تخطيط الإنتاج</small>
  </div>

  @foreach($nav as [$gid, $group, $gicon, $items])
    @php
      $visible = array_values(array_filter($items, fn ($it) => Route::has($it[0]) && $allowed($it[3])));
      $groupCount = 0; $isOpen = false;
      foreach ($visible as $it) {
        $groupCount += $cnt[$it[0]] ?? 0;
        if (request()->routeIs(str_replace('.index','.*',$it[0])) || request()->routeIs($it[0])) $isOpen = true;
      }
    @endphp
    @continue(!count($visible))

    @if(!$group)
      @foreach($visible as [$route,$label,$icon,$perm,$step,$info])
        <a class="nav-item {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}"
           title="{{ $info }}">
          <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
        </a>
      @endforeach
      @continue
    @endif

    <button class="nav-group-btn {{ $isOpen ? 'open' : '' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#g-{{ $gid }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
      <i class="bi {{ $gicon }}"></i>
      <span>{{ $group }}</span>
      @if($groupCount > 0)<span class="badge badge-count rounded-pill">{{ $groupCount }}</span>@endif
      <i class="bi bi-chevron-down caret"></i>
    </button>

    <div class="collapse {{ $isOpen ? 'show' : '' }}" id="g-{{ $gid }}">
      @foreach($visible as [$route,$label,$icon,$perm,$step,$info])
        @php $n = $cnt[$route] ?? 0; @endphp
        <a class="nav-item {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'active' : '' }}"
           href="{{ route($route) }}" title="{{ $info }}">
          <i class="bi {{ $icon }}"></i>
          <span>{{ $label }}</span>
          @if($step)<span class="step-no">{{ $step }}</span>@endif
          @if($n > 0)<span class="badge badge-count rounded-pill">{{ $n }}</span>@endif
        </a>
      @endforeach
    </div>
  @endforeach
  <div style="height:30px"></div>
</aside>

<a href="#main-content" class="skip-link">تخطي للمحتوى</a>

<div class="main">
  <div class="topbar">
    <h2>{{ $title ?? '' }}</h2>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-light position-relative" title="الإشعارات" aria-label="الإشعارات">
        <i class="bi bi-bell"></i>
        @if(($navUnread ?? 0) > 0)
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $navUnread }}</span>
        @endif
      </a>
      <span class="hint">{{ $u?->name }} — {{ $u?->roleNames() }}</span>
      <form method="post" action="{{ route('logout') }}">@csrf
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-left"></i> خروج</button>
      </form>
    </div>
  </div>

  <main class="content" id="main-content" tabindex="-1">
    @include('partials.alerts')
    @yield('content')
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

/* أي فورم بيتبعت: الزرار يتقفل ويبان عليه إنه شغال — بيمنع الدبل سبمِت
   على الاعتمادات والمستندات، وبيدي المستخدم رد فعل خلال أقل من 100ms */
document.addEventListener('submit', e => {
  const f = e.target;
  if (!(f instanceof HTMLFormElement) || f.dataset.noBusy) return;
  const btn = f.querySelector('button[type="submit"], button:not([type])');
  if (!btn || btn.classList.contains('is-loading')) return;
  if (!btn.querySelector('.spin')) btn.insertAdjacentHTML('afterbegin', '<span class="spin"></span>');
  btn.classList.add('is-loading');
  setTimeout(() => btn.classList.remove('is-loading'), 12000);
}, true);
</script>
@stack('scripts')
</body>
</html>
