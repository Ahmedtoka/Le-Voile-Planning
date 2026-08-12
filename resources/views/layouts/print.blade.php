<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'طباعة' }} — Le Voile</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{font-family:'Cairo',sans-serif;box-sizing:border-box}
  body{margin:0;padding:14mm;color:#000;font-size:12px;background:#fff}
  .doc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
  .logo{font-size:26px;font-weight:700;color:#6d2a5f;letter-spacing:1px}
  .logo small{display:block;font-size:10px;color:#8a6a82;letter-spacing:2px;font-weight:400}
  .doc-title{font-size:20px;font-weight:700;text-align:center;flex:1}
  .serial{color:#c0392b;font-weight:700;font-size:14px}
  .meta{width:100%;border-collapse:collapse;margin-bottom:10px}
  .meta td{border:1px solid #000;padding:4px 7px;font-size:11.5px}
  .meta td.k{background:#f2f2f2;font-weight:600;width:110px}
  table.grid{width:100%;border-collapse:collapse;margin-top:6px}
  table.grid th,table.grid td{border:1px solid #000;padding:4px 5px;font-size:11px;text-align:center}
  table.grid th{background:#eee;font-weight:600}
  table.grid td.r{text-align:right}
  .totals{width:290px;border-collapse:collapse;margin-top:8px;margin-inline-start:auto}
  .totals td{border:1px solid #000;padding:4px 7px;font-size:11.5px}
  .totals td.k{background:#f2f2f2;font-weight:600}
  .sigs{display:flex;justify-content:space-between;margin-top:26px;gap:12px}
  .sig{flex:1;text-align:center;font-size:11px}
  .sig .line{margin-top:26px;border-top:1px dotted #000;padding-top:3px}
  .terms{font-size:10.5px;margin-top:8px;line-height:1.7}
  .footer{margin-top:14px;border-top:1px solid #999;padding-top:5px;font-size:10px;color:#444;text-align:center}
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
