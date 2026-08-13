@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.approval_box')

<div class="note-box mb-3">
  <b>ده الإفراج.</b> بيتعمل بعد ما الحوض يوصل بإذن إضافة، ويتفحص، وييجي له تقرير معمل.
  اعتماد الإذن ده هو اللي بيخلّي القماش متاح فعليًا لأوامر الشغل، وبيحدّث الكمية
  المستلمة في أمر الشراء.
</div>

<form method="post" action="{{ $mode==='create' ? route('goods-receipts.store') : route('goods-receipts.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'إذن استلام خام جديد' : 'إذن استلام ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('goods-receipts.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body">
      <fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
          <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="1001546"></div>
        <div class="col-md-3"><label class="form-label req">المخزن</label>
          <select name="warehouse_id" class="form-select form-select-sm" required>
            <option value="">— اختر —</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req">وارد من (المورد)</label>
          <select name="supplier_id" class="form-select form-select-sm" required>
            <option value="">— اختر —</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label">أمر المشتريات</label>
          <select name="purchase_order_id" class="form-select form-select-sm">
            <option value="">— بدون —</option>
            @foreach($pos as $k=>$v)<option value="{{ $k }}" @selected(old('purchase_order_id',$row->purchase_order_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-5"><label class="form-label req">الحوض المتفحص (الرسالة)</label>
          <select name="consignment_id" class="form-select form-select-sm" required>
            <option value="">— اختر حوض اتفحص —</option>
            @foreach($consignments as $c)
              <option value="{{ $c->id }}" @selected(old('consignment_id',$row->consignment_id)==$c->id)>
                {{ $c->consignment_no }} — {{ $c->color?->code }} · {{ number_format((float)$c->total_kg,0) }} كجم
                · {{ $c->rolls_count }} توب @if($c->min_width_cm)· أقل عرض {{ $c->min_width_cm }}@endif
              </option>
            @endforeach
          </select>
          @if(!count($consignments))
            <div class="hint text-danger">مفيش أحواض متفحصة. لازم إذن إضافة ⇐ تقرير فحص الأول.</div>
          @endif
        </div>
        <input type="hidden" name="stock_addition_id" value="{{ old('stock_addition_id',$row->stock_addition_id) }}">
        <input type="hidden" name="fabric_inspection_id" value="{{ old('fabric_inspection_id',$row->fabric_inspection_id) }}">
        <div class="col-md-3"><label class="form-label">مندوب المورد</label>
          <input name="supplier_rep" class="form-control form-control-sm" value="{{ old('supplier_rep',$row->supplier_rep) }}"></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
      </fieldset>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف المستلمة</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> سطر</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:90px">الكود</th><th>الصنف</th><th style="width:150px">اللون</th>
          <th style="width:75px">الوحدة</th><th style="width:80px">العرض</th><th style="width:80px">ع.أتواب</th>
          <th style="width:95px">الكمية</th><th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($lines as $i => $l) @include('receipts.line', ['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines)) @include('receipts.line', ['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle"></i>
      الأرقام هنا هي اللي طلعت من الجرد والفحص — مش اللي المورد قال عليها.
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm"
      onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send"></i> إرسال للاعتماد</button>
  @endif
</form>

@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('goods-receipts.submit',$row) }}" class="d-none">@csrf</form>
  @if($row->consignment)
    <a href="{{ route('consignments.show', $row->consignment) }}" class="btn btn-outline-plum btn-sm">
      <i class="bi bi-box-seam"></i> فتح الحوض {{ $row->consignment->consignment_no }}
    </a>
  @endif
@endif

<template id="lineTpl">@include('receipts.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@if($mode === 'edit')
  @include('partials.comments')
@endif

@include('partials.lines_js', ['startIndex' => max(count($lines),1)])
@endsection
