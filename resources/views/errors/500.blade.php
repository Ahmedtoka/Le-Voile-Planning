<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('errors._head', ['title' => 'فيه مشكلة في السيرفر'])
</head>
<body>
  <main class="card" role="main">
    @include('errors._mark', ['code' => '500'])
    <h1>فيه مشكلة في السيرفر</h1>
    <p class="lead">حصلت مشكلة عندنا مش عندك، وسجّلناها. بياناتك اللي اتحفظت قبل كده موجودة — بس لو كنت في نص حفظ، راجع المستند لما ترجع.</p>
    <p>لو اتكرر، ابعت للمسؤول الوقت ده: <span class="stamp">{{ now()->format('Y-m-d H:i') }}</span></p>
    @include('errors._actions')
    <p class="muted">للمسؤول: شغّل <code>php artisan lv:doctor</code> على السيرفر وراجع <code>storage/logs/laravel.log</code>.</p>
  </main>
</body>
</html>
