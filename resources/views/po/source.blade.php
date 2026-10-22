@extends('layouts.app')
@section('purpose', 'حدد المورد والسعر وتاريخ التوريد — بعد الحفظ الطلب بيبقى مستني الاستلام في المخزن')
@section('content')
@php $cur = config('lvplanning.currency'); @endphp

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'sourcing'])

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>حدد المورد والسعر والوحدة وتاريخ التوريد واضغط <b>«احفظ»</b> — وخلاص.</span>
</div>

<form method="post" action="{{ route('purchase-orders.sourcing', $row) }}">@csrf
  <div class="card mb-3">
    <div class="card-header">
      <span>تسعير الطلب <span class="num">{{ $row->po_no }}</span></span>
      <a href="{{ route('purchasing.queue') }}" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع للمشتريات</a>
    </div>
    <div class="card-body">
      {{-- اللي جاي من التخطيط — للقراءة --}}
      <div class="ro-box mb-3">
        <div class="ro-title">من طلب الشراء — للقراءة</div>
        <div class="sum-grid">
          <div><div class="k">طلبه</div><div class="v">{{ $row->requester?->name ?? '—' }}</div></div>
          <div><div class="k">تاريخ الطلب</div><div class="v num">{{ $row->po_date?->format('Y-m-d') ?? '—' }}</div></div>
          <div><div class="k">نوع الاستلام</div><div class="v">{{ $row->inspection_exempt ? 'مباشر بدون فحص' : 'يدخل فحص' }}</div></div>
          @if($row->models_label)
            <div><div class="k">للموديلات</div><div class="v">{{ $row->models_label }}</div></div>
          @endif
          @if($row->planning_note)
            <div><div class="k">سبب الطلب</div><div class="v">«{{ $row->planning_note }}»</div></div>
          @endif
        </div>
      </div>

      {{-- اللي بتكتبه --}}
      <div class="row g-3">
        <div class="col-md-5"><label class="form-label req" for="sup">المورد</label>
          <select name="supplier_id" id="sup" class="form-select" required onchange="fillSupplier()">
            <option value="">— اختر —</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" data-code="{{ $s->code }}" data-person="{{ $s->contact_person }}"
                      data-phone="{{ $s->phone }}" data-addr="{{ $s->address }}" data-terms="{{ $s->payment_terms }}"
                      @selected(old('supplier_id',$row->supplier_id)==$s->id)>{{ $s->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req" for="delivery_date">تاريخ التوريد</label>
          <input type="date" name="delivery_date" id="delivery_date" class="form-control w-date"
                 value="{{ old('delivery_date',$row->delivery_date?->format('Y-m-d')) }}" required></div>
        <div class="col-md-4"><label class="form-label" for="warehouse_id">المخزن</label>
          <select name="warehouse_id" id="warehouse_id" class="form-select"><option value="">—</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-12">
          <div class="ro-box">
            <div class="ro-title">بيانات المورد — للقراءة</div>
            <div id="supBox" class="small">بيانات المورد بتظهر هنا لما تختاره.</div>
          </div>
        </div>

        <div class="col-md-4"><label class="form-label req" for="payment_method">طريقة الدفع</label>
          <select name="payment_method" id="payment_method" class="form-select" required>
            <option value="">— اختر —</option>
            @foreach(\App\Models\PurchaseOrder::PAYMENT_METHODS as $k => $v)
              <option value="{{ $k }}" @selected(old('payment_method',$row->payment_method)===$k)>{{ $v }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label" for="discount_pct">الخصم</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="discount_pct" id="discount_pct" class="form-control"
                   value="{{ old('discount_pct',$row->discount_pct ?? 0) }}" placeholder="مثال: 0">
            <span class="input-group-text">%</span>
          </div></div>
        <div class="col-md-2"><label class="form-label" for="tax_pct">الضريبة</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="tax_pct" id="tax_pct" class="form-control"
                   value="{{ old('tax_pct',$row->tax_pct ?? 14) }}" placeholder="مثال: 14">
            <span class="input-group-text">%</span>
          </div></div>
      </div>

      <div class="section-head"><h2>الأصناف والأسعار</h2><span class="hint">الصنف واللون والكمية من الطلب — اكتب الوحدة والسعر بس</span></div>
      <div class="table-responsive">
        <table class="table table-sm line-table mb-0">
          <thead><tr><th style="width:35px">م</th><th>الصنف</th><th>اللون</th><th style="width:110px">الكمية</th>
            <th class="req" style="width:110px">الوحدة</th><th class="req" style="width:150px">سعر الوحدة ({{ $cur }})</th><th style="width:140px">الإجمالي ({{ $cur }})</th></tr></thead>
          <tbody>
          @foreach($row->lines as $i => $l)
            <tr>
              <td class="text-center row-no">{{ $i+1 }}</td>
              <td>{{ $l->fabricType?->name }}
                @if($l->notes)<div class="hint">{{ $l->notes }}</div>@endif</td>
              <td>{{ $l->color?->name }}</td>
              <td class="num">{{ lv_num($l->qty, 3) }} <span class="hint">{{ $l->unit }}</span></td>
              <td>
                <input type="hidden" name="prices[{{ $i }}][id]" value="{{ $l->id }}">
                <select name="prices[{{ $i }}][unit]" class="form-select form-select-sm" aria-label="الوحدة">
                  @foreach(array_unique(array_filter(['طن','كيلو','متر', $l->unit === 'كجم' ? null : $l->unit])) as $u)
                    <option value="{{ $u }}" @selected($l->unit===$u || ($l->unit==='كجم' && $u==='كيلو'))>{{ $u }}</option>
                  @endforeach
                </select>
              </td>
              <td class="num"><input type="number" step="0.01" min="0" name="prices[{{ $i }}][unit_price]"
                         class="form-control form-control-sm" value="{{ $l->unit_price }}" placeholder="مثال: 95000" required aria-label="سعر الوحدة"></td>
              <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total,2) : '—' }}
                @if($l->line_total > 0)
                  @include('partials.fx', ['t' => 'إجمالي السطر',
                    'f' => 'الكمية ' . lv_num($l->qty, 3) . ' ' . $l->unit . ' × سعر الوحدة ' . number_format((float)$l->unit_price, 2) . ' = ' . number_format((float)$l->line_total, 2) . ' ' . $cur,
                    'why' => 'بيتحسب لما تحفظ — لو غيّرت الوحدة الكمية بتتحوّل معاها'])
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
          <tfoot>
            @if($row->subtotal > 0)
              <tr><td colspan="5"></td><td>قبل الخصم</td>
                <td class="num">{{ number_format((float)$row->subtotal,2) }}
                  @include('partials.fx', ['t' => 'الإجمالي قبل الخصم', 'f' => 'مجموع إجماليات السطور = ' . number_format((float)$row->subtotal, 2) . ' ' . $cur])</td></tr>
              <tr><td colspan="5"></td><td>الخصم {{ lv_num($row->discount_pct, 2) }}%</td>
                <td class="num">{{ number_format((float)$row->discount_value,2) }}
                  @include('partials.fx', ['t' => 'قيمة الخصم', 'f' => number_format((float)$row->subtotal, 2) . ' × ' . lv_num($row->discount_pct, 2) . '% = ' . number_format((float)$row->discount_value, 2) . ' ' . $cur])</td></tr>
              <tr><td colspan="5"></td><td>الضريبة {{ lv_num($row->tax_pct, 2) }}%</td>
                <td class="num">{{ number_format((float)$row->tax_value,2) }}
                  @include('partials.fx', ['t' => 'قيمة الضريبة', 'f' => '(' . number_format((float)$row->subtotal, 2) . ' − ' . number_format((float)$row->discount_value, 2) . ') × ' . lv_num($row->tax_pct, 2) . '% = ' . number_format((float)$row->tax_value, 2) . ' ' . $cur, 'why' => 'الضريبة على المبلغ بعد الخصم'])</td></tr>
            @endif
            <tr>
              <td colspan="5"></td>
              <td>الإجمالي بعد الضريبة</td>
              <td class="num">{{ $row->total > 0 ? number_format((float)$row->total,2) : '—' }}
                @if($row->total > 0)
                  @include('partials.fx', ['t' => 'الإجمالي بعد الضريبة',
                    'f' => number_format((float)$row->subtotal, 2) . ' − خصم ' . number_format((float)$row->discount_value, 2) . ' + ضريبة ' . number_format((float)$row->tax_value, 2) . ' = ' . number_format((float)$row->total, 2) . ' ' . $cur])
                @endif
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      @if(!($row->total > 0))
        <div class="hint mt-2">الإجماليات بتتحسب بعد الحفظ: الكمية × سعر الوحدة لكل سطر، ثم الخصم والضريبة.</div>
      @endif
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-check2" aria-hidden="true"></i> احفظ</button>
      <span class="hint">
        «احفظ» بيقفل التسعير: الطلب يبقى مستني الاستلام في المخزن،
        والحسابات توصلها الترانزاكشن للمتابعة — وهترجع على المشتريات.
      </span>
      <div class="fa-end"><a href="{{ route('purchasing.queue') }}" class="btn btn-outline-secondary">إلغاء</a></div>
    </div>
  </div>
</form>

@push('scripts')
<script>
function fillSupplier(){
  const o = document.getElementById('sup')?.selectedOptions[0];
  const box = document.getElementById('supBox');
  if (!o || !o.value) { box.textContent = 'بيانات المورد بتظهر هنا لما تختاره.'; return; }
  const d = o.dataset;
  box.innerHTML = `<b>كود المورد:</b> ${d.code || '—'} &nbsp;·&nbsp; <b>الشخص المسؤول:</b> ${d.person || '—'}
    &nbsp;·&nbsp; <b>التليفون:</b> ${d.phone || '—'} &nbsp;·&nbsp; <b>العنوان:</b> ${d.addr || '—'}
    &nbsp;·&nbsp; <b>شروط الدفع:</b> ${d.terms || '—'}`;
}
document.addEventListener('DOMContentLoaded', fillSupplier);
</script>
@endpush
@endsection
