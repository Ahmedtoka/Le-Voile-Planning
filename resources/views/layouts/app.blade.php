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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════
   هوية Le Voile — نظام التصميم
   البراند #9D197E · الجرأة في مكانين بس: القايمة الجانبية وعنوان الصفحة.
   باقي الشاشة هادي: أبيض، خطوط رفيعة، ولون الحالة في نقطة صغيرة.
   كل ألوان النص متحققة على تباين 4.5:1 على الأقل.
   ═══════════════════════════════════════════════════════════════ */
:root{
  /* البراند */
  --lv-brand:      #9D197E;
  --lv-brand-dark: #7A1362;
  --lv-brand-deep: #560D45;
  --lv-brand-ink:  #3B092F;
  --lv-tint:       #F7E8F3;
  --lv-soft:       #ECD1E5;
  --lv-white:      #FFFFFF;
  --lv-offwhite:   #F8F4F1;

  /* المحايد */
  --lv-ink:        #2B2028;   /* 14.8:1 */
  --lv-ink-2:      #4A3F48;   /* 9.6:1 — عناوين الحقول ورأس الجداول */
  --lv-muted:      #6B606A;   /* 5.9:1 */
  --lv-line:       #E7DEE4;
  --lv-line-soft:  #F1ECEF;
  --lv-field:      #DCD2D9;   /* حدود الحقول */
  --lv-surface-2:  #FBF9FA;   /* رأس الجدول والفوتر */
  --lv-hover:      rgba(157,25,126,.045);

  /* حالات دلالية — نص + خلفية + حدود + نقطة */
  --lv-ok:     #1B7A50;  --lv-ok-bg:     #EAF6EF;  --lv-ok-line:     #C5E5D2;  --lv-ok-dot:     #22A06B;
  --lv-warn:   #8A5A0D;  --lv-warn-bg:   #FDF5E6;  --lv-warn-line:   #F0DDB6;  --lv-warn-dot:   #E0A100;
  --lv-danger: #B5342B;  --lv-danger-bg: #FCEFEE;  --lv-danger-line: #F3CDC9;  --lv-danger-dot: #D8394F;
  --lv-info:   #1F5E8C;  --lv-info-bg:   #EBF3FA;  --lv-info-line:   #C7DDEE;  --lv-info-dot:   #2F80C2;
  --lv-alert:  #D8394F;  /* الكاونتر الأحمر: شغل مستني منك */

  /* الخط */
  --ff-ui: 'IBM Plex Sans Arabic', system-ui, 'Segoe UI', Tahoma, sans-serif;
  --ff-display: 'Reem Kufi', 'IBM Plex Sans Arabic', system-ui, sans-serif;
  --fs-xs: .75rem;    /* 12 */
  --fs-sm: .8125rem;  /* 13 */
  --fs-base: .875rem; /* 14 */
  --fs-md: 1rem;      /* 16 */
  --fs-lg: 1.25rem;   /* 20 */
  --fs-xl: 1.5rem;    /* 24 */
  --fs-2xl: 1.875rem; /* 30 */

  /* إيقاع المسافات 4/8 */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:24px; --sp-6:32px; --sp-7:48px;

  /* الاستدارة والارتفاع */
  --r-sm:6px; --r-md:10px; --r-lg:14px; --r-pill:999px;
  --radius:10px;
  --e-0:none;
  --e-1:0 1px 2px rgba(59,9,47,.04);
  --e-2:0 2px 8px -2px rgba(59,9,47,.10), 0 1px 3px rgba(59,9,47,.05);
  --e-3:0 10px 28px -8px rgba(59,9,47,.22), 0 2px 6px rgba(59,9,47,.06);
  --ring: 0 0 0 3px rgba(157,25,126,.18);

  --nav-w:256px;
  --nav-rail:64px;
  --topbar-h:64px;
  --dur:180ms; --ease:cubic-bezier(.2,.7,.3,1);
}

*{font-family:var(--ff-ui)}
.bi::before{font-family:bootstrap-icons !important}
html{-webkit-text-size-adjust:100%}
body{background:var(--lv-offwhite);color:var(--lv-ink);font-size:var(--fs-base);line-height:1.6;
  font-feature-settings:"tnum" 0;-webkit-font-smoothing:antialiased}
h1,h2,h3,h4,h5,h6{color:var(--lv-brand-ink);font-weight:700}
a{text-decoration:none;color:var(--lv-brand)}
a:hover{color:var(--lv-brand-dark);text-decoration:underline;text-underline-offset:3px}
::selection{background:var(--lv-soft);color:var(--lv-brand-ink)}

/* ── تركيز واضح على كل عنصر تفاعلي ── */
a:focus-visible, button:focus-visible, input:focus-visible,
select:focus-visible, textarea:focus-visible, [tabindex]:focus-visible, summary:focus-visible{
  outline:2px solid var(--lv-brand); outline-offset:2px; border-radius:6px;
}
.form-control:focus-visible,.form-select:focus-visible,.form-check-input:focus-visible{outline:0}
main.content:focus-visible{outline:0}
.sidebar a:focus-visible, .sidebar button:focus-visible{
  outline:2px solid #F3C6E4; outline-offset:-2px; border-radius:8px;
}

.skip-link{position:absolute;inset-inline-start:-9999px;top:8px;z-index:2000;
  background:var(--lv-brand);color:#fff;padding:10px 18px;border-radius:8px}
.skip-link:focus{inset-inline-start:8px;color:#fff}

/* ══ القايمة الجانبية — المكان الجريء ══════════════════════════
   ① مفتوحة  ② مطوية (أيقونات + لوحة عايمة)  ③ موبايل (درج)        */
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:var(--nav-w);
  background:linear-gradient(180deg,var(--lv-brand-deep) 0%,var(--lv-brand-ink) 100%);
  color:#F0E3EC;overflow-y:auto;overflow-x:hidden;z-index:1040;
  transition:width var(--dur) var(--ease);
  scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}
.sidebar::-webkit-scrollbar{width:6px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:3px}

.sidebar .brand{padding:22px 18px 16px;text-align:start;position:relative}
.sidebar .brand a{display:inline-block;color:#fff}
.sidebar .brand a:hover{text-decoration:none}
.sidebar .brand img{width:116px;height:auto;display:block;transition:width var(--dur) var(--ease)}
.sidebar .brand .brand-word{font-family:var(--ff-display);font-size:1.6rem;color:#fff;line-height:1.1;letter-spacing:.5px}
.sidebar .brand small{color:#D9B4CC;font-size:var(--fs-xs);display:block;margin-top:8px;font-weight:500}

.nav-toggle{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 28px);
  margin:0 auto 10px;border:1px solid rgba(255,255,255,.14);border-radius:8px;
  background:transparent;color:#E4CCDC;font-size:var(--fs-xs);font-weight:500;
  min-height:32px;transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.nav-toggle:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-toggle i{font-size:.85rem;transition:transform var(--dur) var(--ease)}

/* المستوى الأول: زرار المجموعة */
.sidebar .nav-group{position:relative;padding:0 10px}
.sidebar .nav-group-btn{display:flex;align-items:center;gap:10px;width:100%;border:0;border-radius:8px;
  background:none;color:#EBD7E4;font-size:var(--fs-base);font-weight:600;
  padding:9px 10px;text-align:start;min-height:42px;position:relative;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar .nav-group-btn:hover{background:rgba(255,255,255,.07);color:#fff}
.sidebar .nav-group-btn .gi{width:22px;min-width:22px;text-align:center;font-size:1.02rem;opacity:.85}
.sidebar .nav-group-btn .caret{margin-inline-start:auto;font-size:.62rem;opacity:.55;
  transition:transform var(--dur) var(--ease)}
.sidebar .nav-group-btn[aria-expanded="true"]{color:#fff}
.sidebar .nav-group-btn[aria-expanded="true"] .gi{opacity:1}
.sidebar .nav-group-btn[aria-expanded="true"] .caret{transform:rotate(180deg);opacity:.9}
.sidebar .nav-group-btn .badge-count{margin-inline-start:auto;margin-inline-end:6px}
.sidebar .nav-group-btn .badge-count + .caret{margin-inline-start:0}

/* المستوى التاني: خط إرشادي رفيع بدل اللوحة التقيلة */
.sidebar .nav-sub{position:relative;margin:2px 0 8px;padding-inline-start:20px}
.sidebar .nav-sub::before{content:"";position:absolute;inset-block:4px;inset-inline-start:21px;
  width:1px;background:rgba(255,255,255,.14)}

.sidebar a.nav-item{display:flex;align-items:center;gap:10px;border-radius:8px;
  padding:7px 10px;padding-inline-start:16px;
  color:#D8C1D1;font-size:var(--fs-sm);min-height:38px;position:relative;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.07);color:#fff;text-decoration:none}
.sidebar a.nav-item .ni{width:18px;min-width:18px;text-align:center;opacity:.7;font-size:.9rem}
.sidebar a.nav-item:hover .ni{opacity:1}

/* الصفحة الحالية: لوح فاتح + علامة وردي ساطعة */
.sidebar a.nav-item.active{background:rgba(255,255,255,.13);color:#fff;font-weight:600}
.sidebar a.nav-item.active::before{content:"";position:absolute;inset-inline-start:0;inset-block:9px;
  width:3px;border-radius:3px;background:#F5B6E0}
.sidebar a.nav-item.active .ni{opacity:1;color:#F8CBEA}

/* عنصر مستوى أول من غير مجموعة (لوحة التحكم) */
.sidebar > a.nav-item{margin:0 10px 2px;padding-inline-start:10px;min-height:42px;
  color:#EBD7E4;font-size:var(--fs-base);font-weight:600}
.sidebar > a.nav-item .ni{width:22px;min-width:22px;font-size:1.02rem}

/* الكاونتر الأحمر = شغل مستني منك — لازم يبان */
.sidebar .badge-count{margin-inline-start:auto;background:var(--lv-alert)!important;color:#fff!important;
  border:0;font-size:.7rem;font-weight:700;min-width:22px;height:20px;padding:0 7px;line-height:20px;
  border-radius:var(--r-pill);font-variant-numeric:tabular-nums;font-family:var(--ff-ui);
  box-shadow:0 0 0 2px rgba(59,9,47,.65)}

.nav-sep{margin:16px 20px 6px;font-size:var(--fs-xs);color:#B892AC;font-weight:600}

.nav-sub-title{display:none}

/* ── ② الوضع المطوي ── */
body.nav-collapsed .sidebar{width:var(--nav-rail);overflow:visible}
body.nav-collapsed .sidebar .brand{padding:18px 6px 12px;text-align:center}
body.nav-collapsed .sidebar .brand img{width:40px;margin:0 auto}
body.nav-collapsed .sidebar .brand small,
body.nav-collapsed .nav-sep,
body.nav-collapsed .nav-toggle span,
body.nav-collapsed .sidebar .nav-group-btn > span:not(.badge),
body.nav-collapsed .sidebar .nav-group-btn .caret,
body.nav-collapsed .sidebar > a.nav-item > span:not(.badge){display:none}
body.nav-collapsed .sidebar .nav-group{padding:0 8px}
body.nav-collapsed .sidebar .nav-group-btn,
body.nav-collapsed .sidebar > a.nav-item{justify-content:center;padding-inline:0;gap:0}
body.nav-collapsed .sidebar > a.nav-item{margin-inline:8px}
body.nav-collapsed .sidebar > a.nav-item.active::before{display:none}
body.nav-collapsed .nav-toggle{width:44px;margin-inline:auto}
body.nav-collapsed .nav-toggle i{transform:rotate(180deg)}
body.nav-collapsed .nav-group-btn .badge-count{display:inline-block;position:absolute;
  inset-block-start:2px;inset-inline-end:0;margin:0;min-width:18px;height:17px;line-height:17px;padding:0 4px;font-size:.62rem}
body.nav-collapsed .main{margin-inline-start:var(--nav-rail)}

body.nav-collapsed .nav-group .nav-sub{position:absolute;inset-block-start:0;inset-inline-start:100%;
  width:236px;box-shadow:var(--e-3);z-index:1050;margin:0;padding:6px;
  background:var(--lv-brand-ink);border-radius:var(--r-md);
  max-height:min(70vh,440px);overflow-y:auto}
body.nav-collapsed .nav-group .nav-sub::before{display:none}
body.nav-collapsed .nav-group:hover > .nav-sub,
body.nav-collapsed .nav-group:focus-within > .nav-sub{display:block}
body.nav-collapsed .nav-sub-title{display:block;padding:8px 10px 8px;font-size:var(--fs-sm);font-weight:700;
  color:#fff;border-block-end:1px solid rgba(255,255,255,.12);margin-bottom:4px}
body.nav-collapsed .nav-group .nav-sub a.nav-item{padding-inline-start:12px}

body.nav-collapsed .sidebar > a.nav-item::after{
  content:attr(data-label);position:absolute;inset-inline-start:calc(100% + 10px);inset-block-start:9px;
  background:var(--lv-brand-ink);color:#fff;font-size:var(--fs-xs);padding:5px 10px;border-radius:var(--r-sm);
  white-space:nowrap;opacity:0;pointer-events:none;transition:opacity var(--dur) var(--ease);
  box-shadow:var(--e-2);z-index:1060}
body.nav-collapsed .sidebar > a.nav-item:hover::after,
body.nav-collapsed .sidebar > a.nav-item:focus-visible::after{opacity:1}

@media (max-height:620px){
  body.nav-collapsed .sidebar{overflow-y:auto}
  body.nav-collapsed .nav-group .nav-sub{position:static;width:auto;box-shadow:none;padding:0;
    max-height:none;overflow:visible;background:rgba(0,0,0,.2)}
  body.nav-collapsed .nav-sub-title{display:none}
  body.nav-collapsed .nav-group .nav-sub a.nav-item{justify-content:center;padding-inline:0}
  body.nav-collapsed .nav-group .nav-sub a.nav-item > span:not(.badge){display:none}
}

/* ══ الهيكل والعنوان ═══════════════════════════════════════════ */
.main{margin-inline-start:var(--nav-w);transition:margin var(--dur) var(--ease);min-height:100vh}
.topbar{background:rgba(255,255,255,.92);backdrop-filter:saturate(1.4) blur(8px);
  border-bottom:1px solid var(--lv-line);padding:0 28px;min-height:var(--topbar-h);
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020}
.topbar .page-title{display:flex;flex-direction:column;justify-content:center;min-width:0;padding:10px 0}
.topbar h2{font-family:var(--ff-display);font-size:1.4rem;margin:0;font-weight:600;color:var(--lv-brand-ink);
  line-height:1.25;letter-spacing:.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar .page-purpose{font-size:var(--fs-sm);color:var(--lv-muted);line-height:1.4;margin-top:2px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar .tb-actions{margin-inline-start:auto;display:flex;align-items:center;gap:6px;flex:0 0 auto}
.tb-icon{position:relative;display:inline-flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--lv-ink-2);font-size:1.08rem;transition:background var(--dur) var(--ease)}
.tb-icon:hover{background:var(--lv-tint);color:var(--lv-brand-dark);text-decoration:none}
.tb-icon .badge-alert{position:absolute;top:2px;inset-inline-end:0;min-width:18px;height:18px;line-height:18px;
  padding:0 5px;font-size:.66rem;box-shadow:0 0 0 2px #fff}
.tb-user{display:flex;align-items:center;gap:10px;padding:4px 6px 4px 4px;border-inline-start:1px solid var(--lv-line);
  margin-inline-start:6px;padding-inline-start:14px}
.tb-avatar{width:34px;height:34px;border-radius:50%;background:var(--lv-tint);color:var(--lv-brand);
  display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;flex:0 0 auto}
.tb-who{display:flex;flex-direction:column;line-height:1.25;min-width:0}
.tb-who b{font-size:var(--fs-sm);font-weight:600;color:var(--lv-ink)}
.tb-who span{font-size:var(--fs-xs);color:var(--lv-muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.nav-burger{display:none}
.nav-scrim{position:fixed;inset:0;background:rgba(27,10,22,.5);z-index:1035;
  opacity:0;pointer-events:none;transition:opacity var(--dur) var(--ease)}
body.nav-open .nav-scrim{opacity:1;pointer-events:auto}
.content{padding:var(--sp-5) 28px var(--sp-7);max-width:1600px}

/* ── عناوين الأقسام ── */
.section-head{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 12px;margin:var(--sp-6) 0 var(--sp-3)}
.section-head:first-child{margin-top:0}
.section-head h2{font-size:var(--fs-md);margin:0;font-weight:700;color:var(--lv-brand-ink);line-height:1.35}
.section-head .hint{font-size:var(--fs-sm)}

/* ── الكروت ── */
.card{border:1px solid var(--lv-line);border-radius:var(--r-md);
  box-shadow:var(--e-1);background:var(--lv-white);margin-bottom:var(--sp-4)}
.card:last-child{margin-bottom:0}
.card-header{background:var(--lv-white);border-bottom:1px solid var(--lv-line);font-weight:700;
  border-radius:var(--r-md) var(--r-md) 0 0 !important;padding:.8rem 1.1rem;color:var(--lv-brand-ink);
  font-size:var(--fs-base)}
.card-body{padding:1.1rem}
.card-footer{background:var(--lv-surface-2);border-top:1px solid var(--lv-line);border-radius:0 0 var(--r-md) var(--r-md) !important;
  padding:.65rem 1.1rem}

/* ── الجداول: رأس فاتح، صفوف مريحة، هوفر خفيف ── */
.table{font-size:var(--fs-sm);margin-bottom:0;color:var(--lv-ink);--bs-table-hover-bg:var(--lv-hover)}
.table>thead>tr>th{background:var(--lv-surface-2);color:var(--lv-ink-2);font-weight:600;
  white-space:nowrap;border-bottom:1px solid var(--lv-line);font-size:var(--fs-xs);
  padding:.65rem .75rem;vertical-align:middle}
.table>tbody>tr>td{vertical-align:middle;border-color:var(--lv-line-soft);
  padding:.7rem .75rem;line-height:1.5}
.table-sm>tbody>tr>td{padding:.6rem .7rem}
.table-sm>thead>tr>th{padding:.55rem .7rem}
.table>tbody>tr:hover>*{--bs-table-bg-state:var(--lv-hover)}
.table>tbody>tr:last-child>td{border-bottom:0}
.table>tfoot>tr>td,.table>tfoot>tr>th{border-top:1px solid var(--lv-line);font-size:var(--fs-sm);
  background:var(--lv-surface-2);font-weight:700}
.table>tbody>tr>th{background:var(--lv-surface-2);color:var(--lv-ink-2);font-weight:600;
  font-size:var(--fs-sm);border-color:var(--lv-line-soft);vertical-align:middle;white-space:nowrap}
.table-responsive{border-radius:0 0 var(--r-md) var(--r-md)}
.table a:not(.btn){font-weight:600}

.th-sort{color:inherit;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.th-sort:hover{color:var(--lv-brand);text-decoration:none}
.th-sort i{font-size:.68rem;opacity:.35}
.th-sort.on{color:var(--lv-brand-ink)}
.th-sort.on i{opacity:1;color:var(--lv-brand)}
th[aria-sort] .th-sort{font-weight:700}

/* حالة فاضية موحّدة */
.empty-state{padding:var(--sp-6) var(--sp-4);text-align:center;color:var(--lv-muted)}
.empty-state .ico{width:48px;height:48px;line-height:48px;border-radius:50%;background:var(--lv-tint);
  font-size:1.35rem;color:var(--lv-brand);display:inline-block;margin-bottom:var(--sp-3)}
.empty-state .t{font-weight:600;color:var(--lv-ink);font-size:var(--fs-base);margin-bottom:2px}

/* ── الأزرار: أساسي واحد، وثانوي هادي ── */
.btn{min-height:36px;border-radius:8px;font-weight:600;font-size:var(--fs-sm);
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease),border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.btn.w-100,.btn.d-block{display:flex}
.btn-sm{min-height:32px;font-size:var(--fs-xs);border-radius:7px;padding-inline:.7rem}
.btn-lg{min-height:44px;font-size:var(--fs-md)}
.btn:active{transform:none}
.btn:focus-visible{box-shadow:var(--ring);outline:0}
.table .btn-sm{min-height:30px;min-width:30px;padding-inline:.5rem}
.btn-plum{background:var(--lv-brand);color:#fff;border:1px solid var(--lv-brand)}
.btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);border-color:var(--lv-brand-dark);color:#fff}
.btn-plum:active{background:var(--lv-brand-deep)!important;color:#fff!important}
.btn-outline-plum{border:1px solid var(--lv-field);color:var(--lv-brand-dark);background:var(--lv-white)}
.btn-outline-plum:hover,.btn-outline-plum:focus{background:var(--lv-tint);color:var(--lv-brand-dark);border-color:var(--lv-soft)}
.btn-outline-plum:active{background:var(--lv-soft)!important;color:var(--lv-brand-ink)!important}
.btn-outline-secondary,.btn-light{--bs-btn-color:var(--lv-ink-2);--bs-btn-bg:var(--lv-white);--bs-btn-border-color:var(--lv-field);
  --bs-btn-hover-color:var(--lv-ink);--bs-btn-hover-bg:var(--lv-surface-2);--bs-btn-hover-border-color:#CDBFC8;
  --bs-btn-active-color:var(--lv-ink);--bs-btn-active-bg:var(--lv-line-soft);--bs-btn-active-border-color:#CDBFC8}
.btn-success{--bs-btn-bg:var(--lv-ok);--bs-btn-border-color:var(--lv-ok);--bs-btn-hover-bg:#156240;--bs-btn-hover-border-color:#156240}
.btn-danger{--bs-btn-bg:var(--lv-danger);--bs-btn-border-color:var(--lv-danger);--bs-btn-hover-bg:#962a23;--bs-btn-hover-border-color:#962a23}
.btn-outline-danger{--bs-btn-color:var(--lv-danger);--bs-btn-border-color:var(--lv-danger-line);--bs-btn-hover-bg:var(--lv-danger);--bs-btn-hover-border-color:var(--lv-danger)}
.btn-link{color:var(--lv-brand);font-weight:500}
.btn:disabled,.btn.disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
.actions-bar{display:flex;gap:var(--sp-2);flex-wrap:wrap;align-items:center}
.actions-bar .danger-zone{margin-inline-start:auto;padding-inline-start:var(--sp-3);
  border-inline-start:1px dashed var(--lv-line)}

/* ── الفورم ── */
.form-label{font-size:var(--fs-sm);font-weight:600;margin-bottom:6px;color:var(--lv-ink-2)}
.form-control,.form-select{font-size:var(--fs-base);border-radius:8px;border-color:var(--lv-field);
  min-height:38px;color:var(--lv-ink);transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
textarea.form-control{min-height:76px}
.form-control::placeholder{color:#A0939C}
.form-control:hover,.form-select:hover{border-color:#CDBFC8}
.form-control:focus,.form-select:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
.form-control[readonly]{background:var(--lv-surface-2);color:var(--lv-ink-2)}
.form-control:disabled,.form-select:disabled{background:var(--lv-line-soft)}
.form-control-sm,.form-select-sm{font-size:var(--fs-sm);min-height:32px;border-radius:7px}
.form-check-input:checked{background-color:var(--lv-brand);border-color:var(--lv-brand)}
.form-check-input:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
.form-text{color:var(--lv-muted);font-size:var(--fs-xs)}
.input-group-text{background:var(--lv-surface-2);border-color:var(--lv-field);font-size:var(--fs-sm);color:var(--lv-muted)}
.is-invalid{border-color:var(--lv-danger)!important}
.invalid-feedback{color:var(--lv-danger)}
.req::after{content:' *';color:var(--lv-danger);font-weight:700}

/* ── أرقام: KPI ── */
.stat{position:relative;background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  padding:16px 18px 14px;height:100%;transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.stat{container-type:inline-size}
.stat .v{font-size:clamp(1.25rem, 13cqi, var(--fs-2xl));font-weight:700;line-height:1.1;color:var(--lv-brand-ink);
  font-variant-numeric:tabular-nums;letter-spacing:-.3px;max-width:100%}
.stat .l{color:var(--lv-ink-2);font-size:var(--fs-sm);line-height:1.45;font-weight:500}
.stat .stat-head{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.stat .stat-value{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.stat .stat-sub{font-size:var(--fs-xs);color:var(--lv-muted);font-variant-numeric:tabular-nums}
.stat .stat-note{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.55;margin-top:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.stat .stat-link{margin-top:auto;padding-top:10px;font-size:var(--fs-xs);font-weight:600;color:var(--lv-brand);
  display:inline-flex;align-items:center;gap:4px;align-self:flex-start}
.stat .stat-link i{transition:transform var(--dur) var(--ease)}
.stat:hover .stat-link i{transform:translateX(-3px)}
.stat.is-link:hover{border-color:var(--lv-soft);box-shadow:var(--e-2)}
.stat.is-waiting{border-color:var(--lv-soft);background:linear-gradient(180deg,#FFFFFF 0%,#FDF8FB 100%)}
.stat.is-zero .v{color:#A79BA4}

/* نقطة الحالة */
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;flex:0 0 8px;background:var(--lv-line)}
.dot-brand{background:var(--lv-brand)} .dot-ok{background:var(--lv-ok-dot)} .dot-warn{background:var(--lv-warn-dot)}
.dot-danger{background:var(--lv-danger-dot)} .dot-info{background:var(--lv-info-dot)} .dot-muted{background:#BFB3BB}
.dot-ink{background:var(--lv-brand-ink)}
/* نص حالة بنقطة: <span class="status is-ok">مفرج</span> */
.status{display:inline-flex;align-items:center;gap:6px;font-size:var(--fs-xs);font-weight:600;color:var(--lv-ink-2);white-space:nowrap}
.status::before{content:"";width:7px;height:7px;border-radius:50%;background:#BFB3BB;flex:0 0 7px}
.status.is-ok{color:var(--lv-ok)} .status.is-ok::before{background:var(--lv-ok-dot)}
.status.is-warn{color:var(--lv-warn)} .status.is-warn::before{background:var(--lv-warn-dot)}
.status.is-danger{color:var(--lv-danger)} .status.is-danger::before{background:var(--lv-danger-dot)}
.status.is-info{color:var(--lv-info)} .status.is-info::before{background:var(--lv-info-dot)}
.status.is-brand{color:var(--lv-brand-dark)} .status.is-brand::before{background:var(--lv-brand)}

.badge{font-weight:600;font-size:.72rem;padding:.34em .65em;border-radius:var(--r-pill);font-variant-numeric:tabular-nums}
.hint{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.6}
.text-muted{color:var(--lv-muted)!important}

/* شرايح الحالة: خلفية خفيفة + نص — من غير حدود تقيلة */
.pill{display:inline-flex;align-items:center;gap:5px;font-size:var(--fs-xs);font-weight:600;line-height:1.5;
  padding:.18em .65em;border-radius:var(--r-pill);border:1px solid transparent;white-space:nowrap}
.pill-ok{background:var(--lv-ok-bg);color:var(--lv-ok);border-color:var(--lv-ok-line)}
.pill-warn{background:var(--lv-warn-bg);color:var(--lv-warn);border-color:var(--lv-warn-line)}
.pill-danger{background:var(--lv-danger-bg);color:var(--lv-danger);border-color:var(--lv-danger-line)}
.pill-info{background:var(--lv-info-bg);color:var(--lv-info);border-color:var(--lv-info-line)}
.pill-muted{background:var(--lv-surface-2);color:var(--lv-muted);border-color:var(--lv-line)}
.pill-brand{background:var(--lv-tint);color:var(--lv-brand-dark);border-color:var(--lv-soft)}
.pill .dot{width:6px;height:6px;flex-basis:6px}

.badge.bg-success,.badge.bg-warning,.badge.bg-danger,.badge.bg-info,
.badge.bg-secondary,.badge.bg-light,.badge.bg-primary,.badge.bg-dark{border:1px solid transparent}
.badge.bg-success{background:var(--lv-ok-bg)!important;color:var(--lv-ok)!important;border-color:var(--lv-ok-line)}
.badge.bg-warning{background:var(--lv-warn-bg)!important;color:var(--lv-warn)!important;border-color:var(--lv-warn-line)}
.badge.bg-danger{background:var(--lv-danger-bg)!important;color:var(--lv-danger)!important;border-color:var(--lv-danger-line)}
.badge.bg-info{background:var(--lv-info-bg)!important;color:var(--lv-info)!important;border-color:var(--lv-info-line)}
.badge.bg-secondary,.badge.bg-light,.badge.bg-dark{background:var(--lv-surface-2)!important;color:var(--lv-ink-2)!important;border-color:var(--lv-line)}
.badge.bg-primary{background:var(--lv-tint)!important;color:var(--lv-brand-dark)!important;border-color:var(--lv-soft)}
/* التنبيه الأحمر بيفضل صريح — ده تنبيه مش حالة */
.badge.badge-alert{background:var(--lv-alert)!important;color:#fff!important;border-color:var(--lv-alert)!important}

.table>tbody>tr.table-warning>td{background:var(--lv-warn-bg);--bs-table-bg:var(--lv-warn-bg)}
.table>tbody>tr.table-danger>td{background:var(--lv-danger-bg);--bs-table-bg:var(--lv-danger-bg)}
.table>tbody>tr.table-success>td{background:var(--lv-ok-bg);--bs-table-bg:var(--lv-ok-bg)}

.alert{border-radius:var(--r-md);font-size:var(--fs-sm);border-width:1px;display:block}
.alert>.bi:first-child{margin-inline-end:4px}
.alert-warning{background:var(--lv-warn-bg);border-color:var(--lv-warn-line);color:var(--lv-warn)}
.alert-danger{background:var(--lv-danger-bg);border-color:var(--lv-danger-line);color:var(--lv-danger)}
.alert-success{background:var(--lv-ok-bg);border-color:var(--lv-ok-line);color:var(--lv-ok)}
.alert-info{background:var(--lv-info-bg);border-color:var(--lv-info-line);color:var(--lv-info)}
.alert a{color:inherit;text-decoration:underline}

/* ملاحظة توضيحية: هادية، مش صندوق وردي صارخ */
.note-box{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  padding:10px 14px;font-size:var(--fs-sm);color:var(--lv-ink-2);line-height:1.75}
.note-box>.bi:first-child{color:var(--lv-brand);margin-inline-end:4px}
.calc-box{background:var(--lv-surface-2);border:1px solid var(--lv-line);border-radius:var(--r-md);padding:14px 16px}
.calc-box .kv{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px dashed var(--lv-line)}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:var(--fs-md);color:var(--lv-brand-ink);font-variant-numeric:tabular-nums}
.line-table input,.line-table select{border:1px solid var(--lv-field);border-radius:7px;padding:4px 8px;
  font-size:var(--fs-sm);width:100%;background:var(--lv-white);min-height:32px}
.line-table input:focus,.line-table select:focus{border-color:var(--lv-brand);box-shadow:var(--ring);outline:0}
.lines-locked input,.lines-locked select,.lines-locked button{pointer-events:none;
  background:var(--lv-surface-2);opacity:.75}
.lines-locked .btn{visibility:hidden}
.line-table td{padding:.3rem}
/* أرقام مصفوفة. ممنوع تغيير الـdisplay على td.num — بيتحط في 200+ مكان */
.num{font-variant-numeric:tabular-nums;direction:ltr;text-align:center}
span.num,b.num,strong.num,small.num,div.num{display:inline-block}
td.num,th.num{display:table-cell}
.pagination{--bs-pagination-border-radius:8px;margin:0;gap:4px}
.page-link{color:var(--lv-ink-2);min-width:34px;min-height:34px;border-color:var(--lv-line);border-radius:8px!important;
  display:inline-flex;align-items:center;justify-content:center;font-size:var(--fs-sm);font-variant-numeric:tabular-nums}
.page-link:hover{background:var(--lv-tint);color:var(--lv-brand-dark);border-color:var(--lv-soft)}
.page-item.active .page-link{background:var(--lv-brand);border-color:var(--lv-brand);color:#fff}
.page-link:focus{box-shadow:var(--ring)}
.accordion-button:not(.collapsed){background:var(--lv-tint);color:var(--lv-brand-ink)}
.accordion-button:focus{box-shadow:var(--ring)}
.list-group-item{border-color:var(--lv-line)}
.nav-tabs .nav-link{color:var(--lv-muted);font-weight:600}
.nav-tabs .nav-link.active{color:var(--lv-brand-ink)}
.nav-pills .nav-link.active{background:var(--lv-brand)}
.modal-content{border:0;border-radius:var(--r-lg);box-shadow:var(--e-3)}
.dropdown-menu{border-color:var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-3);font-size:var(--fs-sm)}

/* ── شرايح الفلاتر السريعة ── */
.chip{display:inline-flex;align-items:center;min-height:28px;padding:0 12px;border-radius:var(--r-pill);
  border:1px solid var(--lv-line);background:var(--lv-white);color:var(--lv-ink-2);font-size:var(--fs-xs);font-weight:500;
  transition:background var(--dur) var(--ease),border-color var(--dur) var(--ease)}
.chip:hover{background:var(--lv-tint);border-color:var(--lv-soft);color:var(--lv-brand-dark);text-decoration:none}
.chip.on{background:var(--lv-brand-ink);border-color:var(--lv-brand-ink);color:#fff}
.chip-bar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:10px 16px;border-bottom:1px solid var(--lv-line-soft)}

/* ── طابور «مستني منك» المشترك: لوحة التحكم ومكاتب الإدارات ── */
.section-head .count-badge{font-size:.72rem;min-width:24px;height:22px;line-height:22px;padding:0 8px;
  border-radius:var(--r-pill);background:var(--lv-alert);color:#fff;font-weight:700;text-align:center;
  font-variant-numeric:tabular-nums;align-self:center}

/* ① الطابور الشخصي: بلاطات صغيرة قابلة للضغط */
.q-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:var(--sp-3)}
.q-tile{position:relative;display:flex;flex-direction:column;gap:2px;padding:12px 14px 10px;
  background:linear-gradient(180deg,#fff 0%,#FDF8FB 100%);border:1px solid var(--lv-soft);border-radius:var(--r-md);
  color:var(--lv-ink);transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.q-tile:hover{border-color:#DDB3D2;box-shadow:var(--e-2);text-decoration:none;color:var(--lv-ink)}
.q-tile:focus-visible{outline:0;box-shadow:var(--ring)}
.q-tile .q-top{display:flex;align-items:baseline;gap:10px}
.q-tile .q-n{font-size:var(--fs-xl);font-weight:700;line-height:1.1;color:var(--lv-brand-ink);font-variant-numeric:tabular-nums}
.q-tile .q-l{font-size:var(--fs-sm);font-weight:600;color:var(--lv-ink-2);line-height:1.4}
.q-tile .q-go{margin-top:4px;font-size:var(--fs-xs);font-weight:600;color:var(--lv-brand);display:inline-flex;align-items:center;gap:4px}
.q-tile:hover .q-go i{transform:translateX(-3px)}
.q-tile .q-go i{transition:transform var(--dur) var(--ease)}
.calm-line{display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--lv-white);
  border:1px solid var(--lv-line);border-radius:var(--r-md);font-size:var(--fs-sm);color:var(--lv-ink-2)}
.calm-line .ic{width:28px;height:28px;flex:0 0 28px;border-radius:50%;background:var(--lv-ok-bg);color:var(--lv-ok);
  display:inline-flex;align-items:center;justify-content:center}
.calm-line .hint{margin-inline-start:auto}

.q-tile.is-zero{background:var(--lv-white);border-color:var(--lv-line)}
.q-tile.is-zero .q-n{color:#A79BA4}
.q-tile.is-zero .q-go{color:var(--lv-muted);font-weight:500}
.q-tile .q-l .dot{vertical-align:middle;margin-inline-end:6px}


/* ── شاشات المستندات: شريط الحفظ، شريط الإجراءات، الحقول المقروءة ── */
/* شريط الحفظ: الزرار الأساسي دايمًا ظاهر تحت الفورم وبيلزق في آخر الشاشة */
.form-actions{position:sticky;bottom:0;z-index:5;display:flex;align-items:center;gap:var(--sp-2) var(--sp-3);flex-wrap:wrap;
  margin-top:var(--sp-4);padding:12px 16px;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);
  border:1px solid var(--lv-line);border-radius:var(--r-md);box-shadow:0 -6px 18px -12px rgba(59,9,47,.25)}
.form-actions .btn-plum,.form-actions .btn-success{min-width:140px}
.card-footer.form-actions{border:0;border-top:1px solid var(--lv-line);border-radius:0 0 var(--r-md) var(--r-md)!important}
.form-actions .hint{font-size:var(--fs-sm)}
.form-actions .fa-end{margin-inline-start:auto;display:flex;gap:var(--sp-2);flex-wrap:wrap}
@media (max-width:600px){.form-actions{flex-wrap:nowrap;padding:10px 12px}.form-actions .hint{display:none}
  .form-actions .btn-plum,.form-actions .btn-success{min-width:0;flex:1 1 auto}}
/* شريط إجراءات المستند فوق التفاصيل */
.doc-actions{display:flex;align-items:center;gap:var(--sp-2);flex-wrap:wrap;margin-bottom:var(--sp-4);
  padding:10px 14px;background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md)}
.doc-actions .da-label{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:600;margin-inline-end:4px}
.doc-actions form{display:inline-flex;margin:0}
.doc-actions .da-end{margin-inline-start:auto;display:flex;gap:var(--sp-2);flex-wrap:wrap}
/* رأس الكارت اللي فيه زرار: نفس المحاذاة في كل الشاشات */
.card-header.d-flex{align-items:center;gap:var(--sp-2);flex-wrap:wrap}
.card-header .hint,.card-header small{font-weight:400}
/* قيمة مقروءة جوه فورم: باينة إنها مش حقل للكتابة */
.form-control[readonly],.form-control-plaintext.ro{background:var(--lv-surface-2);border-style:dashed;border-color:var(--lv-line);
  color:var(--lv-ink-2);cursor:default}
.form-control[readonly]:focus{box-shadow:none;border-color:var(--lv-line)}
/* جداول الإدخال: السيلكت ما يتقصّش */
.line-table select{min-width:64px;padding-inline:6px;text-overflow:ellipsis}
.line-table td.num input,.line-table input[type=number]{font-variant-numeric:tabular-nums}
.line-table thead th{white-space:nowrap}
/* أفعال الصف: مجموعة واحدة متلزقة */
.row-actions{display:inline-flex;gap:4px;flex-wrap:nowrap;justify-content:flex-end}
.row-actions form{display:inline-flex;margin:0}

/* ── حالة تحميل الأزرار ── */
.btn .spin{display:none;width:13px;height:13px;border:2px solid currentColor;border-top-color:transparent;
  border-radius:50%;animation:lvspin .6s linear infinite;vertical-align:-2px}
.btn.is-loading .spin{display:inline-block}
.btn.is-loading{pointer-events:none;opacity:.75}
@keyframes lvspin{to{transform:rotate(360deg)}}

/* ── شريط الفلو ── */
.flow-bar{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  margin-bottom:var(--sp-4);overflow:hidden}
.flow-bar .steps{display:flex;overflow-x:auto;scrollbar-width:none;padding:6px;gap:2px}
.flow-bar .steps::-webkit-scrollbar{display:none}
.flow-step{display:flex;align-items:center;gap:8px;padding:6px 12px;font-size:var(--fs-sm);border-radius:8px;
  color:var(--lv-muted);white-space:nowrap;position:relative;min-height:38px;flex:0 0 auto}
.flow-step + .flow-step::before{content:"";position:absolute;inset-inline-start:-2px;top:50%;width:2px;height:14px;
  transform:translateY(-50%);background:var(--lv-line);border-radius:2px}
.flow-step > .bi{display:none}
.flow-step .n{width:22px;height:22px;min-width:22px;border-radius:50%;background:var(--lv-white);
  border:1px solid var(--lv-field);display:inline-flex;align-items:center;justify-content:center;
  font-size:.7rem;font-weight:700;color:var(--lv-muted);font-variant-numeric:tabular-nums}
a.flow-step:hover{background:var(--lv-surface-2);color:var(--lv-ink);text-decoration:none}
.flow-step.done{color:var(--lv-ink-2)}
.flow-step.done .n{background:var(--lv-ok-bg);border-color:var(--lv-ok-line);color:var(--lv-ok)}
.flow-step.done > .bi-check2{display:inline;color:var(--lv-ok-dot)}
.flow-step.now{background:var(--lv-tint);color:var(--lv-brand-ink);font-weight:700}
.flow-step.now .n{background:var(--lv-brand);border-color:var(--lv-brand);color:#fff}
.flow-next{display:flex;gap:var(--sp-2);align-items:center;flex-wrap:wrap;padding:8px 12px;
  border-top:1px solid var(--lv-line-soft);background:var(--lv-surface-2);font-size:var(--fs-xs)}

/* ── مراحل الطلب ── */
.stepper{display:flex;align-items:flex-start}
.stepper .st{flex:1;text-align:center;position:relative;font-size:var(--fs-xs);color:var(--lv-muted)}
.stepper .st .c{width:32px;height:32px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;
  background:var(--lv-white);border:1px solid var(--lv-field);color:#A79BA4;font-size:.9rem;position:relative;z-index:1}
.stepper .st::after{content:"";position:absolute;top:16px;inset-inline-start:calc(50% + 20px);width:calc(100% - 40px);
  height:2px;background:var(--lv-line);border-radius:2px}
.stepper .st:last-child::after{display:none}
.stepper .st.done .c{background:var(--lv-brand);border-color:var(--lv-brand);color:#fff}
.stepper .st.done::after{background:var(--lv-brand)}
.stepper .st.done{color:var(--lv-ink-2)}
.stepper .st.now .c{background:var(--lv-tint);border:2px solid var(--lv-brand);color:var(--lv-brand)}
.stepper .st.now{color:var(--lv-brand-ink);font-weight:700}

/* ── رسالة نجاح بالخطوة الجاية ── */
.flow-toast{display:flex;align-items:flex-start;gap:14px;background:var(--lv-white);border:1px solid var(--lv-ok-line);
  border-radius:var(--r-md);padding:14px 16px;margin-bottom:var(--sp-4);box-shadow:var(--e-1)}
.flow-toast .ft-ico{width:34px;height:34px;flex:0 0 34px;border-radius:50%;background:var(--lv-ok-bg);color:var(--lv-ok);
  display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.flow-toast .ft-title{font-weight:700;color:var(--lv-ink);font-size:var(--fs-base)}
.flow-toast .ft-next{margin-top:6px;font-size:var(--fs-sm);color:var(--lv-ink-2)}

/* ── موبايل: المنيو درج ── */
@media (max-width:900px){
  .sidebar{width:280px;transform:translateX(100%);visibility:hidden;
    transition:transform var(--dur) var(--ease),visibility 0s var(--dur)}
  body.nav-open .sidebar{transform:translateX(0);visibility:visible;transition-delay:0s;box-shadow:var(--e-3)}
  .main{margin-inline-start:0 !important}
  .nav-burger{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
    width:42px;height:42px;border:1px solid var(--lv-line);border-radius:10px;
    background:var(--lv-white);color:var(--lv-brand-ink);font-size:1.25rem}
  .content{padding:var(--sp-4) var(--sp-4) var(--sp-6)}
  .topbar{padding:0 12px;gap:10px;min-height:58px}
  .topbar h2{font-size:1.12rem}
  .topbar .page-purpose{display:none}
  .tb-who,.tb-logout-label{display:none}
  .tb-user{border:0;margin:0;padding:0}
  .form-control,.form-select,.form-control-sm,.form-select-sm,
  .line-table input,.line-table select{font-size:16px}
  .btn,.btn-sm{min-height:44px}
  .table .btn-sm{min-height:36px;min-width:36px}
  .card{margin-bottom:var(--sp-3)}
  .stat{padding:14px}
  .stat .v{font-size:var(--fs-xl)}

  body.nav-collapsed .sidebar{width:280px;overflow-y:auto;overflow-x:hidden}
  body.nav-collapsed .sidebar .brand{padding:22px 18px 16px;text-align:start}
  body.nav-collapsed .sidebar .brand img{width:116px;margin:0}
  body.nav-collapsed .sidebar .brand small,
  body.nav-collapsed .nav-sep,
  body.nav-collapsed .sidebar .nav-group-btn > span,
  body.nav-collapsed .sidebar .nav-group-btn .caret,
  body.nav-collapsed .sidebar > a.nav-item > span{display:inline}
  body.nav-collapsed .sidebar .nav-group{padding:0 10px}
  body.nav-collapsed .sidebar .nav-group-btn,
  body.nav-collapsed .sidebar > a.nav-item{justify-content:flex-start;padding-inline:10px;gap:10px}
  body.nav-collapsed .sidebar > a.nav-item{margin-inline:10px}
  body.nav-collapsed .nav-group-btn .badge-count{position:static;margin-inline-start:auto;
    margin-inline-end:6px;min-width:22px;height:20px;line-height:20px;padding:0 7px;font-size:.7rem}
  body.nav-collapsed .nav-group .nav-sub{position:relative;width:auto;box-shadow:none;border-radius:0;background:none;
    max-height:none;overflow:visible;margin:2px 0 8px;padding:0;padding-inline-start:20px}
  body.nav-collapsed .nav-group .nav-sub::before{display:block}
  body.nav-collapsed .nav-group .nav-sub a.nav-item{padding-inline-start:16px;justify-content:flex-start}
  body.nav-collapsed .nav-group .nav-sub a.nav-item > span{display:inline}
  body.nav-collapsed .nav-group:hover > .nav-sub:not(.show),
  body.nav-collapsed .nav-group:focus-within > .nav-sub:not(.show){display:none}
  body.nav-collapsed .nav-sep{display:block}
  body.nav-collapsed .nav-group-btn .badge-count{display:inline-block}
  body.nav-collapsed .nav-sub-title{display:none}
  body.nav-collapsed .sidebar > a.nav-item::after{content:none}
  .nav-toggle{display:none}
}
@media (max-width:480px){
  .content{padding:var(--sp-3) var(--sp-3) var(--sp-6)}
  .stepper .st span.lbl{font-size:.68rem}
}
@media (min-width:901px){ .nav-scrim{display:none} }

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms !important;animation-iteration-count:1 !important;
    transition-duration:.01ms !important;scroll-behavior:auto !important}
}

@media print{.sidebar,.topbar,.btn,.card-footer{display:none !important}.main{margin:0}body{background:#fff}}
</style>
</head>
<body>

@php
  $u   = auth()->user();
  $cnt = $navCounters ?? [];

  /* المنيو مرتّب بترتيب الدورة الحقيقي، والخطوات مترقّمة 1 ← 13
     بنفس ترتيب «دليل الفلو». كل مجموعة تاب بيتفتح ويتقفل، والتاب
     اللي فيه الشاشة المفتوحة بيفتح لوحده.
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, الشرح]
     الرقم الأحمر هو الرقم الوحيد في المنيو، ومعناه: فيه شغل مستني منك هنا. */
  $nav = [
    ['main', '', 'bi-grid', [
      ['dashboard', 'لوحة التحكم', 'bi-speedometer2', null,
       'صورة السيستم كله: الشراء، القماش، التشغيل، التغطية'],
    ]],

    /* مكاتب الإدارات — بنفس ترتيب الدورة: كل واحد بيفتح مكتبه فيلاقي شغله مستنيه */
    ['desks', 'مكاتب الإدارات', 'bi-person-workspace', [
      ['desk.planning',   'التخطيط', 'bi-graph-up-arrow', 'wo.view|po.request|forecast.view',
       'طلب الخامة، أوامر الشغل، التغطية والفوركاست'],
      ['desk.purchasing', 'المشتريات', 'bi-cart3', 'po.source',
       'تسعير الطلبات وتحديد المورد وتاريخ التوريد'],
      ['desk.finance',    'الحسابات', 'bi-cash-coin', 'po.finance',
       'متابعة المستحق للموردين'],
      ['desk.store',      'المخزن', 'bi-box-seam', 'receipt.view|receipt.manage',
       'الاستلام والإفراج وصرف الخام واستلام الإنتاج'],
      ['desk.quality',    'الجودة والمعمل', 'bi-search', 'qc.view|qc.manage',
       'فحص الأتواب وقراءات البنشر والمرفوضات'],
      ['desk.factory',    'متابعة المصانع', 'bi-buildings', 'cut.view|cut.manage|prod.manage|wo.view',
       'بيانات القص واستلامات الإنتاج'],
    ]],

    ['buy', '① دورة الشراء', 'bi-cart3', [
      ['purchase-orders.index', '1 · طلبات الشراء', 'bi-file-earmark-text',
       'po.view|po.request|po.source|po.finance',
       'التخطيط بيطلب الخامة — الحفظ بينزّل الطلب للمشتريات على طول'],
      ['purchasing.queue', '2 · التسعير', 'bi-tag', 'po.source',
       'المشتريات: المورد والسعر وتاريخ التوريد — بعدها الطلب جاهز للاستلام'],
      ['finance.payables', '3 · المستحقات', 'bi-cash-coin', 'po.finance',
       'الحسابات: المستحق المتوقع لكل مورد — متابعة مش بوابة'],
    ]],

    ['fabric', '② وصول القماش', 'bi-box-arrow-in-down', [
      ['stock-additions.index', '4 · إذن إضافة', 'bi-box-arrow-in-down', 'receipt.view',
       'المخزن بيستلم على الطلب ⇐ كل (خامة + لون) رسالة محجوزة تحت الفحص'],
      ['inspections.index', '5 · فحص القماش', 'bi-search', 'qc.view',
       'جرد الأتواب وقياس عرض وطول كل توب ⇐ أقل عرض'],
      ['lab-reports.index', '6 · تقرير المعمل', 'bi-thermometer-half', 'qc.view',
       'وزن البنشر والانكماش ومطابقة اللون'],
      ['goods-receipts.index', '7 · إذن استلام خام', 'bi-truck', 'receipt.view',
       'الإفراج: السليم يبقى متاح للتشغيل والمرفوض يروح المرفوضات'],
      ['rejections.index', 'المرفوضات والمعلّق', 'bi-x-octagon', 'qc.view|receipt.view|po.view',
       'أتواب مرفوضة وألوان معلّقة لحين رد التخطيط والمشتريات'],
    ]],

    ['prod', '③ التشغيل', 'bi-hammer', [
      ['preparation-requests.index', 'طلبات التحضير (تقسيم)', 'bi-speedometer2', 'prep.request|prep.weigh',
       'أول خطوة: التخطيط يطلب كمية تقريبية من رسالة والمخزن يوزن ويرد بالفعلي — وطلب الماركر بيتعمل معاه'],
      ['markers.requests', '8 · طلبات الماركر', 'bi-envelope-paper', 'marker.view',
       'المخطط بيطلب تعشيقة على أقل عرض'],
      ['markers.index', '9 · الماركرات', 'bi-grid-3x3', 'marker.view',
       'الباترونست: طول الفرشة وعدد القطع والموديلات'],
      ['work-orders.index', '10 · أوامر الشغل', 'bi-hammer', 'wo.view',
       'ورقة المصنع: الرسالة + الماركر + الحسبة، والخامة الحاكمة بتحدد الإنتاج'],
      ['material-issues.index', '11 · إذن صرف خام', 'bi-box-arrow-up', 'receipt.view|wo.view',
       'صرف الخامة من المخزن للمصنع مقابل أمر الشغل — الجزئي مسموح'],
      ['cut-declarations.index', '12 · بيان القص', 'bi-scissors', 'cut.view',
       'الفعلي من المصنع وانحرافه عن المتوقع'],
      ['production-receipts.index', '13 · استلامات الإنتاج', 'bi-inboxes', 'prod.manage|wo.view',
       'استلام على دفعات لحد ما أمر الشغل يتقفل لوحده'],
      ['factory.portal', 'بوابة المصنع', 'bi-phone', 'factory.portal|cut.manage',
       'شاشة المصنع من الموبايل: أوامره، القص، والاستلام'],
    ]],

    ['stock', 'المخزون', 'bi-boxes', [
      ['consignments.index', 'الرسايل (الأحواض)', 'bi-box-seam', 'receipt.view',
       'كل رسالة قماش وحالتها ورصيدها المحجوز والمتاح'],
      ['stock.onhand', 'رصيد الرسايل', 'bi-clipboard-data', 'receipt.view',
       'نفس شيت ON Hand: رصيد كل رسالة — المتاح والمحجوز والأتواب'],
      ['stock.movements', 'حركة المخزون', 'bi-arrow-left-right', 'receipt.view',
       'نفس شيت IN&OUT: كل حركة داخلة وخارجة بمستندها'],
    ]],

    ['plan', 'التخطيط والفوركاست', 'bi-graph-up-arrow', [
      ['planning.calculator', 'حاسبة التخطيط', 'bi-calculator', null,
       'جرّب الأرقام من غير ما تعمل مستند'],
      ['planning.coverage', 'أيام التغطية', 'bi-clock-history', 'forecast.view',
       'الرصيد يكفي كام يوم — بديل شغل النواقص'],
      ['planning.forecast', 'الفوركاست', 'bi-graph-up-arrow', 'forecast.view',
       'المتوقع شهريًا لكل موديل ولون، والفعلي مقابله'],
      ['planning.color-ratios', 'نسب الألوان', 'bi-pie-chart', 'forecast.view',
       'توزيع الموديل على الألوان — مستنتج من الصرف وقابل للتعديل'],
      ['planning.safety-stock', 'مخزون الأمان', 'bi-shield-check', 'forecast.view',
       'الكمية اللي بتتخصم قبل حساب التغطية'],
    ]],

    /* بترتيب التجهيز: كل حاجة بتعتمد على اللي فوقها */
    ['master', 'البيانات الأساسية', 'bi-database', [
      ['suppliers.index', 'الموردين', 'bi-shop', 'master.view', 'الموردين وبياناتهم وشروط الدفع'],
      ['warehouses.index', 'المخازن', 'bi-house-gear', 'master.view', 'المخازن وتاريخ آخر جرد'],
      ['factories.index', 'المصانع', 'bi-building', 'master.view', 'المصانع وطاقتها اليومية ودورة تشغيلها'],
      ['fabric-types.index', 'الخامات', 'bi-layers', 'master.view', 'الخامات ومواصفاتها المعتمدة'],
      ['colors.index', 'الألوان', 'bi-palette', 'master.view', 'أكواد الألوان — دمج وإيقاف، مفيش حذف'],
      ['sizes.index', 'المقاسات', 'bi-rulers', 'master.view', 'المقاسات المتاحة'],
      ['accessories.index', 'الإكسسوارات', 'bi-paperclip', 'master.view', 'الكياس والاستيكرات والزراير والسوست'],
      ['product-models.index', 'الموديلات', 'bi-tags', 'master.view', 'المنتجات ومقاساتها وقائمة إكسسواراتها'],
    ]],

    ['sys', 'النظام', 'bi-gear', [
      ['io.index', 'استيراد وتصدير', 'bi-file-earmark-excel', 'import.manage', 'إكسيل: ألوان ومبيعات وأرصدة وتقارير'],
      ['settings.users', 'المستخدمين', 'bi-people', 'settings.users', 'إضافة مستخدمين وتحديد أدوارهم'],
      ['settings.roles', 'الأدوار والصلاحيات', 'bi-key', 'settings.roles', 'مين يشوف إيه ومين يعمل إيه'],
      ['settings.activity', 'سجل الحركة', 'bi-journal-text', 'settings.audit', 'مين عمل إيه وإمتى'],
      ['settings.data', 'أدوات الداتا', 'bi-database-gear', 'settings.data', 'مسح بيانات الشغل أو توليد داتا ديمو'],
    ]],
  ];

  $allowed = function (?string $perm) use ($u) {
      if (!$perm || !$u) return true;
      foreach (explode('|', $perm) as $p) if ($u->can2($p)) return true;
      return false;
  };
@endphp

<a href="#main-content" class="skip-link">تخطي للمحتوى</a>

<div class="nav-scrim" id="navScrim" aria-hidden="true"></div>

<aside class="sidebar" id="sideNav" aria-label="القايمة الرئيسية">
  <div class="brand">
    <a href="{{ route('dashboard') }}" aria-label="لوحة التحكم">
      <img src="{{ asset('assets/logo-white.png') }}" alt="Le Voile" width="128" height="36"
           onerror="this.hidden=true;this.nextElementSibling.hidden=false">
      <span class="brand-word" hidden>Le Voile</span>
    </a>
    <small>نظام تخطيط الإنتاج</small>
  </div>

  <button class="nav-toggle" type="button" id="navToggle"
          aria-pressed="false" title="طي / فتح القايمة">
    <i class="bi bi-chevron-double-right" aria-hidden="true"></i><span>اطوي القايمة</span>
  </button>

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
      @foreach($visible as [$route,$label,$icon,$perm,$info])
        <a class="nav-item {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}"
           data-label="{{ $label }}" title="{{ $info }}"
           @if(request()->routeIs($route)) aria-current="page" @endif>
          <i class="bi {{ $icon }} ni" aria-hidden="true"></i><span>{{ $label }}</span>
        </a>
      @endforeach
      <div class="nav-sep">دورة الشغل</div>
      @continue
    @endif

    {{-- المجموعة في حاوية واحدة — عشان اللوحة تطلع جنبها في الوضع المطوي --}}
    <div class="nav-group">
      <button class="nav-group-btn" type="button" data-label="{{ $group }}"
              data-bs-toggle="collapse" data-bs-target="#g-{{ $gid }}"
              aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="g-{{ $gid }}">
        <i class="bi {{ $gicon }} gi" aria-hidden="true"></i>
        <span>{{ $group }}</span>
        @if($groupCount > 0)
          <span class="badge badge-count rounded-pill">{{ $groupCount }}<span class="visually-hidden"> مستني منك</span></span>
        @endif
        <i class="bi bi-chevron-down caret" aria-hidden="true"></i>
      </button>

      <div class="collapse nav-sub {{ $isOpen ? 'show' : '' }}" id="g-{{ $gid }}" data-bs-parent="#sideNav">
        <div class="nav-sub-title">{{ $group }}</div>
        @foreach($visible as [$route,$label,$icon,$perm,$info])
          @php
            $on = request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route);
            $n  = $cnt[$route] ?? 0;
          @endphp
          <a class="nav-item {{ $on ? 'active' : '' }}" href="{{ route($route) }}" title="{{ $info }}"
             @if($on) aria-current="page" @endif>
            <i class="bi {{ $icon }} ni" aria-hidden="true"></i>
            <span>{{ $label }}</span>
            @if($n > 0)
              <span class="badge badge-count rounded-pill">{{ $n }}<span class="visually-hidden"> مستني منك</span></span>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  @endforeach
  <div style="height:30px"></div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="nav-burger" type="button" id="navBurger" aria-label="فتح القايمة" aria-expanded="false">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <div class="page-title">
      <h2>{{ $title ?? '' }}</h2>
      @php
        /* سطر الغرض: لو الشاشة ما حددتش، بناخده من شرح نفس القسم في القايمة الجانبية */
        $purpose = $purpose ?? null;
        if (!$purpose) {
          $rn = (string) request()->route()?->getName();
          foreach ($nav as $navGroup) foreach ($navGroup[3] as $it) {
            $base = str_replace('.index', '', $it[0]);
            if ($rn === $it[0] || str_starts_with($rn, $it[0] . '.') || ($base !== $it[0] && str_starts_with($rn, $base . '.'))) { $purpose = $it[4]; break 2; }
          }
        }
      @endphp
      @hasSection('purpose')
        <div class="page-purpose">@yield('purpose')</div>
      @elseif($purpose)
        <div class="page-purpose">{{ $purpose }}</div>
      @endif
    </div>
    <div class="tb-actions">
      <a href="{{ route('notifications.index') }}" class="tb-icon"
         title="الإشعارات" aria-label="الإشعارات{{ ($navUnread ?? 0) > 0 ? ' — ' . $navUnread . ' جديدة' : '' }}">
        <i class="bi bi-bell" aria-hidden="true"></i>
        @if(($navUnread ?? 0) > 0)
          <span class="badge badge-alert rounded-pill">{{ $navUnread }}</span>
        @endif
      </a>
      <div class="tb-user" title="{{ $u?->name }} — {{ $u?->roleNames() }}">
        <span class="tb-avatar" aria-hidden="true">{{ mb_substr($u?->name ?? '؟', 0, 1) }}</span>
        <span class="tb-who"><b>{{ $u?->name }}</b><span>{{ $u?->roleNames() }}</span></span>
      </div>
      <form method="post" action="{{ route('logout') }}">@csrf
        <button class="tb-icon" title="خروج" aria-label="تسجيل الخروج"><i class="bi bi-box-arrow-left" aria-hidden="true"></i></button>
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

/* ── القايمة: طي/فتح كامل + درج الموبايل ──────────────────────
   الحالة بتتحفظ في المتصفح، فالمنيو بيفتح بنفس شكله اللي سبته. */
(function () {
  var body = document.body, KEY = 'lv-nav-collapsed';

  try { if (localStorage.getItem(KEY) === '1') body.classList.add('nav-collapsed'); } catch (e) {}

  var toggle = document.getElementById('navToggle');
  if (toggle) {
    var sync = function () {
      var off = body.classList.contains('nav-collapsed');
      toggle.setAttribute('aria-pressed', off ? 'true' : 'false');
      toggle.title = off ? 'افتح القايمة' : 'اطوي القايمة';
      var t = toggle.querySelector('span'); if (t) t.textContent = off ? 'افتح' : 'اطوي القايمة';
    };
    sync();
    toggle.addEventListener('click', function () {
      body.classList.toggle('nav-collapsed');
      try { localStorage.setItem(KEY, body.classList.contains('nav-collapsed') ? '1' : '0'); } catch (e) {}
      sync();
    });
  }

  var burger = document.getElementById('navBurger'), scrim = document.getElementById('navScrim');
  var close = function (focusBack) {
    if (!body.classList.contains('nav-open')) return;
    body.classList.remove('nav-open');
    burger?.setAttribute('aria-expanded', 'false');
    if (focusBack) burger?.focus();          // الرجوع للزرار اللي فتح الدرج
  };
  burger?.addEventListener('click', function () {
    var on = body.classList.toggle('nav-open');
    burger.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (on) document.querySelector('#sideNav a, #sideNav button')?.focus();
  });
  scrim?.addEventListener('click', function () { close(true); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(true); });
  document.querySelectorAll('#sideNav a.nav-item').forEach(function (a) {
    a.addEventListener('click', function () { close(false); });
  });

  /* اللوحة العايمة في الوضع المطوي: لو هتنزل تحت حدود الشاشة، بنطلّعها لفوق */
  document.querySelectorAll('#sideNav .nav-group').forEach(function (g) {
    var place = function () {
      var sub = g.querySelector('.nav-sub');
      if (!sub || !body.classList.contains('nav-collapsed') || window.innerWidth <= 900) return;
      sub.style.top = '0px';
      var r = sub.getBoundingClientRect();
      var over = r.bottom - (window.innerHeight - 8);
      if (over > 0) sub.style.top = (-over) + 'px';
    };
    g.addEventListener('mouseenter', place);
    g.addEventListener('focusin', place);
    g.addEventListener('mouseleave', function () {
      var sub = g.querySelector('.nav-sub'); if (sub) sub.style.top = '';
    });
  });
})();

/* شريط الفلو على الشاشة الضيقة: الخطوة الحالية تبان في النص من غير سكرول يدوي */
(function () {
  var now = document.querySelector('.flow-bar .flow-step.now'), box = now && now.closest('.steps');
  if (!box || box.scrollWidth <= box.clientWidth) return;
  var b = box.getBoundingClientRect(), n = now.getBoundingClientRect();
  box.scrollLeft += (n.left - b.left) - (b.width - n.width) / 2;
})();

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
