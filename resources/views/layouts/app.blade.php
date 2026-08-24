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
  --lv-line-soft:  #F0EAEE;

  /* حالات دلالية — كل واحدة معاها نص أو أيقونة، مش لون بس */
  --lv-ok:     #1B7A50;  --lv-ok-bg:     #E8F5EE;  --lv-ok-line:     #BFE3CE;
  --lv-warn:   #8A5A0D;  --lv-warn-bg:   #FDF3E2;  --lv-warn-line:   #F0D9AE;
  --lv-danger: #B5342B;  --lv-danger-bg: #FCEDEC;  --lv-danger-line: #F3C9C5;
  --lv-info:   #1F5E8C;  --lv-info-bg:   #EAF3FA;  --lv-info-line:   #C3DCEE;

  /* إيقاع المسافات 4/8 */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:24px; --sp-6:32px; --sp-7:48px;

  /* سلّم الاستدارة والارتفاع — قيمة واحدة لكل مستوى، مفيش ارتجال */
  --r-sm:6px; --r-md:10px; --r-lg:14px; --r-pill:999px;
  --radius:10px;
  --e-0:none;
  --e-1:0 1px 2px rgba(59,9,47,.05);
  --e-2:0 2px 8px -2px rgba(59,9,47,.10), 0 1px 3px rgba(59,9,47,.05);
  --e-3:0 8px 24px -6px rgba(59,9,47,.16), 0 2px 6px rgba(59,9,47,.06);

  --nav-w:250px;   /* عرض المنيو المفتوح */
  --nav-rail:64px; /* عرض المنيو المطوي — أيقونات بس */
  --dur:180ms; --ease:cubic-bezier(.2,.7,.3,1);
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
  outline:2px solid #E8C9DE; outline-offset:-2px; border-radius:0;
}

/* ── تخطي للمحتوى — للكيبورد وقارئ الشاشة ── */
.skip-link{position:absolute;inset-inline-start:-9999px;top:8px;z-index:2000;
  background:var(--lv-brand);color:#fff;padding:10px 18px;border-radius:8px}
.skip-link:focus{inset-inline-start:8px;color:#fff}

/* ── القايمة الجانبية ──────────────────────────────────────────
   ثلاث حالات:
   ① مفتوحة   — مجموعات + صفحات
   ② مطوية    — أيقونات بس (rail)، والمجموعة بتفتح كـflyout عند الوقوف
   ③ موبايل   — درج بينزلق من الجنب مع طبقة تعتيم

   ولونين واضحين: المجموعات على خلفية القايمة، والصفحات على لوحة أغمق
   بشريط جانبي فاتح — عشان تعرف إنت جوه أنهي تاب من أول نظرة.
*/
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:var(--nav-w);
  background:linear-gradient(180deg,var(--lv-brand-deep) 0%,#4A0B3B 100%);
  color:#F0E3EC;overflow-y:auto;overflow-x:hidden;z-index:1040;
  border-inline-end:1px solid rgba(0,0,0,.25);
  transition:width var(--dur) var(--ease);
  scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.22) transparent}
.sidebar::-webkit-scrollbar{width:6px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:3px}

.sidebar .brand{padding:16px 14px 14px;border-bottom:1px solid rgba(255,255,255,.13);
  text-align:center;position:relative}
.sidebar .brand img{width:140px;display:block;margin:0 auto 6px;transition:width var(--dur) var(--ease)}
.sidebar .brand small{color:#DDB9CF;font-size:.71rem;letter-spacing:.4px;display:block}

/* زرار طي/فتح المنيو كله */
.nav-toggle{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 20px);
  margin:10px auto 6px;border:1px solid rgba(255,255,255,.16);border-radius:var(--r-sm);
  background:rgba(255,255,255,.06);color:#E9D2E1;font-size:.76rem;font-weight:600;
  min-height:34px;transition:background var(--dur) var(--ease)}
.nav-toggle:hover{background:rgba(255,255,255,.14);color:#fff}
.nav-toggle i{font-size:.9rem;transition:transform var(--dur) var(--ease)}

/* ── المستوى الأول: زرار المجموعة ── */
.sidebar .nav-group-btn{display:flex;align-items:center;gap:11px;width:100%;border:0;
  background:none;color:#E9D2E1;font-size:.855rem;font-weight:600;letter-spacing:.2px;
  padding:11px 16px;text-align:start;min-height:46px;position:relative;
  border-inline-start:3px solid transparent;transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar .nav-group-btn:hover{background:rgba(255,255,255,.08);color:#fff}
.sidebar .nav-group-btn .gi{width:22px;min-width:22px;text-align:center;font-size:1rem;opacity:.92}
.sidebar .nav-group-btn .caret{margin-inline-start:auto;font-size:.6rem;opacity:.65;
  transition:transform var(--dur) var(--ease)}

/* المجموعة المفتوحة */
.sidebar .nav-group-btn[aria-expanded="true"]{
  background:rgba(0,0,0,.3);color:#fff;border-inline-start-color:var(--lv-soft)}
.sidebar .nav-group-btn[aria-expanded="true"] .caret{transform:rotate(180deg);opacity:1}
.sidebar .nav-group-btn .badge-count{margin-inline-start:auto;margin-inline-end:8px}

/* ── المستوى التاني: لوحة أغمق بشريط جانبي ── */
.sidebar .nav-sub{background:var(--lv-brand-ink);
  border-inline-start:3px solid var(--lv-soft);
  box-shadow:inset 0 7px 9px -7px rgba(0,0,0,.55), inset 0 -7px 9px -7px rgba(0,0,0,.55)}

.sidebar a.nav-item{display:flex;align-items:center;gap:10px;
  padding:8px 14px;padding-inline-start:30px;
  color:#D6BFD0;font-size:.825rem;min-height:40px;position:relative;
  border-inline-start:3px solid transparent;margin-inline-start:-3px;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.09);color:#fff;text-decoration:none}
.sidebar a.nav-item .ni{width:18px;min-width:18px;text-align:center;opacity:.72;font-size:.85rem}
.sidebar a.nav-item:hover .ni{opacity:1}

/* الصفحة اللي إنت فيها دلوقتي */
.sidebar a.nav-item.active{
  background:var(--lv-brand);color:#fff;font-weight:600;
  border-inline-start-color:#fff}
.sidebar a.nav-item.active .ni{opacity:1}

/* عنصر مستوى أول من غير مجموعة (لوحة التحكم) */
.sidebar > a.nav-item{padding-inline-start:16px;margin-inline-start:0;min-height:46px;
  border-inline-start:3px solid transparent;color:#E9D2E1;font-size:.855rem;font-weight:600}
.sidebar > a.nav-item .ni{width:22px;min-width:22px;font-size:1rem}
.sidebar > a.nav-item.active{background:var(--lv-brand);border-inline-start-color:#fff}

.sidebar .badge-count{margin-inline-start:auto;background:#D8394F;color:#fff;font-size:.68rem;
  min-width:20px;padding:2px 6px;font-variant-numeric:tabular-nums}

.nav-sep{margin:10px 16px 4px;font-size:.66rem;letter-spacing:1px;color:#B892AC;
  text-transform:uppercase;font-weight:700}

.nav-group{position:relative}
.nav-sub-title{display:none}

/* ── ② الوضع المطوي: أيقونات بس ──
   ملاحظة مهمة: القايمة بتبقى overflow:visible هنا — لأن اللوحة العايمة
   واللافتات بيطلعوا برّه حدودها، ولو فيه أي overflow هيتقصّوا. الريل نفسه
   أيقونات بس فمساحته صغيرة ومش محتاج سكرول. */
body.nav-collapsed .sidebar{width:var(--nav-rail);overflow:visible}
body.nav-collapsed .sidebar .brand{padding:14px 6px 12px}
body.nav-collapsed .sidebar .brand img{width:38px}
body.nav-collapsed .sidebar .brand small,
body.nav-collapsed .nav-sep,
body.nav-collapsed .nav-toggle span,
body.nav-collapsed .sidebar .nav-group-btn > span:not(.badge),
body.nav-collapsed .sidebar .nav-group-btn .caret,
body.nav-collapsed .sidebar > a.nav-item > span:not(.badge){display:none}
body.nav-collapsed .sidebar .nav-group-btn,
body.nav-collapsed .sidebar > a.nav-item{justify-content:center;padding-inline:0;gap:0}
body.nav-collapsed .nav-toggle{width:44px;margin-inline:auto}
body.nav-collapsed .nav-toggle i{transform:rotate(180deg)}
body.nav-collapsed .nav-group-btn .badge-count{display:inline-block;position:absolute;
  inset-block-start:6px;inset-inline-end:8px;margin:0;min-width:17px;padding:1px 4px;font-size:.6rem}
body.nav-collapsed .main{margin-inline-start:var(--nav-rail)}

/* اللوحة العايمة: بتفتح بالوقوف أو بالكيبورد، وبتفضل مفتوحة لو الصفحة جواها.
   ليها سقف ارتفاع وسكرول لوحدها — عشان آخر مجموعة في القايمة ما تخرجش برّه الشاشة. */
body.nav-collapsed .nav-group .nav-sub{position:absolute;inset-block-start:0;inset-inline-start:100%;
  width:230px;box-shadow:var(--e-3);z-index:1050;border-inline-start:0;
  border-start-end-radius:var(--r-md);border-end-end-radius:var(--r-md);
  max-height:min(70vh,420px);overflow-y:auto}
body.nav-collapsed .nav-group:hover > .nav-sub,
body.nav-collapsed .nav-group:focus-within > .nav-sub{display:block}
body.nav-collapsed .nav-sub-title{display:block;padding:9px 14px 7px;font-size:.75rem;font-weight:700;
  color:#E9D2E1;letter-spacing:.3px;border-block-end:1px solid rgba(255,255,255,.12)}
body.nav-collapsed .nav-group .nav-sub a.nav-item{padding-inline-start:14px}

/* لافتة اسم التاب في الوضع المطوي */
body.nav-collapsed .sidebar > a.nav-item::after{
  content:attr(data-label);position:absolute;inset-inline-start:calc(100% + 8px);inset-block-start:9px;
  background:var(--lv-brand-ink);color:#fff;font-size:.75rem;padding:5px 10px;border-radius:var(--r-sm);
  white-space:nowrap;opacity:0;pointer-events:none;transition:opacity var(--dur) var(--ease);
  box-shadow:var(--e-2);z-index:1060}
body.nav-collapsed .sidebar > a.nav-item:hover::after,
body.nav-collapsed .sidebar > a.nav-item:focus-visible::after{opacity:1}

/* شاشة قصيرة: مفيش مكان للوحة عايمة — القايمة بترجع تسكرول والمجموعة
   بتفتح جواها أيقونات (الاسم بيبان في التلميح) */
@media (max-height:620px){
  body.nav-collapsed .sidebar{overflow-y:auto}
  body.nav-collapsed .nav-group .nav-sub{position:static;width:auto;box-shadow:none;
    max-height:none;overflow:visible;border-inline-start:3px solid var(--lv-soft)}
  body.nav-collapsed .nav-sub-title{display:none}
  body.nav-collapsed .nav-group .nav-sub a.nav-item{justify-content:center;padding-inline:0}
  body.nav-collapsed .nav-group .nav-sub a.nav-item > span:not(.badge){display:none}
}

/* ── الهيكل ── */
.main{margin-inline-start:var(--nav-w);transition:margin var(--dur) var(--ease);min-height:100vh}
.topbar{background:var(--lv-white);border-bottom:1px solid var(--lv-line);padding:10px 20px;
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020;
  box-shadow:0 1px 0 rgba(59,9,47,.03)}
.topbar h2{font-size:1.05rem;margin:0;font-weight:700;color:var(--lv-brand-ink)}
.nav-burger{display:none}
.nav-scrim{position:fixed;inset:0;background:rgba(27,10,22,.5);z-index:1035;
  opacity:0;pointer-events:none;transition:opacity var(--dur) var(--ease)}
body.nav-open .nav-scrim{opacity:1;pointer-events:auto}
.content{padding:var(--sp-5) var(--sp-5) var(--sp-7)}

/* ── عناوين الأقسام ── */
.section-head{display:flex;align-items:center;gap:10px;margin:var(--sp-6) 0 var(--sp-3)}
.section-head:first-child{margin-top:0}
.section-head h2{font-size:.98rem;margin:0;font-weight:700;color:var(--lv-brand-ink);
  padding-inline-start:10px;border-inline-start:4px solid var(--lv-brand);line-height:1.3}

/* ── الكروت ── */
.card{border:1px solid var(--lv-line);border-radius:var(--r-md);
  box-shadow:var(--e-1);background:var(--lv-white);margin-bottom:var(--sp-4)}
.card:last-child{margin-bottom:0}
.card-header{background:var(--lv-white);border-bottom:1px solid var(--lv-line);font-weight:700;
  border-radius:var(--r-md) var(--r-md) 0 0 !important;padding:.72rem 1rem;color:var(--lv-brand-ink);
  font-size:.88rem;letter-spacing:.1px}
.card-footer{border-top:1px solid var(--lv-line);border-radius:0 0 var(--r-md) var(--r-md) !important}

/* ── الجداول: من غير هوفر — هيدر لاصق واضح وفواصل هادية ── */
.table{font-size:.85rem;margin-bottom:0;--bs-table-hover-bg:transparent}
.table>thead>tr>th{background:var(--lv-tint);color:var(--lv-brand-ink);font-weight:700;
  white-space:nowrap;border-bottom:2px solid var(--lv-soft);font-size:.775rem;
  padding:.58rem .6rem;letter-spacing:.2px}
.table>tbody>tr>td{vertical-align:middle;border-color:var(--lv-line-soft);
  padding:.55rem .6rem;line-height:1.5}
.table>tbody>tr:last-child>td{border-bottom:0}
.table>tfoot>tr>td,.table>tfoot>tr>th{border-top:2px solid var(--lv-soft);font-size:.82rem;
  background:var(--lv-offwhite);font-weight:700}
/* جداول البيان/القيمة: الهيدر هو العمود الأول */
.table>tbody>tr>th{background:var(--lv-offwhite);color:#5A4E56;font-weight:600;
  font-size:.8rem;border-color:var(--lv-line-soft);vertical-align:middle;white-space:nowrap}
.table-responsive{border-radius:0 0 var(--r-md) var(--r-md)}

/* رأس عمود قابل للترتيب */
.th-sort{color:inherit;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.th-sort:hover{color:var(--lv-brand);text-decoration:none}
.th-sort i{font-size:.68rem;opacity:.35}
.th-sort.on i{opacity:1;color:var(--lv-brand)}
th[aria-sort] .th-sort{font-weight:800}

/* حالة فاضية موحّدة */
.empty-state{padding:var(--sp-6) var(--sp-4);text-align:center;color:var(--lv-muted)}
.empty-state .ico{font-size:1.9rem;color:var(--lv-soft);display:block;margin-bottom:var(--sp-2)}
.empty-state .t{font-weight:600;color:var(--lv-ink);font-size:.9rem;margin-bottom:2px}

/* ── الأزرار: مساحة لمس كافية + حالات ثابتة ما بتزقّش التخطيط ── */
.btn{min-height:34px;border-radius:var(--r-sm);font-weight:600;font-size:.82rem;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.btn-sm{min-height:31px;font-size:.79rem}
.btn:active{transform:none}
.table .btn-sm{min-height:28px;min-width:28px;padding-inline:.45rem}
.btn-plum{background:var(--lv-brand);color:#fff;border:1px solid var(--lv-brand);box-shadow:var(--e-1)}
.btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);border-color:var(--lv-brand-dark);color:#fff}
.btn-outline-plum{border:1px solid var(--lv-brand);color:var(--lv-brand);background:var(--lv-white)}
.btn-outline-plum:hover{background:var(--lv-tint);color:var(--lv-brand-dark);border-color:var(--lv-brand-dark)}
.btn:disabled,.btn.disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
/* الأكشن الخطر مفصول بصريًا */
.btn-danger,.btn-outline-danger{--bs-btn-color:#fff}
.actions-bar{display:flex;gap:var(--sp-2);flex-wrap:wrap;align-items:center}
.actions-bar .danger-zone{margin-inline-start:auto;padding-inline-start:var(--sp-3);
  border-inline-start:1px dashed var(--lv-line)}

.form-control:focus,.form-select:focus{border-color:var(--lv-soft);
  box-shadow:0 0 0 .2rem rgba(157,25,126,.16)}
.form-control[readonly]{background:var(--lv-offwhite);color:#5A4E56}

/* ── العناصر ── */
.stat{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  padding:14px 16px;height:100%;box-shadow:var(--e-1)}
.stat .v{font-size:1.55rem;font-weight:700;line-height:1.15;color:var(--lv-brand-ink);
  font-variant-numeric:tabular-nums}
.stat .l{color:var(--lv-muted);font-size:.79rem;line-height:1.45}
.form-label{font-size:.8rem;font-weight:600;margin-bottom:.25rem;color:#4C4150}
.form-control,.form-select{font-size:.85rem;border-radius:var(--r-sm);border-color:#DDD2DA}
.form-control-sm,.form-select-sm{font-size:.8rem;min-height:31px}
.req::after{content:' *';color:var(--lv-danger)}
.badge{font-weight:600;font-size:.72rem;padding:.32em .6em;border-radius:var(--r-pill)}
.hint{font-size:.77rem;color:var(--lv-muted);line-height:1.6}

/* شرايح الحالة: لون + حدود + نص — مش لون لوحده */
.pill{display:inline-flex;align-items:center;gap:5px;font-size:.73rem;font-weight:600;
  padding:.22em .62em;border-radius:var(--r-pill);border:1px solid transparent;white-space:nowrap}
.pill-ok{background:var(--lv-ok-bg);color:var(--lv-ok);border-color:var(--lv-ok-line)}
.pill-warn{background:var(--lv-warn-bg);color:var(--lv-warn);border-color:var(--lv-warn-line)}
.pill-danger{background:var(--lv-danger-bg);color:var(--lv-danger);border-color:var(--lv-danger-line)}
.pill-info{background:var(--lv-info-bg);color:var(--lv-info);border-color:var(--lv-info-line)}
.pill-muted{background:var(--lv-offwhite);color:var(--lv-muted);border-color:var(--lv-line)}

/* بادچات الحالة الجاهزة من بوتستراب: بنعيد تلوينها بحدود ونص مقروء —
   الأصفر الافتراضي بنص أبيض كان تباينه تحت الحد بكتير. */
.badge.bg-success,.badge.bg-warning,.badge.bg-danger,.badge.bg-info,
.badge.bg-secondary,.badge.bg-light,.badge.bg-primary{border:1px solid transparent}
.badge.bg-success{background:var(--lv-ok-bg)!important;color:var(--lv-ok)!important;border-color:var(--lv-ok-line)}
.badge.bg-warning{background:var(--lv-warn-bg)!important;color:var(--lv-warn)!important;border-color:var(--lv-warn-line)}
.badge.bg-danger{background:var(--lv-danger-bg)!important;color:var(--lv-danger)!important;border-color:var(--lv-danger-line)}
.badge.bg-info{background:var(--lv-info-bg)!important;color:var(--lv-info)!important;border-color:var(--lv-info-line)}
.badge.bg-secondary,.badge.bg-light,.badge.bg-dark{background:var(--lv-offwhite)!important;color:#5A4E56!important;border-color:var(--lv-line)}
/* التنبيه الأحمر (إشعارات غير مقروءة / كاونتر المنيو) بيفضل صريح — ده تنبيه مش حالة */
.badge.badge-alert{background:#D8394F!important;color:#fff!important;border-color:#D8394F!important}
.badge.bg-primary{background:var(--lv-tint)!important;color:var(--lv-brand-dark)!important;border-color:var(--lv-soft)}
/* الكاونتر الأحمر في المنيو بيفضل صريح — ده تنبيه مش حالة */
.sidebar .badge-count{background:#D8394F!important;color:#fff!important;border:0}

/* صفوف الحالات في الجداول — خفيفة عشان ما تغطّيش على النص */
.table>tbody>tr.table-warning>td{background:var(--lv-warn-bg);--bs-table-bg:var(--lv-warn-bg)}
.table>tbody>tr.table-danger>td{background:var(--lv-danger-bg);--bs-table-bg:var(--lv-danger-bg)}
.table>tbody>tr.table-success>td{background:var(--lv-ok-bg);--bs-table-bg:var(--lv-ok-bg)}

/* التنبيهات بنفس لغة الألوان */
.alert{border-radius:var(--r-md);font-size:.83rem;border-width:1px}
.alert-warning{background:var(--lv-warn-bg);border-color:var(--lv-warn-line);color:var(--lv-warn)}
.alert-danger{background:var(--lv-danger-bg);border-color:var(--lv-danger-line);color:var(--lv-danger)}
.alert-success{background:var(--lv-ok-bg);border-color:var(--lv-ok-line);color:var(--lv-ok)}
.alert-info{background:var(--lv-info-bg);border-color:var(--lv-info-line);color:var(--lv-info)}
.alert a{color:inherit;text-decoration:underline}

.note-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:var(--r-md);
  padding:11px 13px;font-size:.81rem;color:#453040;line-height:1.75}
.calc-box{background:var(--lv-tint);border:1px solid var(--lv-soft);border-radius:var(--r-md);padding:14px}
.calc-box .kv{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--lv-soft)}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:1rem;color:var(--lv-brand);font-variant-numeric:tabular-nums}
.line-table input,.line-table select{border:1px solid #DDD2DA;border-radius:6px;padding:4px 6px;
  font-size:.8rem;width:100%;background:var(--lv-white);min-height:30px}
/* المستند المعتمد: سطوره للقراءة بس */
.lines-locked input,.lines-locked select,.lines-locked button{pointer-events:none;
  background:var(--lv-offwhite);opacity:.75}
.lines-locked .btn{visibility:hidden}
.line-table td{padding:.25rem}
/* أرقام مصفوفة: عرض ثابت لكل رقم + اتجاه لاتيني.
   ممنوع تغيير الـdisplay هنا — الكلاس ده بيتحط على <td> نفسه في 200+ مكان،
   وأي display غير table-cell بيخرّج الخلية من الجدول وينثر الصفوف. */
.num{font-variant-numeric:tabular-nums;direction:ltr;text-align:center}
span.num,b.num,strong.num,small.num,div.num{display:inline-block}
td.num,th.num{display:table-cell}
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

/* ── شريط الفلو: إنت فين، واللي قبلك واللي بعدك ── */
.flow-bar{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  box-shadow:var(--e-1);margin-bottom:var(--sp-4);overflow:hidden}
.flow-bar .steps{display:flex;overflow-x:auto;scrollbar-width:none}
.flow-bar .steps::-webkit-scrollbar{display:none}
.flow-step{display:flex;align-items:center;gap:7px;padding:9px 14px 9px 12px;font-size:.78rem;
  color:var(--lv-muted);white-space:nowrap;position:relative;min-height:42px;
  border-inline-end:1px solid var(--lv-line-soft);flex:0 0 auto}
.flow-step:last-child{border-inline-end:0}
.flow-step .n{width:20px;height:20px;min-width:20px;border-radius:50%;background:var(--lv-offwhite);
  border:1px solid var(--lv-line);display:inline-flex;align-items:center;justify-content:center;
  font-size:.68rem;font-weight:700;color:var(--lv-muted);font-variant-numeric:tabular-nums}
a.flow-step:hover{background:var(--lv-tint);color:var(--lv-brand-dark);text-decoration:none}
.flow-step.done{color:var(--lv-ok)}
.flow-step.done .n{background:var(--lv-ok-bg);border-color:var(--lv-ok-line);color:var(--lv-ok)}
.flow-step.now{background:var(--lv-brand);color:#fff;font-weight:700}
.flow-step.now .n{background:rgba(255,255,255,.24);border-color:rgba(255,255,255,.5);color:#fff}
.flow-next{display:flex;gap:var(--sp-2);align-items:center;padding:8px 12px;
  border-top:1px solid var(--lv-line-soft);background:var(--lv-offwhite);font-size:.78rem}

/* ── موبايل: المنيو بقت درج ── */
@media (max-width:900px){
  /* الدرج مقفول = مخفي فعلًا، مش بس مزقوق برّه — عشان ما ياخدش دور
     في الكيبورد ولا يتقري لقارئ الشاشة */
  .sidebar{width:264px;transform:translateX(100%);visibility:hidden;
    transition:transform var(--dur) var(--ease),visibility 0s var(--dur)}
  body.nav-open .sidebar{transform:translateX(0);visibility:visible;transition-delay:0s}
  .main{margin-inline-start:0 !important}
  .nav-burger{display:inline-flex;align-items:center;justify-content:center;
    min-width:42px;min-height:42px;border:1px solid var(--lv-line);border-radius:var(--r-sm);
    background:var(--lv-white);color:var(--lv-brand-ink);font-size:1.1rem}
  .content{padding:var(--sp-3) var(--sp-3) var(--sp-6)}
  .topbar{padding:8px 12px}
  .topbar h2{font-size:.95rem}
  /* 16px بيمنع iOS من عمل zoom تلقائي عند التركيز */
  .form-control,.form-select,.form-control-sm,.form-select-sm,
  .line-table input,.line-table select{font-size:16px}
  .btn,.btn-sm{min-height:44px}
  .table .btn-sm{min-height:36px;min-width:36px}
  .card{margin-bottom:var(--sp-3)}

  /* الوضع المطوي مالوش معنى على الموبايل — بنرجّع كل حاجة زي ما هي */
  body.nav-collapsed .sidebar{width:264px;overflow-y:auto;overflow-x:hidden}
  body.nav-collapsed .sidebar .brand{padding:16px 14px 14px}
  body.nav-collapsed .sidebar .brand img{width:140px}
  body.nav-collapsed .sidebar .brand small,
  body.nav-collapsed .nav-sep,
  body.nav-collapsed .sidebar .nav-group-btn > span,
  body.nav-collapsed .sidebar .nav-group-btn .caret,
  body.nav-collapsed .sidebar > a.nav-item > span{display:inline}
  body.nav-collapsed .sidebar .nav-group-btn,
  body.nav-collapsed .sidebar > a.nav-item{justify-content:flex-start;padding-inline:16px;gap:11px}
  body.nav-collapsed .nav-group-btn .badge-count{position:static;margin-inline-start:auto;
    margin-inline-end:8px;min-width:20px;padding:2px 6px;font-size:.68rem}
  body.nav-collapsed .nav-group .nav-sub{position:static;width:auto;box-shadow:none;border-radius:0;
    max-height:none;overflow:visible;border-inline-start:3px solid var(--lv-soft)}
  body.nav-collapsed .nav-group .nav-sub a.nav-item{padding-inline-start:30px;justify-content:flex-start}
  body.nav-collapsed .nav-group .nav-sub a.nav-item > span{display:inline}
  /* على الموبايل الفتح بالضغط بس — مش بالوقوف */
  body.nav-collapsed .nav-group:hover > .nav-sub:not(.show),
  body.nav-collapsed .nav-group:focus-within > .nav-sub:not(.show){display:none}
  body.nav-collapsed .nav-sep{display:block}
  body.nav-collapsed .nav-group-btn .badge-count{display:inline-block}
  body.nav-collapsed .nav-sub-title{display:none}
  body.nav-collapsed .sidebar > a.nav-item::after{content:none}
  .nav-toggle{display:none}
}
@media (min-width:901px){ .nav-scrim{display:none} }

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
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, الشرح]
     الرقم الأحمر الوحيد اللي بيظهر معناه: فيه شغل مستني منك هنا. */
  /* المنيو مرتّب بترتيب الفلو الحقيقي.
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, الشرح]
     الرقم الأحمر هو الرقم الوحيد في المنيو، ومعناه: فيه شغل مستني منك هنا. */
  $nav = [
    ['main', '', 'bi-grid', [
      ['dashboard', 'لوحة التحكم', 'bi-speedometer2', null,
       'صورة السيستم كله: الشراء، القماش، التشغيل، التغطية'],
    ]],

    ['buy', 'دورة الشراء', 'bi-cart3', [
      ['purchase-orders.index', 'طلبات الشراء', 'bi-file-earmark-text',
       'po.view|po.request|po.source|po.finance',
       'كل الطلبات بكل مراحلها — الحفظ بينزّل الطلب للمشتريات تلقائيًا'],
      ['purchasing.queue', 'المشتريات', 'bi-cart3', 'po.source',
       'الطلبات اللي نزلت من التخطيط ومستنية مورد وسعر وتاريخ توريد'],
      ['finance.payables', 'الحسابات', 'bi-cash-coin', 'po.finance',
       'الطلبات اللي المشتريات سعّرتها — المستحق المتوقع لكل مورد'],
    ]],

    ['fabric', 'وصول القماش', 'bi-box-arrow-in-down', [
      ['stock-additions.index', 'إذن إضافة', 'bi-box-arrow-in-down', 'receipt.view',
       'أول مستند: بيولّد الحوض ويحجز الكمية تحت الفحص'],
      ['inspections.index', 'تقرير فحص قماش', 'bi-search', 'qc.view',
       'جرد الأتواب وقياس عرض وطول كل توب ⇐ أقل عرض'],
      ['lab-reports.index', 'تقرير المعمل', 'bi-thermometer-half', 'qc.view',
       'وزن البنشر والانكماش ومطابقة اللون'],
      ['goods-receipts.index', 'إذن استلام خام', 'bi-truck', 'receipt.view',
       'الإفراج — وفيه الرفض الجزئي وتعليق الألوان'],
      ['rejections.index', 'المرفوضات والمعلّق', 'bi-x-octagon', 'qc.view|receipt.view|po.view',
       'أتواب مرفوضة وألوان معلّقة لحين رد التخطيط والمشتريات'],
      ['consignments.index', 'الأحواض', 'bi-box-seam', 'receipt.view',
       'كل رسالة قماش وحالتها ورصيدها المحجوز والمتاح'],
    ]],

    ['prod', 'التشغيل', 'bi-hammer', [
      ['markers.requests', 'طلبات الماركر', 'bi-envelope-paper', 'marker.view',
       'المخطط بيطلب تعشيقة على أقل عرض'],
      ['markers.index', 'الماركرات', 'bi-grid-3x3', 'marker.view',
       'طول الفرشة وعدد القطع فيها والموديلات'],
      ['work-orders.index', 'أوامر الشغل', 'bi-hammer', 'wo.view',
       'ورقة المصنع: أكتر من خامة، وكل خامة بحسبتها والحاكمة بتحدد الإنتاج'],
      ['material-issues.index', 'إذن صرف خام', 'bi-box-arrow-up', 'receipt.view|wo.view',
       'صرف الخامات من المخزن للمصنع مقابل أمر الشغل'],
      ['cut-declarations.index', 'بيانات القص', 'bi-scissors', 'cut.view',
       'الفعلي من المصنع — وأهم رقم فيه طول الفرشة الفعلي'],
      ['production-receipts.index', 'استلامات الإنتاج', 'bi-inboxes', 'prod.manage|wo.view',
       'استلام على دفعات لحد ما أمر الشغل يتقفل'],
    ]],

    ['plan', 'التخطيط', 'bi-graph-up-arrow', [
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

    ['master', 'البيانات الأساسية', 'bi-database', [
      ['product-models.index', 'الموديلات', 'bi-tags', 'master.view', 'المنتجات ومقاساتها وقائمة إكسسواراتها'],
      ['colors.index', 'الألوان', 'bi-palette', 'master.view', 'أكواد الألوان — دمج وإيقاف، مفيش حذف'],
      ['fabric-types.index', 'الخامات', 'bi-layers', 'master.view', 'الخامات ومواصفاتها المعتمدة'],
      ['accessories.index', 'الإكسسوارات', 'bi-paperclip', 'master.view', 'الكياس والاستيكرات والزراير والسوست'],
      ['sizes.index', 'المقاسات', 'bi-rulers', 'master.view', 'المقاسات المتاحة'],
      ['suppliers.index', 'الموردين', 'bi-shop', 'master.view', 'الموردين وبياناتهم وشروط الدفع'],
      ['factories.index', 'المصانع', 'bi-building', 'master.view', 'المصانع وطاقتها اليومية ودورة تشغيلها'],
      ['warehouses.index', 'المخازن', 'bi-house-gear', 'master.view', 'المخازن وتاريخ آخر جرد'],
    ]],

    ['sys', 'النظام', 'bi-gear', [
      ['approvals.index', 'الاعتمادات', 'bi-check2-square', null, 'كل حاجة مستنية توقيعك'],
      ['io.index', 'استيراد وتصدير', 'bi-file-earmark-excel', 'import.manage', 'إكسيل: ألوان ومبيعات وأرصدة وتقارير'],
      ['settings.users', 'المستخدمين', 'bi-people', 'settings.users', 'إضافة مستخدمين وتحديد أدوارهم'],
      ['settings.roles', 'الأدوار والصلاحيات', 'bi-key', 'settings.roles', 'مين يشوف إيه ومين يعمل إيه'],
      ['settings.flows', 'دورات الاعتماد', 'bi-diagram-3', 'settings.flows', 'مين يعتمد إيه — بيتغيّر من غير كود'],
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
      <img src="{{ asset('assets/logo-white.png') }}" alt="Le Voile">
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
    <h2>{{ $title ?? '' }}</h2>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-light position-relative"
         title="الإشعارات" aria-label="الإشعارات{{ ($navUnread ?? 0) > 0 ? ' — ' . $navUnread . ' جديدة' : '' }}">
        <i class="bi bi-bell" aria-hidden="true"></i>
        @if(($navUnread ?? 0) > 0)
          <span class="position-absolute top-0 start-100 translate-middle badge badge-alert rounded-pill">{{ $navUnread }}</span>
        @endif
      </a>
      <span class="hint">{{ $u?->name }} — {{ $u?->roleNames() }}</span>
      <form method="post" action="{{ route('logout') }}">@csrf
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-left" aria-hidden="true"></i> خروج</button>
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
