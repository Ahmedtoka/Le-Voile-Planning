<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول — Le Voile</title>
<link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Reem+Kufi:wght@500;600&display=swap" rel="stylesheet">
<style>
 /* هوية Le Voile — نفس توكنز التطبيق */
 :root{--lv-brand:#9D197E;--lv-brand-dark:#7A1362;--lv-brand-deep:#560D45;
       --lv-brand-ink:#3B092F;--lv-offwhite:#F8F4F1;--lv-soft:#ECD1E5;--lv-tint:#F7E8F3;
       --lv-ink:#2B2028;--lv-ink-2:#4A3F48;--lv-muted:#6B606A;--lv-field:#DCD2D9;
       --ring:0 0 0 3px rgba(157,25,126,.18)}
 *{font-family:'IBM Plex Sans Arabic',system-ui,sans-serif}
 body{background:var(--lv-offwhite);color:var(--lv-ink);min-height:100vh;margin:0;display:flex;
   -webkit-font-smoothing:antialiased}
 .wrap{display:flex;width:100%;min-height:100vh}
 .side{flex:0 0 44%;background:linear-gradient(180deg,var(--lv-brand-deep) 0%,var(--lv-brand-ink) 100%);
   color:#F0E3EC;padding:48px 56px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
 .side::after{content:"";position:absolute;inset-inline-end:-120px;bottom:-120px;width:380px;height:380px;border-radius:50%;
   border:1px solid rgba(255,255,255,.08);box-shadow:0 0 0 60px rgba(255,255,255,.025),0 0 0 120px rgba(255,255,255,.02)}
 .side img{width:150px;height:auto}
 .side h1{font-family:'Reem Kufi',sans-serif;font-weight:600;color:#fff;font-size:2.1rem;line-height:1.35;margin:0 0 12px}
 .side p{color:#DCC0D2;font-size:.95rem;line-height:1.8;max-width:34ch;margin:0}
 .side .foot{font-size:.75rem;color:#B892AC}
 .form-side{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 20px}
 .box{width:100%;max-width:380px}
 .box .mobile-logo{display:none;width:150px;margin:0 auto 24px}
 .box h2{font-family:'Reem Kufi',sans-serif;font-weight:600;color:var(--lv-brand-ink);font-size:1.6rem;margin:0 0 4px}
 .box .sub{color:var(--lv-muted);font-size:.875rem;margin-bottom:28px}
 .form-label{font-size:.8125rem;font-weight:600;color:var(--lv-ink-2);margin-bottom:6px}
 .form-control{min-height:44px;border-radius:8px;border-color:var(--lv-field);font-size:.95rem}
 .form-control:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
 .form-check-input:checked{background-color:var(--lv-brand);border-color:var(--lv-brand)}
 .form-check-input:focus{border-color:var(--lv-brand);box-shadow:var(--ring)}
 .btn-plum{background:var(--lv-brand);color:#fff;border:0;min-height:46px;border-radius:8px;font-weight:600;font-size:.95rem}
 .btn-plum:hover,.btn-plum:focus{background:var(--lv-brand-dark);color:#fff}
 .btn-plum:focus-visible{box-shadow:var(--ring)}
 .alert-danger{background:#FCEFEE;border-color:#F3CDC9;color:#B5342B;border-radius:8px}
 .copy{text-align:center;margin-top:28px;font-size:.75rem;color:var(--lv-muted)}

 /* ── دخول تجريبي — زرار لكل دور (local / LV_DEMO_LOGIN بس) ── */
 .demo{width:100%;max-width:520px;margin:36px auto 0;padding-top:24px;border-top:1px dashed var(--lv-field)}
 .demo .demo-title{display:flex;align-items:center;gap:10px;font-size:.8125rem;font-weight:600;color:var(--lv-brand-ink);margin-bottom:4px}
 .demo .demo-title .pill{font-size:.6875rem;font-weight:600;background:var(--lv-tint);color:var(--lv-brand);border-radius:999px;padding:2px 10px}
 .demo .demo-sub{font-size:.75rem;color:var(--lv-muted);margin-bottom:14px}
 .demo .dept{margin-bottom:12px}
 .demo .dept-name{font-size:.7rem;font-weight:600;letter-spacing:.02em;color:var(--lv-muted);margin-bottom:6px}
 .demo .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
 .demo form{margin:0;min-width:0}
 .demo .who{width:100%;text-align:start;background:#fff;border:1px solid var(--lv-field);border-radius:8px;padding:7px 10px;
   display:flex;align-items:center;gap:9px;cursor:pointer;transition:border-color .12s,box-shadow .12s,background .12s}
 .demo .who:hover,.demo .who:focus-visible{border-color:var(--lv-brand);box-shadow:var(--ring);background:#FDF9FC;outline:0}
 .demo .who .av{flex:0 0 30px;width:30px;height:30px;border-radius:50%;background:var(--lv-soft);color:var(--lv-brand-deep);
   font-family:'Reem Kufi',sans-serif;font-weight:600;font-size:.9rem;display:flex;align-items:center;justify-content:center}
 .demo .who.factory .av{background:#E3EEE6;color:#1F5B35}
 .demo .who .t{min-width:0}
 .demo .who .n{font-size:.8125rem;font-weight:600;color:var(--lv-ink);line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .demo .who .j{font-size:.6875rem;color:var(--lv-muted);line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .demo .who .j b{font-weight:600;color:#1F5B35}
 .demo .demo-foot{font-size:.6875rem;color:var(--lv-muted);margin-top:8px}
 @media (max-width:420px){ .demo .grid{grid-template-columns:1fr} }
 @media (max-width:860px){
   .side{display:none}
   .box .mobile-logo{display:block}
   .box h2,.box .sub{text-align:center}
   .form-control{font-size:16px}
 }
</style>
</head>
<body>
<div class="wrap">
  <aside class="side" aria-hidden="true">
    <img src="{{ asset('assets/logo-white.png') }}" alt="">
    <div>
      <h1>تخطيط الإنتاج،<br>من طلب الخامة لحد المخزن.</h1>
      <p>كل إدارة تفتح مكتبها فتلاقي شغلها مستنيها — الشراء، القماش، التشغيل، والتغطية في مكان واحد.</p>
    </div>
    <div class="foot">Le Voile · Fashion Forward</div>
  </aside>

  <main class="form-side">
   <div style="width:100%;max-width:520px">
    <form class="box" style="margin:0 auto" method="post" action="{{ route('login.post') }}">
      @csrf
      <img class="mobile-logo" src="{{ asset('assets/logo.png') }}" alt="Le Voile">
      <h2>أهلًا بيك</h2>
      <div class="sub">سجّل دخولك لنظام تخطيط الإنتاج</div>

      @if($errors->any())
        <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first() }}</div>
      @endif

      <div class="mb-3">
        <label class="form-label" for="username">اسم الدخول</label>
        <input id="username" name="username" value="{{ old('username') }}" class="form-control"
               autocomplete="username" autofocus required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">كلمة المرور</label>
        <input id="password" type="password" name="password" class="form-control"
               autocomplete="current-password" required>
      </div>
      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="remember" id="r">
        <label class="form-check-label small" for="r">افتكرني</label>
      </div>
      <button class="btn btn-plum w-100">دخول</button>
      @if(($demoUsers ?? collect())->isEmpty())
        <div class="copy">Le Voile · نظام تخطيط الإنتاج</div>
      @endif
    </form>

    {{-- دخول تجريبي: بيظهر بس لما config('app.env') === 'local' أو config('lvplanning.demo_login') --}}
    @if(($demoUsers ?? collect())->isNotEmpty())
      <section class="demo" aria-labelledby="demo-h">
        <div class="demo-title" id="demo-h">دخول تجريبي — اختار الدور <span class="pill">بيئة تجريبية</span></div>
        <div class="demo-sub">اضغط على أي اسم وهتدخل بدوره على طول من غير كلمة مرور. كل الحسابات كلمة مرورها <code dir="ltr">123456</code>.</div>

        @foreach($demoUsers as $dept => $users)
          <div class="dept">
            <div class="dept-name">{{ $dept }}</div>
            <div class="grid">
              @foreach($users as $u)
                @php $factory = $u->employerFactory; @endphp
                <form method="post" action="{{ route('login.as', $u->username) }}">
                  @csrf
                  <button type="submit" class="who {{ $factory ? 'factory' : '' }}"
                          title="{{ $u->name }} — {{ $u->job_title }}{{ $factory ? ' — مصنع ' . $factory->name : '' }} (اسم الدخول: {{ $u->username }})">
                    <span class="av" aria-hidden="true">{{ mb_substr(trim($u->name), 0, 1) }}</span>
                    <span class="t">
                      <span class="n d-block">{{ $u->name }}</span>
                      <span class="j d-block">{{ $u->job_title }}@if($factory) · <b>مصنع {{ $factory->name }}</b>@endif</span>
                    </span>
                  </button>
                </form>
              @endforeach
            </div>
          </div>
        @endforeach

        <div class="demo-foot">القسم ده مش بيظهر على السيرفر الحقيقي (بيتفعّل بـ <code dir="ltr">LV_DEMO_LOGIN=true</code> أو <code dir="ltr">APP_ENV=local</code>).</div>
        <div class="copy">Le Voile · نظام تخطيط الإنتاج</div>
      </section>
    @endif
   </div>
  </main>
</div>
</body>
</html>
