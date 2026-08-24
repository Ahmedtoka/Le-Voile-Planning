@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'request'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  اكتب الأصناف والكميات ونسبة الزيادة واضغط <b>«اطلب»</b> — الطلب ينزل للمشتريات
  تلقائيًا ويوصلهم إشعار. التاريخ واسمك بيتسجلوا لوحدهم.
</div>

<form method="post" action="{{ route('purchase-orders.store') }}">
  @csrf
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>طلب شراء جديد</span>
      <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">سبب الطلب / ملاحظات التخطيط</label>
          <input name="planning_note" class="form-control form-control-sm" value="{{ old('planning_note') }}"
                 placeholder="مثال: تغطية فوركاست الربع الأول — الأساسيات">
        </div>
        <div class="col-md-4">
          <label class="form-label">الطلب لموديل معين؟ (اختياري)</label>
          <select name="product_model_id" class="form-select form-select-sm">
            <option value="">— عام / مش لموديل —</option>
            @foreach($models as $k=>$v)<option value="{{ $k }}" @selected(old('product_model_id')==$k)>{{ $v }}</option>@endforeach
          </select>
          <div class="hint">لو الخامة دي لموديل بعينه — بيظهر في المشتريات والحسابات</div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm line-table mb-0">
          <thead><tr>
            <th style="width:35px">م</th><th style="width:170px">كود اللون</th><th>اسم الصنف</th>
            <th style="width:100px">الكمية</th><th style="width:80px">الوحدة</th>
            <th style="width:100px">نسبة الزيادة %</th><th>ملاحظات</th><th style="width:40px"></th>
          </tr></thead>
          <tbody id="lines">
            @foreach(old('lines', []) as $i => $l) @include('po.line', ['i'=>$i,'l'=>$l]) @endforeach
            @if(!count(old('lines', []))) @include('po.line', ['i'=>0,'l'=>[]]) @endif
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-plum mt-2" onclick="LV.add('lineTpl','lines')">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> صنف
      </button>
    </div>
    <div class="card-footer bg-white d-flex gap-2 align-items-center">
      <button class="btn btn-plum btn-sm px-4"><i class="bi bi-send" aria-hidden="true"></i> اطلب</button>
      <span class="hint">هيرجعك على طلبات الشراء، والطلب يظهر عند المشتريات فورًا.</span>
    </div>
  </div>
</form>

<template id="lineTpl">@include('po.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js', ['startIndex' => max(count(old('lines', [])), 1)])
<script>
  // علامة التكرار: نفس الصنف بنفس اللون في سطرين — بيتعلّموا بالأصفر وبيسألك قبل الإرسال
  (function () {
    var body = document.getElementById('lines');
    if (!body) return;

    function markDupes() {
      var seen = {}, dupes = [];
      body.querySelectorAll('tr').forEach(function (tr) {
        tr.classList.remove('table-warning');
        var f = tr.querySelector('select[name$="[fabric_type_id]"]');
        var c = tr.querySelector('select[name$="[color_id]"]');
        if (!f || !c || !f.value || !c.value) return;
        var key = f.value + '|' + c.value;
        if (seen[key]) { tr.classList.add('table-warning'); seen[key].classList.add('table-warning'); dupes.push(tr); }
        else seen[key] = tr;
      });
      return dupes.length;
    }

    body.addEventListener('change', markDupes);
    body.closest('form').addEventListener('submit', function (e) {
      if (markDupes() > 0 &&
          !confirm('فيه صنف متكرر بنفس اللون (السطور المعلّمة بالأصفر) — عادةً بيتجمعوا في سطر واحد بكمية أكبر. تكمّل زي ما هو؟')) {
        e.preventDefault();
      }
    });
  })();
</script>
@endsection
