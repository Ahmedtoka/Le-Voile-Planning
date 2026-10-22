@extends('layouts.app')
@section('purpose', 'اكتب الأصناف والكميات واضغط «اطلب» — الطلب بينزل للمشتريات على طول من غير اعتماد')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'request'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>اكتب الأصناف والكميات ونسبة الزيادة واضغط <b>«اطلب»</b> — الطلب ينزل للمشتريات
  تلقائيًا ويوصلهم إشعار. التاريخ واسمك بيتسجلوا لوحدهم.</span>
</div>

<form method="post" action="{{ route('purchase-orders.store') }}">
  @csrf
  <div class="card mb-3">
    <div class="card-header">
      <span>طلب شراء جديد</span>
      <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      {{-- اللي السيستم عارفه --}}
      <div class="ro-box mb-3">
        <div class="ro-title">بيتسجل لوحده — للقراءة</div>
        <div class="sum-grid">
          <div><div class="k">طلبه</div><div class="v">{{ auth()->user()?->name }}</div></div>
          <div><div class="k">تاريخ الطلب</div><div class="v num">{{ now()->toDateString() }}</div></div>
          <div><div class="k">بعد «اطلب»</div><div class="v">ينزل للمشتريات فورًا</div></div>
        </div>
      </div>

      {{-- اللي إنت بتكتبه --}}
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label req" for="inspection_exempt">نوع الاستلام</label>
          @php $exempt = (bool) old('inspection_exempt', false); @endphp
          <select name="inspection_exempt" id="inspection_exempt" class="form-select">
            <option value="0" @selected(!$exempt)>يدخل فحص (الافتراضي)</option>
            <option value="1" @selected($exempt)>استلام مباشر — بدون فحص (حاويات / مستورد)</option>
          </select>
          <div class="hint">إنت اللي بتحدد — المخزن هياخد القرار ده زي ما هو عند إذن الإضافة</div>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="planning_note">سبب الطلب / ملاحظات التخطيط</label>
          <input name="planning_note" id="planning_note" class="form-control" value="{{ old('planning_note') }}"
                 placeholder="مثال: تغطية فوركاست الربع الأول — الأساسيات">
        </div>

        <div class="col-12">
          <label class="form-label">الخامة دي لأنهي موديلات؟ <span class="hint">(اختياري — علّم كل الموديلات اللي بتستخدمها)</span></label>
          @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', []), 'allowNew' => true])
          <div class="hint">بيظهر للمشتريات والحسابات وبيتحط على طلب الماركر بعدين</div>
        </div>
      </div>

      <div class="section-head"><h2>الأصناف المطلوبة</h2><span class="hint">سطر لكل (صنف + لون)</span></div>
      <div class="table-responsive">
        <table class="table table-sm line-table mb-0">
          <thead><tr>
            <th style="width:35px">م</th><th class="req" style="width:170px">كود اللون</th><th class="req">اسم الصنف</th>
            <th class="req" style="width:110px">الكمية</th><th style="width:96px">الوحدة</th>
            <th style="width:110px">نسبة الزيادة (%)</th><th>ملاحظات</th><th style="width:44px"></th>
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
      <div class="hint mt-1">نسبة الزيادة = أقصى زيادة مسموح المورد يبعتها فوق الكمية (مثال: 2 طن + 5% ⇒ لحد 2.1 طن).</div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> اطلب</button>
      <span class="hint">هيرجعك على طلبات الشراء، والطلب يظهر عند المشتريات فورًا.</span>
      <div class="fa-end"><a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
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
