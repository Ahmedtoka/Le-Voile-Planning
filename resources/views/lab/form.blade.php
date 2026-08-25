@extends('layouts.app')
@section('content')
@php $readings = old('readings', $row->readings?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'lab'])

@include('partials.approval_box')

{{-- الرسالة اللي بيتعمل عليها التقرير — بياناتها ثابتة من الفحص --}}
@if(($arrived ?? null))
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2 d-flex gap-4 flex-wrap align-items-center">
      <div>
        <div class="fw-bold num" style="font-size:1.15rem;color:var(--lv-brand-ink)">{{ $arrived->consignment_no }}</div>
        <div class="hint">{{ $arrived->fabricType?->name ?? '—' }} · {{ $arrived->color?->label ?? $arrived->color?->code ?? '—' }}</div>
      </div>
      <span><b>الكمية:</b> <span class="num fw-bold">{{ rtrim(rtrim(number_format((float)$arrived->total_kg,2),'0'),'.') }} كجم</span>
        · <span class="num">{{ (int) $arrived->rolls_count }} توب</span></span>
      @if($arrived->min_width_cm)
        <span><b>أقل عرض من الفحص:</b> <span class="num fw-bold">{{ $arrived->min_width_cm }} سم</span></span>
      @endif
      <span><b>المورد:</b> {{ $arrived->supplier?->name ?? '—' }}</span>
      <span class="hint ms-auto">بيانات الرسالة ثابتة — سجّل القراءات بس</span>
    </div>
  </div>
@endif

<div class="note-box mb-3">
  <b>وزن البُنشر</b> هو الوزن المعياري للقماش (جرام لكل متر مربع) — يعني سُمك القماش.
  بيطلع وينزل جوه نفس التوب، عشان كده بناخد قراءات متعددة والسيستم بياخد المتوسط،
  والمتوسط ده هو اللي بتتحسب بيه أوزان الرِقّات واستهلاك القطعة.
</div>

<form method="post" enctype="multipart/form-data"
      action="{{ $mode==='create' ? route('lab-reports.store') : route('lab-reports.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'تقرير معمل جديد' : 'تقرير معمل ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('lab-reports.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('lab-reports.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if(($arrived ?? null))
        {{-- كل بيانات الرسالة جاية من الفحص — الفني بيسجل القراءات بس --}}
        <input type="hidden" name="doc_date"       value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="consignment_id" value="{{ old('consignment_id', $row->consignment_id ?? $arrived->id) }}">
        <input type="hidden" name="fabric_type_id" value="{{ old('fabric_type_id', $row->fabric_type_id ?? $arrived->fabric_type_id) }}">
        <input type="hidden" name="color_id"       value="{{ old('color_id', $row->color_id ?? $arrived->color_id) }}">
        <input type="hidden" name="supplier_id"    value="{{ old('supplier_id', $row->supplier_id ?? $arrived->supplier_id) }}">
        <input type="hidden" name="technician_id"  value="{{ old('technician_id', $row->technician_id ?? auth()->id()) }}">

        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
            <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="002192"></div>
          <div class="col-md-2"><label class="form-label">فني المعمل</label>
            <div class="form-control form-control-sm" style="background:var(--lv-offwhite)">{{ auth()->user()?->name }}</div></div>
        </div>
      @else
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label req">التاريخ</label>
            <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
          <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
            <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="002192"></div>
          <div class="col-md-3"><label class="form-label req">الرسالة (الحوض)</label>
            <select name="consignment_id" class="form-select form-select-sm" required><option value="">—</option>
              @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-2"><label class="form-label">اسم الخامة</label>
            <select name="fabric_type_id" class="form-select form-select-sm"><option value="">—</option>
              @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(old('fabric_type_id',$row->fabric_type_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label">اللون</label>
            <select name="color_id" class="form-select form-select-sm"><option value="">—</option>
              @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(old('color_id',$row->color_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label">اسم المورد</label>
            <select name="supplier_id" class="form-select form-select-sm"><option value="">—</option>
              @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label">فني المعمل</label>
            <select name="technician_id" class="form-select form-select-sm"><option value="">—</option>
              @foreach($technicians as $k=>$v)<option value="{{ $k }}" @selected(old('technician_id',$row->technician_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
            </select></div>
        </div>
      @endif

      <hr class="my-3">
      <div class="row g-3">
        <div class="col-12"><b class="small">نسبة الانكماش</b></div>
        <div class="col-md-3"><label class="form-label">عينة 1 — طول %</label>
          <input type="number" step="0.01" name="s1_shrink_len_pct" class="form-control form-control-sm" value="{{ old('s1_shrink_len_pct',$row->s1_shrink_len_pct) }}"></div>
        <div class="col-md-3"><label class="form-label">عينة 1 — عرض %</label>
          <input type="number" step="0.01" name="s1_shrink_width_pct" class="form-control form-control-sm" value="{{ old('s1_shrink_width_pct',$row->s1_shrink_width_pct) }}"></div>
        <div class="col-md-3"><label class="form-label">عينة 2 — طول %</label>
          <input type="number" step="0.01" name="s2_shrink_len_pct" class="form-control form-control-sm" value="{{ old('s2_shrink_len_pct',$row->s2_shrink_len_pct) }}"></div>
        <div class="col-md-3"><label class="form-label">عينة 2 — عرض %</label>
          <input type="number" step="0.01" name="s2_shrink_width_pct" class="form-control form-control-sm" value="{{ old('s2_shrink_width_pct',$row->s2_shrink_width_pct) }}"></div>

        <div class="col-md-3">
          <div class="form-check mt-4">
            <input type="hidden" name="color_match_ok" value="0">
            <input class="form-check-input" type="checkbox" name="color_match_ok" value="1" id="cm" @checked(old('color_match_ok',$row->color_match_ok))>
            <label class="form-check-label" for="cm">اللون مطابق للعينة المعتمدة</label>
          </div>
        </div>
        <div class="col-md-4"><label class="form-label">صورة عينة اللون</label>
          <input type="file" name="color_swatch" accept="image/*" class="form-control form-control-sm">
          @if($row->color_swatch_path)
            <a href="{{ asset('storage/'.$row->color_swatch_path) }}" target="_blank" class="hint">العينة المرفوعة</a>
          @endif
        </div>
        <div class="col-md-5"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">متوسط البنشر</div><b class="num text-danger">{{ $row->avg_gsm ?? '—' }}</b></div>
          <div class="col"><div class="hint">أقل بنشر</div><b class="num">{{ $row->min_gsm ?? '—' }}</b></div>
          <div class="col"><div class="hint">أعلى بنشر</div><b class="num">{{ $row->max_gsm ?? '—' }}</b></div>
          <div class="col"><div class="hint">متوسط انكماش الطول</div><b class="num">{{ $row->avg_shrink_len_pct ?? '—' }}%</b></div>
          <div class="col"><div class="hint">متوسط انكماش العرض</div><b class="num">{{ $row->avg_shrink_width_pct ?? '—' }}%</b></div>
        </div>
        @if($row->shrink_out_of_spec)
          <div class="alert alert-warning py-2 mt-2 mb-0 small">الانكماش خارج مواصفة الخامة المعتمدة.</div>
        @endif
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>قراءات وزن البنشر</span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> قراءة</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th style="width:35px">م</th><th style="width:140px">رقم التوب</th><th style="width:160px">وزن البنشر (جم/م²)</th><th></th><th style="width:40px"></th></tr></thead>
        <tbody id="lines">
          @foreach($readings as $i=>$l) @include('lab.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($readings)) @include('lab.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ واحتساب</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('lab-reports.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('lab.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($readings),1)])
@endsection
