@extends('layouts.app')
@section('purpose', 'استلام القماش على الطلب — كل (خامة + لون) بيتولّد له حوض محجوز تحت الفحص')
@section('content')
@php
  $lines = old('lines', ($preset ?? null) ?: ($row->lines?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'addition'])

@php $po = $poInfo ?? $row->purchaseOrder ?? null; @endphp
@if($po)
  @php
    $po->loadMissing('lines', 'supplier');
    $ordTot  = (float) $po->lines->sum('qty');
    $recTot  = (float) $po->lines->sum('received_qty');
    $remTot  = max(0, $ordTot - $recTot);   // الباقي = المطلوب − المستلم، زي ما هو
    $unitLbl = $po->lines->first()?->unit ?? '';
  @endphp
  <div class="ro-box mb-3">
    <div class="ro-title">من طلب الشراء — للقراءة</div>
    <div class="sum-sec">
      <div class="t"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> <span class="num">{{ $po->po_no }}</span></div>
      <div class="sum-grid">
        <div><div class="k">المورد</div><div class="v">{{ $po->supplier?->name ?? '—' }}</div></div>
        <div><div class="k">توريد متوقع</div><div class="v num">{{ $po->delivery_date?->format('Y-m-d') ?? '—' }}</div></div>
        <div><div class="k">المطلوب إجمالي</div><div class="v num">{{ lv_qty($ordTot, $unitLbl) }}</div></div>
        <div><div class="k">استلمنا قبل كده</div><div class="v num">{{ lv_qty($recTot, $unitLbl) }}</div></div>
        <div><div class="k">الباقي</div>
          <div class="v num {{ $remTot > 0 ? 'text-danger' : 'text-success' }}">
            {{ $remTot > 0 ? lv_qty($remTot, $unitLbl) : 'اكتمل ✓' }}
            @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . lv_qty($ordTot, $unitLbl) . ' − المستلم قبل كده ' . lv_qty($recTot, $unitLbl) . ' = ' . lv_qty($remTot, $unitLbl), 'why' => 'مجموع كل أصناف الطلب'])
          </div></div>
      </div>
    </div>
  </div>
@endif

@if(old('receipt_type', $row->receipt_type ?? 'normal') === 'container')
  <div class="note-box mb-3">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span><b>استلام حاويات — بدون دورة فحص.</b>
    الإذن ده هو الاستلام النهائي: البضاعة بتدخل المخزن <b>مُفرَج عنها ومتاحة للتشغيل فورًا</b>.
    لو محتاجين بيانات العرض والبنشر، الجودة تقدر تفحص 5-6 أتواب استدلاليًا — مش للرفض.</span>
  </div>
@else
  <div class="note-box mb-3">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span><b>ده أول مستند في دورة القماش.</b>
    <b>الحفظ</b> بيولّد <b>رسالة (حوض) لكل لون</b> وبينزّل القماش طابور الفحص فورًا —
    مفيش خطوة «إرسال» بعد كده. الكمية بتدخل المخزن <b>محجوزة</b>،
    والإفراج بيحصل من شاشة استلام الخام بعد ما الفحص والمعمل يخلّصوا.
    <br><b>مهم:</b> اكتب عدد الأتواب صح — الفحص هيجرد عليه.</span>
  </div>
@endif

<form method="post" action="{{ $mode==='create' ? route('stock-additions.store') : route('stock-additions.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header">
      <span>{{ $mode==='create' ? 'إذن إضافة جديد' : 'إذن إضافة ' . $row->doc_no }}
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }}">{{ $row->status_label }}</span>@endif
      </span>
      <div class="d-flex gap-2 ms-auto">
        @if($mode==='edit')
          <a href="{{ route('stock-additions.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('stock-additions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if($po)
        {{-- الإذن من طلب: التاريخ والمورد والمخزن والطلب بييجوا منه — مش بيتكتبوا تاني --}}
        <input type="hidden" name="doc_date"          value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="supplier_id"       value="{{ old('supplier_id', $row->supplier_id ?? $po->supplier_id) }}">
        <input type="hidden" name="warehouse_id"      value="{{ old('warehouse_id', $row->warehouse_id) }}">
        <input type="hidden" name="purchase_order_id" value="{{ old('purchase_order_id', $row->purchase_order_id ?? $po->id) }}">

        @php $rt = $po->inspection_exempt ? 'container' : 'normal'; @endphp
        <input type="hidden" name="receipt_type" value="{{ $rt }}">
        {{-- Labels فوق — للقراءة --}}
        <div class="ro-box mb-3">
          <div class="ro-title">بيتسجل لوحده — للقراءة</div>
          <div class="sum-grid">
            <div><div class="k">رقم الرسالة</div>
              <div class="v num">{{ $row->consignment_no ?: ($consignmentPreview ?? 'بيتولّد تلقائيًا عند الاعتماد') }}</div>
              <div class="k">{{ $row->consignment_no ? 'اتولّد' : 'بيتأكد عند الاعتماد' }} · رسالة لكل لون</div></div>
            <div><div class="k">نوع الاستلام</div>
              <div class="v">{{ $rt === 'container' ? 'مباشر — بدون فحص' : 'عادي — يدخل الفحص' }}</div>
              <div class="k">متحدد من طلب الشراء (قرار التخطيط)</div></div>
            <div><div class="k">تاريخ الإذن</div><div class="v num">{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}</div></div>
            <div><div class="k">استلمه</div><div class="v">{{ $row->creator?->name ?? auth()->user()?->name }}</div></div>
          </div>
        </div>
        {{-- اللي بتكتبه --}}
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label req" for="paper_serial">مسلسل إذن الإضافة (الورقي)</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" required placeholder="مثال: 41456" inputmode="numeric"></div>
          <div class="col-md-4"><label class="form-label req" for="supplier_doc_no">رقم إذن المورد</label>
            <input name="supplier_doc_no" id="supplier_doc_no" class="form-control w-mid" value="{{ old('supplier_doc_no',$row->supplier_doc_no) }}"
                   required placeholder="الرقم اللي على ورقة المورد">
            <div class="hint">بيتربط بالفحص والمعمل والاستلام</div></div>
          <div class="col-12"><label class="form-label" for="notes">ملاحظات <span class="hint">(اختياري)</span></label>
            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="مثال: وصلت على عربيتين — التانية الصبح">{{ old('notes',$row->notes) }}</textarea></div>
        </div>
      @else
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label req" for="doc_date">التاريخ</label>
            <input type="date" name="doc_date" id="doc_date" class="form-control w-date" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
          <div class="col-md-3"><label class="form-label req" for="paper_serial">مسلسل إذن الإضافة (الورقي)</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" required placeholder="مثال: 41456" inputmode="numeric"></div>
          <div class="col-md-3"><label class="form-label req" for="supplier_id">اسم المورد</label>
            <select name="supplier_id" id="supplier_id" class="form-select" required><option value="">—</option>
              @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req" for="warehouse_id">المخزن</label>
            <select name="warehouse_id" id="warehouse_id" class="form-select" required><option value="">—</option>
              @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req" for="supplier_doc_no">رقم إذن المورد</label>
            <input name="supplier_doc_no" id="supplier_doc_no" class="form-control" value="{{ old('supplier_doc_no',$row->supplier_doc_no) }}"
                   required placeholder="الرقم اللي على ورقة المورد">
            <div class="hint">بيتربط بالفحص والمعمل والاستلام</div></div>
          <div class="col-md-3"><label class="form-label" for="receipt_type">نوع الاستلام</label>
            <select name="receipt_type" id="receipt_type" class="form-select"
                    @if($mode==='create')
                      onchange="var po=document.querySelector('[name=purchase_order_id]')?.value; location='{{ route('stock-additions.create') }}?type='+this.value+(po?'&purchase_order_id='+po:'')"
                    @endif>
              <option value="normal" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='normal')>عادي — يدخل الفحص</option>
              <option value="container" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='container')>حاويات — بدون فحص</option>
            </select></div>
          <div class="col-md-3"><label class="form-label" for="purchase_order_id">طلب الشراء</label>
            <select name="purchase_order_id" id="purchase_order_id" class="form-select"
                    @if($mode==='create')
                      onchange="if(this.value) location='{{ route('stock-additions.create') }}?purchase_order_id='+this.value+'&type='+(document.querySelector('[name=receipt_type]')?.value||'normal')"
                    @endif>
              <option value="">— بدون طلب —</option>
              @foreach($pos as $k=>$v)<option value="{{ $k }}" @selected(old('purchase_order_id',$row->purchase_order_id)==$k)>{{ $v }}</option>@endforeach
            </select>
            @if($mode==='create')<div class="hint">اختار الطلب والشاشة هتتظبط لوحدها</div>@endif
          </div>
          <div class="col-md-3"><label class="form-label" for="consignment_no">رقم الرسالة</label>
            <input name="consignment_no" id="consignment_no" class="form-control" value="{{ old('consignment_no',$row->consignment_no) }}"
                   placeholder="سيبه فاضي والسيستم هيولّده">
            <div class="hint">النمط: BUPL-090826-043-00</div></div>
          <div class="col-12"><label class="form-label" for="notes">ملاحظات <span class="hint">(اختياري)</span></label>
            <input name="notes" id="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="مثال: وصلت على عربيتين — التانية الصبح"></div>
        </div>
      @endif
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <span>الأصناف {{ $po ? '— من الطلب ' . $po->po_no : '' }}
        @if($po)<span class="hint">(البيانات ثابتة، اكتب المستلم بس)</span>@endif</span>
      @if($editable && !$po)
        <button type="button" class="btn btn-sm btn-outline-plum ms-auto" onclick="LV.add('lineTpl','lines')">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> سطر</button>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        @if($po)
          <thead><tr>
            <th style="width:35px">م</th>
            <th>الصنف</th>
            <th style="width:120px">اللون المطلوب</th>
            <th style="width:190px">اللون الواصل</th>
            <th style="width:90px">الأتواب (توب)</th>
            <th style="width:110px">المطلوب</th>
            <th style="width:120px">المستلم ({{ $unitLbl ?: 'بوحدة الطلب' }})</th>
            <th style="width:130px">الباقي</th>
            <th style="width:170px">الباقي هيوصل إمتى؟</th>
            <th style="width:44px"></th>
          </tr></thead>
        @else
          <thead><tr>
            <th style="width:35px">م</th><th style="width:95px">كود الصنف</th><th>اسم الصنف</th>
            <th style="width:160px">الخامة</th><th style="width:170px">اللون</th>
            <th style="width:90px">الأتواب (توب)</th><th style="width:110px">الكمية</th>
            <th class="req" style="width:96px">الوحدة</th><th style="width:44px"></th>
          </tr></thead>
        @endif
        <tbody id="lines" class="{{ $editable ? '' : 'lines-locked' }}">
          @foreach($lines as $i=>$l) @include('additions.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines))
            @if($po)
              <tr><td colspan="10">
                <div class="empty-state">
                  <i class="bi bi-check2-circle ico" aria-hidden="true"></i>
                  <div class="t">مفيش باقي على الطلب ده — كل أصنافه اتسلمت.</div>
                </div>
              </td></tr>
            @else
              @include('additions.line',['i'=>0,'l'=>[]])
            @endif
          @endif
        </tbody>
      </table>
    </div>
    @if($po)
      <div class="card-footer bg-white hint">
        طلبت طن؟ بتستلم بالطن — نفس وحدة الطلب. لو اللون الواصل مختلف عن المطلوب
        هيطلعلك سؤال القرار قبل ما تكمّل.
      </div>
    @endif
  </div>

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum">
      <i class="bi bi-save" aria-hidden="true"></i> حفظ
    </button>
    <span class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص الإذن على طول — بيتولّد حوض لكل لون
      {{ ($row->receipt_type ?? 'normal') === 'container' ? 'ويدخل المخزن مباشرة' : 'وينزل طابور الفحص' }}.
    </span>
    <div class="fa-end"><a href="{{ route('stock-additions.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
  </div>
  @endif
</form>

@php
  $saConsignments = $mode === 'edit'
      ? $row->lines->pluck('consignment')->filter()->unique('id')->values()
      : collect();
  if ($saConsignments->isEmpty() && $row->consignment) $saConsignments = collect([$row->consignment]);
@endphp
@if($saConsignments->isNotEmpty())
  <div class="card mt-3">
    <div class="card-header">
      {{ $saConsignments->count() > 1 ? 'الرسايل اللي اتولّدت — رسالة لكل لون' : 'الرسالة اللي اتولّدت' }}
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>رقم الرسالة</th><th>الصنف</th><th>اللون</th><th>الكمية (كجم)</th><th>الأتواب (توب)</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach($saConsignments as $c)
          <tr>
            <td class="num fw-bold"><a href="{{ route('consignments.show', $c) }}">{{ $c->consignment_no }}</a></td>
            <td>{{ $c->fabricType?->name ?? '—' }}</td>
            <td>{{ $c->color?->label ?? $c->color?->code ?? '—' }}</td>
            <td class="num">{{ lv_num($c->total_kg, 1) }}</td>
            <td class="num">{{ (int) $c->rolls_count }}</td>
            <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_name }}</span></td>
            <td class="text-end">
              @if($c->status === 'under_inspection')
                <div class="row-actions">
                  <a href="{{ route('inspections.create', ['consignment_id'=>$c->id]) }}"
                     class="btn btn-sm btn-outline-plum">افحص</a>
                </div>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@if($mode === 'edit' && $row->purchaseOrder)
  @include('partials.po_deliveries', ['po' => $row->purchaseOrder->load('lines.fabricType', 'lines.color')])
@endif

<template id="lineTpl">@include('additions.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
{{-- بوب أب قرار انحراف اللون --}}
<div class="modal fade" id="colorModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h6 class="modal-title"><i class="bi bi-palette" aria-hidden="true"></i> الدرجة اللي وصلت مختلفة</h6>
    </div>
    <div class="modal-body">
      <p class="mb-2">
        إنت كنت طالب <b class="cm-req"></b> واللي وصل <b class="cm-got"></b>.
      </p>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-outline-plum text-start" data-choice="substitute">
          <b>نزّله مكان الطلب</b>
          <div class="hint">الكمية بتتحسب على السطر ده، ولون السطر في طلب الشراء
            بيتحدث للون الواصل — بملاحظة في الهيستوري.</div>
        </button>
        <button type="button" class="btn btn-outline-plum text-start" data-choice="new_po">
          <b>نزّله كسطر جديد — والأصلي يفضل مطلوب</b>
          <div class="hint">الوارد بيتوثّق بطلب تلقائي باللون الجديد، واللون الأصلي
            يفضل مفتوح على المورد يبعته.</div>
        </button>
      </div>
    </div>
    <div class="modal-footer py-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-choice="">
        إلغاء — رجّع اللون المطلوب
      </button>
    </div>
  </div></div>
</div>

<style>
  /* زرار اختيار القرار في البوب أب: نص متعدد الأسطر */
  #colorModal .btn.text-start{display:block;white-space:normal;text-align:start}
  #colorModal .btn.text-start .hint{white-space:normal}
</style>

@include('partials.lines_js',['startIndex'=>max(count($lines),1)])
{{-- السكريبت في ستاك السكريبتات عشان يتنفذ بعد تحميل بوتستراب — وإلا الموديل مش هيشتغل --}}
@push('scripts')
<script>
(function () {
  var body = document.getElementById('lines');
  if (!body) return;
  var EDITABLE = {{ $editable ? 'true' : 'false' }};

  var nf = function (n) {
    return Number(n.toFixed(3)).toLocaleString('en-US', {maximumFractionDigits: 3});
  };

  /* ── المستلم/الباقي لايف: طالب 50 طن وكتبت 30 ⇒ «المستلم 10 توب · 30 طن — باقي 20 طن» ──
     نسبة الزيادة للزيادة بس: الأقل من المطلوب بيفضل باقي، والأكتر منها بيترفض. */
  function calcRow(tr) {
    var out = tr.querySelector('.q-left');
    if (!out) return 0;
    var unit     = tr.dataset.unit || '';
    var ordered  = parseFloat(tr.dataset.ordered)  || 0;
    var minQty   = parseFloat(tr.dataset.min)      || ordered;
    var received = parseFloat(tr.dataset.received) || 0;
    var now      = parseFloat(tr.querySelector('.q-recv')?.value) || 0;
    var rolls    = parseInt(tr.querySelector('[name$="[rolls_count]"]')?.value || '0', 10) || 0;
    var recvLine = '<div class="hint">المستلم دلوقتي: <b class="num">' + (rolls ? rolls + ' توب · ' : '') + nf(now) + ' ' + unit + '</b></div>';
    var left     = ordered - received - now;          // للعرض
    var leftMin  = Math.max(0, minQty - received - now);  // للإقفال
    // ⓘ المعادلة بالأرقام الفعلية للسطر
    var fx = function (title) {
      return (window.LVX && LVX.fx) ? LVX.fx(title, [
        'المطلوب ' + nf(ordered) + ' ' + unit + ' − المستلم قبل كده ' + nf(received) + ' − المستلم دلوقتي ' + nf(now) + ' = ' + nf(left) + ' ' + unit
      ], 'الباقي = المطلوب − اللي وصل كله') : '';
    };
    // خانة «هيوصل إمتى» بتظهر بس لما يبقى فيه باقي على السطر ده
    var eta = tr.querySelector('.r-eta');
    var showEta = function (on) {
      if (!eta) return;
      eta.style.display = on ? '' : 'none';
      if (!on) eta.querySelectorAll('input').forEach(function (x) { x.value = ''; });
    };

    if (!now) { out.innerHTML = '<span class="hint">—</span>'; showEta(false); return Math.max(0, minQty - received); }
    if (leftMin > 0.0005) {
      out.innerHTML = recvLine + '<span class="pill pill-warn">باقي ' + nf(left) + ' ' + unit + '</span>' + fx('الباقي على السطر');
      showEta(true);
    } else if (left < -0.0005) {
      out.innerHTML = recvLine + '<span class="pill pill-danger">زيادة ' + nf(-left) + ' ' + unit + '</span>' + fx('الزيادة على السطر');
      showEta(false);
    } else {
      out.innerHTML = recvLine + '<span class="pill pill-ok">اكتمل ✓</span>' + fx('اكتمل');
      showEta(false);
    }
    if (window.LVX && LVX.popovers) LVX.popovers(out);
    return leftMin;
  }

  function calcAll() {
    body.querySelectorAll('tr.po-row').forEach(calcRow);
  }

  /* الحسبة اللايف للمسودات بس — المستند المعتمد المستلم بتاعه اتحسب
     على الطلب فعلًا، فلو حسبناه تاني هيتخصم مرتين */
  if (EDITABLE) {
    body.addEventListener('input', function (e) {
      if (e.target.classList.contains('q-recv') || /\[rolls_count\]$/.test(e.target.name || '')) calcAll();
    });
    calcAll();
  }

  /* ── قرار اللون: مطابق يكمّل — مختلف يفتح البوب أب ── */
  var modalEl = document.getElementById('colorModal');
  var modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
  var pending = null;   // الصف اللي مستني القرار

  function setStatus(tr, action) {
    var s = tr.querySelector('.c-status'), sel = tr.querySelector('.c-actual');
    if (!s || !sel) return;
    tr.querySelector('.c-action').value = action;
    if (action === 'substitute') {
      s.innerHTML = '<span class="pill pill-warn">تسكين — الطلب هيتحدث للون ده</span>';
    } else if (action === 'new_po') {
      s.innerHTML = '<span class="pill pill-info">سطر جديد — والأصلي يفضل مطلوب</span>';
    } else if (sel.value == sel.dataset.requested) {
      s.innerHTML = '<span class="pill pill-ok"><i class="bi bi-check2" aria-hidden="true"></i> مطابق</span>';
    } else {
      s.innerHTML = '';
    }
  }

  body.addEventListener('change', function (e) {
    if (!EDITABLE || !e.target.classList.contains('c-actual')) return;
    var sel = e.target, tr = sel.closest('tr');

    // سطر الطلب من غير لون محدد؟ مفيش انحراف نسأله عليه
    if (!sel.dataset.requested) { setStatus(tr, ''); return; }

    if (sel.value == sel.dataset.requested) { setStatus(tr, ''); return; }

    if (modal) {
      pending = tr;
      modalEl.querySelector('.cm-req').textContent = sel.dataset.requestedLabel || 'اللون المطلوب';
      modalEl.querySelector('.cm-got').textContent = sel.selectedOptions[0]?.textContent || '';
      modal.show();
    }
  });

  modalEl?.querySelectorAll('[data-choice]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (pending) {
        var choice = btn.dataset.choice;
        if (choice) {
          setStatus(pending, choice);
        } else {
          // إلغاء: رجّع اللون المطلوب زي ما كان
          var sel = pending.querySelector('.c-actual');
          sel.value = sel.dataset.requested;
          setStatus(pending, '');
        }
        pending = null;
      }
      modal.hide();
    });
  });
})();
</script>
@endpush
@endsection
