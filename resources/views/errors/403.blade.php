<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('errors._head', ['title' => 'مالكش صلاحية على الصفحة دي'])
</head>
<body>
  <main class="card" role="main">
    @include('errors._mark', ['code' => '403'])
    <h1>مالكش صلاحية على الصفحة دي</h1>
    <p class="lead">الصفحة دي محتاجة صلاحية مش موجودة في دورك. كلّم مدير النظام يضيفهالك.</p>
    @php $path = trim((string) request()->path(), '/'); @endphp
    @if($path !== '')
      <p>الصفحة اللي حاولت تفتحها: <span class="path">/{{ $path }}</span></p>
    @endif
    @include('errors._actions')
  </main>
</body>
</html>
