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
  <div class="ro-box mb-3">
    <div class="ro-title">من الفحص — للقراءة · بيانات الرسالة ثابتة، سجّل القراءات بس</div>
    <div class="sum-grid">
      <div><div class="k">رقم الرسالة</div><a href="{{ route('consignments.show', $arrived) }}" class="v num">{{ $arrived->consignment_no }}</a></div>
      <div><div class="k">الصنف</div><div class="v">{{ $arrived->fabricType?->name ?? '—' }}</div></div>
      <div><div class="k">اللون</div><div class="v">{{ $arrived->color?->label ?? $arrived->color?->code ?? '—' }}</div></div>
      <div><div class="k">{{ $arrived->is_meter ? 'الأمتار' : 'الكمية (صافي)' }}</div><div class="v num">{{ $arrived->is_meter ? lv_num($arrived->total_kg, 2) . ' م' : lv_kg($arrived->total_kg) }}</div><div class="k">{{ (int) $arrived->rolls_count }} توب</div></div>
      @if($arrived->min_width_cm)
        <div><div class="k">أقل عرض من الفحص</div><div class="v num">{{ lv_num($arrived->min_width_cm, 2) }} سم</div></div>
      @endif
      <div><div class="k">المورد</div><div class="v">{{ $arrived->supplier?->name ?? '—' }}</div></div>
      <div><div class="k">إذن المورد</div><div class="v">@include('partials.supplier_doc', ['sa' => $arrived->sourceAddition()])</div></div>
    </div>
    @include('partials.packaging', ['c' => $arrived])
  </div>
@endif

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span><b>وزن البُنشر</b> هو الوزن المعياري للقماش (جرام لكل متر مربع) — يعني سُمك القماش.
  بيطلع وينزل جوه نفس التوب، عشان كده بناخد قراءات متعددة والسيستم بياخد المتوسط،
  والمتوسط ده هو اللي بتتحسب بيه أوزان الرِقّات واستهلاك القطعة.</span>
</div>

<form method="post" enctype="multipart/form-data"
      action="{{ $mode==='create' ? route('lab-reports.store') : route('lab-reports.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header">
      <span>{{ $mode==='create' ? 'تقرير معمل جديد' : 'تقرير معمل ' . $row->doc_no }}
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }}">{{ $row->status_label }}</span>@endif
      </span>
      <div class="d-flex gap-2 ms-auto">
        @if($mode==='edit')
          <a href="{{ route('lab-reports.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('lab-reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
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
          <div class="col-md-3"><label class="form-label">فني المعمل</label>
            <div class="form-control ro">{{ auth()->user()?->name }}</div></div>
          <div class="col-md-3"><label class="form-label">التاريخ</label>
            <div class="form-control ro num">{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}</div></div>
          <div class="col-md-3"><label class="form-label" for="paper_serial">المسلسل الورقي</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="مثال: 002192" inputmode="numeric"></div>
        </div>
      @else
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label req" for="doc_date">التاريخ</label>
            <input type="date" name="doc_date" id="doc_date" class="form-control w-date" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
          <div class="col-md-4"><label class="form-label req" for="consignment_id">الرسالة (الحوض)</label>
            <select name="consignment_id" id="consignment_id" class="form-select" required><option value="">—</option>
              @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label" for="paper_serial">المسلسل الورقي</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="مثال: 002192" inputmode="numeric"></div>
          <div class="col-md-3"><label class="form-label" for="fabric_type_id">اسم الخامة</label>
            <select name="fabric_type_id" id="fabric_type_id" class="form-select"><option value="">—</option>
              @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(old('fabric_type_id',$row->fabric_type_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label" for="color_id">اللون</label>
            <select name="color_id" id="color_id" class="form-select"><option value="">—</option>
              @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(old('color_id',$row->color_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label" for="supplier_id">اسم المورد</label>
            <select name="supplier_id" id="supplier_id" class="form-select"><option value="">—</option>
              @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label" for="technician_id">فني المعمل</label>
            <select name="technician_id" id="technician_id" class="form-select"><option value="">—</option>
              @foreach($technicians as $k=>$v)<option value="{{ $k }}" @selected(old('technician_id',$row->technician_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
            </select></div>
        </div>
      @endif

      @if($ft)
        <div class="ro-box mt-3">
          <div class="ro-title">مواصفة الخامة المعتمدة — {{ $ft->name }} — للقراءة</div>
          <div class="sum-grid">
            <div><div class="k">البنشر (جم/م²)</div><div class="v num">{{ $ft->spec_gsm_min ?? '—' }} – {{ $ft->spec_gsm_max ?? '—' }}</div></div>
            <div><div class="k">أقل عرض (سم)</div><div class="v num">{{ $ft->spec_width_min_cm ?? ($ft->spec_width_cm ?? '—') }}</div></div>
            <div><div class="k">أقصى انكماش طول</div><div class="v num">{{ $ft->max_shrink_len_pct ?? '—' }}%</div></div>
            <div><div class="k">أقصى انكماش عرض</div><div class="v num">{{ $ft->max_shrink_width_pct ?? '—' }}%</div></div>
          </div>
          <div class="hint mt-1">اللي يطلع برّه المواصفة بيتعلّم بعد الحفظ — وقابل للتفاوض مع المورد (خصم) أو يترفض</div>
        </div>
      @endif

      <div class="section-head"><h2>نسبة الانكماش</h2><span class="hint">لحد 4 عينات، سيب اللي ما اتاخدتش فاضية</span></div>
      <div class="row g-3">
        @for($s = 1; $s <= 4; $s++)
          <div class="col-md-6 col-xl-3">
            <label class="form-label">عينة {{ $s }}</label>
            <div class="input-group shrink-group">
              <span class="input-group-text">طول</span>
              <input type="number" step="0.01" min="0" name="s{{ $s }}_shrink_len_pct" class="form-control" value="{{ old("s{$s}_shrink_len_pct", $row->{"s{$s}_shrink_len_pct"}) }}" placeholder="مثال: 3" aria-label="عينة {{ $s }} — انكماش الطول %">
              <span class="input-group-text">%</span>
              <span class="input-group-text">عرض</span>
              <input type="number" step="0.01" min="0" name="s{{ $s }}_shrink_width_pct" class="form-control" value="{{ old("s{$s}_shrink_width_pct", $row->{"s{$s}_shrink_width_pct"}) }}" placeholder="مثال: 2" aria-label="عينة {{ $s }} — انكماش العرض %">
              <span class="input-group-text">%</span>
            </div>
          </div>
        @endfor
      </div>

      <div class="section-head"><h2>اللون</h2></div>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="form-check">
            <input type="hidden" name="color_match_ok" value="0">
            <input class="form-check-input" type="checkbox" name="color_match_ok" value="1" id="cm" @checked(old('color_match_ok',$row->color_match_ok))>
            <label class="form-check-label" for="cm">اللون مطابق للعينة المعتمدة</label>
          </div>
          {{-- الفواصل / اختلاف اللون — حقل صريح مش ملاحظة (الميتينج): بيأثر على الماركر والقص --}}
          <div class="form-check mt-1">
            <input type="hidden" name="has_color_break" value="0">
            <input class="form-check-input" type="checkbox" name="has_color_break" value="1" id="cb" @checked(old('has_color_break',$row->has_color_break))
                   onchange="document.getElementById('cbWrap').hidden = !this.checked">
            <label class="form-check-label fw-bold" for="cb">فيه فواصل / اختلاف لون داخل الرسالة</label>
            <div class="hint">مهم جدًا — بيوصل للتخطيط والباترونست والقص والمصنع</div>
          </div>
        </div>
        <div class="col-md-8" id="cbWrap" @if(!old('has_color_break',$row->has_color_break)) hidden @endif>
          <div class="row g-2">
            <div class="col-md-4"><label class="form-label" for="color_break_roll_no">رقم التوب / الأتواب اللي فيها الفاصل</label>
              <input name="color_break_roll_no" id="color_break_roll_no" class="form-control" value="{{ old('color_break_roll_no',$row->color_break_roll_no) }}" placeholder="مثال: 007، 012"></div>
            <div class="col-md-8"><label class="form-label" for="color_break_note">وصف الاختلاف</label>
              <input name="color_break_note" id="color_break_note" class="form-control" value="{{ old('color_break_note',$row->color_break_note) }}" placeholder="مثال: فاصل بعد 20 متر — الدرجة أغمق"></div>
          </div>
        </div>
        <div class="col-md-4"><label class="form-label" for="color_swatch">صورة عينة اللون</label>
          <input type="file" name="color_swatch" id="color_swatch" accept="image/*" class="form-control">
          @if($row->color_swatch_path)
            <a href="{{ asset('storage/'.$row->color_swatch_path) }}" target="_blank" class="hint">العينة المرفوعة</a>
          @endif
        </div>
        <div class="col-md-8"><label class="form-label" for="notes">ملاحظات <span class="hint">(اختياري)</span></label>
          <input name="notes" id="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="مثال: البنشر على الحد الأدنى للمواصفة"></div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      @php
        $labG = $row->relationLoaded('readings') ? $row->readings->pluck('gsm')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0) : collect();
        $shL  = collect([$row->s1_shrink_len_pct, $row->s2_shrink_len_pct, $row->s3_shrink_len_pct, $row->s4_shrink_len_pct])->filter(fn ($v) => $v !== null && $v !== '');
        $shW  = collect([$row->s1_shrink_width_pct, $row->s2_shrink_width_pct, $row->s3_shrink_width_pct, $row->s4_shrink_width_pct])->filter(fn ($v) => $v !== null && $v !== '');
      @endphp
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">متوسط البنشر</div><b class="num text-danger">{{ $row->avg_gsm ?? '—' }}</b>
            @if($row->avg_gsm !== null)
              @include('partials.fx', ['t' => 'متوسط البنشر', 'f' => $labG->count()
                  ? 'مجموع القراءات ' . lv_num($labG->sum(), 2) . ' ÷ ' . $labG->count() . ' قراءة = ' . lv_num($row->avg_gsm, 2) . ' جم/م²'
                  : 'متوسط قراءات الأتواب (أقل ' . lv_num($row->min_gsm, 2) . ' – أعلى ' . lv_num($row->max_gsm, 2) . ') = ' . lv_num($row->avg_gsm, 2) . ' جم/م²',
                'why' => $ft ? 'المواصفة ' . lv_num($ft->spec_gsm_min, 2) . ' – ' . lv_num($ft->spec_gsm_max, 2) . ' جم/م²' : null])
            @endif</div>
          <div class="col"><div class="hint">أقل بنشر</div><b class="num">{{ $row->min_gsm ?? '—' }}</b>
            @if($row->min_gsm !== null && $labG->count())
              @include('partials.fx', ['t' => 'أقل بنشر', 'f' => 'أقل قراءة بين ' . $labG->count() . ' قراءة = ' . lv_num($row->min_gsm, 2) . ' جم/م²'])
            @endif</div>
          <div class="col"><div class="hint">أعلى بنشر</div><b class="num">{{ $row->max_gsm ?? '—' }}</b>
            @if($row->max_gsm !== null && $labG->count())
              @include('partials.fx', ['t' => 'أعلى بنشر', 'f' => 'أعلى قراءة بين ' . $labG->count() . ' قراءة = ' . lv_num($row->max_gsm, 2) . ' جم/م²'])
            @endif</div>
          <div class="col"><div class="hint">متوسط انكماش الطول</div><b class="num">{{ $row->avg_shrink_len_pct ?? '—' }}%</b>
            @if($row->avg_shrink_len_pct !== null && $shL->count())
              @include('partials.fx', ['t' => 'متوسط انكماش الطول', 'f' => '(' . $shL->map(fn ($v) => lv_num($v, 2))->implode(' + ') . ') ÷ ' . $shL->count() . ' = ' . lv_num($row->avg_shrink_len_pct, 2) . '%', 'why' => $ft && $ft->max_shrink_len_pct !== null ? 'أقصى مسموح ' . lv_num($ft->max_shrink_len_pct, 2) . '%' : null])
            @endif</div>
          <div class="col"><div class="hint">متوسط انكماش العرض</div><b class="num">{{ $row->avg_shrink_width_pct ?? '—' }}%</b>
            @if($row->avg_shrink_width_pct !== null && $shW->count())
              @include('partials.fx', ['t' => 'متوسط انكماش العرض', 'f' => '(' . $shW->map(fn ($v) => lv_num($v, 2))->implode(' + ') . ') ÷ ' . $shW->count() . ' = ' . lv_num($row->avg_shrink_width_pct, 2) . '%', 'why' => $ft && $ft->max_shrink_width_pct !== null ? 'أقصى مسموح ' . lv_num($ft->max_shrink_width_pct, 2) . '%' : null])
            @endif</div>
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
          <div class="table-responsive mt-2">
            <table class="table table-sm no-min mb-0">
              <thead><tr><th>البند</th><th>المواصفة</th><th>الفعلي</th><th>الحالة</th></tr></thead>
              <tbody>
              @foreach($checks as $c)
                <tr><td>{{ $c['label'] }}</td><td class="num">{{ $c['spec'] }}</td><td class="num fw-bold">{{ $c['actual'] }}</td>
                  <td>@if($c['ok'] === null) — @elseif($c['ok']) <span class="badge bg-success">مطابق</span> @else <span class="badge bg-danger">خارج المواصفة</span> @endif</td></tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <span>قراءات وزن البنشر <span class="hint">— الأتواب اللي اتفحصت نزلت جاهزة، اكتب قراءة كل توب وضيف أتواب زيادة لو قست أكتر</span></span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum ms-auto" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> قراءة</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th style="width:35px">م</th><th style="width:160px">رقم التوب</th><th class="req" style="width:180px">وزن البنشر (جم/م²)</th><th></th><th style="width:44px"></th></tr></thead>
        <tbody id="lines">
          @foreach($readings as $i=>$l) @include('lab.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($readings)) @include('lab.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">متوسط البنشر = مجموع القراءات ÷ عددها — بيتحسب عند الحفظ، والسطر الفاضي بيتجاهل.</div>
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
    <div class="fa-end"><a href="{{ route('lab-reports.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
  </div>
  @endif
</form>

<style>
  /* عينات الانكماش: 6 خانات في input-group واحد — تتكسر على الموبايل */
  .shrink-group{flex-wrap:nowrap}
  .shrink-group .form-control{min-width:64px}
  @media (max-width:600px){
    .shrink-group{flex-wrap:wrap}
    .shrink-group>*{flex:1 1 30%}
  }
</style>

<template id="lineTpl">@include('lab.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($readings),1)])
@endsection
