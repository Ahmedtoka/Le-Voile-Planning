@extends('layouts.app')
@section('purpose', 'استلام القماش على الطلب — كل (خامة + لون) بيتولّد له حوض محجوز تحت الفحص')
@section('content')
@php
  $base  = ($preset ?? null) ?: ($row->lines?->toArray() ?? []);
  /* رجعنا بخطأ؟ اللي اتكتب بيرجع زي ما هو، فوق بيانات الطلب الثابتة (اسم الصنف، المطلوب، التسكينة)
     اللي ما بتتبعتش مع الفورم — من غيرها السطر بيرجع «—» ويبان إن الداتا ضاعت */
  $lines = old('lines') !== null
      ? collect(old('lines'))->map(function ($l) use ($base) {
            $from = !empty($l['po_line_id']) ? collect($base)->firstWhere('po_line_id', $l['po_line_id']) : null;
            return array_merge($from ?? [], $l);
        })->all()
      : $base;
  $editable = $row->isEditable() || $mode==='create';
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'addition'])

@php $po = $poInfo ?? $row->purchaseOrder ?? null; @endphp
@if($po)
  @php
    $po->loadMissing('lines', 'supplier');
    $ordTot  = (float) $po->lines->sum('qty');
    $recTot  = (float) $po->lines->sum('received_qty');
    $remTot  = max(0, $ordTot - $recTot);   // الباقي = المطلوب − المستلم، زي ما هو
    $unitLbl = $po->lines->first()?->unit ?? '';
    /* التسكينة: اللي المورد ده هيورده — الباقي عليه غير الباقي على الطلب كله */
    $src     = $sourcing ?? null;
    $toPo    = fn ($v, $l) => \App\Services\DocumentEffects::toUnit($v, $l->unit, $unitLbl);   // بوحدة الطلب
    $srcOrd  = $src ? (float) $src->lines->sum(fn ($l) => $toPo($l->qty, $l)) : null;
    $srcRec  = $src ? (float) $src->lines->sum(fn ($l) => $toPo($l->received_qty, $l)) : null;
    $srcRem  = $src ? max(0, $srcOrd - $srcRec) : null;
  @endphp
  <div class="ro-box mb-3">
    <div class="ro-title">من طلب الشراء — للقراءة</div>
    <div class="sum-sec">
      <div class="t"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> <span class="num">{{ $po->po_no }}</span></div>
      <div class="sum-grid">
        <div><div class="k">المورد</div><div class="v">{{ ($sourcing ?? null)?->supplier?->name ?? $po->supplier?->name ?? '—' }}</div>
          @if(($sourcing ?? null) && $po->sourcings->count() > 1)<div class="k">تسكينة #{{ $sourcing->seq }} من {{ $po->sourcings->count() }}</div>@endif</div>
        <div><div class="k">توريد متوقع</div><div class="v num">{{ ($sourcing ?? null)?->delivery_date?->format('Y-m-d') ?? $po->delivery_date?->format('Y-m-d') ?? '—' }}</div></div>
        @if($src)
        <div><div class="k">على التسكينة دي</div><div class="v num">{{ lv_qty($srcOrd, $unitLbl) }}</div>
          <div class="k">استلمنا منها {{ lv_num($srcRec, 3) }} — باقي <b class="{{ $srcRem > 0 ? 'text-danger' : 'text-success' }}">{{ lv_num($srcRem, 3) }}</b>
            @include('partials.fx', ['t' => 'الباقي على التسكينة', 'f' => 'على التسكينة ' . lv_qty($srcOrd, $unitLbl) . ' − المستلم منها قبل كده ' . lv_qty($srcRec, $unitLbl) . ' = ' . lv_qty($srcRem, $unitLbl), 'why' => 'اللي المورد ده اتطلب منه — باقي الطلب على تسكينات تانية'])</div></div>
        @endif
        <div><div class="k">المطلوب إجمالي</div><div class="v num">{{ lv_qty($ordTot, $unitLbl) }}</div></div>
        <div><div class="k">استلمنا قبل كده</div><div class="v num">{{ lv_qty($recTot, $unitLbl) }}</div></div>
        <div><div class="k">{{ $src ? 'الباقي على الطلب كله' : 'الباقي' }}</div>
          <div class="v num {{ $remTot > 0 ? 'text-danger' : 'text-success' }}">
            {{ $remTot > 0 ? lv_qty($remTot, $unitLbl) : 'اكتمل ✓' }}
            @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . lv_qty($ordTot, $unitLbl) . ' − المستلم قبل كده ' . lv_qty($recTot, $unitLbl) . ' = ' . lv_qty($remTot, $unitLbl), 'why' => 'مجموع كل أصناف الطلب'])
          </div></div>
      </div>
    </div>
  </div>
@endif

@if(old('receipt_type', $row->receipt_type ?? 'normal') === 'container')
  <div class="note-box mb-3">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span><b>استلام حاويات — بدون دورة فحص.</b>
    الإذن ده هو الاستلام النهائي: البضاعة بتدخل المخزن <b>مُفرَج عنها ومتاحة للتشغيل فورًا</b>.
    لو محتاجين بيانات العرض والبنشر، الجودة تقدر تفحص 5-6 أتواب استدلاليًا — مش للرفض.</span>
  </div>
@else
  <div class="note-box mb-3">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span><b>ده أول مستند في دورة القماش.</b>
    <b>الحفظ</b> بيولّد <b>رسالة (حوض) لكل لون</b> وبينزّل القماش طابور الفحص فورًا —
    مفيش خطوة «إرسال» بعد كده. الكمية بتدخل المخزن <b>محجوزة</b>،
    والإفراج بيحصل من شاشة استلام الخام بعد ما الفحص والمعمل يخلّصوا.
    <br><b>مهم:</b> اكتب عدد الأتواب صح — الفحص هيجرد عليه.</span>
  </div>
@endif

<form method="post" action="{{ $mode==='create' ? route('stock-additions.store') : route('stock-additions.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header">
      <span>{{ $mode==='create' ? 'إذن إضافة جديد' : 'إذن إضافة ' . $row->doc_no }}
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }}">{{ $row->status_label }}</span>@endif
      </span>
      <div class="d-flex gap-2 ms-auto">
        @if($mode==='edit')
          <a href="{{ route('stock-additions.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('stock-additions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if($po)
        {{-- الإذن من طلب: التاريخ والمورد والمخزن والطلب بييجوا منه — مش بيتكتبوا تاني --}}
        <input type="hidden" name="doc_date"          value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="supplier_id"       value="{{ old('supplier_id', $row->supplier_id ?? $po->supplier_id) }}">
        <input type="hidden" name="warehouse_id"      value="{{ old('warehouse_id', $row->warehouse_id) }}">
        <input type="hidden" name="purchase_order_id" value="{{ old('purchase_order_id', $row->purchase_order_id ?? $po->id) }}">
        <input type="hidden" name="po_sourcing_id" value="{{ old('po_sourcing_id', $row->po_sourcing_id ?? ($sourcing?->id ?? '')) }}">
        @if(($sourcings ?? collect())->count() > 1 && $mode === 'create')
          <div class="alert alert-info py-2 d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <span>الطلب ده متسكّن على أكتر من مورد — الاستلام ده على تسكينة:</span>
            <select class="form-select form-select-sm w-auto" aria-label="التسكينة"
                    onchange="location='{{ route('stock-additions.create') }}?purchase_order_id={{ $po->id }}&po_sourcing_id='+this.value">
              @foreach($sourcings as $s)
                <option value="{{ $s->id }}" @selected($sourcing && $sourcing->id === $s->id)>#{{ $s->seq }} — {{ $s->supplier?->name }} · توريد {{ $s->delivery_date?->format('Y-m-d') }} · باقي {{ lv_num($s->outstanding_qty, 3) }}</option>
              @endforeach
            </select>
          </div>
        @endif

        @php $rt = $po->inspection_exempt ? 'container' : 'normal'; @endphp
        <input type="hidden" name="receipt_type" value="{{ $rt }}">
        {{-- Labels فوق — للقراءة --}}
        <div class="ro-box mb-3">
          <div class="ro-title">بيتسجل لوحده — للقراءة</div>
          <div class="sum-grid">
            <div><div class="k">رقم الرسالة</div>
              <div class="v num">{{ $row->consignment_no ?: ($consignmentPreview ?? 'بيتولّد تلقائيًا عند الاعتماد') }}</div>
              <div class="k">{{ $row->consignment_no ? 'اتولّد' : 'بيتأكد عند الاعتماد' }} · رسالة لكل لون</div></div>
            <div><div class="k">نوع الاستلام</div>
              <div class="v">{{ $rt === 'container' ? 'مباشر — بدون فحص' : 'عادي — يدخل الفحص' }}</div>
              <div class="k">متحدد من طلب الشراء (قرار التخطيط)</div></div>
            <div><div class="k">تاريخ الإذن</div><div class="v num">{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}</div></div>
            <div><div class="k">استلمه</div><div class="v">{{ $row->creator?->name ?? auth()->user()?->name }}</div></div>
          </div>
        </div>
        {{-- اللي بتكتبه --}}
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label req" for="paper_serial">مسلسل إذن الإضافة (الورقي)</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" required placeholder="مثال: 41456" inputmode="numeric"></div>
          <div class="col-md-4"><label class="form-label req" for="supplier_doc_no">رقم إذن المورد</label>
            <input name="supplier_doc_no" id="supplier_doc_no" class="form-control w-mid" value="{{ old('supplier_doc_no',$row->supplier_doc_no) }}"
                   required placeholder="الرقم اللي على ورقة المورد">
            <div class="hint">بيتربط بالفحص والمعمل والاستلام</div></div>
          <div class="col-12"><label class="form-label" for="notes">ملاحظات <span class="hint">(اختياري)</span></label>
            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="مثال: وصلت على عربيتين — التانية الصبح">{{ old('notes',$row->notes) }}</textarea></div>
        </div>
      @else
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label req" for="doc_date">التاريخ</label>
            <input type="date" name="doc_date" id="doc_date" class="form-control w-date" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
          <div class="col-md-3"><label class="form-label req" for="paper_serial">مسلسل إذن الإضافة (الورقي)</label>
            <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" required placeholder="مثال: 41456" inputmode="numeric"></div>
          <div class="col-md-3"><label class="form-label req" for="supplier_id">اسم المورد</label>
            <select name="supplier_id" id="supplier_id" class="form-select" required><option value="">—</option>
              @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(old('supplier_id',$row->supplier_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req" for="warehouse_id">المخزن</label>
            <select name="warehouse_id" id="warehouse_id" class="form-select" required><option value="">—</option>
              @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req" for="supplier_doc_no">رقم إذن المورد</label>
            <input name="supplier_doc_no" id="supplier_doc_no" class="form-control" value="{{ old('supplier_doc_no',$row->supplier_doc_no) }}"
                   required placeholder="الرقم اللي على ورقة المورد">
            <div class="hint">بيتربط بالفحص والمعمل والاستلام</div></div>
          <div class="col-md-3"><label class="form-label" for="receipt_type">نوع الاستلام</label>
            <select name="receipt_type" id="receipt_type" class="form-select"
                    @if($mode==='create')
                      onchange="var po=document.querySelector('[name=purchase_order_id]')?.value; location='{{ route('stock-additions.create') }}?type='+this.value+(po?'&purchase_order_id='+po:'')"
                    @endif>
              <option value="normal" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='normal')>عادي — يدخل الفحص</option>
              <option value="container" @selected(old('receipt_type',$row->receipt_type ?? 'normal')==='container')>حاويات — بدون فحص</option>
            </select></div>
          <div class="col-md-3"><label class="form-label" for="purchase_order_id">طلب الشراء</label>
            <select name="purchase_order_id" id="purchase_order_id" class="form-select"
                    @if($mode==='create')
                      onchange="if(this.value) location='{{ route('stock-additions.create') }}?purchase_order_id='+this.value+'&type='+(document.querySelector('[name=receipt_type]')?.value||'normal')"
                    @endif>
              <option value="">— بدون طلب —</option>
              @foreach($pos as $k=>$v)<option value="{{ $k }}" @selected(old('purchase_order_id',$row->purchase_order_id)==$k)>{{ $v }}</option>@endforeach
            </select>
            @if($mode==='create')<div class="hint">اختار الطلب والشاشة هتتظبط لوحدها</div>@endif
          </div>
          <div class="col-md-3"><label class="form-label" for="consignment_no">رقم الرسالة</label>
            <input name="consignment_no" id="consignment_no" class="form-control" value="{{ old('consignment_no',$row->consignment_no) }}"
                   placeholder="سيبه فاضي والسيستم هيولّده">
            <div class="hint">النمط: BUPL-090826-043-00</div></div>
          <div class="col-12"><label class="form-label" for="notes">ملاحظات <span class="hint">(اختياري)</span></label>
            <input name="notes" id="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="مثال: وصلت على عربيتين — التانية الصبح"></div>
        </div>
      @endif
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <span>الأصناف {{ $po ? '— من الطلب ' . $po->po_no : '' }}
        @if($po)<span class="hint">(البيانات ثابتة، اكتب المستلم بس)</span>@endif</span>
      @if($editable && !$po)
        <button type="button" class="btn btn-sm btn-outline-plum ms-auto" onclick="LV.add('lineTpl','lines')">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> سطر</button>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        @if($po)
          <thead><tr>
            <th style="width:35px">م</th>
            <th>الصنف</th>
            <th style="width:110px">اللون المطلوب</th>
            <th style="width:170px">اللون الواصل</th>
            <th style="width:100px">المطلوب</th>
            <th style="width:80px">الأتواب</th>
            <th style="width:150px">الشكل · كيس/ماسورة (جم)</th>
            <th style="width:100px">القائم (كجم)</th>
            <th style="width:120px">الصافي / المستلم</th>
            <th style="width:120px">الباقي</th>
            <th style="width:160px">الباقي هيوصل إمتى؟</th>
            <th style="width:44px"></th>
          </tr></thead>
        @else
          <thead><tr>
            <th style="width:35px">م</th><th style="width:130px">الصنف</th>
            <th style="width:150px">الخامة</th><th style="width:160px">اللون</th>
            <th class="req" style="width:90px">الوحدة</th>
            <th style="width:80px">الأتواب</th>
            <th style="width:150px">الشكل · كيس/ماسورة (جم)</th>
            <th style="width:100px">القائم (كجم)</th>
            <th style="width:120px">الصافي / الكمية</th>
            <th style="width:44px"></th>
          </tr></thead>
        @endif
        <tbody id="lines" class="{{ $editable ? '' : 'lines-locked' }}">
          @foreach($lines as $i=>$l) @include('additions.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($lines))
            @if($po)
              <tr><td colspan="10">
                <div class="empty-state">
                  <i class="bi bi-check2-circle ico" aria-hidden="true"></i>
                  <div class="t">مفيش باقي على الطلب ده — كل أصنافه اتسلمت.</div>
                </div>
              </td></tr>
            @else
              @include('additions.line',['i'=>0,'l'=>[]])
            @endif
          @endif
        </tbody>
      </table>
    </div>
    {{-- ملخص الصافي والقائم — بيتحسب لايف (الميتينج 37) --}}
    <div class="card-footer bg-white">
      <div class="sum-grid" id="pkSummary">
        <div><div class="k">الأتواب</div><div class="v num" data-k="rolls">—</div></div>
        <div><div class="k">الوزن القائم</div><div class="v num" data-k="gross">—</div></div>
        <div><div class="k">خصم الكيس والماسورة</div><div class="v num text-danger" data-k="pack">—</div>
          <div class="k">(كيس + ماسورة) × الأتواب</div></div>
        <div><div class="k">الصافي اللي هيدخل الرصيد</div><div class="v num text-success" data-k="net">—</div></div>
      </div>
      <div class="hint num mt-1" id="pkFormula"></div>
      <div class="hint mt-2">
        المخزن بيوزن قائم بالكيس والماسورة ومش بيفك أتواب — اختار الشكل واكتب وزن الكيس الواحد والماسورة الواحدة بالجرام،
        والسيستم يخصمهم ويطلّع الصافي. <b>الصافي</b> هو اللي بيدخل الرصيد والتشغيل، و<b>القائم</b> هو اللي بيتحسب على طلب الشراء
        (المورد سلّمه ودفعنا عليه — الكيس والماسورة مش نقص في التوريد).
        الأصناف اللي بالمتر مفيش فيها وزن خالص.
        @if($po) لو اللون الواصل مختلف عن المطلوب هيطلعلك سؤال القرار قبل ما تكمّل. @endif
      </div>
    </div>
  </div>

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum">
      <i class="bi bi-save" aria-hidden="true"></i> حفظ
    </button>
    <span class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص الإذن على طول — بيتولّد حوض لكل لون
      {{ ($row->receipt_type ?? 'normal') === 'container' ? 'ويدخل المخزن مباشرة' : 'وينزل طابور الفحص' }}.
    </span>
    <div class="fa-end"><a href="{{ route('stock-additions.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
  </div>
  @endif
</form>

@php
  $saConsignments = $mode === 'edit'
      ? $row->lines->pluck('consignment')->filter()->unique('id')->values()
      : collect();
  if ($saConsignments->isEmpty() && $row->consignment) $saConsignments = collect([$row->consignment]);
@endphp
@if($saConsignments->isNotEmpty())
  <div class="card mt-3">
    <div class="card-header">
      {{ $saConsignments->count() > 1 ? 'الرسايل اللي اتولّدت — رسالة لكل لون' : 'الرسالة اللي اتولّدت' }}
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>رقم الرسالة</th><th>الصنف</th><th>اللون</th><th>الكمية (كجم)</th><th>الأتواب (توب)</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach($saConsignments as $c)
          <tr>
            <td class="num fw-bold"><a href="{{ route('consignments.show', $c) }}">{{ $c->consignment_no }}</a></td>
            <td>{{ $c->fabricType?->name ?? '—' }}</td>
            <td>{{ $c->color?->label ?? $c->color?->code ?? '—' }}</td>
            <td class="num">{{ lv_num($c->total_kg, 1) }}</td>
            <td class="num">{{ (int) $c->rolls_count }}</td>
            <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_name }}</span></td>
            <td class="text-end">
              @if($c->status === 'under_inspection')
                <div class="row-actions">
                  <a href="{{ route('inspections.create', ['consignment_id'=>$c->id]) }}"
                     class="btn btn-sm btn-outline-plum">افحص</a>
                </div>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@if($mode === 'edit' && $row->purchaseOrder)
  @include('partials.po_deliveries', ['po' => $row->purchaseOrder->load('lines.fabricType', 'lines.color')])
@endif

<template id="lineTpl">@include('additions.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
{{-- بوب أب قرار انحراف اللون --}}
<div class="modal fade" id="colorModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h6 class="modal-title"><i class="bi bi-palette" aria-hidden="true"></i> الدرجة اللي وصلت مختلفة</h6>
    </div>
    <div class="modal-body">
      <p class="mb-2">
        إنت كنت طالب <b class="cm-req"></b> واللي وصل <b class="cm-got"></b>.
      </p>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-outline-plum text-start" data-choice="substitute">
          <b>نزّله مكان الطلب</b>
          <div class="hint">الكمية بتتحسب على السطر ده، ولون السطر في طلب الشراء
            بيتحدث للون الواصل — بملاحظة في الهيستوري.</div>
        </button>
        <button type="button" class="btn btn-outline-plum text-start" data-choice="new_po">
          <b>نزّله كسطر جديد — والأصلي يفضل مطلوب</b>
          <div class="hint">الوارد بيتوثّق بطلب تلقائي باللون الجديد، واللون الأصلي
            يفضل مفتوح على المورد يبعته.</div>
        </button>
      </div>
    </div>
    <div class="modal-footer py-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-choice="">
        إلغاء — رجّع اللون المطلوب
      </button>
    </div>
  </div></div>
</div>

<style>
  /* زرار اختيار القرار في البوب أب: نص متعدد الأسطر */
  #colorModal .btn.text-start{display:block;white-space:normal;text-align:start}
  #colorModal .btn.text-start .hint{white-space:normal}
</style>

@include('partials.lines_js',['startIndex'=>max(count($lines),1)])
{{-- السكريبت في ستاك السكريبتات عشان يتنفذ بعد تحميل بوتستراب — وإلا الموديل مش هيشتغل --}}
@push('scripts')
<script>
/* الصافي والقائم: الصافي = القائم − (كيس + ماسورة) × الأتواب ÷ 1000 — والمتر من غير وزن */
window.LVA = (function () {
  var nf = function (n, d) { return Number(n).toLocaleString('en-US', {maximumFractionDigits: d === undefined ? 3 : d}); };
  function isMeter(tr) { var u = tr.querySelector('.q-unit'); return u ? /متر|^م$/.test(u.value) : tr.dataset.meter === '1'; }
  function form(sel) {
    var tr = sel.closest('tr'), g = tr.querySelector('.pk-g'), tube = tr.querySelector('.q-tube');
    if (g) g.hidden = !sel.value;
    if (tube) { tube.hidden = sel.value !== 'open'; if (sel.value !== 'open') tube.value = ''; }
    net(sel);
  }
  function unit(sel) {
    var tr = sel.closest('tr'), m = /متر|^م$/.test(sel.value);
    tr.querySelectorAll('.pk-cell').forEach(function (c) { c.hidden = m; });
    var q = tr.querySelector('.q-recv');
    if (q) { q.readOnly = !m && !!tr.querySelector('.q-gross'); q.placeholder = m ? 'مثال: 950' : 'يتحسب'; q.classList.toggle('q-net', !m); if (m) q.tabIndex = 0; }
    net(sel);
  }
  function net(el) {
    var tr = el.closest('tr'); if (!tr) return;
    var q = tr.querySelector('.q-recv'), pk = tr.querySelector('.q-pk');
    if (isMeter(tr)) { if (pk) pk.textContent = ''; summary(); return; }
    var gross = Number(tr.querySelector('.q-gross')?.value) || 0, rolls = Number(tr.querySelector('.q-rolls')?.value) || 0;
    var bag = Number(tr.querySelector('.q-bag')?.value) || 0, tube = Number(tr.querySelector('.q-tube')?.value) || 0;
    var pack = Math.round((bag + tube) * rolls) / 1000, n = Math.max(0, Math.round((gross - pack) * 1000) / 1000);
    if (q && gross > 0) { q.value = n; q.dispatchEvent(new Event('input', {bubbles: true})); }
    else if (q && !gross) { q.value = ''; q.dispatchEvent(new Event('input', {bubbles: true})); }
    // المعادلة ظاهرة تحت الصافي: القائم − (كيس + ماسورة) × الأتواب ÷ 1000
    if (pk) pk.textContent = gross > 0
      ? (pack > 0
          ? nf(gross) + ' − (' + nf(bag, 1) + ' + ' + nf(tube, 1) + ') جم × ' + rolls + ' توب ÷ 1000 = ' + nf(gross) + ' − ' + nf(pack) + ' = ' + nf(n) + ' كجم'
          : 'من غير خصم كيس وماسورة: الصافي = القائم ' + nf(gross))
      : '';
    summary();
  }
  function summary() {
    var rolls = 0, gross = 0, pack = 0, netT = 0, any = false;
    document.querySelectorAll('#lines tr').forEach(function (tr) {
      if (isMeter(tr)) return;
      var g = Number(tr.querySelector('.q-gross')?.value) || 0; if (!g) return;
      any = true;
      var r = Number(tr.querySelector('.q-rolls')?.value) || 0, bag = Number(tr.querySelector('.q-bag')?.value) || 0, tube = Number(tr.querySelector('.q-tube')?.value) || 0;
      var p = Math.round((bag + tube) * r) / 1000;
      rolls += r; gross += g; pack += p; netT += Math.max(0, g - p);
    });
    var box = document.getElementById('pkSummary'); if (!box) return;
    box.querySelector('[data-k=rolls]').textContent = any ? nf(rolls, 0) + ' توب' : '—';
    box.querySelector('[data-k=gross]').textContent = any ? nf(gross) + ' كجم' : '—';
    box.querySelector('[data-k=pack]').textContent  = any ? '− ' + nf(pack) + ' كجم' : '—';
    box.querySelector('[data-k=net]').textContent   = any ? nf(netT) + ' كجم' : '—';
    var f = document.getElementById('pkFormula');
    if (f) f.textContent = any ? 'الصافي = القائم ' + nf(gross) + ' − خصم الكيس والماسورة ' + nf(pack) + ' = ' + nf(netT) + ' كجم · المستلم على الطلب بيتحسب بالقائم ' + nf(gross) : '';
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#lines tr').forEach(function (tr) {
      var f = tr.querySelector('.q-form'); if (f && f.value) { var g = tr.querySelector('.pk-g'); if (g) g.hidden = false; }
      // المعادلات تظهر من أول ما الصفحة تفتح — مش بس لما تكتب
      var gr = tr.querySelector('.q-gross'); if (gr && gr.value) net(gr);
    });
    summary();
  });
  return {form: form, unit: unit, net: net, summary: summary};
})();
(function () {
  var body = document.getElementById('lines');
  if (!body) return;
  var EDITABLE = {{ $editable ? 'true' : 'false' }};

  var nf = function (n) {
    return Number(n.toFixed(3)).toLocaleString('en-US', {maximumFractionDigits: 3});
  };

  /* ── المستلم/الباقي لايف: طالب 50 طن وكتبت 30 ⇒ «المستلم 10 توب · 30 طن — باقي 20 طن» ──
     نسبة الزيادة للزيادة بس: الأقل من المطلوب بيفضل باقي، والأكتر منها بيترفض. */
  function calcRow(tr) {
    var out = tr.querySelector('.q-left');
    if (!out) return 0;
    var unit     = tr.dataset.unit || '';
    var ordered  = parseFloat(tr.dataset.ordered)  || 0;
    var minQty   = parseFloat(tr.dataset.min)      || ordered;
    var received = parseFloat(tr.dataset.received) || 0;
    /* المقارنة مع الطلب على القائم (اللي المورد سلّمه) — الكيس والماسورة مش نقص في التوريد.
       بالمتر أو من غير قائم: الكمية المكتوبة. الصافي بيدخل الرصيد بس. */
    var net      = parseFloat(tr.querySelector('.q-recv')?.value) || 0;
    var gross    = parseFloat(tr.querySelector('.q-gross')?.value) || 0;
    var now      = gross > 0 ? gross : net;
    var rolls    = parseInt(tr.querySelector('[name$="[rolls_count]"]')?.value || '0', 10) || 0;
    var recvLine = '<div class="hint">المستلم دلوقتي (' + (gross > 0 ? 'قائم' : unit) + '): <b class="num">' + (rolls ? rolls + ' توب · ' : '') + nf(now) + ' ' + unit + '</b></div>';
    var left     = ordered - received - now;          // للعرض
    var leftMin  = Math.max(0, minQty - received - now);  // للإقفال
    // المعادلة ظاهرة بالأرقام الفعلية للسطر
    var fx = function (title) {
      var onSrc = tr.dataset.scope === 'src';
      var line = (onSrc ? 'المطلوب على التسكينة ' : 'المطلوب ') + nf(ordered) + (onSrc ? ' − اتستلم منها قبل كده ' : ' − اتستلم قبل كده ') + nf(received) + ' − ' + (gross > 0 ? 'القائم دلوقتي ' : 'المستلم دلوقتي ') + nf(now) + ' = ' + nf(left) + ' ' + unit;
      return '<div class="hint fx-line">' + line + '</div>';
    };
    // خانة «هيوصل إمتى» بتظهر بس لما يبقى فيه باقي على السطر ده
    var eta = tr.querySelector('.r-eta');
    var showEta = function (on) {
      if (!eta) return;
      eta.style.display = on ? '' : 'none';
      // الباقي له تاريخ إجباري — المتصفح بيوقف الحفظ قبل ما يتبعت (نفس قاعدة السيرفر)
      var d = eta.querySelector('input[type=date]'); if (d) d.required = !!on;
      if (!on) eta.querySelectorAll('input').forEach(function (x) { x.value = ''; });
    };
    // اتكتب وزن أو كمية؟ عدد الأتواب إجباري — الفحص بيجرد عليه
    var rollsIn = tr.querySelector('.q-rolls'); if (rollsIn) rollsIn.required = now > 0;

    if (!now) { out.innerHTML = '<span class="hint">—</span>'; showEta(false); return Math.max(0, minQty - received); }
    if (leftMin > 0.0005) {
      out.innerHTML = recvLine + '<span class="pill pill-warn">باقي ' + nf(left) + ' ' + unit + '</span>' + fx('الباقي على السطر');
      showEta(true);
    } else if (left < -0.0005) {
      out.innerHTML = recvLine + '<span class="pill pill-danger">زيادة ' + nf(-left) + ' ' + unit + '</span>' + fx('الزيادة على السطر');
      showEta(false);
    } else {
      out.innerHTML = recvLine + '<span class="pill pill-ok">اكتمل ✓</span>' + fx('اكتمل');
      showEta(false);
    }
    if (window.LVX && LVX.popovers) LVX.popovers(out);
    return leftMin;
  }

  function calcAll() {
    body.querySelectorAll('tr.po-row').forEach(calcRow);
  }

  /* الحسبة اللايف للمسودات بس — المستند المعتمد المستلم بتاعه اتحسب
     على الطلب فعلًا، فلو حسبناه تاني هيتخصم مرتين */
  if (EDITABLE) {
    body.addEventListener('input', function (e) {
      if (e.target.classList.contains('q-recv') || /\[rolls_count\]$/.test(e.target.name || '')) calcAll();
    });
    calcAll();
  }

  /* ── قرار اللون: مطابق يكمّل — مختلف يفتح البوب أب ── */
  var modalEl = document.getElementById('colorModal');
  var modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
  var pending = null;   // الصف اللي مستني القرار

  function setStatus(tr, action) {
    var s = tr.querySelector('.c-status'), sel = tr.querySelector('.c-actual');
    if (!s || !sel) return;
    tr.querySelector('.c-action').value = action;
    if (action === 'substitute') {
      s.innerHTML = '<span class="pill pill-warn">تسكين — الطلب هيتحدث للون ده</span>';
    } else if (action === 'new_po') {
      s.innerHTML = '<span class="pill pill-info">سطر جديد — والأصلي يفضل مطلوب</span>';
    } else if (sel.value == sel.dataset.requested) {
      s.innerHTML = '<span class="pill pill-ok"><i class="bi bi-check2" aria-hidden="true"></i> مطابق</span>';
    } else {
      s.innerHTML = '';
    }
  }

  body.addEventListener('change', function (e) {
    if (!EDITABLE || !e.target.classList.contains('c-actual')) return;
    var sel = e.target, tr = sel.closest('tr');

    // سطر الطلب من غير لون محدد؟ مفيش انحراف نسأله عليه
    if (!sel.dataset.requested) { setStatus(tr, ''); return; }

    if (sel.value == sel.dataset.requested) { setStatus(tr, ''); return; }

    if (modal) {
      pending = tr;
      modalEl.querySelector('.cm-req').textContent = sel.dataset.requestedLabel || 'اللون المطلوب';
      modalEl.querySelector('.cm-got').textContent = sel.selectedOptions[0]?.textContent || '';
      modal.show();
    }
  });

  modalEl?.querySelectorAll('[data-choice]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (pending) {
        var choice = btn.dataset.choice;
        if (choice) {
          setStatus(pending, choice);
        } else {
          // إلغاء: رجّع اللون المطلوب زي ما كان
          var sel = pending.querySelector('.c-actual');
          sel.value = sel.dataset.requested;
          setStatus(pending, '');
        }
        pending = null;
      }
      modal.hide();
    });
  });
})();
</script>
@endpush
@endsection
