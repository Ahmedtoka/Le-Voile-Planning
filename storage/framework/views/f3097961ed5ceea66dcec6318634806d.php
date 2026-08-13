<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول — Le Voile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
 *{font-family:'Cairo',sans-serif}
 body{background:linear-gradient(140deg,#4e1e44,#6d2a5f 60%,#8a4478);height:100vh;
   display:flex;align-items:center;justify-content:center}
 .box{background:#fff;border-radius:14px;padding:34px 30px;width:370px;box-shadow:0 12px 40px rgba(0,0,0,.28)}
 .box h1{font-size:1.8rem;color:#6d2a5f;text-align:center;margin-bottom:2px}
 .box .sub{text-align:center;color:#9a8d96;font-size:.78rem;margin-bottom:22px;letter-spacing:1px}
 .btn-plum{background:#6d2a5f;color:#fff}
 .btn-plum:hover{background:#4e1e44;color:#fff}
</style>
</head>
<body>
<form class="box" method="post" action="<?php echo e(route('login.post')); ?>">
  <?php echo csrf_field(); ?>
  <h1>Le Voile</h1>
  <div class="sub">نظام تخطيط الإنتاج</div>

  <?php if($errors->any()): ?>
    <div class="alert alert-danger py-2 small"><?php echo e($errors->first()); ?></div>
  <?php endif; ?>

  <div class="mb-3">
    <label class="form-label small fw-bold">اسم الدخول</label>
    <input name="username" value="<?php echo e(old('username')); ?>" class="form-control" autofocus required>
  </div>
  <div class="mb-3">
    <label class="form-label small fw-bold">كلمة المرور</label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="remember" id="r">
    <label class="form-check-label small" for="r">افتكرني</label>
  </div>
  <button class="btn btn-plum w-100">دخول</button>
</form>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/auth/login.blade.php ENDPATH**/ ?>