@extends('layouts.app')
@section('content')
@php
  $lines = old('lines', ($preset ?? null) ?: ($row->lines?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'addition'])

@include('partials.approval_box')

@php $po = $poInfo ?? $row->purchaseOrder ?? null; @endphp
@if($po)
  @php
    $po->loadMissing('lines');
    $ordTot = (float) $po->lines->sum('qty');
    $recTot = (float) $po->lines->sum('received_qty');
    // الباقي بيتحسب سطر بسطر بنفس مسطرة الإقفال (الحد الأدنى المقبول)
    $remTot = 0; $unitLbl = '';
    foreach ($po->lines as $l) {
      $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty);
      if ($left > 0.0001) { $remTot += $left; $unitLbl = $unitLbl ?: $l->unit; }
    }
    $unitLbl = $unitLbl ?: ($po->lines->first()?->unit ?? '');
  @endphp
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2 d-flex gap-4 flex-wrap align-items-center">
      <span><b>طلب الشراء:</b> <span class="num">{{ $po->po_no }}</span></span>
      <span><b>المورد:</b> {{ $po->supplier?->name ?? '—' }}</span>
      <span><b>توريد متوقع:</b> <span class="num">{{ $po->delivery_date?->format('Y-m-d') ?? '—' }}</span></span>
      <span><b>مطلوب:</b> <span class="num">{{ rtrim(rtrim(number_format($ordTot,3),'0'),'.') }} {{ $unitLbl }}</span></span>
      <span><b>استلمنا قبل كده:</b> <span class="num">{{ rtrim(rtrim(number_format($recTot,3),'0'),'.') }}</span></span>
      <span class="fw-bold {{ $remTot > 0 ? 'text-danger' : 'text-success' }}">
        <b>الباقي قبل الإذن ده:</b>
        <span class="num">{{ $remTot > 0 ? rtrim(rtrim(number_format($remTot,3),'0'),'.') . ' ' . $unitLbl : 'مفيش' }}</span>
      </span>
      <span class="hint ms-auto">بيانات الطلب ثابتة — اكتب المستلم فعلًا وعدد الأتواب بس</span>
    </div>
  </div>
@endif

@if(old('receipt_type', $row->receipt_type ?? 'normal') === 'container')
  <div class="note-box mb-3">
    <b>استلام حاويات — بدون دورة فحص.</b>
    الإذن ده هو الاستلام النهائي: البضاعة بتدخل المخزن <b>مُفرَج عنها ومتاحة للتشغيل فورًا</b>.
    لو محتاجين بيانات العرض والبنشر، الجودة تقدر تفحص 5-6 أتواب استدلاليًا — مش للرفض.
  </div>
@else
  <div class="note-box mb-3">
    <b>ده أول مستند في دورة القماش.</b>
    اعتماد الإذن ده بيولّد الحوض (الرسالة) وبيدخّل الكمية المخزن <b>محجوزة تحت الفحص</b> —
    ممنوع تشغيلها. الإفراج بيحصل بإذن الاستلام الخام بعد ما الفحص والمعمل يخلّصوا.
    <br><b>مهم:</b> اكتب عدد الأتواب صح — الفحص هيجرد عليه.
  </div>
@endif

<form method="post" action="{{ $mode==='create' ? route('stock-additions.store') : route('stock-additions.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'إذن إضافة جديد' : 'إذن إضافة ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('stock-additions.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('stock-additions.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
          <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="41456"></div>
        <div class="col-md-3"><label class="form-label req">اسم المورد</label>
          <select name="supplier_id" class="form-select form-select-sm" required><option value="">—</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req">المخزن</label>
          <select name="warehouse_id" class="form-select form-select-sm" required><option value="">—</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-4"><label class="form-label">طلب الشراء</label>
          {{-- في التعديل الطلب مقفول — السطور مربوطة بسطوره، وتغييره هيبوّظ الحسبة --}}
          @if($mode==='edit' && $row->purchase_order_id)
            <input type="hidden" name="purchase_order_id" value="{{ $row->purchase_order_id }}">
          @endif
          <select name="purchase_order_id" class="form-select form-select-sm"
                  @if($mode==='edit' && $row->purchase_order_id) disabled @endif
                  @if($mode==='create')
                    onchange="if(this.value) location='{{ route('stock-additions.create') }}?purchase_order_id='+this.value+'&type='+(document.querySelector('[name=receipt_type]')?.value||'normal')"
                  @endif>
            <option value="">— بدون طلب —</option>
            @foreach($pos as $k=>$v)<option value="{{ $k }}" @selected(old('purchase_order_id',$row->purchase_order_id)==$k)>{{ $v }}</option>@endforeach
          </select>
          @if($mode==='create')<div class="hint">اختار الطلب والسطور هتتملى لوحدها</div>@endif
        </div>
        <div class="col-md-3"><label class="form-label">رقم الرسالة</label>
          <input name="consignment_no" class="form-control form-control-sm" value="{{ old('consignment_no',$row->consignment_no) }}"
                 placeholder="سيبه فاضي والسيستم هيولّده">
          <div class="hint">النمط: BUPL-090826-043-00</div></div>
        <div class="col-md-3"><label class="form-label">رقم إذن المورد</label>
          <input name="supplier_doc_no" class="form-control form-control-sm" value="{{ old('supplier_doc_no',$row->supplier_doc_no) }}"
                 placeholder="اختياري">
          <div class="hint">للعلم بس — عشان المطابقة مع المورد بعدين</div></div>
        <div class="col-md-3"><label class="form-label">نوع الاستلام</label>
          <select name="receipt_type" class="form-select form-select-sm"
                  @if($mode==='create')
                    onchange="var po=document.querySelector('[name=purchase_order_id]')?.value; location='{{ route('stock-additions.create') }}?type='+this.value+(po?'&purchase_order_id='+po:'')"
                  @endif>
            <option value="normal" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='normal')>عادي — يدخل الفحص</option>
            <option value="container" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='container')>حاويات — بدون فحص</option>
          </select></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>

      {{-- الباقي على الطلب: طالب 50 ووصل 30 ⇒ الباقي 20 هيوصل إمتى؟ --}}
      @if($po)
        <div class="alert alert-warning mt-3 mb-0 py-2" id="remainderBox">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <b><i class="bi bi-hourglass-split" aria-hidden="true"></i> الباقي على الطلب قبل الإذن ده:</b>
              <span class="num fw-bold">{{ rtrim(rtrim(number_format($remTot,3),'0'),'.') }} {{ $unitLbl }}</span>
              <div id="remainderTotal" class="mt-1"></div>
              <div class="hint">
                لو الإذن ده مش هيكمّل الطلب، حدد المورد هيوصّل الباقي إمتى — التاريخ ده بيفضل ظاهر
                في طابور الاستلام وعند الفحص لحد ما الطلب يقفل.
                (لو الإذن هيكمّل الطلب، سيب التاريخ فاضي والسيستم هيقفله لوحده.)
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">الباقي هيوصل إمتى؟</label>
              <input type="date" name="remainder_eta" class="form-control form-control-sm"
                     value="{{ old('remainder_eta', $row->remainder_eta?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">ملاحظة على الباقي</label>
              <input name="remainder_note" class="form-control form-control-sm"
                     value="{{ old('remainder_note', $row->remainder_note) }}"
                     placeholder="مثال: المورد قال الصبغة تحت التشغيل">
            </div>
          </div>
        </div>
      @endif
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف {{ $po ? '— من الطلب ' . $po->po_no . ' (البيانات ثابتة، اكتب المستلم بس)' : '' }}</span>
      @if($editable && !$po)
        <button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> سطر</button>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        @if($po)
          <thead><tr>
            <th style="width:35px">م</th>
            <th>الصنف</th>
            <th style="width:130px">اللون المطلوب</th>
            <th style="width:210px">اللون الواصل</th>
            <th style="width:85px">ع. أتواب</th>
            <th style="width:110px">المطلوب</th>
            <th style="width:120px">المستلم</th>
            <th style="width:120px">الباقي</th>
            <th style="width:40px"></th>
          </tr></thead>
        @else
          <thead><tr>
            <th style="width:35px">م</th><th style="width:95px">كود الصنف</th><th>اسم الصنف</th>
            <th style="width:160px">الخامة</th><th style="width:170px">اللون</th>
            <th style="width:90px">ع. أتواب</th><th style="width:110px">الكمية</th>
            <th style="width:80px">الوحدة</th><th style="width:40px"></th>
          </tr></thead>
        @endif
        <tbody id="lines" class="{{ $editable ? '' : 'lines-locked' }}">
          @foreach($lines as $i=>$l) @include('additions.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines))
            @if($po)
              <tr><td colspan="9">
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

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('stock-additions.submit',$row) }}" class="d-none">@csrf</form>
@endif

@if($row->consignment)
  <div class="card mt-3">
    <div class="card-header">الحوض اللي اتولّد</div>
    <div class="card-body d-flex gap-3 align-items-center flex-wrap">
      <a href="{{ route('consignments.show', $row->consignment) }}" class="fw-bold num">{{ $row->consignment->consignment_no }}</a>
      <span class="badge bg-{{ $row->consignment->status_color }}">{{ $row->consignment->status_name }}</span>
      <span class="hint">{{ number_format((float)$row->consignment->total_kg,1) }} كجم · {{ $row->consignment->rolls_count }} توب</span>
      @if($row->consignment->status === 'under_inspection')
        <a href="{{ route('inspections.create', ['consignment_id'=>$row->consignment->id]) }}"
           class="btn btn-sm btn-outline-plum ms-auto">الخطوة الجاية: تقرير فحص</a>
      @endif
    </div>
  </div>
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

  /* ── المستلم/الباقي لايف: طالب 50 طن وكتبت 30 ⇒ «باقي 20 طن» ──
     العرض بالكمية الاسمية، لكن قرار «اكتمل/محتاج تاريخ» بنفس مسطرة
     الإقفال بتاعة السيستم: الحد الأدنى المقبول (الكمية − نسبة الزيادة). */
  function calcRow(tr) {
    var out = tr.querySelector('.q-left');
    if (!out) return 0;
    var ordered  = parseFloat(tr.dataset.ordered)  || 0;
    var minQty   = parseFloat(tr.dataset.min)      || ordered;
    var received = parseFloat(tr.dataset.received) || 0;
    var now      = parseFloat(tr.querySelector('.q-recv')?.value) || 0;
    var left     = ordered - received - now;          // للعرض
    var leftMin  = Math.max(0, minQty - received - now);  // للإقفال
    var unit     = tr.dataset.unit || '';

    if (!now) { out.innerHTML = '<span class="hint">—</span>'; return Math.max(0, minQty - received); }
    if (leftMin > 0.0005) {
      out.innerHTML = '<span class="pill pill-warn">باقي ' + nf(left) + ' ' + unit + '</span>';
    } else if (left < -0.0005) {
      out.innerHTML = '<span class="pill pill-danger">زيادة ' + nf(-left) + ' ' + unit + '</span>';
    } else {
      out.innerHTML = '<span class="pill pill-ok">اكتمل ✓'
        + (left > 0.0005 ? ' <span class="hint">(الفرق ' + nf(left) + ' جوه نسبة الزيادة)</span>' : '')
        + '</span>';
    }
    return leftMin;
  }

  function calcAll() {
    var total = 0, unit = '';
    body.querySelectorAll('tr.po-row').forEach(function (tr) {
      total += calcRow(tr);
      unit = unit || tr.dataset.unit || '';
    });
    var box = document.getElementById('remainderTotal');
    if (box) {
      box.innerHTML = total > 0.0005
        ? 'الباقي بعد الإذن ده: <b class="num">' + nf(total) + ' ' + unit + '</b> — حدد هيوصل إمتى'
        : '<span class="text-success fw-bold">الإذن ده بيكمّل الطلب ✓ — سيب التاريخ فاضي</span>';
    }
  }

  /* الحسبة اللايف للمسودات بس — المستند المعتمد المستلم بتاعه اتحسب
     على الطلب فعلًا، فلو حسبناه تاني هيتخصم مرتين */
  if (EDITABLE) {
    body.addEventListener('input', function (e) {
      if (e.target.classList.contains('q-recv')) calcAll();
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
