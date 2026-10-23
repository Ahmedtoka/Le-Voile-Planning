@extends('layouts.app')
@section('purpose', 'ورقة المصنع: الرسالة + الماركر + الحسبة — الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج')
@section('content')
@php
  $fabs  = old('fabrics', $fabrics ?: []);
  $prods = old('products', $mode === 'edit' ? $row->lines->toArray() : ($products ?? []));
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    المنتج ممكن يتعمل من <b>أكتر من خامة</b> (طرحة تل + بونيه رباط مياي). كل خامة ليها
    رسالتها وطول فرشتها وعرضها وعدد قطعها في الفرشة، وبتتحسب بطريقة مختلفة:
    <b>بالوزن</b> (محتاجة عرض وبنشر) أو <b>بالطول</b> (الطول وحده كفاية).
    <br>
    <b>الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج</b> — والفرق بينها وبين الباقي
    هو أهم رقم هنا، لأنه على الورق بيبقى مخفي تمامًا. اضغط ⓘ جنب أي رقم تشوف حسبته.
  </span>
</div>

<form method="post" action="{{ $mode==='create' ? route('work-orders.store') : route('work-orders.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ① ورقة المصنع --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>{{ $mode==='create' ? 'أمر شغل جديد' : 'أمر الشغل ' . $row->wo_no }}</span>
      <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-5"><label class="form-label req">اسم المنتج</label>
          <input name="product_title" class="form-control"
                 value="{{ old('product_title',$row->product_title) }}" required
                 placeholder="مثال: طرحة تل + بونيه رباط مياي (بيج 241)"></div>
        <div class="col-md-2"><label class="form-label">كود المنتج</label>
          <input name="product_code" class="form-control" value="{{ old('product_code',$row->product_code) }}" placeholder="مثال: P-1014"></div>
        <div class="col-md-2"><label class="form-label">كود Q.B</label>
          <input name="qb_code" class="form-control" value="{{ old('qb_code',$row->qb_code) }}" placeholder="مثال: O1014"></div>
        <div class="col-md-3"><label class="form-label req">تشغيل مصنع</label>
          <select name="factory_id" class="form-select" required><option value="">— اختر المصنع —</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="wo_date" class="form-control"
                 value="{{ old('wo_date',$row->wo_date?->format('Y-m-d') ?? $row->wo_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">تاريخ الاستلام</label>
          <input type="date" name="receive_date" class="form-control"
                 value="{{ old('receive_date',$row->receive_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label">تاريخ التسليم</label>
          <input type="date" name="due_date" class="form-control"
                 value="{{ old('due_date',$row->due_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label">نسخ الماركر</label>
          <div class="input-group">
            <input type="number" min="0" name="marker_copies" class="form-control"
                   value="{{ old('marker_copies',$row->marker_copies ?? 2) }}" placeholder="مثال: 2">
            <span class="input-group-text">نسخة</span>
          </div></div>
        <div class="col-md-2"><label class="form-label">باركود التكويد</label>
          <input name="barcode" class="form-control" value="{{ old('barcode',$row->barcode) }}" placeholder="اختياري"></div>
        <div class="col-md-2"><label class="form-label">إدارة التخطيط</label>
          <select name="planner_id" class="form-select"><option value="">—</option>
            @foreach($planners as $k=>$v)<option value="{{ $k }}" @selected(old('planner_id',$row->planner_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-md-6"><label class="form-label">ملاحظات خاصة بقسم القص</label>
          <textarea name="cutting_notes" rows="2" class="form-control" placeholder="مثال: الفرشة على وش القماش — خد بالك من اتجاه النقشة">{{ old('cutting_notes',$row->cutting_notes) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">ملاحظات عامة</label>
          <textarea name="notes" rows="2" class="form-control" placeholder="اختياري">{{ old('notes',$row->notes) }}</textarea></div>
      </div>
    </div>
  </div>

  {{-- ② الخامات --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <span>الخامات وحسبة كل واحدة</span>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <input class="form-control form-control-sm w-wide" placeholder="فلتر الرسايل: كود / خامة / لون (مثال 0805)"
               aria-label="فلتر الرسايل" oninput="LVF.filter(this.value)" title="بيفلتر قايمة الرسايل في كل السطور — مرتبة بالأقدم أولًا">
        <button type="button" class="btn btn-sm btn-outline-plum" onclick="LV.add('fabTpl','fabrics'); LVF.calc()">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> خامة
        </button>
      </div>
    </div>
    <div class="table-responsive wo-fab-wrap">
      <table class="table table-sm line-table wo-fab mb-0">
        <colgroup>
          <col style="width:36px"><col style="width:260px"><col style="width:104px"><col style="width:84px">
          <col style="width:112px"><col style="width:96px"><col style="width:96px"><col style="width:88px">
          <col style="width:96px"><col style="width:84px"><col style="width:170px"><col style="width:84px">
          <col style="width:96px"><col style="width:200px"><col style="width:44px">
        </colgroup>
        <thead><tr>
          <th>م</th>
          <th class="req">رقم الرسالة</th>
          <th>الحساب</th>
          <th>الوحدة</th>
          <th class="req">الكمية</th>
          <th class="req">طول الفرشة (م)</th>
          <th>بالأمان (م)</th>
          <th>العرض (م)
            <select class="form-select form-select-sm mt-1" id="widthBasis" aria-label="احسب الاستهلاك على أنهي عرض" onchange="LVF.basis(this.value)" style="min-width:150px">
              <option value="usable">عرض التشغيل (− تأمين)</option>
              <option value="full">العرض الكامل المقاس</option>
            </select></th>
          <th>البنشر (كجم/م²)</th>
          <th class="req">قطع الفرشة</th>
          <th>حسبة السيستم</th>
          <th>الرقات</th>
          <th>القص المعتمد</th>
          <th>الماركر</th>
          <th><span class="visually-hidden">حذف</span></th>
        </tr></thead>
        <tbody id="fabrics">
          @foreach($fabs as $i => $l) @include('workorders.fabric_row', ['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($fabs)) @include('workorders.fabric_row', ['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      <div id="fabSummary" class="hint">دخّل الخامات وشوف الحسبة.</div>
      <div class="hint mt-1">
        <b>الرقات والقص المعتمد</b> سيبهم فاضيين والسيستم هيملاهم بحسبته.
        لو كتبت رقم مختلف، ده اللي هيروح للمصنع والفرق هيفضل ظاهر.
        <b>طول الأمان</b> = طول الفرشة + {{ rtrim(rtrim(number_format($safetyCm,2),'0'),'.') }} سم تلقائيًا لو سيبته فاضي.
        الرسايل مرتبة <b>بالأقدم أولًا</b> — القديم يخرج الأول.
      </div>
    </div>
  </div>


  {{-- ③ المنتجات والمقاسات --}}
  <div class="card mb-3">
    <div class="card-header">المنتجات والمقاسات <span class="hint">— ابحث بالكود أو الاسم واختار الموديلات اللي هتتشغل، كل موديل بيبقى سطر</span></div>
    <div class="card-body pb-2">
      @php $pickedModels = collect($prods)->pluck('product_model_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all(); @endphp
      @include('partials.model_picker', ['name' => 'picked_models', 'models' => $models, 'picked' => $pickedModels, 'onchange' => 'LVW.sync()', 'allowNew' => true])
      <div class="hint mt-2">
        لكل موديل اكتب <b>قطعه في الفرشة</b> (6 تلبيسة + 6 كويتي مثلًا = نفس إجمالي قطع الفرشة اللي فوق) —
        والسيستم هيوزّع الاستهلاك الفعلي على كل موديل بنسبة متوسطه التاريخي، وهيحسب كمية كل موديل من الرِقّات تلقائيًا.
        سيب الكمية فاضية والسيستم هيحسبها.
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th>الموديل</th>
          <th style="width:150px">المقاس</th>
          <th style="width:120px">قطعه في الفرشة</th>
          <th style="width:130px">الكمية (قطعة)</th><th style="width:44px"><span class="visually-hidden">حذف</span></th>
        </tr></thead>
        <tbody id="products">
          @foreach($prods as $i => $l) @include('workorders.product_row', ['i'=>$i,'l'=>$l]) @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- ④ الكمية المعتمدة --}}
  <div class="card mb-3">
    <div class="card-header">الكمية المعتمدة للإنتاج</div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <div class="ro-box h-100">
            <div class="ro-title">من حسبة الخامات — للقراءة</div>
            <div class="hint">الحاكمة (أقل خامة)</div>
            <div class="d-flex align-items-center gap-1">
              <b class="num" id="govQty" style="font-size:var(--fs-lg)">—</b>
              <span id="govQtyFx"></span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">الكمية المعتمدة</label>
          <div class="input-group">
            <input type="number" min="0" name="approved_qty" class="form-control"
                   value="{{ old('approved_qty',$row->approved_qty) }}" placeholder="فاضية = الحاكمة">
            <span class="input-group-text">قطعة</span>
          </div>
          <div class="hint">فاضية معناها السيستم هياخد أقل خامة</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">سبب اختلافها عن الحاكمة</label>
          <input name="approved_qty_reason" class="form-control"
                 value="{{ old('approved_qty_reason',$row->approved_qty_reason) }}"
                 placeholder="مثال: هيوصل تل إضافي الأسبوع الجاي يغطي الفرق">
        </div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ أمر الشغل</button>
    <span class="hint"><i class="bi bi-info-circle" aria-hidden="true"></i> بيتحفظ مسودة — تقدر تراجعه وتعتمده من شاشة أمر الشغل.</span>
    <div class="fa-end">
      <a href="{{ $mode==='edit' ? route('work-orders.show',$row) : route('work-orders.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    </div>
  </div>
</form>

<style>
/* جدول خامات أمر الشغل: عرض ثابت لكل عمود عشان السيلكت ما يتقصّش،
   وعمود الرسالة ثابت مكانه وإنت بتعمل سكرول للحسبة */
.wo-fab{table-layout:fixed;width:1650px}
.wo-fab-wrap{contain:paint} /* من غيرها الجدول العريض بيوسّع الصفحة كلها في RTL */
.wo-fab td,.wo-fab th{overflow:hidden}
.wo-fab td.f-out{white-space:normal;font-size:var(--fs-xs);line-height:1.5;overflow:visible}
.wo-fab td.f-out .fx{margin-inline-start:2px}
.wo-fab tr > :nth-child(1),.wo-fab tr > :nth-child(2){position:sticky;z-index:2;background:var(--lv-white)}
.wo-fab thead tr > :nth-child(1),.wo-fab thead tr > :nth-child(2){background:var(--lv-surface-2);z-index:3}
.wo-fab tr > :nth-child(1){inset-inline-start:0}
.wo-fab tr > :nth-child(2){inset-inline-start:36px;box-shadow:inset 1px 0 0 var(--lv-line)}
.wo-fab .f-target{font-size:var(--fs-xs)!important;min-height:28px!important}
@media (max-width:600px){ .wo-fab{width:1400px} }
</style>

<template id="fabTpl">@include('workorders.fabric_row', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
<template id="prodTpl">@include('workorders.product_row', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js', ['startIndex' => max(count($fabs), count($prods), 1)])

@push('scripts')
<script>
/* الموديلات: اختيار من البحث ⇒ سطر لكل موديل (وشيل الـ chip بيشيل السطر) */
window.LVW = (function () {
  function picked() {
    return [...document.querySelectorAll('#mp_pickedmodels .mp-chip')].map(c => ({id: c.dataset.id, label: c.childNodes[0].textContent.trim()}));
  }
  function sync() {
    var body = document.getElementById('products');
    var have = [...body.querySelectorAll('tr.prod-row')];
    var want = picked();
    // شيل السطور اللي اتشالت من الاختيار
    have.forEach(tr => { if (!want.find(w => w.id === tr.dataset.model)) tr.remove(); });
    // ضيف السطور الجديدة
    want.forEach(w => {
      if (body.querySelector('tr.prod-row[data-model="' + w.id + '"]')) return;
      var tr = LV.add('prodTpl', 'products');
      tr.classList.add('prod-row'); tr.dataset.model = w.id;
      tr.querySelector('.p-model').value = w.id;
      tr.querySelector('.p-label').textContent = w.label;
    });
    LV.renumber('products');
    // اسم المنتج تلقائي من الموديلات لو فاضي
    var t = document.querySelector('[name=product_title]');
    if (t && (!t.value || t.dataset.auto === '1')) {
      t.value = want.map(w => w.label.replace(/^[^—]+—\s*/, '')).join(' + '); t.dataset.auto = '1';
    }
  }
  function removeRow(btn) {
    var tr = btn.closest('tr'), id = tr.dataset.model;
    var chip = document.querySelector('#mp_pickedmodels .mp-chip[data-id="' + id + '"]');
    if (chip) chip.remove();
    tr.remove(); LV.renumber('products');
    var empty = document.querySelector('#mp_pickedmodels .mp-empty'); if (empty) empty.hidden = picked().length > 0;
    var t = document.querySelector('[name=product_title]');
    if (t && t.dataset.auto === '1') t.value = picked().map(w => w.label.replace(/^[^—]+—\s*/, '')).join(' + ');
  }
  document.addEventListener('DOMContentLoaded', function () {
    var t = document.querySelector('[name=product_title]'); if (t) t.addEventListener('input', () => t.dataset.auto = '0');
  });
  return {sync, removeRow};
})();

const LVF = (function () {
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const nf = n => n === null || n === undefined ? '—' : Number(n).toLocaleString('en-US', {maximumFractionDigits: 4});
  let timer = null;

  /* التخطيط بالكمية المستهدفة: قطع مطلوبة ⇒ رقات كاملة ⇒ كمية خامة.
     رقات = سقف(القطع ÷ قطع الفرشة) · الكمية = رقات × (طول×عرض×بنشر) أو رقات × الطول */
  function target(el) {
    const r = el.closest('tr');
    const pcs = Number(el.value) || 0;
    const pps = Number(r.querySelector('.f-pps')?.value) || 0;
    const sp  = Number(r.querySelector('.f-sps')?.value) || Number(r.querySelector('.f-sp')?.value) || 0;
    const mode = r.querySelector('.f-mode')?.value || 'weight';
    if (!pcs || !pps || !sp) return;

    let perPly = sp;
    if (mode === 'weight') {
      const wd = Number(r.querySelector('.f-wd')?.value) || 0;
      const g  = Number(r.querySelector('.f-gsm')?.value) || 0;
      if (!wd || !g) return;
      perPly = sp * wd * g;
    }
    const plies = Math.ceil(pcs / pps);
    const qty = r.querySelector('.f-qty');
    if (qty) { qty.value = (plies * perPly).toFixed(3); calc(); }
  }

  const SAFETY_M = {{ $safetyCm }} / 100;

  /* فلتر قايمة الرسايل في كل السطور — بحث بجزء من الكود/الخامة/اللون */
  function filter(v) {
    v = (v || '').toLowerCase().trim();
    document.querySelectorAll('#fabrics select.f-cn option').forEach(o => {
      if (!o.value) return;
      o.hidden = v && !(o.dataset.search || '').includes(v);
    });
  }

  /* طول الأمان الثابت: بيتملى لوحده أول ما تكتب طول الفرشة */
  function safety(el) {
    const r = el.closest('tr'), sps = r.querySelector('.f-sps');
    const sp = Number(el.value) || 0;
    if (sps && sp > 0 && (!sps.value || sps.dataset.auto === '1')) {
      sps.value = (sp + SAFETY_M).toFixed(3); sps.dataset.auto = '1';
    }
    calc();
  }

  function pick(sel) {
    const o = sel.selectedOptions[0], row = sel.closest('tr');
    if (!o || !o.value) return;
    const bw = row.querySelector('.f-break');
    if (bw) { bw.hidden = o.dataset.break !== '1'; bw.title = o.dataset.breaknote || ''; }
    const wd = row.querySelector('.f-wd'), gsm = row.querySelector('.f-gsm'), qty = row.querySelector('.f-qty');
    const basis = document.getElementById('widthBasis')?.value || 'usable';
    const wSrc = basis === 'full' ? (o.dataset.wf || o.dataset.w) : (o.dataset.w || o.dataset.wf);
    if (wd && !wd.value && wSrc) wd.value = (Number(wSrc) / 100).toFixed(3);
    const wb = row.querySelector('.f-wb'); if (wb) wb.value = basis;
    if (gsm && !gsm.value && o.dataset.g) gsm.value = (Number(o.dataset.g) / 1000).toFixed(4);
    if (qty && !qty.value && o.dataset.rem) qty.value = Number(o.dataset.rem).toFixed(3);
    calc();
  }

  function collect() {
    return [...document.querySelectorAll('#fabrics tr')].map(r => ({
      label: (r.querySelector('.f-cn')?.selectedOptions[0]?.dataset.name) || 'خامة',
      calc_mode: r.querySelector('.f-mode')?.value || 'weight',
      spread_length_m: r.querySelector('.f-sp')?.value || 0,
      // لو طول الأمان فاضي (مثلًا الطول جاي جاهز من طلب التحضير) نحسب بنفس قاعدة السيرفر: الطول + 5 سم
      spread_length_safe_m: r.querySelector('.f-sps')?.value
        || ((Number(r.querySelector('.f-sp')?.value) || 0) > 0 ? (Number(r.querySelector('.f-sp').value) + SAFETY_M).toFixed(3) : 0),
      fabric_width_m: r.querySelector('.f-wd')?.value || 0,
      gsm_kg_m2: r.querySelector('.f-gsm')?.value || 0,
      pieces_per_spread: r.querySelector('.f-pps')?.value || 0,
      available: r.querySelector('.f-qty')?.value || 0
    }));
  }

  async function calc() {
    clearTimeout(timer);
    timer = setTimeout(run, 350);
  }

  async function run() {
    const fabrics = collect();
    if (!fabrics.length) return;

    const res = await fetch('{{ route('work-orders.calc') }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
      body: JSON.stringify({fabrics})
    });
    if (!res.ok) return;
    const d = await res.json();

    const rows = document.querySelectorAll('#fabrics tr');
    const names = [];
    d.fabrics.forEach((f, i) => {
      const cell = rows[i]?.querySelector('.f-out');
      if (!cell) return;
      if (!f.ok) { cell.innerHTML = '<span class="text-danger">ناقص بيانات</span>'; return; }

      /* الحسبة بأرقام السطر نفسه — نفس اللي السيرفر حسب بيه */
      const inRow = fabrics[i] || {};
      const unit  = f.unit || (inRow.calc_mode === 'length' ? 'متر' : 'كجم');
      const sp    = nf(f.spread_used_m ?? (Number(inRow.spread_length_safe_m) || Number(inRow.spread_length_m)));
      const wd    = nf(inRow.fabric_width_m), g = nf(inRow.gsm_kg_m2);
      const pps   = nf(inRow.pieces_per_spread), avail = nf(inRow.available);
      const ply   = nf(f.ply_weight_kg), cons = nf(f.consumption_per_piece);
      const plies = nf(f.plies), expected = nf(f.expected_pieces);
      const lines = f.ply_weight_kg !== null
        ? ['وزن الرِقّة = ' + sp + ' م × ' + wd + ' م × ' + g + ' كجم/م² = ' + ply + ' كجم',
           'استهلاك القطعة = ' + ply + ' ÷ ' + pps + ' = ' + cons + ' كجم',
           'الرِقّات = ' + avail + ' ÷ ' + ply + ' = ' + plies + ' (مقطوع لأقل صحيح)',
           'القطع = ' + plies + ' × ' + pps + ' = ' + expected + ' قطعة']
        : ['استهلاك القطعة = ' + sp + ' م ÷ ' + pps + ' = ' + cons + ' م',
           'الرِقّات = ' + avail + ' ÷ ' + sp + ' = ' + plies + ' (مقطوع لأقل صحيح)',
           'القطع = ' + plies + ' × ' + pps + ' = ' + expected + ' قطعة'];

      cell.innerHTML =
        (f.ply_weight_kg !== null ? 'راق ' + ply + ' كجم<br>' : '') +
        'استهلاك ' + cons + ' ' + unit + '<br>' +
        '<b>' + plies + ' رقة → ' + expected + ' قطعة</b>' +
        LVX.fx('حسبة الخامة', lines, f.leftover !== undefined && f.leftover !== null ? 'المتبقي بعد آخر رِقّة: ' + nf(f.leftover) + ' ' + unit : null) +
        (f.is_governing ? ' <span class="badge bg-warning">حاكمة</span>' : '');
      LVX.popovers(cell);
      names.push((f.label || inRow.label || 'خامة') + ' ' + expected);

      const pl = rows[i].querySelector('.f-plies'), ex = rows[i].querySelector('.f-exp');
      if (pl) pl.placeholder = f.plies;
      if (ex) ex.placeholder = f.expected_pieces;
    });

    const s = document.getElementById('fabSummary');
    let html = '<b>الكمية الحاكمة: ' + nf(d.governing_qty) + ' قطعة</b>'
             + LVX.fx('الحاكمة', ['الحاكمة = أقل قطع بين الخامات', 'الخامات: ' + (names.join(' · ') || '—') + ' ← أقلهم ' + nf(d.governing_qty)],
                      'أول خامة تخلص بتوقف الإنتاج حتى لو الباقي موجود');
    if (d.max_qty > d.governing_qty) {
      html += ' · أعلى خامة تدي ' + nf(d.max_qty) +
              ' · <span class="text-danger">فرق ' + nf(d.max_qty - d.governing_qty) + ' قطعة</span>' +
              LVX.fx('فرق الخامات', ['فرق الخامات = أعلى − الحاكمة', nf(d.max_qty) + ' − ' + nf(d.governing_qty) + ' = ' + nf(d.max_qty - d.governing_qty) + ' قطعة',
                     'نسبة الفرق = ' + nf(d.max_qty - d.governing_qty) + ' ÷ ' + nf(d.max_qty) + ' × 100 = ' + nf(Math.round(((d.max_qty - d.governing_qty) / d.max_qty) * 1000) / 10) + '%'],
                     'فوق 10% = الخامات مش متوازنة');
    }
    (d.warnings || []).forEach(w => {
      html += '<div class="alert alert-' + (w.level === 'danger' ? 'danger' : 'warning') +
              ' py-2 mt-2 mb-0 small">' + w.text + '</div>';
    });
    s.innerHTML = html;
    LVX.popovers(s);

    /* الحاكمة جنب خانة الكمية المعتمدة — عشان اللي بيعتمد يشوف هو بيختلف عن إيه */
    const gq = document.getElementById('govQty'), gf = document.getElementById('govQtyFx');
    if (gq) gq.textContent = d.governing_qty ? nf(d.governing_qty) + ' قطعة' : '—';
    if (gf) {
      gf.innerHTML = d.governing_qty ? LVX.fx('الحاكمة', ['الحاكمة = أقل قطع بين الخامات', (names.join(' · ') || '—') + ' ← ' + nf(d.governing_qty)]) : '';
      LVX.popovers(gf);
    }
  }

  document.addEventListener('DOMContentLoaded', () => setTimeout(run, 200));
  /* احسب الاستهلاك على عرض التشغيل (الافتراضي) ولا العرض الكامل — بيتطبق على كل السطور */
  function basis(v) {
    document.querySelectorAll('#fabrics tr').forEach(r => {
      const o = r.querySelector('.f-cn')?.selectedOptions[0], wd = r.querySelector('.f-wd'), wb = r.querySelector('.f-wb');
      if (!o || !o.value || !wd) return;
      const src = v === 'full' ? (o.dataset.wf || o.dataset.w) : (o.dataset.w || o.dataset.wf);
      if (src) wd.value = (Number(src) / 100).toFixed(3);
      if (wb) wb.value = v;
    });
    calc();
  }
  return {pick, calc, target, filter, safety, basis};
})();
</script>
@endpush
@endsection
