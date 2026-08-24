@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'receipt'])

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
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> سطر</button>@endif
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
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الأرقام هنا هي اللي طلعت من الجرد والفحص — مش اللي المورد قال عليها.
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm"
      onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>

@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('goods-receipts.submit',$row) }}" class="d-none">@csrf</form>
  @if($row->consignment)
    <a href="{{ route('consignments.show', $row->consignment) }}" class="btn btn-outline-plum btn-sm">
      <i class="bi bi-box-seam" aria-hidden="true"></i> فتح الحوض {{ $row->consignment->consignment_no }}
    </a>
  @endif
@endif

<template id="lineTpl">@include('receipts.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>

@if($mode === 'edit')
  @php $rejections = $row->consignment?->rejections()->where('goods_receipt_id', $row->id)->get() ?? collect(); @endphp
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-x-octagon" aria-hidden="true"></i> الرفض الجزئي وتعليق الألوان</span>
      <span class="hint">{{ $rejections->count() }} بند</span>
    </div>

    <div class="card-body pb-2">
      <div class="hint mb-3">
        زي ما بيتكتب على الورقة بالظبط: «تم رفض عدد 2 توب كود 1132 بوزنه 8.36 كيلو — مصلحة الجودة»،
        أو «تم تعليق اللون الروز كود 2580 لحين الرد من إدارة التخطيط والمشتريات».
        المرفوض ما بيدخلش المخزون وما بيتحسبش على أمر الشراء.
      </div>

      @forelse($rejections as $rj)
        <div class="d-flex align-items-start gap-2 mb-2 pb-2" style="border-bottom:1px dashed var(--lv-line)">
          <span class="badge bg-{{ $rj->kind === 'on_hold' ? 'warning' : 'danger' }}">{{ $rj->kind_name }}</span>
          <div class="flex-grow-1">
            <b style="font-size:.85rem">{{ $rj->label }}</b>
            <div class="hint">{{ $rj->party_name }} — {{ $rj->reason }}</div>
          </div>
          <span class="badge bg-{{ $rj->resolution === 'open' ? 'secondary' : 'success' }}">{{ $rj->resolution_name }}</span>
          @if($rj->resolution === 'open' && $row->isEditable())
            <form method="post" action="{{ route('rejections.destroy', $rj) }}" onsubmit="return confirm('حذف البند؟')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-link text-danger p-0" style="font-size:.75rem">حذف</button>
            </form>
          @endif
        </div>
      @empty
        <div class="text-muted small py-2">مفيش رفض ولا تعليق على الإذن ده.</div>
      @endforelse
    </div>

    @if($row->isEditable())
      <div class="card-footer bg-white">
        <form method="post" action="{{ route('rejections.store', $row) }}" class="row g-2">@csrf
          <div class="col-md-2">
            <label class="form-label req">النوع</label>
            <select name="kind" class="form-select form-select-sm" required>
              <option value="rejected">مرفوض</option>
              <option value="on_hold">معلّق لحين الرد</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">كود اللون</label>
            <input name="color_code" class="form-control form-control-sm" placeholder="1132">
          </div>
          <div class="col-md-2">
            <label class="form-label">وصف الحوض</label>
            <input name="lot_label" class="form-control form-control-sm" placeholder="الحوض الأخضر">
          </div>
          <div class="col-md-1">
            <label class="form-label req">أتواب</label>
            <input type="number" name="rolls_count" class="form-control form-control-sm" value="0" required>
          </div>
          <div class="col-md-2">
            <label class="form-label req">الوزن</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.001" name="qty" class="form-control" required>
              <select name="unit" class="form-select" style="max-width:75px">
                <option value="كجم">كجم</option><option value="متر">متر</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label req">الجهة</label>
            <select name="party" class="form-select form-select-sm" required>
              <option value="quality">مصلحة الجودة</option>
              <option value="planning">إدارة التخطيط</option>
              <option value="purchasing">إدارة المشتريات</option>
            </select>
          </div>
          <div class="col-md-10">
            <label class="form-label req">السبب</label>
            <input name="reason" class="form-control form-control-sm" required
                   placeholder="مثال: بيهم تل غريبة وتناسخ · لون غير مطابق">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-danger btn-sm w-100">تسجيل</button>
          </div>
        </form>
      </div>
    @endif
  </div>
@endif

@include('partials.lines_js', ['startIndex' => max(count($lines),1)])
@endsection
