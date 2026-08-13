@extends('layouts.app')
@section('content')
@php
  $lines   = old('lines', $row->lines?->toArray() ?? []);
  $u       = auth()->user();
  $planEd  = $mode === 'create' || ($row->planningEditable() && $u->can2('po.request'));
  $srcEd   = $mode === 'edit' && $row->purchasingEditable() && $u->can2('po.source');
  $finTurn = $mode === 'edit' && $row->stage === 'finance' && $u->can2('po.finance');
@endphp

@if($mode === 'edit')
  @include('partials.po_stepper')
  @include('partials.approval_box')
@endif

{{-- ① بند التخطيط --}}
<form method="post" action="{{ $mode==='create' ? route('purchase-orders.store') : route('purchase-orders.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>
        <span class="badge bg-{{ $planEd ? 'warning' : 'success' }}">①</span>
        التخطيط — الأصناف والكميات
        @if($mode==='edit') <span class="hint">· {{ $row->po_no }}</span> @endif
      </span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->stage_color }} align-self-center">{{ $row->stage_name }}</span>
          <a href="{{ route('purchase-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>

    <div class="card-body"><fieldset @disabled(!$planEd)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="po_date" class="form-control form-control-sm"
                 value="{{ old('po_date', $row->po_date?->format('Y-m-d') ?? $row->po_date) }}" required></div>
        <div class="col-md-3"><label class="form-label">اسم الموظف</label>
          <select name="employee_id" class="form-select form-select-sm">
            <option value="">— اختر —</option>
            @foreach($employees as $k=>$v)<option value="{{ $k }}" @selected(old('employee_id',$row->employee_id ?? auth()->id())==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-7"><label class="form-label">سبب الطلب / ملاحظات التخطيط</label>
          <input name="planning_note" class="form-control form-control-sm" value="{{ old('planning_note',$row->planning_note) }}"
                 placeholder="مثال: تغطية فوركاست الربع الأول — الأساسيات"></div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm line-table mb-0">
          <thead><tr>
            <th style="width:35px">م</th><th style="width:170px">كود اللون</th><th>اسم الصنف</th>
            <th style="width:100px">الكمية</th><th style="width:80px">الوحدة</th>
            <th style="width:100px">نسبة الزيادة %</th><th>ملاحظات</th><th style="width:40px"></th>
          </tr></thead>
          <tbody id="lines">
            @foreach($lines as $i => $l) @include('po.line', ['i'=>$i,'l'=>$l]) @endforeach
            @if(!count($lines)) @include('po.line', ['i'=>0,'l'=>[]]) @endif
          </tbody>
        </table>
      </div>

      @if($planEd)
        <button type="button" class="btn btn-sm btn-outline-plum mt-2" onclick="LV.add('lineTpl','lines')">
          <i class="bi bi-plus-lg"></i> صنف
        </button>
      @endif
    </fieldset></div>

    @if($planEd)
      <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>
        @if($mode==='edit')
          <button type="button" class="btn btn-success btn-sm"
                  onclick="if(confirm('تنزيل الطلب للمشتريات؟ مش هتقدر تعدّل الأصناف بعدها.')) document.getElementById('toPurchasing').submit()">
            <i class="bi bi-arrow-left"></i> نزّل للمشتريات
          </button>
        @endif
      </div>
    @endif
  </div>
</form>

@if($mode==='edit' && $planEd)
  <form id="toPurchasing" method="post" action="{{ route('purchase-orders.to-purchasing',$row) }}" class="d-none">@csrf</form>
@endif

@if($mode === 'edit')

{{-- ② بند المشتريات --}}
<form method="post" action="{{ route('purchase-orders.sourcing',$row) }}">@csrf
  <div class="card mb-3">
    <div class="card-header">
      <span class="badge bg-{{ $row->stage === 'planning' ? 'secondary' : ($srcEd ? 'warning' : 'success') }}">②</span>
      المشتريات — المورد والأسعار والتوريد
      @if($row->sourced_at)<span class="hint">· {{ $row->sourcer?->name }} · {{ $row->sourced_at->format('Y-m-d H:i') }}</span>@endif
    </div>

    @if($row->stage === 'planning')
      <div class="card-body text-muted small">الطلب لسه عند التخطيط — هيوصل للمشتريات بعد ما يتنزّل.</div>
    @else
      <div class="card-body"><fieldset @disabled(!$srcEd)>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label req">المورد</label>
            <select name="supplier_id" id="sup" class="form-select form-select-sm" required onchange="fillSupplier()">
              <option value="">— اختر —</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}" data-code="{{ $s->code }}" data-person="{{ $s->contact_person }}"
                        data-phone="{{ $s->phone }}" data-addr="{{ $s->address }}" data-terms="{{ $s->payment_terms }}"
                        @selected(old('supplier_id',$row->supplier_id)==$s->id)>{{ $s->name }}</option>
              @endforeach
            </select></div>
          <div class="col-md-2"><label class="form-label req">تاريخ التوريد</label>
            <input type="date" name="delivery_date" class="form-control form-control-sm"
                   value="{{ old('delivery_date',$row->delivery_date?->format('Y-m-d')) }}" required></div>
          <div class="col-md-3"><label class="form-label">مكان التسليم</label>
            <input name="delivery_place" class="form-control form-control-sm" value="{{ old('delivery_place',$row->delivery_place ?: 'العبور') }}"></div>
          <div class="col-md-3"><label class="form-label">المخزن</label>
            <select name="warehouse_id" class="form-select form-select-sm"><option value="">—</option>
              @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>

          <div class="col-12">
            <div class="note-box" id="supBox">بيانات المورد بتظهر هنا لما تختاره.</div>
          </div>

          <div class="col-md-3"><label class="form-label">طريقة الدفع</label>
            <input name="payment_method" class="form-control form-control-sm" value="{{ old('payment_method',$row->payment_method) }}"></div>
          <div class="col-md-2"><label class="form-label">الخصم %</label>
            <input type="number" step="0.01" name="discount_pct" class="form-control form-control-sm" value="{{ old('discount_pct',$row->discount_pct ?? 0) }}"></div>
          <div class="col-md-2"><label class="form-label">الضريبة %</label>
            <input type="number" step="0.01" name="tax_pct" class="form-control form-control-sm" value="{{ old('tax_pct',$row->tax_pct ?? 14) }}"></div>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-sm mb-0">
            <thead><tr><th style="width:35px">م</th><th>الصنف</th><th>اللون</th><th>الكمية</th>
              <th style="width:130px">سعر الوحدة</th><th style="width:120px">الإجمالي</th></tr></thead>
            <tbody>
            @foreach($row->lines as $i => $l)
              <tr>
                <td class="text-center">{{ $i+1 }}</td>
                <td>{{ $l->fabricType?->name }}</td>
                <td>{{ $l->color?->name }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty,3),'0'),'.') }} {{ $l->unit }}</td>
                <td>
                  <input type="hidden" name="prices[{{ $i }}][id]" value="{{ $l->id }}">
                  <input type="number" step="0.01" name="prices[{{ $i }}][unit_price]"
                         class="form-control form-control-sm" value="{{ $l->unit_price }}" @disabled(!$srcEd)>
                </td>
                <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total,2) : '—' }}</td>
              </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="table-light">
              <td colspan="4"></td>
              <td class="fw-bold">الإجمالي بعد الضريبة</td>
              <td class="num fw-bold">{{ $row->total > 0 ? number_format((float)$row->total,2) : '—' }}</td>
            </tr></tfoot>
          </table>
        </div>
      </fieldset></div>

      @if($srcEd)
        <div class="card-footer bg-white d-flex gap-2">
          <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ بيانات المورد والأسعار</button>
          <button type="button" class="btn btn-success btn-sm"
                  onclick="if(confirm('تنزيل الطلب للحسابات؟')) document.getElementById('toFinance').submit()">
            <i class="bi bi-arrow-left"></i> نزّل للحسابات
          </button>
        </div>
      @endif
    @endif
  </div>
</form>

@if($srcEd)
  <form id="toFinance" method="post" action="{{ route('purchase-orders.to-finance',$row) }}" class="d-none">@csrf</form>
@endif

{{-- ③ بند الحسابات --}}
<div class="card mb-3">
  <div class="card-header">
    <span class="badge bg-{{ in_array($row->stage,['planning','purchasing'],true) ? 'secondary' : ($finTurn ? 'warning' : 'success') }}">③</span>
    الحسابات — المستحق المتوقع للمورد
    @if($row->finance_at)<span class="hint">· {{ $row->financer?->name }} · {{ $row->finance_at->format('Y-m-d H:i') }}</span>@endif
  </div>

  @if(in_array($row->stage, ['planning','purchasing'], true))
    <div class="card-body text-muted small">هيوصل للحسابات بعد ما المشتريات تخلّص المورد والأسعار.</div>
  @else
    <div class="card-body">
      <div class="row g-3 text-center mb-3">
        <div class="col-md-3"><div class="stat"><div class="v num">{{ number_format((float)$row->total, 2) }}</div><div class="l">المستحق المتوقع ({{ config('lvplanning.currency') }})</div></div></div>
        <div class="col-md-3"><div class="stat"><div class="v">{{ $row->supplier?->name ?? '—' }}</div><div class="l">المورد</div></div></div>
        <div class="col-md-3"><div class="stat"><div class="v num">{{ $row->delivery_date?->format('Y-m-d') ?? '—' }}</div><div class="l">تاريخ التوريد</div></div></div>
        <div class="col-md-3"><div class="stat"><div class="v">{{ $row->payment_method ?: '—' }}</div><div class="l">طريقة الدفع</div></div></div>
      </div>

      @if($finTurn)
        <form method="post" action="{{ route('purchase-orders.finance-ack',$row) }}">@csrf
          <label class="form-label">ملاحظة الحسابات</label>
          <textarea name="finance_note" rows="2" class="form-control form-control-sm mb-2"
                    placeholder="مثال: هيتصرف على دفعتين — الأولى مع التوريد"></textarea>
          <button class="btn btn-plum btn-sm"><i class="bi bi-check2"></i> علمت ومتابع — إرسال للاعتماد</button>
          <div class="hint mt-1">الحسابات مش بتوقف الطلب — بس بتسجّل إنها شايفة المستحق.</div>
        </form>
      @elseif($row->finance_note)
        <div class="note-box">{{ $row->finance_note }}</div>
      @endif
    </div>
  @endif
</div>
@endif

<template id="lineTpl">@include('po.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@if($mode === 'edit')
  @include('partials.comments')
@endif

@include('partials.lines_js', ['startIndex' => max(count($lines), 1)])

@push('scripts')
<script>
function fillSupplier(){
  const o = document.getElementById('sup')?.selectedOptions[0];
  const box = document.getElementById('supBox');
  if (!o || !o.value) { box.textContent = 'بيانات المورد بتظهر هنا لما تختاره.'; return; }
  const d = o.dataset;
  box.innerHTML = `<b>كود المورد:</b> ${d.code || '—'} &nbsp;·&nbsp;
    <b>الشخص المسؤول:</b> ${d.person || '—'} &nbsp;·&nbsp;
    <b>التليفون:</b> ${d.phone || '—'} &nbsp;·&nbsp;
    <b>العنوان:</b> ${d.addr || '—'} &nbsp;·&nbsp;
    <b>شروط الدفع:</b> ${d.terms || '—'}`;
}
document.addEventListener('DOMContentLoaded', fillSupplier);
</script>
@endpush
@endsection
