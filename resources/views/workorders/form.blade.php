@extends('layouts.app')
@section('content')
@php
  $fabs  = old('fabrics', $fabrics ?: []);
  $prods = old('products', $mode === 'edit' ? $row->lines->toArray() : []);
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  المنتج ممكن يتعمل من <b>أكتر من خامة</b> (طرحة تل + بونيه رباط مياي). كل خامة ليها
  رسالتها وطول فرشتها وعرضها وعدد قطعها في الفرشة، وبتتحسب بطريقة مختلفة:
  <b>بالوزن</b> (محتاجة عرض وبنشر) أو <b>بالطول</b> (الطول وحده كفاية).
  <br>
  <b>الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج</b> — والفرق بينها وبين الباقي
  هو أهم رقم هنا، لأنه على الورق بيبقى مخفي تمامًا.
</div>

<form method="post" action="{{ $mode==='create' ? route('work-orders.store') : route('work-orders.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ① ورقة المصنع --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>{{ $mode==='create' ? 'أمر شغل جديد' : 'أمر الشغل ' . $row->wo_no }}</span>
      <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-5"><label class="form-label req">اسم المنتج</label>
          <input name="product_title" class="form-control form-control-sm"
                 value="{{ old('product_title',$row->product_title) }}" required
                 placeholder="طرحة تل + بونيه رباط مياي (بيج 241)"></div>
        <div class="col-md-2"><label class="form-label">كود المنتج</label>
          <input name="product_code" class="form-control form-control-sm" value="{{ old('product_code',$row->product_code) }}"></div>
        <div class="col-md-2"><label class="form-label">كود Q.B</label>
          <input name="qb_code" class="form-control form-control-sm" value="{{ old('qb_code',$row->qb_code) }}" placeholder="O1014"></div>
        <div class="col-md-3"><label class="form-label req">تشغيل مصنع</label>
          <select name="factory_id" class="form-select form-select-sm" required><option value="">—</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="wo_date" class="form-control form-control-sm"
                 value="{{ old('wo_date',$row->wo_date?->format('Y-m-d') ?? $row->wo_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">تاريخ الاستلام</label>
          <input type="date" name="receive_date" class="form-control form-control-sm"
                 value="{{ old('receive_date',$row->receive_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label">تاريخ التسليم</label>
          <input type="date" name="due_date" class="form-control form-control-sm"
                 value="{{ old('due_date',$row->due_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label">نسخ الماركر</label>
          <input type="number" name="marker_copies" class="form-control form-control-sm"
                 value="{{ old('marker_copies',$row->marker_copies ?? 2) }}"></div>
        <div class="col-md-2"><label class="form-label">باركود التكويد</label>
          <input name="barcode" class="form-control form-control-sm" value="{{ old('barcode',$row->barcode) }}"></div>
        <div class="col-md-2"><label class="form-label">إدارة التخطيط</label>
          <select name="planner_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($planners as $k=>$v)<option value="{{ $k }}" @selected(old('planner_id',$row->planner_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-md-6"><label class="form-label">ملاحظات خاصة بقسم القص</label>
          <textarea name="cutting_notes" rows="2" class="form-control form-control-sm">{{ old('cutting_notes',$row->cutting_notes) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">ملاحظات عامة</label>
          <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes',$row->notes) }}</textarea></div>
      </div>
    </div>
  </div>

  {{-- ② الخامات --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الخامات وحسبة كل واحدة</span>
      <button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('fabTpl','fabrics'); LVF.calc()">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> خامة
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0" style="min-width:1280px">
        <thead><tr>
          <th style="width:32px">م</th>
          <th style="width:250px">رقم الرسالة</th>
          <th style="width:88px">الحساب</th>
          <th style="width:70px">الوحدة</th>
          <th style="width:90px">الكمية</th>
          <th style="width:90px">طول الفرشة</th>
          <th style="width:90px">بالأمان</th>
          <th style="width:80px">العرض (م)</th>
          <th style="width:90px">البنشر كجم/م²</th>
          <th style="width:80px">قطع الفرشة</th>
          <th style="width:150px">حسبة السيستم</th>
          <th style="width:80px">الرقات</th>
          <th style="width:90px">القص المعتمد</th>
          <th style="width:110px">الماركر</th>
          <th style="width:36px"></th>
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
      </div>
    </div>
  </div>


  {{-- ③ المنتجات والمقاسات --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>المنتجات والمقاسات</span>
      <button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('prodTpl','products')">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> موديل
      </button>
    </div>
    <div class="card-body pb-0">
      <div class="hint mb-2">
        <b>لازم سطر واحد على الأقل.</b> اكتب <b>قطع الموديل في الفرشة</b> (6 تلبيسة + 6 كويتي مثلًا =
        نفس إجمالي قطع الفرشة اللي فوق) — والسيستم هيوزّع الاستهلاك الفعلي على كل موديل بنسبة
        متوسطه التاريخي بدل ما يعمّم رقم واحد، وهيحسب كمية كل موديل من الرِقّات تلقائيًا.
        سيب الكمية فاضية والسيستم هيحسبها.
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th>الموديل</th>
          <th style="width:150px">المقاس</th>
          <th style="width:120px">قطعه في الفرشة</th>
          <th style="width:130px">الكمية</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="products">
          @foreach($prods as $i => $l) @include('workorders.product_row', ['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($prods)) @include('workorders.product_row', ['i'=>0,'l'=>[]]) @endif
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
          <label class="form-label">الكمية المعتمدة</label>
          <input type="number" name="approved_qty" class="form-control form-control-sm"
                 value="{{ old('approved_qty',$row->approved_qty) }}" placeholder="سيبها فاضية = الحاكمة">
          <div class="hint">فاضية معناها السيستم هياخد أقل خامة</div>
        </div>
        <div class="col-md-9">
          <label class="form-label">سبب اختلافها عن الحاكمة</label>
          <input name="approved_qty_reason" class="form-control form-control-sm"
                 value="{{ old('approved_qty_reason',$row->approved_qty_reason) }}"
                 placeholder="مثال: هيوصل تل إضافي الأسبوع الجاي يغطي الفرق">
        </div>
      </div>
    </div>
  </div>

  <button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ أمر الشغل</button>
</form>

<template id="fabTpl">@include('workorders.fabric_row', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
<template id="prodTpl">@include('workorders.product_row', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js', ['startIndex' => max(count($fabs), count($prods), 1)])

@push('scripts')
<script>
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

  function pick(sel) {
    const o = sel.selectedOptions[0], row = sel.closest('tr');
    if (!o || !o.value) return;
    const wd = row.querySelector('.f-wd'), gsm = row.querySelector('.f-gsm'), qty = row.querySelector('.f-qty');
    if (wd && !wd.value && o.dataset.w) wd.value = (Number(o.dataset.w) / 100).toFixed(3);
    if (gsm && !gsm.value && o.dataset.g) gsm.value = (Number(o.dataset.g) / 1000).toFixed(4);
    if (qty && !qty.value && o.dataset.rem) qty.value = Number(o.dataset.rem).toFixed(3);
    calc();
  }

  function collect() {
    return [...document.querySelectorAll('#fabrics tr')].map(r => ({
      label: (r.querySelector('.f-cn')?.selectedOptions[0]?.dataset.name) || 'خامة',
      calc_mode: r.querySelector('.f-mode')?.value || 'weight',
      spread_length_m: r.querySelector('.f-sp')?.value || 0,
      spread_length_safe_m: r.querySelector('.f-sps')?.value || 0,
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
    d.fabrics.forEach((f, i) => {
      const cell = rows[i]?.querySelector('.f-out');
      if (!cell) return;
      if (!f.ok) { cell.innerHTML = '<span class="text-danger">ناقص بيانات</span>'; return; }
      cell.innerHTML =
        (f.ply_weight_kg !== null ? 'راق ' + nf(f.ply_weight_kg) + ' كجم<br>' : '') +
        'استهلاك ' + nf(f.consumption_per_piece) + '<br>' +
        '<b>' + nf(f.plies) + ' رقة → ' + nf(f.expected_pieces) + ' قطعة</b>' +
        (f.is_governing ? ' <span class="badge bg-warning">حاكمة</span>' : '');

      const pl = rows[i].querySelector('.f-plies'), ex = rows[i].querySelector('.f-exp');
      if (pl) pl.placeholder = f.plies;
      if (ex) ex.placeholder = f.expected_pieces;
    });

    const s = document.getElementById('fabSummary');
    let html = '<b>الكمية الحاكمة: ' + nf(d.governing_qty) + ' قطعة</b>';
    if (d.max_qty > d.governing_qty) {
      html += ' · أعلى خامة تدي ' + nf(d.max_qty) +
              ' · <span class="text-danger">فرق ' + nf(d.max_qty - d.governing_qty) + ' قطعة</span>';
    }
    (d.warnings || []).forEach(w => {
      html += '<div class="alert alert-' + (w.level === 'danger' ? 'danger' : 'warning') +
              ' py-2 mt-2 mb-0 small">' + w.text + '</div>';
    });
    s.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', () => setTimeout(run, 200));
  return {pick, calc, target};
})();
</script>
@endpush
@endsection
