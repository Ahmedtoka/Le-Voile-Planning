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

        <div class="col-md-8">
          <label class="form-label">الخامة دي لأنهي موديلات؟ <span class="hint">(اختار الموديلات وخاماتها هتنزل في الأصناف لوحدها)</span></label>
          @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', []), 'allowNew' => true, 'onchange' => 'LVPO.models()'])
          <div class="hint">خامات كل موديل من كارت الموديل (البيانات الأساسية ← الموديلات ← خاماته). بتظهر للمشتريات وبتتحط على طلب الماركر بعدين</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="target_pieces">عايز تنتج كام قطعة؟ <span class="hint">(اختياري)</span></label>
          <div class="input-group">
            <input type="number" min="0" step="1" name="target_pieces" id="target_pieces" class="form-control" value="{{ old('target_pieces') }}" placeholder="مثال: 1300" oninput="LVPO.target()">
            <span class="input-group-text">قطعة</span>
          </div>
          <div class="hint">السيستم يضربها في استهلاك الموديل ويقترح الكمية — وتقدر تزود أو تقلل</div>
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
/* الموديلات ⇐ خاماتها لوحدها (الميتينج 37) + حاسبة الكمية من عدد القطع */
window.LVPO = (function () {
  var body = document.getElementById('lines');
  var nf = function (v, d) { return Number(v).toLocaleString('en-US', {maximumFractionDigits: d === undefined ? 3 : d}); };
  function target() { return Number(document.getElementById('target_pieces')?.value) || 0; }
  function rows() { return [...body.querySelectorAll('tr')]; }
  function rowKey(tr) {
    return (tr.querySelector('select[name$="[fabric_type_id]"]')?.value || '') + '|' + (tr.querySelector('select[name$="[color_id]"]')?.value || '');
  }
  function emptyRow(tr) {
    return !tr.querySelector('select[name$="[fabric_type_id]"]')?.value && !(Number(tr.querySelector('.po-qty')?.value) > 0);
  }
  /* الهنت تحت الكمية: الكمية دي تكفي كام قطعة — من الاستهلاك اللي جاي مع الخامة */
  function hint(inp) {
    if (!inp) return;
    var tr = inp.closest('tr'), h = tr.querySelector('.po-hint'), c = Number(inp.dataset.consumption) || 0, q = Number(inp.value) || 0;
    var models = inp.dataset.models || '';
    var unit = tr.querySelector('.po-unit')?.value || '';
    var parts = [];
    if (models) parts.push('موديلات: ' + models);
    if (c > 0 && q > 0) {
      var pcs = Math.floor(q / c), t = target();
      parts.push('تكفي ' + nf(pcs, 0) + ' قطعة' + (t ? (pcs >= t ? ' ✓' : ' — أقل من المستهدف ' + nf(t, 0)) : '') + ' (استهلاك ' + nf(c, 4) + ' ' + unit + '/قطعة)');
    }
    if (h) h.textContent = parts.join(' · ');
  }
  /* عدد القطع اتغير ⇐ الكميات الفاضية (أو اللي السيستم حطها) تتحسب تاني */
  function target_() {
    var t = target();
    rows().forEach(function (tr) {
      var q = tr.querySelector('.po-qty'), c = Number(q?.dataset.consumption) || 0;
      if (!q || !c) return;
      if (!q.value || q.dataset.auto === '1') { q.value = t ? Math.ceil(t * c * 1000) / 1000 : ''; q.dataset.auto = '1'; }
      hint(q);
    });
  }
  async function models() {
    var ids = [...document.querySelectorAll('input[name="product_model_ids[]"]')].map(function (x) { return x.value; });
    if (!ids.length) return;
    var url = '{{ route('product-models.fabrics-of') }}?' + ids.map(function (i) { return 'ids[]=' + i; }).join('&');
    var r = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!r.ok) return;
    var list = await r.json();
    var have = {}; rows().forEach(function (tr) { have[rowKey(tr)] = tr; });
    list.forEach(function (f) {
      var key = f.fabric_type_id + '|' + (f.color_id || '');
      var tr = have[key];
      if (!tr) {
        // لو فيه سطر فاضي استخدمه، وإلا ضيف سطر جديد
        tr = rows().find(emptyRow);
        if (!tr) { LV.add('lineTpl', 'lines'); tr = rows().slice(-1)[0]; }
        var ft = tr.querySelector('select[name$="[fabric_type_id]"]'), co = tr.querySelector('select[name$="[color_id]"]');
        if (ft) ft.value = f.fabric_type_id;
        if (co && f.color_id) co.value = f.color_id;
        var un = tr.querySelector('.po-unit'); if (un && f.unit) un.value = f.unit;
        have[key] = tr;
      }
      var q = tr.querySelector('.po-qty');
      if (q) {
        q.dataset.consumption = f.consumption || '';
        q.dataset.models = (f.models || []).filter(Boolean).join('، ');
        if (!q.value && f.consumption && target()) { q.value = Math.ceil(target() * f.consumption * 1000) / 1000; q.dataset.auto = '1'; }
        hint(q);
      }
    });
    body.dispatchEvent(new Event('change'));
  }
  body.addEventListener('input', function (e) { if (e.target.classList.contains('po-qty')) e.target.dataset.auto = '0'; });
  return {models: models, target: target_, hint: hint};
})();
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
