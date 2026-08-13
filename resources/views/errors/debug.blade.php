{{--
  صفحة خطأ تفصيلية بديلة.
  بتشتغل بس لما APP_DEBUG=true و highlight_file مقفولة على السيرفر.
  متعمّدة تكون بسيطة جدًا: مفيش layout ولا داتابيز ولا أصول خارجية —
  عشان تشتغل حتى لو السيستم نفسه واقع.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>خطأ في السيستم — Le Voile</title>
<style>
 *{box-sizing:border-box;font-family:'Cairo','Segoe UI',Tahoma,sans-serif}
 body{background:#F8F4F1;margin:0;padding:24px;color:#2B2028;line-height:1.7}
 .wrap{max-width:1000px;margin:0 auto}
 .head{background:#B5342B;color:#fff;border-radius:12px 12px 0 0;padding:18px 22px}
 .head .t{font-size:.78rem;opacity:.9;letter-spacing:.5px}
 .head h1{margin:4px 0 0;font-size:1.15rem;line-height:1.6;word-break:break-word}
 .box{background:#fff;border:1px solid #E3D9E0;border-top:0;border-radius:0 0 12px 12px;padding:20px 22px}
 .kv{display:grid;grid-template-columns:120px 1fr;gap:6px 12px;font-size:.85rem;margin-bottom:18px}
 .kv b{color:#6B606A;font-weight:600}
 .kv span{word-break:break-all;direction:ltr;text-align:left;font-family:ui-monospace,Consolas,monospace;font-size:.8rem}
 h2{font-size:.9rem;margin:22px 0 8px;color:#3B092F}
 pre{background:#2B2028;color:#EDE6EA;padding:14px;border-radius:8px;overflow:auto;
   font-size:.76rem;direction:ltr;text-align:left;line-height:1.65;max-height:340px;margin:0}
 .note{background:#F7E8F3;border:1px solid #ECD1E5;border-radius:8px;padding:12px 14px;
   font-size:.82rem;margin-top:20px}
 .note b{color:#9D197E}
 code{background:#F1ECEF;padding:2px 6px;border-radius:4px;direction:ltr;display:inline-block;
   font-family:ui-monospace,Consolas,monospace;font-size:.8rem}
 ol{margin:8px 0 0;padding-inline-start:20px}
 li{margin-bottom:6px;font-size:.83rem}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div class="t">{{ get_class($e) }}</div>
    <h1>{{ $e->getMessage() ?: 'خطأ من غير رسالة' }}</h1>
  </div>

  <div class="box">
    <div class="kv">
      <b>الملف</b><span>{{ $e->getFile() }}</span>
      <b>السطر</b><span>{{ $e->getLine() }}</span>
      <b>الكود</b><span>{{ $e->getCode() }}</span>
      <b>الطلب</b><span>{{ request()->method() }} {{ request()->fullUrl() }}</span>
      <b>PHP</b><span>{{ PHP_VERSION }}</span>
    </div>

    @php
      $prev = $e->getPrevious();
      $frames = array_slice(explode("\n", $e->getTraceAsString()), 0, 20);
    @endphp

    @if($prev)
      <h2>السبب الأصلي</h2>
      <pre>{{ get_class($prev) }}
{{ $prev->getMessage() }}
{{ $prev->getFile() }}:{{ $prev->getLine() }}</pre>
    @endif

    <h2>مسار الاستدعاء (أول 20 سطر)</h2>
    <pre>{{ implode("\n", $frames) }}</pre>

    <div class="note">
      <b>ليه الصفحة دي مختلفة؟</b>
      الاستضافة قافلة دالة <code>highlight_file</code>، وصفحة الخطأ التفصيلية بتاعة
      Symfony بتستدعيها — فبتقع هي كمان وبتخبّي الخطأ الحقيقي. الصفحة دي بديل بسيط
      بيعرضلك الخطأ زي ما هو.

      <ol>
        <li>الخطأ الكامل موجود في <code>storage/logs/laravel.log</code></li>
        <li>شغّل <code>php artisan lv:doctor</code> — بيفحص البيئة والداتابيز والصلاحيات</li>
        <li>على السيرفر الحقيقي خلّي <code>APP_DEBUG=false</code> وبعدها <code>php artisan config:clear</code></li>
      </ol>
    </div>
  </div>
</div>
</body>
</html>
