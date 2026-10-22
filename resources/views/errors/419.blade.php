<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('errors._head', ['title' => 'انتهت الجلسة'])
</head>
<body>
  <main class="card" role="main">
    @include('errors._mark', ['code' => '419'])
    <h1>انتهت الجلسة</h1>
    <p class="lead">الصفحة قعدت مفتوحة وقت طويل، أو دخلت على السيستم من تبويب تاني.</p>
    <p>حدّث الصفحة وحاول تاني — ولو كنت بتكتب بيانات، هتحتاج تكتبها من جديد.</p>
    @include('errors._actions', ['primary' => '<button type="button" class="btn btn-plum" onclick="location.reload()">'
      .'<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>'
      .'حدّث الصفحة وحاول تاني</button>'])
    @if(Route::has('login'))
      <div class="foot">لسه مش شغالة؟ <a href="{{ route('login') }}">ادخل على السيستم من جديد</a></div>
    @endif
  </main>
</body>
</html>
