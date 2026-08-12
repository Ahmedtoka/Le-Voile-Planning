<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'تخطيط الإنتاج' }} — Le Voile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --lv-plum:#6d2a5f; --lv-plum-dark:#4e1e44; --lv-plum-light:#f3e9f1;
  --lv-ink:#26202a; --lv-muted:#7a7280; --lv-line:#e7e2ea; --lv-bg:#f7f5f8;
}
*{font-family:'Cairo',system-ui,sans-serif}
body{background:var(--lv-bg);color:var(--lv-ink);font-size:.9rem}
a{text-decoration:none}
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:245px;background:var(--lv-plum-dark);
  color:#eadfe7;overflow-y:auto;z-index:1030}
.sidebar .brand{padding:18px 16px;border-bottom:1px solid rgba(255,255,255,.12)}
.sidebar .brand h1{font-size:1.25rem;margin:0;color:#fff;letter-spacing:.5px}
.sidebar .brand small{color:#c9a9c0;font-size:.72rem}
.sidebar .nav-group{padding:14px 16px 4px;font-size:.7rem;color:#b58aa9;letter-spacing:.4px}
.sidebar a.nav-item{display:flex;align-items:center;gap:9px;padding:8px 16px;color:#e3d3de;font-size:.85rem}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.07);color:#fff}
.sidebar a.nav-item.active{background:var(--lv-plum);color:#fff;font-weight:600;
  box-shadow:inset 3px 0 0 #e8c9de}
.sidebar a.nav-item i{width:18px;text-align:center;opacity:.85}
.sidebar .badge-count{margin-inline-start:auto;background:#e8c9de;color:var(--lv-plum-dark);font-size:.68rem}
.main{margin-inline-start:245px}
.topbar{background:#fff;border-bottom:1px solid var(--lv-line);padding:10px 20px;
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020}
.topbar h2{font-size:1.05rem;margin:0;font-weight:700}
.content{padding:20px}
.card{border:1px solid var(--lv-line);border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.card-header{background:#fff;border-bottom:1px solid var(--lv-line);font-weight:600;
  border-radius:10px 10px 0 0 !important;padding:.7rem 1rem}
.btn-plum{background:var(--lv-plum);color:#fff;border:0}
.btn-plum:hover{background:var(--lv-plum-dark);color:#fff}
.btn-outline-plum{border:1px solid var(--lv-plum);color:var(--lv-plum);background:#fff}
.btn-outline-plum:hover{background:var(--lv-plum);color:#fff}
.table{font-size:.85rem;margin-bottom:0}
.table>thead>tr>th{background:var(--lv-plum-light);color:var(--lv-plum-dark);font-weight:600;
  white-space:nowrap;border-bottom:1px solid var(--lv-line);font-size:.8rem}
.table>tbody>tr>td{vertical-align:middle}
.stat{background:#fff;border:1px solid var(--lv-line);border-radius:10px;padding:14px 16px;height:100%}
.stat .v{font-size:1.6rem;font-weight:700;line-height:1.1}
.stat .l{color:var(--lv-muted);font-size:.78rem}
.form-label{font-size:.8rem;font-weight:600;margin-bottom:.2rem;color:#4a4350}
.form-control,.form-select{font-size:.85rem}
.form-control-sm,.form-select-sm{font-size:.8rem}
.req::after{content:' *';color:#c0392b}
.badge{font-weight:600;font-size:.72rem}
.hint{font-size:.76rem;color:var(--lv-muted)}
.note-box{background:#fffbea;border:1px solid #f2e3ab;border-radius:8px;padding:10px 12px;font-size:.8rem}
.calc-box{background:var(--lv-plum-light);border:1px solid #dcc5d6;border-radius:10px;padding:14px}
.calc-box .kv{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #d9c2d3}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:1rem;color:var(--lv-plum-dark)}
.line-table input,.line-table select{border:1px solid #dfd9e4;border-radius:6px;padding:3px 6px;
  font-size:.8rem;width:100%;background:#fff}
.line-table td{padding:.25rem}
.num{font-variant-numeric:tabular-nums;direction:ltr;text-align:center;display:inline-block}
@media (max-width:900px){.sidebar{display:none}.main{margin-inline-start:0}}
</style>
</head>
<body>

@php
  $u = auth()->user();

  // العنصر: [اسم الراوت, العنوان, الأيقونة, الصلاحية المطلوبة (null = للكل)]
  $nav = [
    ['التشغيل', [
      ['dashboard',                'لوحة التحكم',        'bi-speedometer2',   null],
      ['consignments.index',       'الأحواض (الرسائل)',  'bi-box-seam',       'receipt.view'],
      ['work-orders.index',        'أوامر الشغل',        'bi-hammer',         'wo.view'],
      ['cut-declarations.index',   'بيانات القص',        'bi-scissors',       'cut.view'],
      ['production-receipts.index','استلامات الإنتاج',   'bi-inboxes',        'prod.manage|wo.view'],
    ]],
    ['المشتريات والمخازن', [
      ['purchase-orders.index',    'طلبات الشراء',       'bi-cart3',          'po.view'],
      ['goods-receipts.index',     'أذون استلام خام',    'bi-truck',          'receipt.view'],
      ['stock-additions.index',    'أذون الإضافة',       'bi-plus-square',    'receipt.view'],
    ]],
    ['الجودة', [
      ['inspections.index',        'فحص القماش',         'bi-search',         'qc.view'],
      ['lab-reports.index',        'تقارير المعمل',      'bi-thermometer-half','qc.view'],
    ]],
    ['التخطيط', [
      ['markers.requests',         'طلبات الماركر',      'bi-envelope-paper', 'marker.view'],
      ['markers.index',            'الماركرات',          'bi-grid-3x3',       'marker.view'],
      ['planning.calculator',      'حاسبة التخطيط',      'bi-calculator',     null],
      ['planning.coverage',        'أيام التغطية',       'bi-clock-history',  'forecast.view'],
      ['planning.forecast',        'الفوركاست',          'bi-graph-up-arrow', 'forecast.view'],
      ['planning.color-ratios',    'نسب الألوان',        'bi-pie-chart',      'forecast.view'],
      ['planning.safety-stock',    'مخزون الأمان',       'bi-shield-check',   'forecast.view'],
    ]],
    ['البيانات الأساسية', [
      ['product-models.index',     'الموديلات',          'bi-tags',           'master.view'],
      ['colors.index',             'الألوان',            'bi-palette',        'master.view'],
      ['fabric-types.index',       'الخامات',            'bi-layers',         'master.view'],
      ['accessories.index',        'الإكسسوارات',        'bi-paperclip',      'master.view'],
      ['sizes.index',              'المقاسات',           'bi-rulers',         'master.view'],
      ['suppliers.index',          'الموردين',           'bi-shop',           'master.view'],
      ['factories.index',          'المصانع',            'bi-building',       'master.view'],
      ['warehouses.index',         'المخازن',            'bi-house-gear',     'master.view'],
    ]],
    ['النظام', [
      ['approvals.index',          'الاعتمادات',         'bi-check2-square',  null],
      ['io.index',                 'استيراد وتصدير',     'bi-file-earmark-excel', 'import.manage'],
      ['settings.users',           'المستخدمين',         'bi-people',         'settings.users'],
      ['settings.roles',           'الأدوار والصلاحيات', 'bi-key',            'settings.roles'],
      ['settings.flows',           'دورات الاعتماد',     'bi-diagram-3',      'settings.flows'],
      ['settings.activity',        'سجل الحركة',         'bi-journal-text',   'settings.audit'],
    ]],
  ];

  // إظهار اللينك بس لو المستخدم يقدر يفتحه — عشان ما يخبطش في 403
  $allowed = function (?string $perm) use ($u) {
      if (!$perm || !$u) return true;
      foreach (explode('|', $perm) as $p) {
          if ($u->can2($p)) return true;
      }
      return false;
  };
@endphp

<aside class="sidebar">
  <div class="brand">
    <h1>Le Voile</h1>
    <small>نظام تخطيط الإنتاج</small>
  </div>
  @foreach($nav as [$group, $items])
    @php $visible = array_filter($items, fn ($it) => Route::has($it[0]) && $allowed($it[3])); @endphp
    @continue(!count($visible))
    <div class="nav-group">{{ $group }}</div>
    @foreach($visible as [$route, $label, $icon, $perm])
      <a class="nav-item {{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'active' : '' }}"
         href="{{ route($route) }}">
        <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
        @if($route === 'approvals.index' && ($navApprovals ?? 0) > 0)
          <span class="badge badge-count rounded-pill">{{ $navApprovals }}</span>
        @endif
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
