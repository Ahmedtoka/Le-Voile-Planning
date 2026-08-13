<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول — Le Voile</title>
<link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
 /* هوية Le Voile — #9D197E + أبيض + أوف وايت */
 :root{--lv-brand:#9D197E;--lv-brand-dark:#7A1362;--lv-brand-deep:#560D45;
       --lv-brand-ink:#3B092F;--lv-offwhite:#F8F4F1;--lv-soft:#ECD1E5}
 *{font-family:'Cairo',sans-serif}
 body{background:linear-gradient(145deg,var(--lv-brand-ink) 0%,var(--lv-brand-deep) 45%,var(--lv-brand) 100%);
   min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
 .box{background:#fff;border-radius:16px;padding:38px 32px 30px;width:380px;
   box-shadow:0 18px 50px rgba(59,9,47,.35)}
 .box img{width:190px;display:block;margin:0 auto 6px}
 .box .sub{text-align:center;color:#9C8E97;font-size:.78rem;margin-bottom:24px;letter-spacing:.5px}
 .btn-plum{background:var(--lv-brand);color:#fff;border:0;padding:.55rem}
 .btn-plum:hover{background:var(--lv-brand-dark);color:#fff}
 .form-label{font-size:.82rem;font-weight:600;color:#5A4E56}
 .form-control:focus{border-color:var(--lv-soft);box-shadow:0 0 0 .18rem rgba(157,25,126,.14)}
 .foot{text-align:center;margin-top:18px;font-size:.72rem;color:#B79FAF}
</style>
</head>
<body>
<div>
  <form class="box" method="post" action="{{ route('login.post') }}">
    @csrf
    <img src="{{ asset('assets/logo.png') }}" alt="Le Voile">
    <div class="sub">نظام تخطيط الإنتاج</div>

    @if($errors->any())
      <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    <div class="mb-3">
      <label class="form-label">اسم الدخول</label>
      <input name="username" value="{{ old('username') }}" class="form-control" autofocus required>
    </div>
    <div class="mb-3">
      <label class="form-label">كلمة المرور</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="remember" id="r">
      <label class="form-check-label small" for="r">افتكرني</label>
    </div>
    <button class="btn btn-plum w-100">دخول</button>
  </form>
  <div class="foot">Le Voile · Fashion Forward</div>
</div>
</body>
</html>
