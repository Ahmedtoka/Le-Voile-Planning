@extends('layouts.app')
@section('purpose', 'ورقة المصنع: طلب التحضير (الوزن الفعلي) + الماركر + الحسبة — إنت بتختار المصنع وتاريخ التسليم بس')
@section('content')
@php
  $fabs  = old('fabrics', $fabrics ?: []);
  $prods = old('products', $mode === 'edit' ? $row->lines->toArray() : ($products ?? []));
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    الأمر مبني على <b>طلب التحضير</b> اللي المخزن وزنه و<b>الماركر</b> اللي الباترونيست سلّمه —
    <b>مفيش أرقام بتتكتب باليد</b>: الكمية = الوزن الفعلي، طول الفرشة والقطع = الماركر، العرض والبنشر = الجودة،
    والموديلات والمقاسات من سطور الماركر (وتقدر تعدّلها). إنت بتختار <b>المصنع وتاريخ التسليم</b>.
    المنتج من أكتر من خامة (طرحة تل + بونيه)؟ ضيف «خامة تانية من طلب تحضير» — والخامة اللي بتدي أقل قطع هي اللي بتحكم.
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
        <div class="col-md-4"><label class="form-label req" for="wo_factory">تشغيل مصنع</label>
          <select name="factory_id" id="wo_factory" class="form-select" required><option value="">— اختر المصنع —</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>@endforeach
          </select>
          @if($mode === 'create' && $row->factory_id)<div class="hint">من طلب التحضير — تقدر تغيّره</div>@endif</div>
        <div class="col-md-2"><label class="form-label" for="wo_pcode">كود المنتج</label>
          <input name="product_code" id="wo_pcode" class="form-control" value="{{ old('product_code',$row->product_code) }}" placeholder="P-1014"></div>
        <div class="col-md-2"><label class="form-label" for="wo_qb">كود Q.B</label>
          <input name="qb_code" id="wo_qb" class="form-control" value="{{ old('qb_code',$row->qb_code) }}" placeholder="O1014"></div>
        <div class="col-md-4"><label class="form-label">المنتج</label>
          <div class="form-control-plaintext fw-bold" id="woTitle">{{ $row->product_title ?: '—' }}</div>
          <div class="hint">بيتكوّن لوحده من الموديلات تحت</div></div>

        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="wo_date" class="form-control"
                 value="{{ old('wo_date',$row->wo_date?->format('Y-m-d') ?? $row->wo_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">تاريخ الاستلام</label>
          <input type="date" name="receive_date" class="form-control"
                 value="{{ old('receive_date',$row->receive_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label req" for="wo_due">تاريخ التسليم</label>
          <input type="date" name="due_date" id="wo_due" class="form-control" required
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

  {{-- ② الخامات: كل خامة = طلب تحضير اتوزن + ماركر معتمد --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <span>الخامات <span class="hint">— من طلب التحضير والماركر، كلها ليبلات</span></span>
      @if(($readyMore ?? collect())->isNotEmpty())
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <select class="form-select form-select-sm w-auto" id="moreFabric" aria-label="خامة تانية من طلب تحضير">
            <option value="">— خامة تانية من طلب تحضير جاهز —</option>
            @foreach($readyMore as $r)
              <option value="{{ $r->id }}">{{ $r->doc_no }} · {{ $r->consignment?->consignment_no }} · {{ $r->consignment?->fabricType?->name }} {{ $r->consignment?->color?->code }} · {{ lv_num($r->qty_for_work_order) }}</option>
            @endforeach
          </select>
          <button type="button" class="btn btn-sm btn-outline-plum" onclick="LVF.addFabric()"><i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف</button>
        </div>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table wo-fab mb-0">
        <thead><tr>
          <th style="width:36px">م</th>
          <th>الرسالة وطلب التحضير</th>
          <th style="width:130px">الوزن الفعلي</th>
          <th style="width:300px">الماركر</th>
          <th style="width:110px">طول الفرشة</th>
          <th style="width:90px">قطع الفرشة</th>
          <th style="width:170px">عرض الحسبة</th>
          <th style="width:200px">حسبة السيستم</th>
          <th style="width:44px"><span class="visually-hidden">شيل</span></th>
        </tr></thead>
        <tbody id="fabrics">
          @foreach($fabs as $i => $l) @include('workorders.fabric_row', ['i'=>$i,'l'=>$l]) @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      <div id="fabSummary" class="hint">—</div>
      <div class="hint mt-1">الرِقّات والقص المعتمد بيتحسبوا من الوزن الفعلي والماركر — مفيش حاجة تتكتب.
        طول الأمان = طول الفرشة + {{ lv_num($safetyCm, 0) }} سم تلقائيًا.</div>
    </div>
  </div>


  {{-- ③ المنتجات والمقاسات --}}
  <div class="card mb-3">
    <div class="card-header">المنتجات والمقاسات <span class="hint">— نازلة من سطور الماركر؛ لو هتغيّر ابحث بالكود أو الاسم</span></div>
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
                   value="{{ old('approved_qty',$row->approved_qty) }}" placeholder="= الحاكمة">
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
.wo-fab td.f-out{white-space:normal;font-size:var(--fs-xs);line-height:1.5}
</style>

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
    title();
  }
  /* المنتج = الموديلات اللي هتتشغل (ليبل — السيرفر بيكتبه بنفس الطريقة) */
  function title() {
    var el = document.getElementById('woTitle'); if (!el) return;
    var names = picked().map(w => w.label.replace(/^[^—]+—\s*/, ''));
    el.textContent = names.length ? names.join(' + ') : '—';
  }
  function removeRow(btn) {
    var tr = btn.closest('tr'), id = tr.dataset.model;
    var chip = document.querySelector('#mp_pickedmodels .mp-chip[data-id="' + id + '"]');
    if (chip) chip.remove();
    tr.remove(); LV.renumber('products');
    var empty = document.querySelector('#mp_pickedmodels .mp-empty'); if (empty) empty.hidden = picked().length > 0;
    title();
  }
  document.addEventListener('DOMContentLoaded', title);
  return {sync, removeRow};
})();

const LVF = (function () {
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const nf = n => n === null || n === undefined ? '—' : Number(n).toLocaleString('en-US', {maximumFractionDigits: 4});
  let timer = null;

  const SAFETY_M = {{ $safetyCm }} / 100;

  /* تغيير الماركر ⇒ طول الفرشة والقطع من الماركر الجديد */
  function marker(sel) {
    const o = sel.selectedOptions[0], r = sel.closest('tr');
    if (!o) return;
    r.querySelector('.f-sp').value = o.dataset.sp; r.querySelector('.f-pps').value = o.dataset.pps;
    r.querySelector('.f-sp-l').textContent = Number(o.dataset.sp).toLocaleString('en-US', {maximumFractionDigits: 2});
    r.querySelector('.f-pps-l').textContent = o.dataset.pps;
    calc();
  }

  /* عرض الحسبة للسطر: عرض التشغيل (أقل عرض − التأمين) ولا العرض الكامل المقاس */
  function basis(sel) {
    const r = sel.closest('tr'), src = sel.value === 'full' ? r.dataset.wf : r.dataset.w;
    if (src) r.querySelector('.f-wd').value = (Number(src) / 100).toFixed(3);
    calc();
  }

  /* خامة تانية: السطر بيتبني على السيرفر من طلب التحضير */
  async function addFabric() {
    const sel = document.getElementById('moreFabric'); if (!sel || !sel.value) { sel?.focus(); return; }
    if (document.querySelector('#fabrics [name$="[preparation_request_id]"][value="' + sel.value + '"]')) { sel.value = ''; return; }
    const i = 'n' + Date.now();
    const res = await fetch('{{ route('work-orders.fabric-row') }}?preparation_request_id=' + sel.value + '&i=' + i, {headers: {'Accept': 'text/html'}});
    if (!res.ok) { alert('طلب التحضير ده مش جاهز — لازم يتوزن ويكون له ماركر معتمد.'); return; }
    document.getElementById('fabrics').insertAdjacentHTML('beforeend', await res.text());
    sel.selectedOptions[0].disabled = true; sel.value = '';
    LV.renumber('fabrics'); calc();
  }

  function collect() {
    return [...document.querySelectorAll('#fabrics tr')].filter(r => r.querySelector('.f-qty')).map(r => ({
      label: r.querySelector('.f-name')?.value || 'خامة',
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

    const rows = [...document.querySelectorAll('#fabrics tr')].filter(r => r.querySelector('.f-qty'));
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
  return {calc, marker, basis, addFabric};
})();
</script>
@endpush
@endsection
