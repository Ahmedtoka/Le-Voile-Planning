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
   هوية Le Voile
   اللون الرسمي مأخوذ حرفيًا من ملف اللوجو: #9D197E
   الباليت: البراند + الأبيض + الأوف وايت — مفيش ألوان تانية
   ═══════════════════════════════════════════════════════════════ */
:root{
  --lv-brand:      #9D197E;   /* اللون الرسمي */
  --lv-brand-dark: #7A1362;   /* hover وحالات الضغط */
  --lv-brand-deep: #560D45;   /* خلفية القايمة الجانبية */
  --lv-brand-ink:  #3B092F;   /* أغمق درجة */
  --lv-tint:       #F7E8F3;   /* خلفية رؤوس الجداول */
  --lv-soft:       #ECD1E5;   /* حدود وبادچات فاتحة */
  --lv-white:      #FFFFFF;
  --lv-offwhite:   #F8F4F1;   /* خلفية الصفحة */
  --lv-ink:        #2B2028;
  --lv-muted:      #857A81;
  --lv-line:       #EAE2E7;
}
*{font-family:'Cairo',system-ui,sans-serif}
body{background:var(--lv-offwhite);color:var(--lv-ink);font-size:.9rem}
a{text-decoration:none;color:var(--lv-brand)}
a:hover{color:var(--lv-brand-dark)}

/* ── القايمة الجانبية ── */
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:245px;
  background:linear-gradient(180deg,var(--lv-brand-deep),var(--lv-brand-ink));
  color:#EFE2EB;overflow-y:auto;z-index:1030}
.sidebar .brand{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.13);text-align:center}
.sidebar .brand img{width:150px;display:block;margin:0 auto 8px}
.sidebar .brand small{color:#D6AFC9;font-size:.72rem;letter-spacing:.5px}
.sidebar .nav-group{padding:15px 16px 4px;font-size:.7rem;color:#C495B5;letter-spacing:.4px}
.sidebar a.nav-item{display:flex;align-items:center;gap:9px;padding:8px 16px;color:#EADCE5;font-size:.85rem}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.sidebar a.nav-item.active{background:var(--lv-brand);color:#fff;font-weight:600;
  box-shadow:inset 3px 0 0 var(--lv-soft)}
.sidebar a.nav-item i{width:18px;text-align:center;opacity:.85}
.sidebar .step-no{margin-inline-start:auto;font-size:.62rem;color:#9C7A90;
  border:1px solid rgba(255,255,255,.18);border-radius:4px;padding:0 4px;line-height:14px}
.sidebar .badge-count{margin-inline-start:6px;background:#E8506B;color:#fff;font-size:.68rem;
  min-width:19px;padding:2px 5px}
.sidebar a.nav-item.active .step-no{color:#F0D6E6;border-color:rgba(255,255,255,.35)}

/* ── الهيكل ── */
.main{margin-inline-start:245px}
.topbar{background:var(--lv-white);border-bottom:1px solid var(--lv-line);padding:10px 20px;
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020}
.topbar h2{font-size:1.05rem;margin:0;font-weight:700;color:var(--lv-brand-ink)}
.content{padding:20px}

/* ── الكروت والجداول ── */
.card{border:1px solid var(--lv-line);border-radius:10px;box-shadow:0 1px 2px rgba(59,9,47,.04);background:var(--lv-white)}
.card-header{background:var(--lv-white);border-bottom:1px solid var(--lv-line);font-weight:600;
  border-radius:10px 10px 0 0 !important;padding:.7rem 1rem;color:var(--lv-brand-ink)}
.table{font-size:.85rem;margin-bottom:0}
.table>thead>tr>th{background:var(--lv-tint);color:var(--lv-brand-ink);font-weight:600;
  white-space:nowrap;border-bottom:1px solid var(--lv-soft);font-size:.8rem}
.table>tbody>tr>td{vertical-align:middle}
.table-hover>tbody>tr:hover>*{background:var(--lv-tint)}

/* ── الأزرار ── */
.btn-plum{background:var(--lv-brand);color:#fff;border:0}
.btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);color:#fff}
.btn-outline-plum{border:1px solid var(--lv-brand);color:var(--lv-brand);background:var(--lv-white)}
.btn-outline-plum:hover{background:var(--lv-brand);color:#fff}
.form-control:focus,.form-select:focus{border-color:var(--lv-soft);
  box-shadow:0 0 0 .18rem rgba(157,25,126,.14)}

/* ── العناصر ── */
.stat{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:10px;padding:14px 16px;height:100%}
.stat .v{font-size:1.6rem;font-weight:700;line-height:1.1;color:var(--lv-brand-ink)}
.stat .l{color:var(--lv-muted);font-size:.78rem}
.form-label{font-size:.8rem;font-weight:600;margin-bottom:.2rem;color:#5A4E56}
.form-control,.form-select{font-size:.85rem}
.form-control-sm,.form-select-sm{font-size:.8rem}
.req::after{content:' *';color:#C0392B}
.badge{font-weight:600;font-size:.72rem}
.hint{font-size:.76rem;color:var(--lv-muted)}
.note-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:8px;
  padding:10px 12px;font-size:.8rem;color:#4A3A44}
.calc-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:10px;padding:14px}
.calc-box .kv{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--lv-soft)}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:1rem;color:var(--lv-brand)}
.line-table input,.line-table select{border:1px solid #E2D8DF;border-radius:6px;padding:3px 6px;
  font-size:.8rem;width:100%;background:var(--lv-white)}
.line-table td{padding:.25rem}
.num{font-variant-numeric:tabular-nums;direction:ltr;text-align:center;display:inline-block}
.page-item.active .page-link{background:var(--lv-brand);border-color:var(--lv-brand)}
.page-link{color:var(--lv-brand)}
.accordion-button:not(.collapsed){background:var(--lv-tint);color:var(--lv-brand-ink)}
.accordion-button:focus{box-shadow:0 0 0 .18rem rgba(157,25,126,.14)}
@media (max-width:900px){.sidebar{display:none}.main{margin-inline-start:0}}
</style>
</head>
<body>

@php
  $u = auth()->user();
  $cnt = $navCounters ?? [];

  /* المنيو مرتّب بترتيب الفلو الحقيقي — كل مجموعة خطوة، وجوّاها الترتيب اللي
     الورق بيمشي بيه. الرقم اللي جنب الشاشة معناه: فيه شغل عليك هنا دلوقتي.
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, رقم الخطوة] */
  $nav = [
    ['', [
      ['dashboard', 'لوحة التحكم', 'bi-speedometer2', null, null],
    ]],

    ['① دورة الشراء', [
      ['purchase-orders.index', 'طلبات الشراء',        'bi-file-earmark-text', 'po.view|po.request|po.source|po.finance', null],
      ['finance.payables',      'المستحقات المتوقعة',  'bi-cash-coin',         'po.finance', null],
    ]],

    ['② وصول القماش', [
      ['stock-additions.index', 'إذن إضافة',        'bi-box-arrow-in-down', 'receipt.view', '1'],
      ['inspections.index',     'تقرير فحص قماش',   'bi-search',            'qc.view',      '2'],
      ['lab-reports.index',     'تقرير المعمل',     'bi-thermometer-half',  'qc.view',      '3'],
      ['goods-receipts.index',  'إذن استلام خام',   'bi-truck',             'receipt.view', '4'],
      ['consignments.index',    'الأحواض',          'bi-box-seam',          'receipt.view', null],
    ]],

    ['③ التشغيل', [
      ['markers.requests',          'طلبات الماركر',    'bi-envelope-paper', 'marker.view',        '1'],
      ['markers.index',             'الماركرات',        'bi-grid-3x3',       'marker.view',        '2'],
      ['work-orders.index',         'أوامر الشغل',      'bi-hammer',         'wo.view',            '3'],
      ['cut-declarations.index',    'بيانات القص',      'bi-scissors',       'cut.view',           '4'],
      ['production-receipts.index', 'استلامات الإنتاج', 'bi-inboxes',        'prod.manage|wo.view','5'],
    ]],

    ['④ التخطيط', [
      ['planning.calculator',   'حاسبة التخطيط', 'bi-calculator',     null, null],
      ['planning.coverage',     'أيام التغطية',  'bi-clock-history',  'forecast.view', null],
      ['planning.forecast',     'الفوركاست',     'bi-graph-up-arrow', 'forecast.view', null],
      ['planning.color-ratios', 'نسب الألوان',   'bi-pie-chart',      'forecast.view', null],
      ['planning.safety-stock', 'مخزون الأمان',  'bi-shield-check',   'forecast.view', null],
    ]],

    ['البيانات الأساسية', [
      ['product-models.index', 'الموديلات',   'bi-tags',       'master.view', null],
      ['colors.index',         'الألوان',     'bi-palette',    'master.view', null],
      ['fabric-types.index',   'الخامات',     'bi-layers',     'master.view', null],
      ['accessories.index',    'الإكسسوارات', 'bi-paperclip',  'master.view', null],
      ['sizes.index',          'المقاسات',    'bi-rulers',     'master.view', null],
      ['suppliers.index',      'الموردين',    'bi-shop',       'master.view', null],
      ['factories.index',      'المصانع',     'bi-building',   'master.view', null],
      ['warehouses.index',     'المخازن',     'bi-house-gear', 'master.view', null],
    ]],

    ['النظام', [
      ['approvals.index',   'الاعتمادات',         'bi-check2-square',      null, null],
      ['io.index',          'استيراد وتصدير',     'bi-file-earmark-excel', 'import.manage', null],
      ['settings.users',    'المستخدمين',         'bi-people',             'settings.users', null],
      ['settings.roles',    'الأدوار والصلاحيات', 'bi-key',                'settings.roles', null],
      ['settings.flows',    'دورات الاعتماد',     'bi-diagram-3',          'settings.flows', null],
      ['settings.activity', 'سجل الحركة',         'bi-journal-text',       'settings.audit', null],
    ]],
  ];

  // اللينك يظهر بس لو المستخدم يقدر يفتحه — عشان ما يخبطش في 403
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

  @foreach($nav as [$group, $items])
    @php $visible = array_filter($items, fn ($it) => Route::has($it[0]) && $allowed($it[3])); @endphp
    @continue(!count($visible))
    @if($group)<div class="nav-group">{{ $group }}</div>@else<div style="height:10px"></div>@endif

    @foreach($visible as [$route, $label, $icon, $perm, $step])
      @php $n = $cnt[$route] ?? 0; @endphp
      <a class="nav-item {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'active' : '' }}"
         href="{{ route($route) }}">
        <i class="bi {{ $icon }}"></i>
        <span>{{ $label }}</span>
        @if($step)<span class="step-no">{{ $step }}</span>@endif
        @if($n > 0)<span class="badge badge-count rounded-pill" title="مستني منك">{{ $n }}</span>@endif
      </a>
    @endforeach
  @endforeach
  <div style="height:30px"></div>
</aside>

<div class="main">
  <div class="topbar">
    <h2>{{ $title ?? '' }}</h2>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-light position-relative" title="الإشعارات">
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

  <div class="content">
    @include('partials.alerts')
    @yield('content')
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
