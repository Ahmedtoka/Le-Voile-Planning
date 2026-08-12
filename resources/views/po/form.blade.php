@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.approval_box')

<form method="post" action="{{ $mode==='create' ? route('purchase-orders.store') : route('purchase-orders.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'طلب شراء جديد' : 'طلب شراء ' . $row->po_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('purchase-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i> طباعة</a>
        @endif
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body">
      <fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="po_date" class="form-control form-control-sm"
                 value="{{ old('po_date', $row->po_date?->format('Y-m-d') ?? $row->po_date) }}" required></div>
        <div class="col-md-3"><label class="form-label">المورد</label>
          <select name="supplier_id" class="form-select form-select-sm">
            <option value="">— اختر —</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">اسم الموظف</label>
          <select name="employee_id" class="form-select form-select-sm">
            <option value="">— اختر —</option>
            @foreach($employees as $k=>$v)<option value="{{ $k }}" @selected(old('employee_id',$row->employee_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label">تاريخ التوريد</label>
          <input type="date" name="delivery_date" class="form-control form-control-sm"
                 value="{{ old('delivery_date', $row->delivery_date?->format('Y-m-d')) }}"></div>
        <div class="col-md-2"><label class="form-label">مكان التسليم</label>
          <input name="delivery_place" class="form-control form-control-sm" value="{{ old('delivery_place',$row->delivery_place) }}"></div>
        <div class="col-md-2"><label class="form-label">طريقة الدفع</label>
          <input name="payment_method" class="form-control form-control-sm" value="{{ old('payment_method',$row->payment_method) }}"></div>
        <div class="col-md-2"><label class="form-label">الخصم %</label>
          <input type="number" step="0.01" name="discount_pct" class="form-control form-control-sm" value="{{ old('discount_pct',$row->discount_pct ?? 0) }}"></div>
        <div class="col-md-2"><label class="form-label">الضريبة %</label>
          <input type="number" step="0.01" name="tax_pct" class="form-control form-control-sm" value="{{ old('tax_pct',$row->tax_pct ?? 0) }}"></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
      </fieldset>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> سطر</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:160px">كود اللون</th><th>اسم الصنف</th>
          <th style="width:100px">الكمية</th><th style="width:80px">الوحدة</th><th style="width:100px">سعر الوحدة</th>
          <th style="width:95px">نسبة الزيادة %</th><th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($lines as $i => $l)
            @include('po.line', ['i'=>$i, 'l'=>$l])
          @endforeach
          @if(!count($lines))
            @include('po.line', ['i'=>0, 'l'=>[]])
          @endif
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle"></i>
      نسبة الزيادة المسموح بها بتتقارن أوتوماتيك عند الاستلام — لو المورد ورّد فوقها، السيستم هيمنع الإذن.
    </div>
  </div>

  @if($editable)
    <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>
  @endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm"
            onclick="if(confirm('إرسال للاعتماد؟ المستند هيتقفل عن التعديل.')) document.getElementById('submitForm').submit()">
      <i class="bi bi-send"></i> إرسال للاعتماد
    </button>
  @endif
</form>

@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('purchase-orders.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">
  @include('po.line', ['i'=>'__IDX__', 'l'=>[], 'tpl'=>true])
</template>

@include('partials.lines_js', ['startIndex' => max(count($lines), 1)])
@endsection
