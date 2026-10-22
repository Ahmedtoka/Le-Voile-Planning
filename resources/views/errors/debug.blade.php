{{--
  صفحة خطأ تفصيلية بديلة.
  بتشتغل بس لما APP_DEBUG=true و highlight_file مقفولة على السيرفر (bootstrap/app.php).
  متعمّدة تكون بسيطة جدًا: مفيش layout ولا داتابيز ولا أصول خارجية غير الخط —
  عشان تشتغل حتى لو السيستم نفسه واقع. بتستقبل $e (Throwable).
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>خطأ في السيستم — Le Voile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
 :root{
   --bg:#F8F4F1; --card:#fff; --ink:#2B2028; --muted:#6B606A; --line:#EAE2E7;
   --plum:#9D197E; --plum-d:#7A1362; --plum-t:#F7E8F3; --plum-r:#ECD1E5;
   --red:#B5342B; --red-d:#8F2721;
   --r:14px;
   --sh:0 12px 32px -10px rgba(59,9,47,.22), 0 2px 6px rgba(59,9,47,.06);
   --ff:'IBM Plex Sans Arabic', system-ui, 'Segoe UI', Tahoma, sans-serif;
   --mono:ui-monospace, Consolas, 'Courier New', monospace;
 }
 *{box-sizing:border-box}
 html,body{margin:0}
 body{font-family:var(--ff);background:var(--bg);color:var(--ink);padding:32px 16px;line-height:1.7}
 .wrap{max-width:1000px;margin:0 auto}
 .card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
 .head{background:var(--red);color:#fff;padding:18px 22px;display:flex;gap:14px;align-items:flex-start}
 .head svg{width:28px;height:28px;flex:none;margin-top:4px}
 .head .t{font-size:.78rem;opacity:.9;letter-spacing:.4px;direction:ltr;text-align:left;font-family:var(--mono)}
 .head h1{margin:4px 0 0;font-size:1.1rem;line-height:1.6;word-break:break-word;font-weight:600}
 .body{padding:20px 22px}
 .kv{display:grid;grid-template-columns:120px 1fr;gap:6px 12px;font-size:.88rem;margin:0}
 .kv b{color:var(--muted);font-weight:600}
 .kv span{word-break:break-all;direction:ltr;text-align:left;font-family:var(--mono);font-size:.82rem;unicode-bidi:isolate}
 details{border:1px solid var(--line);border-radius:10px;margin-top:14px;background:#fff}
 details[open]{border-color:var(--plum-r)}
 summary{cursor:pointer;padding:10px 14px;font-weight:600;font-size:.9rem;color:#3B092F;list-style:none;
   display:flex;align-items:center;gap:8px;user-select:none}
 summary::-webkit-details-marker{display:none}
 summary::before{content:'';width:8px;height:8px;border-right:2px solid var(--plum);border-bottom:2px solid var(--plum);
   transform:rotate(-45deg);transition:transform .15s;flex:none;margin-inline-start:2px}
 details[open] summary::before{transform:rotate(45deg)}
 summary .n{color:var(--muted);font-weight:400;font-size:.8rem}
 details > *:not(summary){margin:0 14px 14px}
 pre{background:var(--ink);color:#EDE6EA;padding:14px;border-radius:10px;overflow:auto;
   font-family:var(--mono);font-size:.78rem;direction:ltr;text-align:left;line-height:1.65;max-height:380px;margin:0;
   tab-size:4}
 .note{background:var(--plum-t);border:1px solid var(--plum-r);border-radius:10px;padding:12px 14px;
   font-size:.85rem;margin-top:18px}
 .note b{color:var(--plum)}
 code{background:#F1ECEF;padding:1px 7px;border-radius:5px;direction:ltr;display:inline-block;unicode-bidi:isolate;
   font-family:var(--mono);font-size:.8rem}
 ol{margin:8px 0 0;padding-inline-start:20px}
 li{margin-bottom:6px;font-size:.85rem}
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
 .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:9px 20px;
   border-radius:10px;font-family:var(--ff);font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none;
   border:1.5px solid var(--plum);background:#fff;color:var(--plum)}
 .btn:hover,.btn:focus{background:var(--plum-t);color:var(--plum-d)}
 .btn:focus-visible{outline:3px solid var(--plum-r);outline-offset:2px}
 .stamp{direction:ltr;unicode-bidi:isolate;font-family:var(--mono);font-size:.8rem;color:var(--muted)}
 @media (max-width:600px){
   body{padding:16px}
   .head,.body{padding:14px 16px}
   .kv{grid-template-columns:1fr;gap:2px 0}
   .kv b{margin-top:8px}
   .actions{flex-direction:column}
   .btn{width:100%}
 }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.146.146 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.163.163 0 0 1-.054.06.116.116 0 0 1-.066.017H1.146a.115.115 0 0 1-.066-.017.163.163 0 0 1-.054-.06.176.176 0 0 1 .002-.183L7.884 2.073a.147.147 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995z"/></svg>
      <div>
        <div class="t">{{ get_class($e) }}</div>
        <h1>{{ $e->getMessage() ?: 'خطأ من غير رسالة' }}</h1>
      </div>
    </div>

    <div class="body">
      <div class="kv">
        <b>الملف</b><span>{{ $e->getFile() }}</span>
        <b>السطر</b><span>{{ $e->getLine() }}</span>
        <b>الكود</b><span>{{ $e->getCode() }}</span>
        <b>الطلب</b><span>{{ request()->method() }} {{ request()->fullUrl() }}</span>
        <b>PHP</b><span>{{ PHP_VERSION }}</span>
        <b>الوقت</b><span>{{ now()->format('Y-m-d H:i:s') }}</span>
      </div>

      @php
        $prev = $e->getPrevious();
        $allFrames = explode("\n", $e->getTraceAsString());
        $frames = array_slice($allFrames, 0, 20);
      @endphp

      @if($prev)
        <details open>
          <summary>السبب الأصلي <span class="n">({{ get_class($prev) }})</span></summary>
          <pre>{{ get_class($prev) }}
{{ $prev->getMessage() }}
{{ $prev->getFile() }}:{{ $prev->getLine() }}</pre>
        </details>
      @endif

      <details open>
        <summary>مسار الاستدعاء <span class="n">(أول {{ count($frames) }} سطر)</span></summary>
        <pre>{{ implode("\n", $frames) }}</pre>
      </details>

      @if(count($allFrames) > count($frames))
        <details>
          <summary>المسار الكامل <span class="n">({{ count($allFrames) }} سطر)</span></summary>
          <pre>{{ implode("\n", $allFrames) }}</pre>
        </details>
      @endif

      <details>
        <summary>ليه الصفحة دي مختلفة؟</summary>
        <div class="note" style="margin-top:0">
          <b>السبب:</b>
          الاستضافة قافلة دالة <code>highlight_file</code>، وصفحة الخطأ التفصيلية بتاعة
          Symfony بتستدعيها — فبتقع هي كمان وبتخبّي الخطأ الحقيقي. الصفحة دي بديل بسيط
          بيعرضلك الخطأ زي ما هو.
          <ol>
            <li>الخطأ الكامل موجود في <code>storage/logs/laravel.log</code></li>
            <li>شغّل <code>php artisan lv:doctor</code> — بيفحص البيئة والداتابيز والصلاحيات</li>
            <li>على السيرفر الحقيقي خلّي <code>APP_DEBUG=false</code> وبعدها <code>php artisan config:clear</code></li>
          </ol>
        </div>
      </details>

      <div class="actions">
        <a class="btn" href="{{ url('/') }}">ارجع للوحة التحكم</a>
        <button type="button" class="btn" onclick="history.back()">ارجع للصفحة اللي قبلها</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
