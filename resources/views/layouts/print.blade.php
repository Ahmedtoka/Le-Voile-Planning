<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@hasSection('doc_title')@yield('doc_title') — @yield('doc_serial')@else{{ $title ?? 'طباعة' }}@endif — Le Voile</title>
<link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ============================================================
   Le Voile — مستندات الطباعة (A4)
   stylesheet واحد لكل مستندات print/*  — البراند #9D197E / #3B092F
   ① الصفحة والخط   ② الشريط (no-print)   ③ رأس المستند
   ④ جدول البيانات .meta   ⑤ جدول الأصناف .grid   ⑥ الإجماليات .totals
   ⑦ أقسام/ملاحظات/شروط   ⑧ التوقيعات   ⑨ التذييل   ⑩ أدوات صغيرة   ⑪ الطباعة
   ============================================================ */

/* ① الصفحة والخط ------------------------------------------------ */
@page { size: A4; margin: 12mm; }
*, *::before, *::after { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body {
  margin: 0; background: #ECE7EA; color: #111;
  font: 400 11.5px/1.55 'IBM Plex Sans Arabic', 'Cairo', system-ui, 'Segoe UI', Tahoma, sans-serif;
  font-variant-numeric: tabular-nums;
}
.sheet {
  width: 210mm; min-height: 297mm; margin: 14px auto 28px; padding: 12mm;
  background: #fff; box-shadow: 0 2px 14px rgba(59, 9, 47, .14);
}
b, strong { font-weight: 700; }
img { max-width: 100%; }

/* ② شريط الأوامر (لا يُطبع) ------------------------------------- */
.no-print {
  position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; align-items: center;
  justify-content: center; padding: 10px 12px; background: #fff; border-bottom: 1px solid #DDD3DA;
}
.tb-btn {
  display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 18px;
  border-radius: 8px; border: 1px solid #9D197E; background: #9D197E; color: #fff;
  font: 600 13px/1 'IBM Plex Sans Arabic', system-ui, sans-serif; cursor: pointer; text-decoration: none;
}
.tb-btn svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.tb-btn:hover { background: #7E1465; border-color: #7E1465; }
.tb-btn.secondary { background: #fff; color: #3B092F; border-color: #C9B6C3; }
.tb-btn.secondary:hover { background: #F7EEF4; }

/* ③ رأس المستند --------------------------------------------------- */
.doc-head {
  display: grid; grid-template-columns: 150px 1fr 150px; align-items: center; gap: 10px;
  padding-bottom: 8px; margin-bottom: 10px; border-bottom: 2px solid #9D197E;
}
.logo img { width: 140px; display: block; }
.logo small { display: block; width: 140px; margin-top: 2px; text-align: center; font-size: 8.5px; letter-spacing: 3px; color: #9D197E; }
.doc-title { text-align: center; }
.doc-title h1 { margin: 0; font-size: 19px; font-weight: 700; line-height: 1.3; color: #3B092F; }
.doc-subtitle { margin-top: 2px; font-size: 12.5px; font-weight: 600; color: #333; }
.doc-no { justify-self: end; min-width: 150px; text-align: center; border: 1px solid #444; border-radius: 4px; padding: 5px 8px; }
.doc-no .serial { display: block; font-size: 15px; font-weight: 700; color: #9D197E; direction: ltr; unicode-bidi: isolate; letter-spacing: .3px; }
.doc-no .sub { font-size: 10px; color: #555; margin-top: 1px; }

/* ④ جدول البيانات الرئيسية (مفتاح / قيمة) ------------------------ */
table { border-collapse: collapse; width: 100%; }
td, th { word-break: break-word; overflow-wrap: anywhere; }
.meta { margin-bottom: 8px; }
.meta + .meta { margin-top: -2px; }
.meta td, .meta th { border: 1px solid #444; padding: 4px 7px; font-size: 11.5px; text-align: right; vertical-align: top; }
.meta th, .meta td.k { background: #F7E8F3; color: #3B092F; font-weight: 600; width: 105px; white-space: nowrap; }
.meta .v-num { width: 130px; }
.meta .v-sm { width: 90px; }
.meta .v-md { width: 110px; }
.meta .v-lg { width: 150px; }
.meta td.photo-cell { width: 130px; text-align: center; vertical-align: middle; }

/* ⑤ جدول الأصناف ------------------------------------------------- */
.grid { margin-top: 6px; margin-bottom: 8px; table-layout: fixed; }
.grid th, .grid td { border: 1px solid #444; padding: 4px 5px; font-size: 11px; text-align: center; vertical-align: middle; }
.grid thead th { background: #F7E8F3; color: #3B092F; font-weight: 600; line-height: 1.35; }
.grid th.r, .grid td.r { text-align: right; }
.grid td.num, .grid th.num { font-variant-numeric: tabular-nums; }
.grid tbody th { background: #F7E8F3; color: #3B092F; font-weight: 600; text-align: right; }
.grid tr.total td { font-weight: 700; border-top: 2px solid #444; background: #FBF7FA; }
.grid tr.blank td { height: 21px; border-color: #999; }
.grid tr.blank td:first-child { color: #999; }
.grid td.top { vertical-align: top; }
.grid td.tall { height: 190px; vertical-align: top; text-align: right; }
.grid .cell-sep { display: block; margin-top: 3px; padding-top: 3px; border-top: 1px solid #444; font-weight: 700; }
.grid tr, .meta tr, .totals tr { page-break-inside: avoid; break-inside: avoid; }
thead { display: table-header-group; }

/* ⑥ جدول الإجماليات ----------------------------------------------- */
.totals { width: 100%; }
.totals td { border: 1px solid #444; padding: 4px 7px; font-size: 11.5px; text-align: right; }
.totals td.k { background: #F7E8F3; color: #3B092F; font-weight: 600; width: 150px; }
.totals tr.grand td { font-weight: 700; background: #FBF7FA; }

/* ⑦ أقسام / ملاحظات / شروط ---------------------------------------- */
.sec { margin: 10px 0 2px; padding-bottom: 2px; border-bottom: 1px solid #C9B6C3; font-size: 12px; font-weight: 700; color: #3B092F; }
.sec:first-child { margin-top: 0; }
.terms { margin-top: 8px; font-size: 10.5px; line-height: 1.75; }
.terms ul { margin: 0; padding: 0; list-style: none; }
.terms li { padding-inline-start: 12px; position: relative; }
.terms li::before { content: '•'; position: absolute; inset-inline-start: 0; color: #9D197E; }
.note { margin-top: 8px; font-size: 11px; }
.note .lbl { font-weight: 600; color: #3B092F; }
.box { border: 1px solid #444; border-radius: 3px; padding: 6px 8px; margin-top: 8px; font-size: 10.5px; line-height: 1.75; }
.box.warn { border-color: #C0392B; }
.box.warn .mark { color: #C0392B; font-weight: 700; }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; align-items: start; }
.cols-end { display: grid; grid-template-columns: 1fr 290px; gap: 14px; align-items: start; margin-top: 8px; }
.cols .meta, .cols-end .meta { margin-bottom: 0; }

/* ⑧ التوقيعات ----------------------------------------------------- */
.sigs { display: flex; justify-content: space-between; gap: 14px; margin-top: 26px; page-break-inside: avoid; break-inside: avoid; }
.sig { flex: 1; text-align: center; font-size: 11px; font-weight: 600; color: #3B092F; }
.sig .line { margin-top: 28px; padding-top: 3px; border-top: 1px dotted #444; font-weight: 400; color: #111; min-height: 18px; }
.approval { margin-top: 22px; text-align: center; font-weight: 600; color: #3B092F; }
.approval .line { width: 180px; margin: 24px auto 0; border-top: 1px dotted #444; }

/* ⑨ التذييل --------------------------------------------------------- */
.footer { margin-top: 16px; padding-top: 5px; border-top: 1px solid #C9B6C3; font-size: 9.5px; color: #666; text-align: center; }

/* ⑩ أدوات صغيرة ------------------------------------------------------ */
.num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
.code { direction: ltr; unicode-bidi: isolate; letter-spacing: .2px; }
.small { font-size: 10px; color: #555; }
.muted { color: #666; }
.ok { color: #1E7B34; font-weight: 600; }
.bad { color: #C0392B; font-weight: 600; }
.star { color: #9D197E; }
.photo { display: block; max-width: 120px; max-height: 120px; margin: 0 auto 2px; border: 1px solid #999; }
.thumb { height: 34px; max-width: 50px; vertical-align: middle; border: 1px solid #999; margin-inline-end: 4px; }
.swatch { margin-top: 6px; }
.swatch img { max-height: 150px; border: 1px solid #999; }

/* ⑪ الطباعة ---------------------------------------------------------- */
@media print {
  body { background: #fff; padding-bottom: 9mm; }
  .no-print { display: none !important; }
  .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
  .footer { position: fixed; bottom: 0; left: 0; right: 0; margin: 0; background: #fff; }
  a { color: inherit; text-decoration: none; }
}
@media screen and (max-width: 820px) {
  .sheet { width: auto; min-height: 0; margin: 0; padding: 16px; }
}
</style>
</head>
<body>
<div class="no-print">
  <button type="button" class="tb-btn" onclick="window.print()">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
    طباعة
  </button>
  <button type="button" class="tb-btn secondary" onclick="history.back()">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
    رجوع
  </button>
</div>
<main class="sheet">
@hasSection('doc_title')
  <header class="doc-head">
    <div class="logo">
      <img src="{{ asset('assets/logo.png') }}" alt="Le Voile">
      <small>@yield('doc_tagline', 'FASHION FORWARD')</small>
    </div>
    <div class="doc-title">
      <h1>@yield('doc_title')</h1>
      @hasSection('doc_subtitle')<div class="doc-subtitle">@yield('doc_subtitle')</div>@endif
    </div>
    <div class="doc-no">
      <span class="serial">@yield('doc_serial')</span>
      @hasSection('doc_serial_note')<div class="sub">@yield('doc_serial_note')</div>@endif
    </div>
  </header>
@endif
@yield('doc')
<div class="footer">
  Le Voile — Fashion Forward · نظام تخطيط الإنتاج · طُبع {{ now()->format('Y-m-d H:i') }} بواسطة {{ auth()->user()?->name }}
</div>
</main>
</body>
</html>
