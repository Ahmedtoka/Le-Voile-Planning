<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'طباعة' }} — Le Voile</title>
<link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{font-family:'Cairo',sans-serif;box-sizing:border-box}
  body{margin:0;padding:14mm;color:#000;font-size:12px;background:#fff}
  .doc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
  /* هوية Le Voile — #9D197E */
  .logo img{width:150px;display:block}
  .logo small{display:block;font-size:9px;color:#9D197E;letter-spacing:3px;font-weight:400;
    margin-top:2px;text-align:center}
  .doc-title{font-size:20px;font-weight:700;text-align:center;flex:1;color:#3B092F}
  .serial{color:#C0392B;font-weight:700;font-size:14px}
  .meta{width:100%;border-collapse:collapse;margin-bottom:10px}
  .meta td{border:1px solid #000;padding:4px 7px;font-size:11.5px}
  .meta td.k{background:#F8F4F1;font-weight:600;width:110px;color:#3B092F}
  table.grid{width:100%;border-collapse:collapse;margin-top:6px}
  table.grid th,table.grid td{border:1px solid #000;padding:4px 5px;font-size:11px;text-align:center}
  table.grid th{background:#F7E8F3;font-weight:600;color:#3B092F}
  table.grid td.r{text-align:right}
  .totals{width:290px;border-collapse:collapse;margin-top:8px;margin-inline-start:auto}
  .totals td{border:1px solid #000;padding:4px 7px;font-size:11.5px}
  .totals td.k{background:#F8F4F1;font-weight:600;color:#3B092F}
  .sigs{display:flex;justify-content:space-between;margin-top:26px;gap:12px}
  .sig{flex:1;text-align:center;font-size:11px}
  .sig .line{margin-top:26px;border-top:1px dotted #000;padding-top:3px}
  .terms{font-size:10.5px;margin-top:8px;line-height:1.7}
  .footer{margin-top:14px;border-top:1px solid #ECD1E5;padding-top:5px;font-size:10px;color:#666;text-align:center}
  .no-print{margin-bottom:10px}
  @media print{.no-print{display:none}body{padding:8mm}}
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()" style="padding:6px 16px;font-size:13px;cursor:pointer">🖨 طباعة</button>
  <button onclick="history.back()" style="padding:6px 16px;font-size:13px;cursor:pointer">رجوع</button>
</div>
@yield('doc')
<div class="footer">
  Le Voile — Fashion Forward · نظام تخطيط الإنتاج · طُبع {{ now()->format('Y-m-d H:i') }} بواسطة {{ auth()->user()?->name }}
</div>
</body>
</html>
