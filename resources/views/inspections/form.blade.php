@extends('layouts.app')
@section('content')
@php $rolls = old('rolls', $row->rolls?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.approval_box')

<div class="note-box mb-3">
  <b>الفحص عيّنة مش 100%.</b>
  السيستم بياخد من التقرير ده <b>أقل عرض</b> (مش المتوسط) عشان يبني عليه الماركر —
  لأن الماركر لو طلع أوسع من القماش هنحرق الجنب ونرميه. سجّل إجمالي أتواب الحوض صح
  عشان نسبة العيّنة تطلع مظبوطة.
</div>

<form method="post" action="{{ $mode==='create' ? route('inspections.store') : route('inspections.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'تقرير فحص جديد' : 'تقرير فحص ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('inspections.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i></a>
        @endif
        <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
          <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="04619"></div>
        <div class="col-md-3"><label class="form-label req">الحوض (الرسالة)</label>
          <select name="consignment_id" class="form-select form-select-sm" required><option value="">—</option>
            @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label">الصنف</label>
          <select name="fabric_type_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(old('fabric_type_id',$row->fabric_type_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">كود اللون</label>
          <select name="color_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(old('color_id',$row->color_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">اسم المورد</label>
          <select name="supplier_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">الفاحص</label>
          <select name="inspector_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($inspectors as $k=>$v)<option value="{{ $k }}" @selected(old('inspector_id',$row->inspector_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label req">إجمالي أتواب الحوض</label>
          <input type="number" name="total_rolls" class="form-control form-control-sm" value="{{ old('total_rolls',$row->total_rolls) }}" required>
          <div class="hint">مش المفحوص — الإجمالي</div></div>
        <div class="col-md-2"><label class="form-label req">النتيجة</label>
          <select name="result" class="form-select form-select-sm" required>
            @foreach($results as $k=>$v)<option value="{{ $k }}" @selected(old('result',$row->result)===$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">العيّنة</div><b class="num">{{ $row->sampled_rolls }}/{{ $row->total_rolls }} ({{ $row->sample_pct }}%)</b></div>
          <div class="col"><div class="hint">أقل عرض</div><b class="num text-danger">{{ $row->min_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">متوسط العرض</div><b class="num">{{ $row->avg_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">فرق العرض</div>
            <b class="num {{ $row->width_alert ? 'text-danger' : '' }}">{{ $row->width_spread_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">إجمالي الطول</div><b class="num">{{ number_format((float)$row->total_length_m,1) }} م</b></div>
          <div class="col"><div class="hint">نسبة العيوب</div><b class="num">{{ $row->defect_pct }}%</b></div>
        </div>
        @if($row->width_alert)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">
            فرق العرض بين الأتواب {{ $row->width_spread_cm }} سم — مش طبيعي لقماش حوض واحد.
            المفروض القماش ده يترفض من برّه.
          </div>
        @endif
        @if($row->sample_too_small)
          <div class="alert alert-warning py-2 mt-2 mb-0 small">
            العيّنة {{ $row->sample_pct }}% بس من الأتواب — أي أرقام مبنية عليها تقديرية.
          </div>
        @endif
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأتواب المفحوصة</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> توب</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:90px">رقم التوب</th><th style="width:100px">الطول (م)</th>
          <th style="width:100px">العرض (سم)</th><th style="width:100px">البنشر</th><th style="width:90px">عدد العيوب</th>
          <th>وصف العيب</th><th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($rolls as $i=>$l) @include('inspections.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($rolls)) @include('inspections.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ واحتساب</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('inspections.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('inspections.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($rolls),1)])
@endsection
