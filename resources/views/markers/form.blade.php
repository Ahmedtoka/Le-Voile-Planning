@extends('layouts.app')
@section('purpose', 'الباترونست: طول الفرشة وعدد القطع والموديلات على عرض القماش')
@section('content')
@php
  $lines    = old('lines', $mode === 'create' ? ($preset ?? []) : ($row->lines?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
  $mrId     = old('marker_request_id', $row->marker_request_id);
  $mr       = $mrId ? \App\Models\MarkerRequest::with(['consignment.fabricType', 'consignment.color', 'models', 'creator', 'preparationRequest'])->find($mrId) : null;
  $cn       = $mr?->consignment;
  $widthCm  = $mr?->fabric_width_cm ?? $row->fabric_width_cm;
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'marker'])

<form method="post" enctype="multipart/form-data" id="mkForm"
      action="{{ $mode==='create' ? route('markers.store') : route('markers.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ① اللي جاي من طلب الماركر — للقراءة، كله لينكات --}}
  @if($mr)
    <input type="hidden" name="marker_request_id" value="{{ $mr->id }}">
    <input type="hidden" name="fabric_width_cm" value="{{ old('fabric_width_cm', $row->fabric_width_cm ?: $mr->fabric_width_cm) }}">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>طلب الماركر <a href="{{ route('markers.requests') }}" class="fw-bold num">{{ $mr->doc_no }}</a>
          <span class="hint">— ده اللي التخطيط طالبه. مفيش حاجة تتعدل هنا</span></span>
        <div class="d-flex gap-2">
          @if($mode==='edit')<span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>@endif
          <a href="{{ route('markers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
        </div>
      </div>
      <div class="card-body">
        <div class="ro-box">
          <div class="ro-title">من طلب الماركر — للقراءة</div>
          <div class="sum-grid">
            <div><div class="k">الرسالة</div>@if($cn)<a href="{{ route('consignments.show', $cn) }}" class="v num">{{ $cn->consignment_no }}</a>@else <div class="v">—</div> @endif</div>
            <div><div class="k">الصنف · اللون</div><div class="v">{{ $cn?->fabricType?->name ?? '—' }} · {{ $cn?->color?->label ?? $cn?->color?->code ?? '—' }}</div></div>
            <div><div class="k">عرض التشغيل</div><div class="v num text-danger">{{ lv_num($mr->fabric_width_cm, 2) }} سم</div>
              @if($cn?->min_width_cm && (float) $cn->min_width_cm > (float) $mr->fabric_width_cm)<div class="k">أقل عرض مقاس {{ lv_num($cn->min_width_cm, 2) }} − تأمين {{ lv_num((float) $cn->min_width_cm - (float) $mr->fabric_width_cm, 2) }} سم</div>@endif</div>
            <div><div class="k">البنشر (من المعمل)</div><div class="v num">{{ $mr->avg_gsm ? lv_num($mr->avg_gsm, 2) . ' جم/م²' : '—' }}</div></div>
            <div><div class="k">المتاح على الرسالة</div><div class="v num">{{ $cn ? lv_kg($cn->remaining_kg, false) : '—' }}</div></div>
            <div><div class="k">نسخ مطلوبة</div><div class="v num">{{ (int) $mr->copies }}</div></div>
            <div><div class="k">طلبه</div><div class="v">{{ $mr->creator?->name ?? '—' }}</div><div class="k num">{{ $mr->doc_date?->format('Y-m-d') }}{{ $mr->needed_by ? ' · مطلوب في ' . $mr->needed_by->format('Y-m-d') : '' }}</div></div>
            <div><div class="k">طلب التحضير</div><div class="v num">{{ $mr->preparationRequest?->doc_no ?? '—' }}</div></div>
          </div>
          <table class="table table-sm kv mb-0 mt-2">
            <tr><th>الموديلات المطلوبة في الفرشة</th><td>{{ $mr->models_label ?: '—' }}</td></tr>
            @if($mr->notes)<tr><th>ملاحظات التخطيط</th><td>{{ $mr->notes }}</td></tr>@endif
          </table>
        </div>
        @if($cn?->has_color_break)
          <div class="alert alert-danger py-2 mt-2 mb-0"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> الرسالة فيها فواصل / اختلاف لون: {{ $cn->color_break_note }} — خد بالك في التعشيق.</div>
        @endif
      </div>
    </div>
  @else
    <div class="note-box mb-3">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span><b>قاعدة حاكمة:</b> عرض التعشيقة لازم يكون أصغر من أو يساوي عرض القماش. لو أكبر، هنحرق الجنب ونرميه.</span>
    </div>
  @endif

  {{-- ② اللي الباترونست بيدخله — بالترتيب --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'بيانات الماركر' : 'الماركر ' . $row->code }}</span>
      @if(!$mr)
        <div class="d-flex gap-2">
          @if($mode==='edit')<span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>@endif
          <a href="{{ route('markers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
        </div>
      @endif
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        @if(!$mr)
          <div class="col-md-3"><label class="form-label">طلب الماركر</label>
            <select name="marker_request_id" class="form-select" onchange="if(this.value) location='{{ route('markers.create') }}?request_id='+this.value"><option value="">— بدون طلب —</option>
              @foreach($requests as $k=>$v)<option value="{{ $k }}" @selected($mrId==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req">عرض القماش</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" name="fabric_width_cm" class="form-control" value="{{ old('fabric_width_cm',$row->fabric_width_cm) }}" required placeholder="مثال: 172">
              <span class="input-group-text">سم</span>
            </div>
            <div class="hint">أقل عرض من الفحص</div></div>
        @endif

        <div class="col-md-3"><label class="form-label req">طول الفرشة</label>
          <div class="input-group">
            <input type="number" step="0.001" min="0" name="spread_length_m" class="form-control" value="{{ old('spread_length_m',$row->spread_length_m) }}" required placeholder="مثال: 3.07">
            <span class="input-group-text">م</span>
          </div>
          <div class="hint">الطول اللي المصنع هيفرش عليه</div></div>
        <div class="col-md-3"><label class="form-label">عرض التعشيقة</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" name="marker_width_cm" class="form-control" value="{{ old('marker_width_cm',$row->marker_width_cm) }}" placeholder="≤ {{ lv_num($widthCm, 2) }}">
            <span class="input-group-text">سم</span>
          </div>
          <div class="hint">لازم ≤ عرض القماش {{ $widthCm ? lv_num($widthCm, 2) . ' سم' : '' }}</div></div>
        <div class="col-md-3"><label class="form-label">كفاءة التعشيق</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="efficiency_pct" class="form-control" value="{{ old('efficiency_pct',$row->efficiency_pct) }}" placeholder="مثال: 85">
            <span class="input-group-text">%</span>
          </div>
          <div class="hint">نسبة القماش المستغل في القطع — الباقي فاقد</div></div>
        <div class="col-md-3"><label class="form-label">إجمالي قطع الفرشة</label>
          <div class="input-group">
            <input type="number" min="0" name="pieces_per_spread" class="form-control" value="{{ old('pieces_per_spread',$row->pieces_per_spread) }}" placeholder="تلقائي من الجدول">
            <span class="input-group-text">قطعة</span>
          </div>
          <div class="hint">سيبه فاضي = مجموع الموديلات تحت</div></div>

        <div class="col-md-4"><label class="form-label">اسم الماركر</label>
          <input name="name" class="form-control" value="{{ old('name',$row->name) }}" placeholder="اختياري"></div>
        <div class="col-md-8"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="اختياري"></div>
      </div>
    </fieldset></div>
  </div>

  {{-- ③ الموديلات في الفرشة --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الموديلات في الفرشة <span class="hint">— كام قطعة من كل موديل/مقاس في الرِقّة الواحدة</span></span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> موديل</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th style="width:35px">م</th><th class="req">الموديل</th><th style="width:150px">المقاس</th>
          <th class="req" style="width:140px">عدد القطع في الفرشة</th><th>ملاحظات</th><th style="width:44px"><span class="visually-hidden">حذف</span></th></tr></thead>
        <tbody id="lines">
          @foreach($lines as $i=>$l) @include('markers.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines)) @include('markers.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- ④ ملف الماركر — لوحده --}}
  <div class="card mb-3">
    <div class="card-header">ملف الميني ماركر <span class="hint">— صورة أو PDF للتعشيقة</span></div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3 align-items-center">
        <div class="col-md-6">
          <input type="file" name="marker_file" class="form-control" accept="image/*,.pdf" aria-label="ملف الماركر">
        </div>
        <div class="col-md-6">
          @if($row->file_path)
            <a href="{{ asset('storage/'.$row->file_path) }}" target="_blank" class="btn btn-sm btn-outline-plum"><i class="bi bi-paperclip" aria-hidden="true"></i> الملف المرفوع</a>
          @else <span class="hint">مفيش ملف مرفوع لسه</span> @endif
        </div>
      </div>
    </fieldset></div>
  </div>

  @if($editable || ($mode==='edit' && $row->isEditable()))
  <div class="form-actions">
    @if($editable)<button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
    @if($mode==='edit' && $row->isEditable())
      <button type="button" class="btn btn-success" onclick="if(confirm('الماركر هيبقى متاح لأوامر الشغل. متأكد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> اعتمد الماركر للتشغيل</button>
    @endif
    <div class="fa-end">
      <a href="{{ route('markers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    </div>
  </div>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('markers.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('markers.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($lines),1)])
@endsection
