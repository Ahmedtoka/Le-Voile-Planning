@extends('layouts.app')
@section('purpose', 'وزن البنشر والانكماش ومطابقة اللون — بعد الحفظ الرسالة بتظهر في استلام الخام')
@section('content')
@php
  $readings = old('readings', $mode === 'create' ? ($preset ?? []) : ($row->readings?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
  $ft = $arrived?->fabricType ?? $row->fabricType;
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'lab'])

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
      @include('partials.supplier_doc', ['sa' => $arrived->sourceAddition()])
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

      @if($ft)
        <hr class="my-3">
        <b class="small">مواصفة الخامة المعتمدة — {{ $ft->name }}</b>
        <div class="d-flex flex-wrap gap-3 mt-1 small">
          <span>البنشر: <b class="num">{{ $ft->spec_gsm_min ?? '—' }} – {{ $ft->spec_gsm_max ?? '—' }}</b> جم/م²</span>
          <span>أقل عرض: <b class="num">{{ $ft->spec_width_min_cm ?? ($ft->spec_width_cm ?? '—') }}</b> سم</span>
          <span>أقصى انكماش: طول <b class="num">{{ $ft->max_shrink_len_pct ?? '—' }}%</b> · عرض <b class="num">{{ $ft->max_shrink_width_pct ?? '—' }}%</b></span>
          <span class="hint">اللي يطلع برّه المواصفة بيتعلّم بعد الحفظ — وقابل للتفاوض مع المورد (خصم) أو يترفض</span>
        </div>
      @endif

      <hr class="my-3">
      <div class="row g-3">
        <div class="col-12"><b class="small">نسبة الانكماش</b> <span class="hint">— لحد 4 عينات، سيب اللي ما اتاخدتش فاضية</span></div>
        @for($s = 1; $s <= 4; $s++)
          <div class="col-md-3"><label class="form-label">عينة {{ $s }} — طول %</label>
            <input type="number" step="0.01" name="s{{ $s }}_shrink_len_pct" class="form-control form-control-sm" value="{{ old("s{$s}_shrink_len_pct", $row->{"s{$s}_shrink_len_pct"}) }}"></div>
          <div class="col-md-3"><label class="form-label">عينة {{ $s }} — عرض %</label>
            <input type="number" step="0.01" name="s{{ $s }}_shrink_width_pct" class="form-control form-control-sm" value="{{ old("s{$s}_shrink_width_pct", $row->{"s{$s}_shrink_width_pct"}) }}"></div>
        @endfor

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

        {{-- الفواصل / اختلاف اللون — حقل صريح مش ملاحظة (الميتينج): بيأثر على الماركر والقص --}}
        <div class="col-12">
          <div class="p-2 rounded" style="border:1px solid var(--lv-line);background:var(--lv-offwhite)">
            <div class="form-check">
              <input type="hidden" name="has_color_break" value="0">
              <input class="form-check-input" type="checkbox" name="has_color_break" value="1" id="cb" @checked(old('has_color_break',$row->has_color_break))
                     onchange="document.getElementById('cbWrap').hidden = !this.checked">
              <label class="form-check-label fw-bold" for="cb">فيه فواصل / اختلاف لون داخل الرسالة</label>
              <span class="hint">— مهم جدًا: بيوصل للتخطيط والباترونست والقص والمصنع</span>
            </div>
            <div class="row g-2 mt-1" id="cbWrap" @if(!old('has_color_break',$row->has_color_break)) hidden @endif>
              <div class="col-md-4"><label class="form-label">رقم التوب / الأتواب اللي فيها الفاصل</label>
                <input name="color_break_roll_no" class="form-control form-control-sm" value="{{ old('color_break_roll_no',$row->color_break_roll_no) }}" placeholder="مثال: 007، 012"></div>
              <div class="col-md-8"><label class="form-label">وصف الاختلاف</label>
                <input name="color_break_note" class="form-control form-control-sm" value="{{ old('color_break_note',$row->color_break_note) }}" placeholder="مثال: فاصل بعد 20 متر — الدرجة أغمق"></div>
            </div>
          </div>
        </div>
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
        @if($row->gsm_out_of_spec)
          <div class="alert alert-warning py-2 mt-2 mb-0 small">متوسط البنشر {{ $row->avg_gsm }} خارج مواصفة الخامة — قابل للتفاوض مع المورد (خصم) أو يترفض.</div>
        @endif
        @if($row->has_color_break)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">فواصل / اختلاف لون: {{ $row->color_break_roll_no ? 'أتواب ' . $row->color_break_roll_no . ' · ' : '' }}{{ $row->color_break_note }}</div>
        @endif
        @php $checks = $row->specChecks(); @endphp
        @if($checks)
          <table class="table table-sm mt-2 mb-0" style="font-size:.8rem">
            <thead><tr><th>البند</th><th>المواصفة</th><th>الفعلي</th><th></th></tr></thead>
            <tbody>
            @foreach($checks as $c)
              <tr><td>{{ $c['label'] }}</td><td class="num">{{ $c['spec'] }}</td><td class="num fw-bold">{{ $c['actual'] }}</td>
                <td>@if($c['ok'] === null) — @elseif($c['ok']) <span class="badge bg-success">مطابق</span> @else <span class="badge bg-danger">خارج المواصفة</span> @endif</td></tr>
            @endforeach
            </tbody>
          </table>
        @endif
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>قراءات وزن البنشر <span class="hint">— الأتواب اللي اتفحصت نزلت جاهزة، اكتب قراءة كل توب وضيف أتواب زيادة لو قست أكتر</span></span>
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

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum">
      <i class="bi bi-save" aria-hidden="true"></i> حفظ المعمل
    </button>
    <span class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص التقرير على طول — الرسالة بتظهر في شاشة استلام الخام.
    </span>
  </div>
  @endif
</form>

<template id="lineTpl">@include('lab.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($readings),1)])
@endsection
