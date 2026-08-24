@extends('layouts.app')
@section('content')
@php
  $rolls = old('rolls', ($preset ?? null) ?: ($row->rolls?->toArray() ?? []));
  $editable = $row->isEditable() || $mode==='create';
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'inspection'])

@include('partials.approval_box')

{{-- إيه اللي وصل بالظبط، وكام لسه باقي على الطلب --}}
@if(($arrived ?? null))
  @php
    $rem = 0; $unit = '';
    foreach (($arrivedPo?->lines ?? collect()) as $l) {
      $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty);
      if ($left > 0.0001) { $rem += $left; $unit = $unit ?: $l->unit; }
    }
  @endphp
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2 d-flex gap-4 flex-wrap align-items-center">
      <div>
        <div class="fw-bold num" style="font-size:1.15rem;color:var(--lv-brand-ink)">{{ $arrived->consignment_no }}</div>
        <div class="hint">{{ $arrived->fabricType?->name ?? '—' }} · {{ $arrived->color?->label ?? $arrived->color?->code ?? '—' }}</div>
      </div>
      <span><b>وصل في الرسالة دي:</b> <span class="num fw-bold">{{ rtrim(rtrim(number_format((float)$arrived->total_kg,2),'0'),'.') }} كجم</span>
        · <span class="num">{{ (int) $arrived->rolls_count }} توب</span></span>
      <span><b>المورد:</b> {{ $arrived->supplier?->name ?? '—' }}</span>
      @if($arrivedPo)
        <span><b>الطلب:</b> <span class="num">{{ $arrivedPo->po_no }}</span></span>
        @if($rem > 0)
          <span class="text-danger fw-bold">
            وصول جزئي — باقي {{ rtrim(rtrim(number_format($rem,3),'0'),'.') }} {{ $unit }}
            {{ $arrivedPo->remainder_eta ? '· متوقع ' . $arrivedPo->remainder_eta->format('Y-m-d') : '· الموعد مش محدد' }}
          </span>
        @else
          <span class="text-success fw-bold">الطلب اكتمل</span>
        @endif
      @endif
      <span class="hint ms-auto">افحص اللي وصل دلوقتي — الباقي هيتفحص برسالته لما يوصل</span>
    </div>
  </div>
@endif

<div class="note-box mb-3">
  التقرير ده بيعمل حاجتين:
  <b>① الجرد</b> — كام توب موجود فعلًا مقابل اللي جه في إذن الإضافة. أي فرق بيتسجّل ويتبعت تنبيه.
  <b>② القياس</b> — طول وعرض وعيوب كل توب مفحوص. السيستم بياخد <b>أقل عرض</b> (مش المتوسط)
  عشان يبني عليه الماركر — لأن الماركر لو طلع أوسع من القماش هنحرق الجنب ونرميه.
  <br>والفحص عيّنة مش 100%، فنسبة العيّنة بتفضل ظاهرة على كل رقم مبني عليه.
</div>

<form method="post" action="{{ $mode==='create' ? route('inspections.store') : route('inspections.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'تقرير فحص جديد' : 'تقرير فحص ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('inspections.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if(($arrived ?? null))
        {{-- الفحص على رسالة محددة: بياناتها ثابتة من إذن الإضافة — الفاحص بيجرد ويقيس بس --}}
        <input type="hidden" name="doc_date"       value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="consignment_id" value="{{ old('consignment_id', $row->consignment_id ?? $arrived->id) }}">
        <input type="hidden" name="fabric_type_id" value="{{ old('fabric_type_id', $row->fabric_type_id ?? $arrived->fabric_type_id) }}">
        <input type="hidden" name="color_id"       value="{{ old('color_id', $row->color_id ?? $arrived->color_id) }}">
        <input type="hidden" name="supplier_id"    value="{{ old('supplier_id', $row->supplier_id ?? $arrived->supplier_id) }}">
        <input type="hidden" name="inspector_id"   value="{{ old('inspector_id', $row->inspector_id ?? auth()->id()) }}">
        <input type="hidden" name="declared_rolls" value="{{ old('declared_rolls', $row->declared_rolls ?? $arrived->rolls_count) }}">

        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
            <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="04619"></div>
          <div class="col-md-2"><label class="form-label">أتواب حسب الإذن</label>
            <div class="form-control form-control-sm num" style="background:var(--lv-offwhite)">{{ (int) ($row->declared_rolls ?? $arrived->rolls_count) }}</div>
            <div class="hint">اللي جه في إذن الإضافة</div></div>
          <div class="col-md-2"><label class="form-label req">الأتواب المجرودة فعليًا</label>
            <input type="number" name="counted_rolls" class="form-control form-control-sm"
                   value="{{ old('counted_rolls',$row->counted_rolls) }}" required>
            <div class="hint">الجرد الحقيقي</div></div>
          <div class="col-md-2"><label class="form-label">الوزن المجرود (كجم)</label>
            <input type="number" step="0.001" name="counted_kg" class="form-control form-control-sm"
                   value="{{ old('counted_kg',$row->counted_kg) }}"></div>
          <div class="col-md-2"><label class="form-label req">النتيجة</label>
            <select name="result" class="form-select form-select-sm" required>
              @foreach($results as $k=>$v)<option value="{{ $k }}" @selected(old('result',$row->result)===$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-2"><label class="form-label">الفاحص</label>
            <div class="form-control form-control-sm" style="background:var(--lv-offwhite)">{{ auth()->user()?->name }}</div></div>
          <div class="col-md-12"><label class="form-label">ملاحظات</label>
            <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
        </div>
      @else
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
          <div class="col-md-2"><label class="form-label">أتواب حسب إذن الإضافة</label>
            <input type="number" name="declared_rolls" class="form-control form-control-sm"
                   value="{{ old('declared_rolls',$row->declared_rolls) }}" readonly style="background:#F8F4F1">
            <div class="hint">اللي المورد قال عليه</div></div>
          <div class="col-md-2"><label class="form-label req">الأتواب المجرودة فعليًا</label>
            <input type="number" name="counted_rolls" class="form-control form-control-sm"
                   value="{{ old('counted_rolls',$row->counted_rolls) }}" required>
            <div class="hint">الجرد الحقيقي</div></div>
          <div class="col-md-2"><label class="form-label">الوزن المجرود (كجم)</label>
            <input type="number" step="0.001" name="counted_kg" class="form-control form-control-sm"
                   value="{{ old('counted_kg',$row->counted_kg) }}"></div>
          <div class="col-md-2"><label class="form-label req">النتيجة</label>
            <select name="result" class="form-select form-select-sm" required>
              @foreach($results as $k=>$v)<option value="{{ $k }}" @selected(old('result',$row->result)===$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-6"><label class="form-label">ملاحظات</label>
            <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
        </div>
      @endif
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">الجرد</div>
            <b class="num {{ $row->rolls_variance != 0 ? 'text-danger' : '' }}">
              {{ $row->counted_rolls }}/{{ $row->declared_rolls }} — {{ $row->rolls_variance_label }}</b></div>
          <div class="col"><div class="hint">العيّنة</div><b class="num">{{ $row->sampled_rolls }}/{{ $row->total_rolls }} ({{ $row->sample_pct }}%)</b></div>
          <div class="col"><div class="hint">أقل عرض</div><b class="num text-danger">{{ $row->min_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">متوسط العرض</div><b class="num">{{ $row->avg_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">فرق العرض</div>
            <b class="num {{ $row->width_alert ? 'text-danger' : '' }}">{{ $row->width_spread_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">إجمالي الطول</div><b class="num">{{ number_format((float)$row->total_length_m,1) }} م</b></div>
          <div class="col"><div class="hint">نسبة العيوب</div><b class="num">{{ $row->defect_pct }}%</b></div>
        </div>
        @if($row->rolls_variance != 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">
            فرق في الجرد: المورد قال {{ $row->declared_rolls }} توب، والمجرود {{ $row->counted_rolls }}.
            راجع مع المورد قبل الإفراج.
          </div>
        @endif
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
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>الأتواب المفحوصة
        <span class="hint">— اكتب طول وعرض كل توب قسته، وامسح السطور اللي ما قستهاش</span>
      </span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> توب</button>@endif
    </div>
    @if($editable && ($preset ?? null))
      <div class="card-body py-2 pb-0">
        <div class="hint">
          السطور دي اتملت بأتواب الرسالة اللي وصلت ({{ count($preset) }} توب).
          عدد الأتواب اللي قستها فعلًا = عدد السطور اللي فيها طول وعرض — والسيستم بيحسب نسبة العيّنة منها لوحده.
        </div>
      </div>
    @endif
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

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ واحتساب</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('inspections.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('inspections.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>max(count($rolls),1)])
@endsection
