<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('errors._head', ['title' => 'السيستم في صيانة'])
<meta http-equiv="refresh" content="60">
</head>
<body>
  <main class="card" role="main">
    @include('errors._mark', ['code' => '503'])
    <h1>السيستم في صيانة قصيرة</h1>
    <p class="lead">السيستم في صيانة قصيرة، جرّب كمان دقايق.</p>
    <p>مفيش حاجة ضاعت — كل اللي اتحفظ قبل كده موجود. الصفحة هتحاول تفتح لوحدها كل دقيقة.</p>
    @include('errors._actions', ['primary' => '<button type="button" class="btn btn-plum" onclick="location.reload()">'
      .'<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>'
      .'جرّب تاني دلوقتي</button>'])
  </main>
</body>
</html>
