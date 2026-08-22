@extends('layouts.app')
@section('content')
@php
  $lines = old('lines', ($preset ?? null) ?: ($row->lines?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
@endphp

@include('partials.approval_box')

@if(($poInfo ?? null))
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2 d-flex gap-4 flex-wrap align-items-center">
      <span><b>طلب الشراء:</b> <span class="num">{{ $poInfo->po_no }}</span></span>
      <span><b>المورد:</b> {{ $poInfo->supplier?->name ?? '—' }}</span>
      <span><b>توريد متوقع:</b> <span class="num">{{ $poInfo->delivery_date?->format('Y-m-d') ?? '—' }}</span></span>
      <span><b>طريقة الدفع:</b> {{ $poInfo->payment_method ?? '—' }}</span>
      <span class="hint ms-auto">السطور اتملت بالكميات المتبقية — عدّل الفعلي اللي وصل وكمّل عدد الأتواب</span>
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
          <select name="purchase_order_id" class="form-select form-select-sm"
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
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> سطر</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:95px">كود الصنف</th><th>اسم الصنف</th>
          <th style="width:145px">الخامة</th><th style="width:135px">اللون</th><th style="width:125px">إكسسوار</th>
          <th style="width:80px">ع. أتواب</th><th style="width:95px">الكمية</th>
          <th style="width:75px">الوحدة</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($lines as $i=>$l) @include('additions.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines)) @include('additions.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send"></i> إرسال للاعتماد</button>
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
@if($mode === 'edit')
  @include('partials.comments')
@endif

@include('partials.lines_js',['startIndex'=>max(count($lines),1)])
<script>
  // انحراف اللون: أول ما اللون الفعلي يختلف عن المطلوب في الـPO يظهر سؤال القرار
  document.getElementById('lines')?.addEventListener('change', function (e) {
    if (!e.target.name || !/\[color_id\]$/.test(e.target.name)) return;
    var box = e.target.closest('td')?.querySelector('.color-mismatch');
    if (!box) return;
    var diff = e.target.value && e.target.value != box.dataset.pocolor;
    box.style.display = diff ? '' : 'none';
    if (!diff) { var s = box.querySelector('select'); if (s) s.value = ''; }
  });
</script>
@endsection
