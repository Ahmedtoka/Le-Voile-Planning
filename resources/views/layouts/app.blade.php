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
   هوية Le Voile — نظام التصميم الموحّد
   ───────────────────────────────────────────────────────────────
   المبدأ: الشاشة هادية وبيضا، البراند #9D197E في مكانين بس
   (القايمة الجانبية + الفعل الأساسي)، ولون الحالة في نقطة/شريحة
   صغيرة مش في مساحات كبيرة. كل رقم بخط tabular عشان يتصف تحت بعضه.
   كل ألوان النص متحققة على تباين 4.5:1 على الأقل.

   الطبقات:
   ① Tokens  ② الأساس  ③ القايمة الجانبية  ④ الهيكل والعنوان
   ⑤ الكروت والجداول  ⑥ الأزرار  ⑦ الفورم  ⑧ الحالات والشرايح
   ⑨ الأرقام/KPI  ⑩ شريط الفلو  ⑪ الفلاتر  ⑫ الرسايل
   ⑬ المعادلات ⓘ  ⑭ الموبايل  ⑮ الطباعة
   ═══════════════════════════════════════════════════════════════ */
:root{
  /* ① البراند */
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
  --lv-faint:      #A79BA4;   /* أرقام صفرية — زخرفة مش نص */
  --lv-line:       #E7DEE4;
  --lv-line-soft:  #F1ECEF;
  --lv-field:      #D7CBD3;   /* حدود الحقول */
  --lv-surface-2:  #FBF9FA;   /* رأس الجدول والفوتر */
  --lv-surface-3:  #F5F0F3;   /* بلوك «للقراءة» */
  --lv-hover:      rgba(157,25,126,.045);

  /* حالات دلالية — نص + خلفية + حدود + نقطة */
  --lv-ok:     #1B7A50;  --lv-ok-bg:     #EAF6EF;  --lv-ok-line:     #C5E5D2;  --lv-ok-dot:     #22A06B;
  --lv-warn:   #8A5A0D;  --lv-warn-bg:   #FDF5E6;  --lv-warn-line:   #F0DDB6;  --lv-warn-dot:   #E0A100;
  --lv-danger: #B5342B;  --lv-danger-bg: #FCEFEE;  --lv-danger-line: #F3CDC9;  --lv-danger-dot: #D8394F;
  --lv-info:   #1F5E8C;  --lv-info-bg:   #EBF3FA;  --lv-info-line:   #C7DDEE;  --lv-info-dot:   #2F80C2;
  --lv-alert:  #D8394F;  /* الكاونتر الأحمر: شغل مستني منك */

  /* الخط — سلّم واضح: 12 / 13 / 14 / 16 / 18 / 22 / 28 */
  --ff-ui: 'IBM Plex Sans Arabic', system-ui, 'Segoe UI', Tahoma, sans-serif;
  --ff-display: 'Reem Kufi', 'IBM Plex Sans Arabic', system-ui, sans-serif;
  --fs-xs: .75rem;    /* 12 — تلميحات، شرايح */
  --fs-sm: .8125rem;  /* 13 — جداول، عناوين الحقول */
  --fs-base: .875rem; /* 14 — النص */
  --fs-md: 1rem;      /* 16 — عناوين الأقسام */
  --fs-lg: 1.125rem;  /* 18 — أرقام متوسطة */
  --fs-xl: 1.375rem;  /* 22 — عنوان الصفحة */
  --fs-2xl: 1.75rem;  /* 28 — أرقام KPI */

  /* إيقاع المسافات 4/8 */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:24px; --sp-6:32px; --sp-7:48px;

  /* الاستدارة والارتفاع */
  --r-sm:6px; --r-md:10px; --r-lg:14px; --r-pill:999px;
  --radius:10px;
  --e-0:none;
  --e-1:0 1px 2px rgba(59,9,47,.05);
  --e-2:0 4px 12px -4px rgba(59,9,47,.14), 0 1px 3px rgba(59,9,47,.05);
  --e-3:0 12px 32px -10px rgba(59,9,47,.28), 0 2px 6px rgba(59,9,47,.06);
  --ring: 0 0 0 3px rgba(157,25,126,.18);

  /* مقاسات عناصر التحكم: ارتفاع واحد لكل الإنبوتس والزراير */
  --ctl-h: 38px;      /* الإنبوت والزرار العادي */
  --ctl-h-sm: 34px;   /* الفلاتر وأزرار الجدول */
  --ctl-h-row: 32px;  /* إنبوت جوه جدول سطور */
  --ctl-h-lg: 46px;

  --nav-w:264px;
  --nav-rail:64px;
  --topbar-h:64px;
  --dur:180ms; --ease:cubic-bezier(.2,.7,.3,1);
}

/* ══ ② الأساس ════════════════════════════════════════════════ */
*{font-family:var(--ff-ui)}
.bi::before{font-family:bootstrap-icons !important}
html{-webkit-text-size-adjust:100%;scrollbar-color:#CDBFC8 transparent}
body{background:var(--lv-offwhite);color:var(--lv-ink);font-size:var(--fs-base);line-height:1.6;
  -webkit-font-smoothing:antialiased}
h1,h2,h3,h4,h5,h6{color:var(--lv-brand-ink);font-weight:700}
a{text-decoration:none;color:var(--lv-brand)}
a:hover{color:var(--lv-brand-dark);text-decoration:underline;text-underline-offset:3px}
::selection{background:var(--lv-soft);color:var(--lv-brand-ink)}
b,strong{font-weight:700}
small,.small{font-size:var(--fs-xs)}
hr{border-color:var(--lv-line);opacity:1}
/* الأرقام في كل مكان بخط tabular — فتتصف تحت بعضها */
.num,td,th,.badge,.pill,input[type=number],input[type=date]{font-variant-numeric:tabular-nums}
/* ممنوع تغيير الـdisplay على td.num — بيتحط في 200+ مكان */
.num{direction:ltr;text-align:center}
span.num,b.num,strong.num,small.num,div.num,a.num{display:inline-block}
td.num,th.num{display:table-cell}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:#CDBFC8;border-radius:8px;border:2px solid transparent;background-clip:padding-box}
::-webkit-scrollbar-track{background:transparent}

/* تركيز واضح على كل عنصر تفاعلي */
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

/* ══ ③ القايمة الجانبية — المكان الجريء ══════════════════════
   ① مفتوحة  ② مطوية (أيقونات + لوحة عايمة)  ③ موبايل (درج)
   الترتيب = ترتيب الدورة، والخطوات مترقّمة 1 ← 13 في شريحة صغيرة. */
.sidebar{position:fixed;inset-block:0;inset-inline-start:0;width:var(--nav-w);
  background:linear-gradient(180deg,var(--lv-brand-deep) 0%,var(--lv-brand-ink) 100%);
  color:#F0E3EC;overflow-y:auto;overflow-x:hidden;z-index:1040;
  transition:width var(--dur) var(--ease);
  scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}
.sidebar::-webkit-scrollbar{width:6px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:3px;border:0}

.sidebar .brand{padding:22px 18px 14px;text-align:start;position:relative}
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
.sidebar .nav-group{position:relative;padding:0 10px;margin-bottom:2px}
.sidebar .nav-group-btn{display:flex;align-items:center;gap:10px;width:100%;border:0;border-radius:8px;
  background:none;color:#EBD7E4;font-size:var(--fs-base);font-weight:600;
  padding:9px 10px;text-align:start;min-height:42px;position:relative;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar .nav-group-btn:hover{background:rgba(255,255,255,.07);color:#fff}
.sidebar .nav-group-btn .gi{width:22px;min-width:22px;text-align:center;font-size:1.02rem;opacity:.85}
.sidebar .nav-group-btn .caret{margin-inline-start:auto;font-size:.62rem;opacity:.55;
  transition:transform var(--dur) var(--ease)}
.sidebar .nav-group-btn .badge-count{margin-inline-start:auto;margin-inline-end:6px}
.sidebar .nav-group-btn .badge-count + .caret{margin-inline-start:0}
/* المجموعة المفتوحة: لوح أغمق شوية + العنوان أبيض — واضحة من بعيد */
.sidebar .nav-group.is-open{background:rgba(0,0,0,.16);border-radius:10px;padding-bottom:4px}
.sidebar .nav-group-btn[aria-expanded="true"]{color:#fff}
.sidebar .nav-group-btn[aria-expanded="true"] .gi{opacity:1;color:#F5B6E0}
.sidebar .nav-group-btn[aria-expanded="true"] .caret{transform:rotate(180deg);opacity:.9}
/* رقم المرحلة ① ② ③ في عنوان المجموعة */
.sidebar .nav-group-btn .phase-n{font-family:var(--ff-ui);font-size:.95rem;color:#F5B6E0;line-height:1;min-width:18px;text-align:center}

/* المستوى التاني: خط إرشادي رفيع */
.sidebar .nav-sub{position:relative;margin:2px 0 6px;padding-inline-start:16px}
.sidebar .nav-sub::before{content:"";position:absolute;inset-block:4px;inset-inline-start:21px;
  width:1px;background:rgba(255,255,255,.14)}

.sidebar a.nav-item{display:flex;align-items:center;gap:9px;border-radius:8px;
  padding:6px 10px;padding-inline-start:14px;
  color:#D8C1D1;font-size:var(--fs-sm);min-height:38px;position:relative;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.sidebar a.nav-item:hover{background:rgba(255,255,255,.07);color:#fff;text-decoration:none}
.sidebar a.nav-item .ni{width:18px;min-width:18px;text-align:center;opacity:.7;font-size:.9rem}
.sidebar a.nav-item:hover .ni{opacity:1}
.sidebar a.nav-item > span:not(.badge){min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* رقم الخطوة 1 ← 13: نفس الرقم اللي في شريط الفلو */
.sidebar .step-no{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;min-width:22px;
  border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);
  color:#F0E3EC;font-size:.7rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1}
.sidebar a.nav-item .step-no + .ni{display:none}

/* الصفحة الحالية: لوح فاتح + علامة وردي ساطعة */
.sidebar a.nav-item.active{background:rgba(255,255,255,.14);color:#fff;font-weight:600}
.sidebar a.nav-item.active::before{content:"";position:absolute;inset-inline-start:0;inset-block:9px;
  width:3px;border-radius:3px;background:#F5B6E0}
.sidebar a.nav-item.active .ni{opacity:1;color:#F8CBEA}
.sidebar a.nav-item.active .step-no{background:#F5B6E0;border-color:#F5B6E0;color:var(--lv-brand-ink)}

/* عنصر مستوى أول من غير مجموعة (لوحة التحكم) */
.sidebar > a.nav-item{margin:0 10px 2px;padding-inline-start:10px;min-height:42px;
  color:#EBD7E4;font-size:var(--fs-base);font-weight:600}
.sidebar > a.nav-item .ni{width:22px;min-width:22px;font-size:1.02rem}

/* الكاونتر الأحمر = شغل مستني منك — لازم يبان */
.sidebar .badge-count{margin-inline-start:auto;background:var(--lv-alert)!important;color:#fff!important;
  border:0;font-size:.7rem;font-weight:700;min-width:22px;height:20px;padding:0 7px;line-height:20px;
  border-radius:var(--r-pill);font-variant-numeric:tabular-nums;font-family:var(--ff-ui);
  box-shadow:0 0 0 2px rgba(59,9,47,.65)}

.nav-sep{margin:14px 20px 6px;font-size:var(--fs-xs);color:#B892AC;font-weight:600;letter-spacing:.2px}
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
body.nav-collapsed .sidebar .nav-group.is-open{background:none;padding-bottom:0}
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
  width:244px;box-shadow:var(--e-3);z-index:1050;margin:0;padding:6px;
  background:var(--lv-brand-ink);border-radius:var(--r-md);
  max-height:min(70vh,460px);overflow-y:auto}
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

/* ══ ④ الهيكل والعنوان ═══════════════════════════════════════ */
.main{margin-inline-start:var(--nav-w);transition:margin var(--dur) var(--ease);min-height:100vh}
.topbar{background:rgba(255,255,255,.94);backdrop-filter:saturate(1.4) blur(8px);
  border-bottom:1px solid var(--lv-line);padding:0 28px;min-height:var(--topbar-h);
  display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:1020}
.topbar .page-title{display:flex;flex-direction:column;justify-content:center;min-width:0;padding:10px 0}
.topbar h2{font-family:var(--ff-display);font-size:var(--fs-xl);margin:0;font-weight:600;color:var(--lv-brand-ink);
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

/* عناوين الأقسام */
.section-head{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 12px;margin:var(--sp-6) 0 var(--sp-3)}
.section-head:first-child{margin-top:0}
.section-head h2{font-size:var(--fs-md);margin:0;font-weight:700;color:var(--lv-brand-ink);line-height:1.35}
.section-head .hint{font-size:var(--fs-sm)}
.section-head .count-badge{font-size:.72rem;min-width:24px;height:22px;line-height:22px;padding:0 8px;
  border-radius:var(--r-pill);background:var(--lv-alert);color:#fff;font-weight:700;text-align:center;
  font-variant-numeric:tabular-nums;align-self:center}

/* ══ ⑤ الكروت والجداول ══════════════════════════════════════ */
.card{border:1px solid var(--lv-line);border-radius:var(--r-md);
  box-shadow:var(--e-1);background:var(--lv-white);margin-bottom:var(--sp-4)}
.card:last-child{margin-bottom:0}
.card-header{background:var(--lv-white);border-bottom:1px solid var(--lv-line);font-weight:700;
  border-radius:var(--r-md) var(--r-md) 0 0 !important;padding:.75rem 1.1rem;color:var(--lv-brand-ink);
  font-size:var(--fs-base);min-height:52px;display:flex;align-items:center;gap:var(--sp-2);flex-wrap:wrap}
.card-header .hint,.card-header small{font-weight:400}
.card-header > span:first-child{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap}
.card-body{padding:1.1rem}
.card-footer{background:var(--lv-surface-2);border-top:1px solid var(--lv-line);border-radius:0 0 var(--r-md) var(--r-md) !important;
  padding:.65rem 1.1rem}
.card-footer.bg-white{background:var(--lv-white)!important}
.card .alert.rounded-0{border-inline:0}

/* الجداول: رأس ثابت فاتح، صفوف مريحة، هوفر خفيف، أعمدة الأفعال في الآخر */
.table{font-size:var(--fs-sm);margin-bottom:0;color:var(--lv-ink);--bs-table-hover-bg:var(--lv-hover)}
.table>thead>tr>th{background:var(--lv-surface-2);color:var(--lv-ink-2);font-weight:600;
  white-space:nowrap;border-bottom:1px solid var(--lv-line);font-size:var(--fs-xs);
  padding:.6rem .75rem;vertical-align:middle;position:sticky;top:0;z-index:2}
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
/* contain:paint — من غيرها الجدول العريض بيوسّع الصفحة كلها في RTL على كروم (موبايل خصوصًا) */
.table-responsive{border-radius:0 0 var(--r-md) var(--r-md);-webkit-overflow-scrolling:touch;contain:paint;overflow-x:auto}
.table a:not(.btn){font-weight:600}
.table td.num,.table th.num{white-space:nowrap}
.table .hint{line-height:1.45}
/* عمود الأفعال: دايمًا آخر عمود، ضيق، والزراير أيقونات مربعة */
.table td:last-child .btn-sm,.table .row-actions .btn{min-height:var(--ctl-h-row);min-width:var(--ctl-h-row);padding-inline:.45rem;
  border-radius:7px;font-size:.85rem}
.table td:last-child .btn-sm:not(:has(span)):not(:has(.visually-hidden)){padding-inline:0}
.row-actions{display:inline-flex;gap:4px;flex-wrap:nowrap;justify-content:flex-end;align-items:center}
.row-actions form{display:inline-flex;margin:0}
/* صفوف الحالة: لون خفيف جدًا + خط جانبي */
.table>tbody>tr.table-warning>td{background:var(--lv-warn-bg);--bs-table-bg:var(--lv-warn-bg)}
.table>tbody>tr.table-danger>td{background:var(--lv-danger-bg);--bs-table-bg:var(--lv-danger-bg)}
.table>tbody>tr.table-success>td{background:var(--lv-ok-bg);--bs-table-bg:var(--lv-ok-bg)}
.table>tbody>tr.table-warning>td:first-child{box-shadow:inset 3px 0 0 var(--lv-warn-dot)}
.table>tbody>tr.table-danger>td:first-child{box-shadow:inset 3px 0 0 var(--lv-danger-dot)}
.table>tbody>tr.table-success>td:first-child{box-shadow:inset 3px 0 0 var(--lv-ok-dot)}
/* جدول Labels (th على الشمال, td على اليمين) */
.table.kv>tbody>tr>th,.table.kv>tr>th{width:150px;background:transparent;color:var(--lv-muted);font-weight:500;font-size:var(--fs-xs)}

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

/* ══ ⑥ الأزرار: أساسي واحد (بنفسجي) + ثانوي + هادي + خطر ═══
   كل الزراير نفس الارتفاع 38 · الصغيرة 34 · الكبيرة 46. */
.btn{min-height:var(--ctl-h);border-radius:8px;font-weight:600;font-size:var(--fs-sm);
  display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:.3rem .9rem;
  white-space:nowrap;line-height:1.2;
  transition:background var(--dur) var(--ease),color var(--dur) var(--ease),border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.btn.w-100,.btn.d-block{display:flex}
.btn-sm{min-height:var(--ctl-h-sm);font-size:var(--fs-xs);border-radius:7px;padding:.25rem .7rem}
.btn-lg{min-height:var(--ctl-h-lg);font-size:var(--fs-md);padding-inline:1.2rem}
.btn.py-0{padding-block:.25rem!important}
.btn:active{transform:none}
.btn:focus-visible{box-shadow:var(--ring);outline:0}
.btn .bi{font-size:1em;line-height:1}
/* زرار أيقونة مربع */
.btn-icon{width:var(--ctl-h-sm);min-width:var(--ctl-h-sm);min-height:var(--ctl-h-sm);padding:0}
.btn-plum{background:var(--lv-brand);color:#fff;border:1px solid var(--lv-brand);box-shadow:0 1px 2px rgba(59,9,47,.15)}
.btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);border-color:var(--lv-brand-dark);color:#fff}
.btn-plum:active{background:var(--lv-brand-deep)!important;color:#fff!important}
.btn-outline-plum{border:1px solid var(--lv-soft);color:var(--lv-brand-dark);background:var(--lv-white)}
.btn-outline-plum:hover,.btn-outline-plum:focus{background:var(--lv-tint);color:var(--lv-brand-dark);border-color:#DDB3D2}
.btn-outline-plum:active{background:var(--lv-soft)!important;color:var(--lv-brand-ink)!important}
.btn-outline-secondary,.btn-light{--bs-btn-color:var(--lv-ink-2);--bs-btn-bg:var(--lv-white);--bs-btn-border-color:var(--lv-field);
  --bs-btn-hover-color:var(--lv-ink);--bs-btn-hover-bg:var(--lv-surface-2);--bs-btn-hover-border-color:#CDBFC8;
  --bs-btn-active-color:var(--lv-ink);--bs-btn-active-bg:var(--lv-line-soft);--bs-btn-active-border-color:#CDBFC8}
.btn-ghost{background:transparent;border:1px solid transparent;color:var(--lv-ink-2)}
.btn-ghost:hover{background:var(--lv-line-soft);color:var(--lv-ink)}
.btn-success{--bs-btn-bg:var(--lv-ok);--bs-btn-border-color:var(--lv-ok);--bs-btn-hover-bg:#156240;--bs-btn-hover-border-color:#156240}
.btn-outline-success{--bs-btn-color:var(--lv-ok);--bs-btn-border-color:var(--lv-ok-line);--bs-btn-hover-bg:var(--lv-ok);--bs-btn-hover-border-color:var(--lv-ok)}
.btn-danger{--bs-btn-bg:var(--lv-danger);--bs-btn-border-color:var(--lv-danger);--bs-btn-hover-bg:#962a23;--bs-btn-hover-border-color:#962a23}
.btn-outline-danger{--bs-btn-color:var(--lv-danger);--bs-btn-border-color:var(--lv-danger-line);--bs-btn-bg:var(--lv-white);
  --bs-btn-hover-bg:var(--lv-danger);--bs-btn-hover-border-color:var(--lv-danger)}
.btn-warning,.btn-outline-warning{--bs-btn-color:var(--lv-warn);--bs-btn-bg:var(--lv-warn-bg);--bs-btn-border-color:var(--lv-warn-line);
  --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--lv-warn);--bs-btn-hover-border-color:var(--lv-warn);
  --bs-btn-active-color:#fff;--bs-btn-active-bg:var(--lv-warn);--bs-btn-active-border-color:var(--lv-warn)}
.btn-dark,.btn-outline-dark{--bs-btn-color:var(--lv-ink);--bs-btn-bg:var(--lv-white);--bs-btn-border-color:var(--lv-ink-2);
  --bs-btn-hover-color:#fff;--bs-btn-hover-bg:var(--lv-ink);--bs-btn-hover-border-color:var(--lv-ink);
  --bs-btn-active-color:#fff;--bs-btn-active-bg:var(--lv-ink);--bs-btn-active-border-color:var(--lv-ink)}
.btn-link{color:var(--lv-brand);font-weight:600;text-decoration:none}
.btn-link:hover{text-decoration:underline}
.btn:disabled,.btn.disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
.actions-bar{display:flex;gap:var(--sp-2);flex-wrap:wrap;align-items:center}
.actions-bar .danger-zone{margin-inline-start:auto;padding-inline-start:var(--sp-3);
  border-inline-start:1px dashed var(--lv-line)}
.btn-close{font-size:.7rem}

/* حالة تحميل الأزرار */
.btn .spin{display:none;width:13px;height:13px;border:2px solid currentColor;border-top-color:transparent;
  border-radius:50%;animation:lvspin .6s linear infinite;vertical-align:-2px}
.btn.is-loading .spin{display:inline-block}
.btn.is-loading{pointer-events:none;opacity:.75}
@keyframes lvspin{to{transform:rotate(360deg)}}

/* ══ ⑦ الفورم ═══════════════════════════════════════════════
   قاعدة: Labels (للقراءة) في بلوك رمادي .ro-box — والإنبوتس بيضا بحدود.
   كل الإنبوتس نفس الارتفاع، والعرض على قد المحتوى:
   الأرقام والتواريخ قصيرة، النصوص أعرض. الوحدة جوه الخانة. */
.form-label{font-size:var(--fs-sm);font-weight:600;margin-bottom:5px;color:var(--lv-ink-2);display:block}
.form-label .hint{font-weight:400}
.form-control,.form-select{font-size:var(--fs-base);border-radius:8px;border-color:var(--lv-field);
  min-height:var(--ctl-h);padding:.35rem .75rem;color:var(--lv-ink);background-color:var(--lv-white);
  transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease)}
.form-control-sm,.form-select-sm{font-size:var(--fs-base);min-height:var(--ctl-h);border-radius:8px;padding:.35rem .7rem}
.form-select{padding-inline-end:2.1rem;background-position:left .7rem center}
textarea.form-control{min-height:76px;line-height:1.55}
textarea.form-control-sm{min-height:66px}
.form-control::placeholder{color:#9A8D97;opacity:1}
.form-control:hover,.form-select:hover{border-color:#C4B4BE}
.form-control:focus,.form-select:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
.form-control:disabled,.form-select:disabled{background:var(--lv-line-soft);color:var(--lv-muted)}
.form-check-input{border-color:var(--lv-field);width:1.05em;height:1.05em;margin-top:.22em}
.form-check-input:checked{background-color:var(--lv-brand);border-color:var(--lv-brand)}
.form-check-input:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
.form-check-label{font-size:var(--fs-sm)}
.form-text{color:var(--lv-muted);font-size:var(--fs-xs)}
/* الوحدة جوه الخانة: كجم / سم / متر / % */
.input-group>.form-control,.input-group>.form-select{min-height:var(--ctl-h)}
.input-group-sm>.form-control,.input-group-sm>.form-select,.input-group-sm>.input-group-text,.input-group-sm>.btn{min-height:var(--ctl-h);font-size:var(--fs-base)}
.input-group-text{background:var(--lv-surface-2);border-color:var(--lv-field);font-size:var(--fs-xs);color:var(--lv-muted);
  font-weight:600;padding-inline:.6rem;min-width:44px;justify-content:center}
.input-group>.input-group-text:first-child{border-inline-end:0}
.input-group .form-control+.input-group-text{border-inline-start:0}
.input-group:focus-within .input-group-text{border-color:var(--lv-brand)}
/* الحقول القصيرة: رقم وتاريخ ما ياخدوش عرض العمود كله */
input[type=date].form-control{max-width:190px}
input[type=number].form-control{max-width:220px}
input[type=time].form-control{max-width:140px}
.input-group:has(>input[type=number]){max-width:260px}
.w-num{width:120px!important;max-width:100%}
.w-date{width:160px!important;max-width:100%}
.w-short{width:150px!important;max-width:100%}
.w-mid{width:210px!important;max-width:100%}
.w-wide{width:300px!important;max-width:100%}
.w-full{width:100%!important;max-width:100%!important}
input[type=number]{-moz-appearance:textfield}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
/* الحقل الإجباري */
.req::after{content:' *';color:var(--lv-danger);font-weight:700}
th.req::after{font-size:.9em}
.req-legend{font-size:var(--fs-xs);color:var(--lv-muted)}
.req-legend::before{content:'* ';color:var(--lv-danger);font-weight:700}
/* قيمة مقروءة جوه فورم: باينة إنها مش حقل للكتابة */
.form-control[readonly],.form-control-plaintext.ro,.form-control.ro{background:var(--lv-surface-3);border-style:dashed;border-color:var(--lv-line);
  color:var(--lv-ink-2);cursor:default;font-weight:600}
.form-control[readonly]:focus{box-shadow:none;border-color:var(--lv-line)}
/* بلوك Labels — معلومات للقراءة، مش للكتابة */
.ro-box{background:var(--lv-surface-3);border:1px solid var(--lv-line);border-radius:var(--r-md);padding:12px 14px}
.ro-box>.hint:first-child{margin-bottom:6px}
.ro-box .table{--bs-table-bg:transparent;background:transparent}
.ro-box .table>tbody>tr>th,.ro-box .table>tr>th{background:transparent;color:var(--lv-muted);font-weight:500;font-size:var(--fs-xs);padding:.35rem .5rem;border-color:var(--lv-line)}
.ro-box .table>tbody>tr>td,.ro-box .table>tr>td{padding:.35rem .5rem;border-color:var(--lv-line);font-weight:600}
.ro-box .table>tbody>tr:last-child>*,.ro-box .table>tr:last-child>*{border-bottom:0}
.ro-title{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.ro-title::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--lv-brand)}
/* سامري المستند: أقسام (الرسالة | الإذن | الطلب) كل قسم شبكة Labels */
.sum-sec{display:grid;grid-template-columns:150px 1fr;gap:10px;align-items:start;padding:10px 0;border-bottom:1px dashed var(--lv-line)}
.sum-sec:last-child{border-bottom:0}
.sum-sec .t{font-weight:700;color:var(--lv-brand-ink);padding-top:4px;font-size:var(--fs-sm);display:flex;align-items:center;gap:6px}
.sum-sec .t .bi{color:var(--lv-brand)}
.sum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px;text-align:center;font-size:var(--fs-sm)}
.sum-grid .k{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.25}
.sum-grid .v{font-weight:600;font-size:var(--fs-sm);line-height:1.4;overflow-wrap:anywhere}
.sum-grid a.v{font-size:var(--fs-sm)}
/* الأرقام الكبيرة فوق المستند */
.big-nums{display:flex;gap:var(--sp-5);flex-wrap:wrap;text-align:center}
.big-nums .bn-v{font-size:var(--fs-xl);font-weight:700;color:var(--lv-brand-ink);line-height:1.2}
/* جداول الإدخال */
.line-table input,.line-table select,.line-table textarea{border:1px solid var(--lv-field);border-radius:7px;padding:4px 8px;
  font-size:var(--fs-sm);width:100%;background:var(--lv-white);min-height:var(--ctl-h-row);max-width:none;color:var(--lv-ink)}
.line-table input:hover,.line-table select:hover{border-color:#C4B4BE}
.line-table input:focus,.line-table select:focus{border-color:var(--lv-brand);box-shadow:var(--ring);outline:0}
.line-table input::placeholder{color:#A79BA4}
.line-table select{min-width:64px;padding-inline:6px;padding-inline-end:22px;text-overflow:ellipsis;appearance:none;-webkit-appearance:none;
  background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236B606A' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  background-repeat:no-repeat;background-position:left 6px center;background-size:12px}
.line-table td.num input,.line-table input[type=number]{font-variant-numeric:tabular-nums;direction:ltr;text-align:center}
.line-table thead th{white-space:nowrap}
.line-table td{padding:.3rem .35rem}
.line-table .row-no{color:var(--lv-muted);font-size:var(--fs-xs);font-variant-numeric:tabular-nums}
.lines-locked input,.lines-locked select,.lines-locked button{pointer-events:none;
  background:var(--lv-surface-2);opacity:.75}
.lines-locked .btn{visibility:hidden}
.line-table .form-control.form-control-sm{min-height:var(--ctl-h-row);font-size:var(--fs-xs)}
/* التحقق: الحقل الغلط + رسالته تحته مباشرة */
.is-invalid,.line-table .is-invalid{border-color:var(--lv-danger)!important;background-color:#FFF8F7}
.is-invalid:focus{box-shadow:0 0 0 3px rgba(181,52,43,.16)!important}
.invalid-feedback{color:var(--lv-danger);font-size:var(--fs-xs);font-weight:600;margin-top:4px;line-height:1.45}
.invalid-feedback.d-block{display:block}
.line-table .invalid-feedback{white-space:normal}
fieldset:disabled .form-control,fieldset:disabled .form-select{background:var(--lv-surface-3);border-style:dashed;color:var(--lv-ink-2)}
/* شريط الحفظ: الزرار الأساسي دايمًا ظاهر تحت الفورم وبيلزق في آخر الشاشة */
.form-actions{position:sticky;bottom:0;z-index:5;display:flex;align-items:center;gap:var(--sp-2) var(--sp-3);flex-wrap:wrap;
  margin-top:var(--sp-4);padding:12px 16px;background:rgba(255,255,255,.97);backdrop-filter:blur(6px);
  border:1px solid var(--lv-line);border-radius:var(--r-md);box-shadow:0 -6px 18px -12px rgba(59,9,47,.25)}
.form-actions .btn-plum,.form-actions .btn-success{min-width:150px}
.card-footer.form-actions{border:0;border-top:1px solid var(--lv-line);border-radius:0 0 var(--r-md) var(--r-md)!important}
.form-actions .hint{font-size:var(--fs-sm)}
.form-actions .fa-end{margin-inline-start:auto;display:flex;gap:var(--sp-2);flex-wrap:wrap}
/* شريط إجراءات المستند فوق التفاصيل */
.doc-actions{display:flex;align-items:center;gap:var(--sp-2);flex-wrap:wrap;margin-bottom:var(--sp-4);
  padding:10px 14px;background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md)}
.doc-actions .da-label{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:600;margin-inline-end:4px}
.doc-actions form{display:inline-flex;margin:0}
.doc-actions .da-end{margin-inline-start:auto;display:flex;gap:var(--sp-2);flex-wrap:wrap}
/* الملاحظة التوضيحية فوق الشاشة */
.note-box{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  padding:10px 14px;font-size:var(--fs-sm);color:var(--lv-ink-2);line-height:1.75;
  display:flex;gap:8px;align-items:flex-start}
.note-box>.bi:first-child{color:var(--lv-brand);margin-top:3px;flex:0 0 auto}
.note-box>:not(.bi){flex:1 1 auto}
.calc-box{background:var(--lv-surface-2);border:1px solid var(--lv-line);border-radius:var(--r-md);padding:14px 16px}
.calc-box .kv{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:7px 0;border-bottom:1px dashed var(--lv-line)}
.calc-box .kv:last-child{border:0}
.calc-box .kv b{font-size:var(--fs-md);color:var(--lv-brand-ink);font-variant-numeric:tabular-nums;white-space:nowrap}
.calc-box .kv>span{display:inline-flex;align-items:center;gap:6px}

/* ══ ⑧ الحالات والشرايح ═════════════════════════════════════ */
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;flex:0 0 8px;background:var(--lv-line)}
.dot-brand{background:var(--lv-brand)} .dot-ok{background:var(--lv-ok-dot)} .dot-warn{background:var(--lv-warn-dot)}
.dot-danger{background:var(--lv-danger-dot)} .dot-info{background:var(--lv-info-dot)} .dot-muted{background:#BFB3BB}
.dot-ink{background:var(--lv-brand-ink)}
.status{display:inline-flex;align-items:center;gap:6px;font-size:var(--fs-xs);font-weight:600;color:var(--lv-ink-2);white-space:nowrap}
.status::before{content:"";width:7px;height:7px;border-radius:50%;background:#BFB3BB;flex:0 0 7px}
.status.is-ok{color:var(--lv-ok)} .status.is-ok::before{background:var(--lv-ok-dot)}
.status.is-warn{color:var(--lv-warn)} .status.is-warn::before{background:var(--lv-warn-dot)}
.status.is-danger{color:var(--lv-danger)} .status.is-danger::before{background:var(--lv-danger-dot)}
.status.is-info{color:var(--lv-info)} .status.is-info::before{background:var(--lv-info-dot)}
.status.is-brand{color:var(--lv-brand-dark)} .status.is-brand::before{background:var(--lv-brand)}

.hint{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.6}
.text-muted{color:var(--lv-muted)!important}
.text-warning{color:var(--lv-warn)!important}
.text-success{color:var(--lv-ok)!important}
.text-danger{color:var(--lv-danger)!important}
.text-info{color:var(--lv-info)!important}

/* الشرايح: خلفية خفيفة + نص بلون ثابت لكل حالة — نفس الشكل في كل الشاشات */
.badge,.pill{display:inline-flex;align-items:center;gap:5px;font-size:var(--fs-xs);font-weight:600;line-height:1.5;
  padding:.18em .6em;border-radius:var(--r-pill);border:1px solid transparent;white-space:nowrap;vertical-align:middle;
  font-variant-numeric:tabular-nums}
.badge .bi{font-size:.85em}
.pill-ok,.badge.bg-success{background:var(--lv-ok-bg)!important;color:var(--lv-ok)!important;border-color:var(--lv-ok-line)}
.pill-warn,.badge.bg-warning{background:var(--lv-warn-bg)!important;color:var(--lv-warn)!important;border-color:var(--lv-warn-line)}
.pill-danger,.badge.bg-danger{background:var(--lv-danger-bg)!important;color:var(--lv-danger)!important;border-color:var(--lv-danger-line)}
.pill-info,.badge.bg-info{background:var(--lv-info-bg)!important;color:var(--lv-info)!important;border-color:var(--lv-info-line)}
.pill-muted,.badge.bg-secondary,.badge.bg-light,.badge.bg-dark{background:var(--lv-surface-2)!important;color:var(--lv-ink-2)!important;border-color:var(--lv-line)}
.pill-brand,.badge.bg-primary{background:var(--lv-tint)!important;color:var(--lv-brand-dark)!important;border-color:var(--lv-soft)}
.badge.text-dark{color:var(--lv-ink-2)!important}
.pill .dot{width:6px;height:6px;flex-basis:6px}
/* التنبيه الأحمر بيفضل صريح — ده تنبيه مش حالة */
.badge.badge-alert{background:var(--lv-alert)!important;color:#fff!important;border-color:var(--lv-alert)!important}

/* ══ ⑨ الأرقام: KPI ═══════════════════════════════════════ */
.stat{position:relative;background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);
  padding:14px 16px 12px;height:100%;transition:border-color var(--dur) var(--ease),box-shadow var(--dur) var(--ease);
  container-type:inline-size}
.stat .v{font-size:clamp(1.25rem, 13cqi, var(--fs-2xl));font-weight:700;line-height:1.1;color:var(--lv-brand-ink);
  font-variant-numeric:tabular-nums;letter-spacing:-.3px;max-width:100%}
.stat .l{color:var(--lv-ink-2);font-size:var(--fs-sm);line-height:1.4;font-weight:600}
.stat .stat-head{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.stat .stat-value{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.stat .stat-sub{font-size:var(--fs-xs);color:var(--lv-muted);font-variant-numeric:tabular-nums;font-weight:600}
.stat .stat-note{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.5;margin-top:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.stat .stat-link{margin-top:auto;padding-top:10px;font-size:var(--fs-xs);font-weight:600;color:var(--lv-brand);
  display:inline-flex;align-items:center;gap:4px;align-self:flex-start}
.stat .stat-link i{transition:transform var(--dur) var(--ease)}
.stat:hover .stat-link i{transform:translateX(-3px)}
.stat.is-link:hover{border-color:var(--lv-soft);box-shadow:var(--e-2)}
.stat.is-waiting{border-color:var(--lv-soft);background:linear-gradient(180deg,#FFFFFF 0%,#FDF8FB 100%)}
.stat.is-zero .v{color:var(--lv-faint)}

/* طابور «مستني منك» المشترك: لوحة التحكم ومكاتب الإدارات */
.q-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,200px),1fr));gap:var(--sp-3)}
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
.q-tile.is-zero{background:var(--lv-white);border-color:var(--lv-line)}
.q-tile.is-zero .q-n{color:var(--lv-faint)}
.q-tile.is-zero .q-go{color:var(--lv-muted);font-weight:500}
.q-tile .q-l .dot{vertical-align:middle;margin-inline-end:6px}
.calm-line{display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--lv-white);
  border:1px solid var(--lv-line);border-radius:var(--r-md);font-size:var(--fs-sm);color:var(--lv-ink-2)}
.calm-line .ic{width:28px;height:28px;flex:0 0 28px;border-radius:50%;background:var(--lv-ok-bg);color:var(--lv-ok);
  display:inline-flex;align-items:center;justify-content:center}
.calm-line .hint{margin-inline-start:auto}

/* ══ ⑩ شريط الفلو ════════════════════════════════════════════ */
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

/* مراحل الطلب */
.stepper{display:flex;align-items:flex-start}
.stepper .st{flex:1;text-align:center;position:relative;font-size:var(--fs-xs);color:var(--lv-muted)}
.stepper .st .c{width:32px;height:32px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;
  background:var(--lv-white);border:1px solid var(--lv-field);color:var(--lv-faint);font-size:.9rem;position:relative;z-index:1}
.stepper .st::after{content:"";position:absolute;top:16px;inset-inline-start:calc(50% + 20px);width:calc(100% - 40px);
  height:2px;background:var(--lv-line);border-radius:2px}
.stepper .st:last-child::after{display:none}
.stepper .st.done .c{background:var(--lv-brand);border-color:var(--lv-brand);color:#fff}
.stepper .st.done::after{background:var(--lv-brand)}
.stepper .st.done{color:var(--lv-ink-2)}
.stepper .st.now .c{background:var(--lv-tint);border:2px solid var(--lv-brand);color:var(--lv-brand)}
.stepper .st.now{color:var(--lv-brand-ink);font-weight:700}

/* ══ ⑪ الفلاتر: سطر واحد، كل حقل على قد محتواه، وزرار مسح ═══ */
.filter-bar{display:flex;gap:var(--sp-2);flex-wrap:wrap;align-items:center;min-width:0}
/* جوه رأس الكارت: العنوان وزرار الإنشاء في السطر الأول، والفلاتر سطر كامل تحتهم */
.card-header>.filter-bar,.card-header>form.d-flex{order:10;flex:1 1 100%;margin-inline-start:0;padding-top:8px;margin-top:4px;border-top:1px dashed var(--lv-line-soft)}
.card-header>span:first-child{margin-inline-end:auto}
.filter-bar .form-control,.filter-bar .form-select,.filter-bar .btn{min-height:var(--ctl-h-sm);font-size:var(--fs-sm);border-radius:8px}
.filter-bar .form-control{width:190px;max-width:100%}
.filter-bar .form-select{width:160px;max-width:100%;padding-inline-end:1.9rem}
.filter-bar input[type=date]{width:148px}
.filter-bar .fb-range{display:inline-flex;align-items:center;gap:6px;background:var(--lv-surface-2);border:1px solid var(--lv-line);
  border-radius:8px;padding:0 8px 0 2px;min-height:var(--ctl-h-sm)}
.filter-bar .fb-range .hint{margin:0}
.filter-bar .fb-range input{border:0;background:transparent;min-height:30px;padding-inline:4px;width:132px}
.filter-bar .fb-range input:focus{box-shadow:none}
.filter-bar .form-check{display:inline-flex;align-items:center;gap:6px;margin:0;padding:0 10px;min-height:var(--ctl-h-sm);
  border:1px solid var(--lv-line);border-radius:8px;background:var(--lv-white)}
.filter-bar .form-check .form-check-input{margin:0;float:none}
.filter-bar .form-check .form-check-label{font-size:var(--fs-xs);font-weight:600;color:var(--lv-ink-2)}
.filter-bar .fb-search{position:relative}
.filter-bar .fb-search .form-control{padding-inline-start:30px}
.filter-bar .fb-search .bi{position:absolute;inset-inline-start:10px;top:50%;transform:translateY(-50%);color:var(--lv-muted);font-size:.9rem;pointer-events:none}
.filter-bar .btn-link{padding:0 6px;font-size:var(--fs-xs)}
/* فورم الفلاتر القديم (d-flex جوه رأس الكارت) ياخد نفس الشكل من غير ما يتعدّل */
.card-header>form.d-flex{gap:var(--sp-2)!important;align-items:center!important;min-width:0}
.card-header>form.d-flex .form-control,.card-header>form.d-flex .form-select,.card-header>form.d-flex .btn{min-height:var(--ctl-h-sm);font-size:var(--fs-sm);border-radius:8px}
.card-header>form.d-flex .form-check{display:inline-flex;align-items:center;gap:6px;margin:0;padding:0 10px;min-height:var(--ctl-h-sm);
  border:1px solid var(--lv-line);border-radius:8px;background:var(--lv-white)}
.card-header>form.d-flex .form-check .form-check-input{margin:0;float:none}
.card-header>form.d-flex .form-check .form-check-label{font-size:var(--fs-xs);font-weight:600;color:var(--lv-ink-2)}
/* شرايح الفلاتر السريعة */
.chip{display:inline-flex;align-items:center;min-height:28px;padding:0 12px;border-radius:var(--r-pill);
  border:1px solid var(--lv-line);background:var(--lv-white);color:var(--lv-ink-2);font-size:var(--fs-xs);font-weight:500;
  transition:background var(--dur) var(--ease),border-color var(--dur) var(--ease)}
.chip:hover{background:var(--lv-tint);border-color:var(--lv-soft);color:var(--lv-brand-dark);text-decoration:none}
.chip.on{background:var(--lv-brand-ink);border-color:var(--lv-brand-ink);color:#fff}
.chip-bar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:8px 16px;border-bottom:1px solid var(--lv-line-soft);background:var(--lv-surface-2)}
.pagination{--bs-pagination-border-radius:8px;--bs-pagination-color:var(--lv-ink-2);--bs-pagination-border-color:var(--lv-line);
  --bs-pagination-hover-bg:var(--lv-tint);--bs-pagination-hover-color:var(--lv-brand-dark);--bs-pagination-hover-border-color:var(--lv-soft);
  --bs-pagination-active-bg:var(--lv-brand);--bs-pagination-active-border-color:var(--lv-brand);--bs-pagination-font-size:var(--fs-sm);
  margin:0;gap:4px}
.page-link{border-radius:8px!important;min-width:34px;text-align:center}
.page-link:focus{box-shadow:var(--ring)}

/* ══ ⑫ الرسايل: تنبيهات + «تم» + الأخطاء ════════════════════ */
.alert{border-radius:var(--r-md);font-size:var(--fs-sm);border-width:1px;display:block;padding:.7rem 1rem;line-height:1.65}
.alert>.bi:first-child{margin-inline-end:4px}
.alert-warning{background:var(--lv-warn-bg);border-color:var(--lv-warn-line);color:var(--lv-warn)}
.alert-danger{background:var(--lv-danger-bg);border-color:var(--lv-danger-line);color:var(--lv-danger)}
.alert-success{background:var(--lv-ok-bg);border-color:var(--lv-ok-line);color:var(--lv-ok)}
.alert-info{background:var(--lv-info-bg);border-color:var(--lv-info-line);color:var(--lv-info)}
.alert a{color:inherit;text-decoration:underline;text-underline-offset:2px}
.alert.py-2{padding-block:.55rem}
.alert b{font-weight:700}
/* رسالة أخطاء التحقق: عنوان واضح + ليستة بلغة بسيطة */
.lv-errors{background:var(--lv-white);border:1px solid var(--lv-danger-line);border-inline-start:4px solid var(--lv-danger-dot);
  border-radius:var(--r-md);padding:12px 16px;margin-bottom:var(--sp-4);display:flex;gap:12px;align-items:flex-start;box-shadow:var(--e-1)}
.lv-errors .le-ico{width:34px;height:34px;flex:0 0 34px;border-radius:50%;background:var(--lv-danger-bg);color:var(--lv-danger);
  display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.lv-errors .le-title{font-weight:700;color:var(--lv-danger);font-size:var(--fs-base)}
.lv-errors .le-sub{font-size:var(--fs-xs);color:var(--lv-muted);margin-bottom:6px}
.lv-errors ul{margin:0;padding-inline-start:18px;font-size:var(--fs-sm);color:var(--lv-ink)}
.lv-errors li{margin-bottom:2px}
.lv-errors li a{color:var(--lv-danger);font-weight:600}
/* رسالة نجاح بالخطوة الجاية — بارزة */
.flow-toast{display:flex;align-items:flex-start;gap:14px;background:var(--lv-white);border:1px solid var(--lv-ok-line);
  border-inline-start:4px solid var(--lv-ok-dot);
  border-radius:var(--r-md);padding:14px 16px;margin-bottom:var(--sp-4);box-shadow:var(--e-2)}
.flow-toast .ft-ico{width:36px;height:36px;flex:0 0 36px;border-radius:50%;background:var(--lv-ok-bg);color:var(--lv-ok);
  display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.flow-toast .ft-title{font-weight:700;color:var(--lv-ink);font-size:var(--fs-md)}
.flow-toast .ft-next{margin-top:6px;font-size:var(--fs-sm);color:var(--lv-ink-2);background:var(--lv-surface-2);
  border:1px solid var(--lv-line);border-radius:8px;padding:8px 12px}
.flow-toast .ft-next b{color:var(--lv-brand-ink)}
.modal-content{border:0;border-radius:var(--r-lg);box-shadow:var(--e-3)}
.modal-header{border-bottom-color:var(--lv-line);padding:1rem 1.25rem}
.modal-title{font-weight:700;color:var(--lv-brand-ink);font-size:var(--fs-md)}
.modal-footer{border-top-color:var(--lv-line);background:var(--lv-surface-2);border-radius:0 0 var(--r-lg) var(--r-lg)}
.dropdown-menu{border-color:var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-3);font-size:var(--fs-sm)}
.accordion-button:not(.collapsed){background:var(--lv-tint);color:var(--lv-brand-ink)}
.accordion-button:focus{box-shadow:var(--ring)}
.list-group-item{border-color:var(--lv-line)}
.list-group-item-action.active,.list-group-item-action:active{background:var(--lv-tint);color:var(--lv-brand-ink);border-color:var(--lv-line)}
.nav-tabs .nav-link{color:var(--lv-muted);font-weight:600}
.nav-tabs .nav-link.active{color:var(--lv-brand-ink)}
.nav-pills .nav-link.active{background:var(--lv-brand)}
details>summary{cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:6px}
details>summary::-webkit-details-marker{display:none}
details>summary::before{content:"";width:0;height:0;border:5px solid transparent;border-inline-start-color:currentColor;transition:transform var(--dur) var(--ease)}
details[open]>summary::before{transform:rotate(-90deg)}
.tooltip{font-family:var(--ff-ui);font-size:var(--fs-xs)}
.tooltip-inner{background:var(--lv-brand-ink);border-radius:8px;padding:6px 10px;max-width:280px;text-align:start}

/* ══ ⑬ المعادلات ⓘ: كل رقم محسوب جنبه زرار يفتح الحسبة بالأرقام ═══ */
.fx{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;border:0;
  background:var(--lv-tint);color:var(--lv-brand);font-size:.7rem;line-height:1;padding:0;vertical-align:middle;
  margin-inline-start:4px;cursor:help;flex:0 0 18px;transition:background var(--dur) var(--ease),color var(--dur) var(--ease)}
.fx:hover,.fx:focus-visible,.fx[aria-describedby]{background:var(--lv-brand);color:#fff;outline:0}
.fx .bi{font-size:.72rem}
.stat .fx,.kpi-fx{position:absolute;inset-inline-end:12px;inset-block-start:12px;z-index:2}
/* نقطة لون صغيرة جنب كود اللون */
.color-dot{display:inline-block;width:12px;height:12px;border-radius:3px;border:1px solid var(--lv-line);vertical-align:-1px;margin-inline-end:4px}
.popover{font-family:var(--ff-ui);border:1px solid var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-3);max-width:360px;font-size:var(--fs-sm)}
.popover.fx-pop .popover-header{background:var(--lv-tint);color:var(--lv-brand-ink);font-weight:700;font-size:var(--fs-sm);border-bottom-color:var(--lv-soft);
  border-radius:var(--r-md) var(--r-md) 0 0}
.popover.fx-pop .popover-body{padding:10px 12px;color:var(--lv-ink);line-height:1.8}
.fx-f{font-variant-numeric:tabular-nums;background:var(--lv-surface-2);border:1px solid var(--lv-line);border-radius:7px;padding:6px 10px;
  margin:4px 0;display:block;direction:rtl;text-align:start;unicode-bidi:plaintext;font-weight:600;color:var(--lv-brand-ink);overflow-wrap:anywhere}
.fx-f .op{color:var(--lv-muted);font-weight:400;padding:0 3px}
.fx-why{color:var(--lv-muted);font-size:var(--fs-xs);display:block;margin-top:4px}

/* ══ ⑭ الموبايل: المنيو درج، الجداول سكرول أفقي، الفلاتر تحت بعض ══ */
@media (max-width:1100px){
  .filter-bar .form-control{width:160px}
  .filter-bar .form-select{width:140px}
}
@media (max-width:900px){
  .sidebar{width:280px;transform:translateX(100%);visibility:hidden;
    transition:transform var(--dur) var(--ease),visibility 0s var(--dur)}
  body.nav-open .sidebar{transform:translateX(0);visibility:visible;transition-delay:0s;box-shadow:var(--e-3)}
  .main{margin-inline-start:0 !important}
  .nav-burger{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
    width:42px;height:42px;border:1px solid var(--lv-line);border-radius:10px;
    background:var(--lv-white);color:var(--lv-brand-ink);font-size:1.25rem}
  .content{padding:var(--sp-4) var(--sp-4) var(--sp-7)}
  .topbar{padding:0 12px;gap:10px;min-height:58px}
  .topbar h2{font-size:1.1rem}
  .topbar .page-purpose{display:none}
  .tb-who,.tb-logout-label{display:none}
  .tb-user{border:0;margin:0;padding:0}
  /* iOS ما يزوّمش على الإنبوت */
  .form-control,.form-select,.form-control-sm,.form-select-sm,
  .line-table input,.line-table select{font-size:16px}
  .btn{min-height:44px}
  .btn-sm{min-height:40px}
  .table td:last-child .btn-sm,.table .row-actions .btn{min-height:36px;min-width:36px}
  .card{margin-bottom:var(--sp-3)}
  .card-header{padding:.7rem .9rem}
  .card-body{padding:.9rem}
  .stat{padding:12px 14px}
  .stat .v{font-size:var(--fs-xl)}
  /* الجدول ما يتكسرش: سكرول أفقي بعرض أدنى */
  .table-responsive{overflow-x:auto;scroll-snap-type:none}
  .table-responsive>.table{min-width:640px}
  .table-responsive>.table.kv,.table-responsive>.table.no-min{min-width:0}
  .table>thead>tr>th{position:static}
  .form-actions{flex-wrap:nowrap;padding:10px 12px;margin-inline:calc(-1 * var(--sp-4));border-radius:0;border-inline:0}
  .form-actions .hint,.form-actions .req-legend{display:none}
  .form-actions .btn-plum,.form-actions .btn-success{min-width:0;flex:1 1 auto}
  .sum-sec{grid-template-columns:1fr;gap:4px}
  .sum-grid{grid-template-columns:repeat(auto-fit,minmax(110px,1fr))}
  .filter-bar,.card-header>form.d-flex{width:100%;margin-inline-start:0}
  .filter-bar .form-control,.filter-bar .form-select,.filter-bar .fb-search,.filter-bar .fb-range,
  .card-header>form.d-flex .form-control,.card-header>form.d-flex .form-select{flex:1 1 140px;width:auto!important}
  .filter-bar .fb-range input{width:auto;flex:1}
  .doc-actions .da-end{margin-inline-start:0;width:100%}
  .flow-next>.hint{flex-basis:100%}
  .flow-next .btn{flex:1 1 auto;margin-inline-start:0!important;min-height:38px}
  input[type=date].form-control,input[type=number].form-control{max-width:none}
  .input-group:has(>input[type=number]){max-width:none}

  body.nav-collapsed .sidebar{width:280px;overflow-y:auto;overflow-x:hidden}
  body.nav-collapsed .sidebar .brand{padding:22px 18px 16px;text-align:start}
  body.nav-collapsed .sidebar .brand img{width:116px;margin:0}
  body.nav-collapsed .sidebar .brand small,
  body.nav-collapsed .nav-sep,
  body.nav-collapsed .sidebar .nav-group-btn > span,
  body.nav-collapsed .sidebar .nav-group-btn .caret,
  body.nav-collapsed .sidebar > a.nav-item > span{display:inline}
  body.nav-collapsed .sidebar .nav-group{padding:0 10px}
  body.nav-collapsed .sidebar .nav-group.is-open{background:rgba(0,0,0,.16);padding-bottom:4px}
  body.nav-collapsed .sidebar .nav-group-btn,
  body.nav-collapsed .sidebar > a.nav-item{justify-content:flex-start;padding-inline:10px;gap:10px}
  body.nav-collapsed .sidebar > a.nav-item{margin-inline:10px}
  body.nav-collapsed .nav-group-btn .badge-count{position:static;margin-inline-start:auto;
    margin-inline-end:6px;min-width:22px;height:20px;line-height:20px;padding:0 7px;font-size:.7rem}
  body.nav-collapsed .nav-group .nav-sub{position:relative;width:auto;box-shadow:none;border-radius:0;background:none;
    max-height:none;overflow:visible;margin:2px 0 6px;padding:0;padding-inline-start:16px}
  body.nav-collapsed .nav-group .nav-sub::before{display:block}
  body.nav-collapsed .nav-group .nav-sub a.nav-item{padding-inline-start:14px;justify-content:flex-start}
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
  .content{padding:var(--sp-3) var(--sp-3) var(--sp-7)}
  .stepper .st span.lbl{font-size:.68rem}
  .card-header{padding:.65rem .8rem}
  .card-header>span:first-child{width:100%}
  .card-header .btn-plum{margin-inline-start:auto}
  .form-actions{margin-inline:calc(-1 * var(--sp-3))}
  .big-nums{gap:var(--sp-3);justify-content:space-between}
  .q-grid{grid-template-columns:1fr 1fr}
  .row.g-3>[class*=col-6]{flex:0 0 50%}
}
@media (min-width:901px){ .nav-scrim{display:none} }

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms !important;animation-iteration-count:1 !important;
    transition-duration:.01ms !important;scroll-behavior:auto !important}
}

/* ══ ⑮ الطباعة من الشاشة العادية ═══════════════════════════ */
@media print{
  .sidebar,.topbar,.btn,.card-footer,.flow-bar,.form-actions,.doc-actions,.filter-bar,.chip-bar,.nav-scrim,.fx{display:none !important}
  .main{margin:0}body{background:#fff}.content{padding:0;max-width:none}
  .card{box-shadow:none;break-inside:avoid}
}
</style>
</head>
<body>

@php
  $u   = auth()->user();
  $cnt = $navCounters ?? [];

  /* المنيو مرتّب بترتيب الدورة الحقيقي، والخطوات مترقّمة 1 ← 13
     بنفس ترتيب «دليل الفلو» ونفس الأرقام اللي في شريط الفلو.
     كل مجموعة تاب بيتفتح ويتقفل، والتاب اللي فيه الشاشة المفتوحة بيفتح لوحده.
     العنصر: [الراوت, العنوان, الأيقونة, الصلاحية, الشرح, رقم الخطوة (اختياري)]
     الرقم الأحمر هو التنبيه الوحيد في المنيو، ومعناه: فيه شغل مستني منك هنا. */
  $nav = [
    ['main', '', 'bi-grid', [
      ['dashboard', 'لوحة التحكم', 'bi-speedometer2', null,
       'صورة السيستم كله: الشراء، القماش، التشغيل، التغطية'],
    ]],

    /* مكاتب الإدارات — كل واحد بيفتح مكتبه فيلاقي شغله مستنيه */
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
    ], '', 'desks'],

    ['buy', 'الشراء', 'bi-cart3', [
      ['purchase-orders.index', 'طلب شراء', 'bi-file-earmark-text',
       'po.view|po.request|po.source|po.finance',
       'التخطيط بيطلب الخامة — الحفظ بينزّل الطلب للمشتريات على طول', 1],
      ['purchasing.queue', 'التسعير', 'bi-tag', 'po.source',
       'المشتريات: المورد والسعر وتاريخ التوريد — بعدها الطلب جاهز للاستلام', 2],
      ['finance.payables', 'الحسابات', 'bi-cash-coin', 'po.finance',
       'الحسابات: المستحق المتوقع لكل مورد — متابعة مش بوابة', 3],
    ], '①'],

    ['fabric', 'وصول القماش', 'bi-box-arrow-in-down', [
      ['stock-additions.index', 'إذن إضافة', 'bi-box-arrow-in-down', 'receipt.view',
       'المخزن بيستلم على الطلب ⇐ كل (خامة + لون) رسالة محجوزة تحت الفحص', 4],
      ['inspections.index', 'الفحص', 'bi-search', 'qc.view',
       'جرد الأتواب وقياس عرض وطول كل توب ⇐ أقل عرض — والمعمل مدمج في نفس الورقة', 5],
      ['lab-reports.index', 'المعمل', 'bi-thermometer-half', 'qc.view',
       'وزن البنشر والانكماش ومطابقة اللون', 6],
      ['goods-receipts.index', 'إذن استلام خام', 'bi-truck', 'receipt.view',
       'الإفراج: السليم يبقى متاح للتشغيل والمرفوض يروح المرفوضات', 7],
      ['rejections.index', 'المرفوضات', 'bi-x-octagon', 'qc.view|receipt.view|po.view',
       'أتواب مرفوضة وألوان معلّقة لحين رد التخطيط والمشتريات'],
    ], '②'],

    ['prod', 'التشغيل', 'bi-hammer', [
      ['preparation-requests.index', 'طلب تحضير (تقسيم)', 'bi-speedometer2', 'prep.request|prep.weigh',
       'أول خطوة: التخطيط يطلب كمية تقريبية من رسالة والمخزن يوزن ويرد بالفعلي — وطلب الماركر بيتعمل معاه'],
      ['markers.requests', 'طلبات الماركر', 'bi-envelope-paper', 'marker.view',
       'المخطط بيطلب تعشيقة على أقل عرض', 8],
      ['markers.index', 'الماركرات', 'bi-grid-3x3', 'marker.view',
       'الباترونست: طول الفرشة وعدد القطع والموديلات', 9],
      ['work-orders.index', 'أوامر الشغل', 'bi-hammer', 'wo.view',
       'ورقة المصنع: الرسالة + الماركر + الحسبة، والخامة الحاكمة بتحدد الإنتاج', 10],
      ['material-issues.index', 'صرف خام', 'bi-box-arrow-up', 'receipt.view|wo.view',
       'صرف الخامة من المخزن للمصنع مقابل أمر الشغل — الجزئي مسموح', 11],
      ['cut-declarations.index', 'بيان القص', 'bi-scissors', 'cut.view',
       'الفعلي من المصنع وانحرافه عن المتوقع', 12],
      ['production-receipts.index', 'استلامات الإنتاج', 'bi-inboxes', 'prod.manage|wo.view',
       'استلام على دفعات لحد ما أمر الشغل يتقفل لوحده', 13],
      ['factory.portal', 'بوابة المصنع', 'bi-phone', 'factory.portal|cut.manage',
       'شاشة المصنع من الموبايل: أوامره، القص، والاستلام'],
    ], '③'],

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

  @foreach($nav as $g)
    @php
      [$gid, $group, $gicon, $items] = $g;
      $phase = $g[4] ?? '';
      $visible = array_values(array_filter($items, fn ($it) => Route::has($it[0]) && $allowed($it[3])));
      $groupCount = 0; $isOpen = false;
      foreach ($visible as $it) {
        $groupCount += $cnt[$it[0]] ?? 0;
        if (request()->routeIs(str_replace('.index','.*',$it[0])) || request()->routeIs($it[0])) $isOpen = true;
      }
    @endphp
    @continue(!count($visible))

    @if(!$group)
      @foreach($visible as $it)
        @php [$route,$label,$icon,$perm,$info] = $it; @endphp
        <a class="nav-item {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}"
           data-label="{{ $label }}" title="{{ $info }}"
           @if(request()->routeIs($route)) aria-current="page" @endif>
          <i class="bi {{ $icon }} ni" aria-hidden="true"></i><span>{{ $label }}</span>
        </a>
      @endforeach
      @continue
    @endif

    @if($gid === 'buy')<div class="nav-sep">دورة الشغل — بالترتيب</div>@endif
    @if($gid === 'stock')<div class="nav-sep">المتابعة والإعداد</div>@endif

    {{-- المجموعة في حاوية واحدة — عشان اللوحة تطلع جنبها في الوضع المطوي --}}
    <div class="nav-group {{ $isOpen ? 'is-open' : '' }}" data-group="{{ $gid }}">
      <button class="nav-group-btn" type="button" data-label="{{ $phase ? $phase . ' ' : '' }}{{ $group }}"
              data-bs-toggle="collapse" data-bs-target="#g-{{ $gid }}"
              aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="g-{{ $gid }}">
        @if($phase)
          <span class="phase-n" aria-hidden="true">{{ $phase }}</span>
        @else
          <i class="bi {{ $gicon }} gi" aria-hidden="true"></i>
        @endif
        <span>{{ $group }}</span>
        @if($groupCount > 0)
          <span class="badge badge-count rounded-pill">{{ $groupCount }}<span class="visually-hidden"> مستني منك</span></span>
        @endif
        <i class="bi bi-chevron-down caret" aria-hidden="true"></i>
      </button>

      <div class="collapse nav-sub {{ $isOpen ? 'show' : '' }}" id="g-{{ $gid }}" data-bs-parent="#sideNav">
        <div class="nav-sub-title">{{ $phase ? $phase . ' ' : '' }}{{ $group }}</div>
        @foreach($visible as $it)
          @php
            [$route,$label,$icon,$perm,$info] = $it;
            $stepNo = $it[5] ?? null;
            $on = request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route);
            $n  = $cnt[$route] ?? 0;
          @endphp
          <a class="nav-item {{ $on ? 'active' : '' }}" href="{{ route($route) }}" title="{{ $info }}"
             @if($on) aria-current="page" @endif>
            @if($stepNo)
              <span class="step-no" aria-hidden="true">{{ $stepNo }}</span>
            @endif
            <i class="bi {{ $icon }} ni" aria-hidden="true"></i>
            <span>@if($stepNo)<span class="visually-hidden">خطوة {{ $stepNo }}: </span>@endif{{ $label }}</span>
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
      <form method="post" action="{{ route('logout') }}" data-no-busy="1">@csrf
        <button class="tb-icon" title="خروج" aria-label="تسجيل الخروج"><i class="bi bi-box-arrow-left" aria-hidden="true"></i></button>
      </form>
    </div>
  </div>

  <main class="content" id="main-content" tabindex="-1">
    @include('partials.alerts')
    @yield('content')
  </main>
</div>

{{-- أخطاء التحقق بالحقل — بتتحط جنب الحقل نفسه بالجافاسكريبت (شوف LVX.fieldErrors) --}}
@if($errors->any())
  <script type="application/json" id="lv-field-errors">@json($errors->toArray())</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── LVX: أدوات الواجهة المشتركة ─────────────────────────────
   LVX.popovers(root)   — تفعيل ⓘ المعادلات والتلميحات جوه عنصر
   LVX.fx(title, lines) — HTML لزرار ⓘ (للسطور اللي بتتبني بالجافاسكريبت)
   LVX.fieldErrors()    — نقل أخطاء التحقق لجنب الحقل */
window.LVX = (function () {
  function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  function popovers(root) {
    (root || document).querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
      if (bootstrap.Popover.getInstance(el)) return;
      new bootstrap.Popover(el, {
        html: true, trigger: el.dataset.bsTrigger || 'hover focus', placement: el.dataset.bsPlacement || 'top',
        customClass: 'fx-pop', container: 'body', sanitize: false
      });
    });
    (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      if (!bootstrap.Tooltip.getInstance(el)) new bootstrap.Tooltip(el, {container: 'body'});
    });
  }

  /* lines: سطر واحد أو مصفوفة سطور — كل سطر معادلة بالأرقام الفعلية. why: سطر شرح اختياري */
  function fx(title, lines, why) {
    var arr = Array.isArray(lines) ? lines : [lines];
    var body = arr.filter(Boolean).map(l => '<span class="fx-f">' + esc(l) + '</span>').join('')
             + (why ? '<span class="fx-why">' + esc(why) + '</span>' : '');
    return '<button type="button" class="fx" data-bs-toggle="popover" data-bs-title="' + esc(title || 'إزاي اتحسب؟')
         + '" data-bs-content="' + esc(body) + '" aria-label="' + esc(title || 'إزاي اتحسب؟') + '"><i class="bi bi-info-lg" aria-hidden="true"></i></button>';
  }

  /* اسم الحقل في Laravel: lines.0.qty ⇒ lines[0][qty] (وكمان lines[0][qty][] للمصفوفات) */
  function fieldErrors() {
    var el = document.getElementById('lv-field-errors'); if (!el) return;
    var map; try { map = JSON.parse(el.textContent); } catch (e) { return; }
    var first = null;
    Object.keys(map).forEach(function (key) {
      var parts = key.split('.'), name = parts[0] + parts.slice(1).map(p => '[' + p + ']').join('');
      var f = document.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
      if (!f) return;
      f.classList.add('is-invalid'); f.setAttribute('aria-invalid', 'true');
      var host = f.closest('.input-group') || f.closest('.cons-picker') || f.closest('.model-picker') || f;
      if (!host.nextElementSibling || !host.nextElementSibling.classList.contains('invalid-feedback')) {
        var d = document.createElement('div'); d.className = 'invalid-feedback d-block';
        d.textContent = map[key].join(' · ');
        host.insertAdjacentElement('afterend', d);
      }
      if (!first) first = f;
    });
    /* لينكات في ليستة الأخطاء فوق بتنط للحقل */
    document.querySelectorAll('.lv-errors li[data-field]').forEach(function (li) {
      var key = li.dataset.field, parts = key.split('.'), name = parts[0] + parts.slice(1).map(p => '[' + p + ']').join('');
      var f = document.querySelector('[name="' + name + '"], [name="' + name + '[]"]'); if (!f) return;
      li.style.cursor = 'pointer'; li.title = 'روح للحقل';
      li.addEventListener('click', function () { f.scrollIntoView({block: 'center', behavior: 'smooth'}); try { f.focus({preventScroll: true}); } catch (e) {} });
    });
  }

  /* علامة «* إجباري» تحت أي فورم فيه حقول إجبارية */
  function reqLegend() {
    document.querySelectorAll('form').forEach(function (form) {
      var bar = form.querySelector('.form-actions'); if (!bar || bar.querySelector('.req-legend')) return;
      if (!form.querySelector('.req')) return;
      var s = document.createElement('span'); s.className = 'req-legend'; s.textContent = 'إجباري';
      bar.appendChild(s);
    });
  }

  document.addEventListener('DOMContentLoaded', function () { popovers(document); fieldErrors(); reqLegend(); });
  return {popovers, fx, fieldErrors, esc};
})();

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

  /* المجموعة المفتوحة تاخد لوح أغمق — بيتحدّث مع الطي والفتح */
  document.querySelectorAll('#sideNav .nav-sub').forEach(function (sub) {
    sub.addEventListener('show.bs.collapse', function () { sub.closest('.nav-group')?.classList.add('is-open'); });
    sub.addEventListener('hide.bs.collapse', function () { sub.closest('.nav-group')?.classList.remove('is-open'); });
  });

  var burger = document.getElementById('navBurger'), scrim = document.getElementById('navScrim');
  var close = function (focusBack) {
    if (!body.classList.contains('nav-open')) return;
    body.classList.remove('nav-open');
    burger?.setAttribute('aria-expanded', 'false');
    if (focusBack) burger?.focus();
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
