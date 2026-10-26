@extends('layouts.app')
@section('purpose', 'صرف الخامة من المخزن للمصنع مقابل أمر الشغل — الصرف الجزئي مسموح')
@section('content')
@php $lines = old('lines', $preset ?: []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'issue'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    الورقة الواحدة بتصرف لأكتر من أمر شغل وأكتر من خامة. لو اخترت أمر شغل في السطر،
    السيستم بيربط الصرف بخامة الأمر عشان يعرف اتصرف منها كام والباقي كام.
    <b>اعتماد الإذن بيخصم فعليًا من رصيد الحوض.</b>
  </span>
</div>

<form method="post" action="{{ $mode==='create' ? route('material-issues.store') : route('material-issues.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $mode==='create' ? 'إذن صرف خام جديد' : 'إذن صرف ' . $row->doc_no }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('material-issues.print',$row) }}" target="_blank"
             class="btn btn-sm btn-outline-secondary btn-icon" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('material-issues.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
      </div>
    </div>

    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control"
                 value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-3"><label class="form-label req">المخزن</label>
          <select name="warehouse_id" class="form-select" required>
            @foreach($warehouses as $k=>$v)
              <option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label">المصنع</label>
          <select name="factory_id" class="form-select"><option value="">—</option>
            @foreach($factories as $k=>$v)
              <option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>
            @endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req">منصرف إلى</label>
          <input name="issued_to" class="form-control"
                 value="{{ old('issued_to',$row->issued_to) }}" placeholder="مثال: أ/ الخطيب" required></div>
        <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
          <input name="paper_serial" class="form-control"
                 value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="مثال: 1303774"></div>
        <div class="col-md-3"><label class="form-label">المستلم</label>
          <input name="receiver_name" class="form-control" value="{{ old('receiver_name',$row->receiver_name) }}" placeholder="اسم اللي استلم من المصنع"></div>
        <div class="col-md-9"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="اختياري"></div>
      </div>
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف المنصرفة</span>
      @if($editable)
        <button type="button" class="btn btn-sm btn-outline-plum" onclick="LV.add('lineTpl','lines')">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> سطر
        </button>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:190px">أمر الشغل</th><th style="width:110px">الكود</th>
          <th class="req" style="width:270px">رقم الرسالة</th><th style="width:96px">الوحدة</th>
          <th style="width:90px">العرض (سم)</th><th style="width:90px">عدد الأتواب</th>
          <th style="width:120px">القائم على الميزان</th>
          <th class="req" style="width:110px">الصافي / الكمية</th><th>ملاحظات</th><th style="width:44px"><span class="visually-hidden">حذف</span></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($lines as $i => $l) @include('issues.line', ['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines)) @include('issues.line', ['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      بالكيلو: اكتب اللي طلع على الميزان (قائم) وعدد الأتواب — السيستم يخصم الكيس والماسورة بأوزان الرسالة نفسها ويطلّع <b>الصافي</b>، وده اللي بيتخصم من الرصيد وبيتحسب عليه الاستهلاك.
      بالمتر: اكتب الأمتار على طول. السيستم بيمنع الإرسال لو الكمية أكبر من المفرج عنه في الحوض.
    </div>
  </div>

  @if($editable || ($mode==='edit' && $row->isEditable()))
  <div class="form-actions">
  @if($editable)<button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success"
            onclick="if(confirm('الخامة هتتخصم من المخزن وتتسجل على أمر الشغل. متأكد؟')) document.getElementById('submitForm').submit()">
      <i class="bi bi-send" aria-hidden="true"></i> اصرف للمصنع
    </button>
  @endif
  <div class="fa-end">
    <a href="{{ route('material-issues.index') }}" class="btn btn-outline-secondary">إلغاء</a>
  </div>
  </div>
  @endif
</form>

@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('material-issues.submit',$row) }}" class="d-none">@csrf</form>
@endif

<template id="lineTpl">@include('issues.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js', ['startIndex' => max(count($lines), 1)])

@push('scripts')
<script>
window.LVI = {
  /* الصافي = القائم − (كيس + ماسورة) × الأتواب ÷ 1000 — بأوزان الرسالة المسجلة في إذن الإضافة */
  net(el) {
    const tr = el.closest('tr'), o = tr.querySelector('.i-cn')?.selectedOptions[0];
    const gross = Number(tr.querySelector('.i-gross')?.value) || 0, rolls = Number(tr.querySelector('.i-rolls')?.value) || 0;
    const netIn = tr.querySelector('.i-net'), pk = tr.querySelector('.i-pk'), gIn = tr.querySelector('.i-gross');
    if (!o || !o.value) return;
    const cpk = tr.querySelector('.i-cpk'); if (cpk) cpk.textContent = o.dataset.pk || '';
    const meter = o.dataset.meter === '1';
    if (gIn) { gIn.disabled = meter; gIn.placeholder = meter ? 'بالمتر — مفيش وزن' : 'على الميزان'; }
    if (meter) { if (pk) pk.textContent = ''; return; }
    if (!gross) { if (pk) pk.textContent = ''; return; }
    const pack = Math.round((Number(o.dataset.bag) + Number(o.dataset.tube)) * rolls) / 1000;
    const n = Math.max(0, Math.round((gross - pack) * 1000) / 1000);
    if (netIn) netIn.value = n;
    if (pk) pk.textContent = pack > 0 ? '− ' + pack + ' كيس وماسورة' : 'الرسالة من غير أوزان كيس/ماسورة';
  },
  manual(el) {
    /* كتب الصافي بإيده؟ القائم بيتشال عشان ما يبقاش في رقمين متناقضين */
    const tr = el.closest('tr'), g = tr.querySelector('.i-gross');
    if (g && g.value && Math.abs(Number(g.value) - Number(el.value)) > 0.0005 && document.activeElement === el) { g.value = ''; const pk = tr.querySelector('.i-pk'); if (pk) pk.textContent = 'صافي مكتوب يدوي'; }
  },
  onWo(sel) {
    /* لما تختار أمر شغل، مفيش ربط تلقائي بخامة معينة —
       السيستم بيربط بالرسالة اللي تختارها في نفس السطر. */
    const row = sel.closest('tr');
    const hidden = row.querySelector('input[name$="[work_order_fabric_id]"]');
    if (hidden && !sel.value) hidden.value = '';
  }
};
</script>
@endpush
@endsection
