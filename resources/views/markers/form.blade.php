@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'marker'])

@include('partials.approval_box')

<div class="note-box mb-3">
  <b>قاعدة حاكمة:</b> عرض التعشيقة لازم يكون أصغر من أو يساوي عرض القماش.
  لو أكبر، هنحرق الجنب ونرميه — وده فلوس بتترمي من كل رِقّة.
  الماركر ممكن يشيل أكتر من موديل ومقاس بنِسَب مختلفة.
</div>

<form method="post" enctype="multipart/form-data"
      action="{{ $mode==='create' ? route('markers.store') : route('markers.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'ماركر جديد' : 'ماركر ' . $row->code }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>@endif
        <a href="{{ route('markers.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">الاسم</label>
          <input name="name" class="form-control form-control-sm" value="{{ old('name',$row->name) }}"></div>
        <div class="col-md-2"><label class="form-label">طلب الماركر</label>
          <select name="marker_request_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($requests as $k=>$v)<option value="{{ $k }}" @selected(old('marker_request_id',$row->marker_request_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">المصنع</label>
          <select name="factory_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label req">عرض القماش (سم)</label>
          <input type="number" step="0.01" name="fabric_width_cm" class="form-control form-control-sm" value="{{ old('fabric_width_cm',$row->fabric_width_cm) }}" required></div>
        <div class="col-md-2"><label class="form-label">عرض التعشيقة (سم)</label>
          <input type="number" step="0.01" name="marker_width_cm" class="form-control form-control-sm" value="{{ old('marker_width_cm',$row->marker_width_cm) }}">
          <div class="hint">لازم ≤ عرض القماش</div></div>
        <div class="col-md-2"><label class="form-label req">طول الفرشة (متر)</label>
          <input type="number" step="0.001" name="spread_length_m" class="form-control form-control-sm" value="{{ old('spread_length_m',$row->spread_length_m) }}" required>
          <div class="hint">مثال: 3.07</div></div>
        <div class="col-md-2"><label class="form-label">قطع الفرشة</label>
          <input type="number" name="pieces_per_spread" class="form-control form-control-sm" value="{{ old('pieces_per_spread',$row->pieces_per_spread) }}">
          <div class="hint">سيبه فاضي = مجموع السطور</div></div>
        <div class="col-md-2"><label class="form-label">كفاءة التعشيق %</label>
          <input type="number" step="0.01" name="efficiency_pct" class="form-control form-control-sm" value="{{ old('efficiency_pct',$row->efficiency_pct) }}"></div>
        <div class="col-md-3"><label class="form-label">ملف الميني ماركر</label>
          <input type="file" name="marker_file" class="form-control form-control-sm">
          @if($row->file_path)<a href="{{ asset('storage/'.$row->file_path) }}" target="_blank" class="hint">الملف المرفوع</a>@endif</div>
        <div class="col-md-5"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الموديلات في الفرشة</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> موديل</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th style="width:35px">م</th><th>الموديل</th><th style="width:150px">المقاس</th>
          <th style="width:130px">عدد القطع في الفرشة</th><th>ملاحظات</th><th style="width:40px"></th></tr></thead>
        <tbody id="lines">
          @foreach($lines as $i=>$l) @include('markers.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines)) @include('markers.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('markers.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('markers.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($lines),1)])
@endsection
