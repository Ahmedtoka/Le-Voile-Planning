<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('errors._head', ['title' => 'الصفحة مش موجودة'])
</head>
<body>
  <main class="card" role="main">
    @include('errors._mark', ['code' => '404'])
    <h1>الصفحة مش موجودة</h1>
    <p class="lead">ملقيناش الصفحة دي. يمكن الرابط اتغيّر، أو المستند اللي بتدوّر عليه اتحذف.</p>
    <p>ارجع للصفحة اللي كنت فيها، أو افتح لوحة التحكم ودوّر عليه من القايمة.</p>
    @include('errors._actions')
  </main>
</body>
</html>
