@extends('layouts.app')
@section('content')

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  طلبه <b>{{ $row->requester?->name }}</b> يوم {{ $row->po_date?->format('Y-m-d') }}
  @if($row->planning_note) — «{{ $row->planning_note }}» @endif.
  حدد المورد والسعر والوحدة وتاريخ التوريد، <b>احفظ</b>، وبعدين <b>نزّل للحسابات</b>.
</div>

<form method="post" action="{{ route('purchase-orders.sourcing', $row) }}">@csrf
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>تسعير الطلب {{ $row->po_no }}</span>
      <a href="{{ route('purchasing.queue') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع للمشتريات</a>
    </div>
    <div class="card-body">
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
          <input name="delivery_place" class="form-control form-control-sm"
                 value="{{ old('delivery_place',$row->delivery_place ?: 'العبور') }}"></div>
        <div class="col-md-3"><label class="form-label">المخزن</label>
          <select name="warehouse_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>

        <div class="col-12"><div class="note-box" id="supBox">بيانات المورد بتظهر هنا لما تختاره.</div></div>

        <div class="col-md-3"><label class="form-label req">طريقة الدفع</label>
          <select name="payment_method" class="form-select form-select-sm" required>
            <option value="">— اختر —</option>
            @foreach(\App\Models\PurchaseOrder::PAYMENT_METHODS as $k => $v)
              <option value="{{ $k }}" @selected(old('payment_method',$row->payment_method)===$k)>{{ $v }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label">الخصم %</label>
          <input type="number" step="0.01" name="discount_pct" class="form-control form-control-sm"
                 value="{{ old('discount_pct',$row->discount_pct ?? 0) }}"></div>
        <div class="col-md-2"><label class="form-label">الضريبة %</label>
          <input type="number" step="0.01" name="tax_pct" class="form-control form-control-sm"
                 value="{{ old('tax_pct',$row->tax_pct ?? 14) }}"></div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm mb-0">
          <thead><tr><th style="width:35px">م</th><th>الصنف</th><th>اللون</th><th style="width:100px">الكمية</th>
            <th style="width:95px">الوحدة</th><th style="width:125px">سعر الوحدة</th><th style="width:120px">الإجمالي</th></tr></thead>
          <tbody>
          @foreach($row->lines as $i => $l)
            <tr>
              <td class="text-center">{{ $i+1 }}</td>
              <td>{{ $l->fabricType?->name }}
                @if($l->notes)<div class="hint">{{ $l->notes }}</div>@endif</td>
              <td>{{ $l->color?->name }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty,3),'0'),'.') }}</td>
              <td>
                <input type="hidden" name="prices[{{ $i }}][id]" value="{{ $l->id }}">
                <select name="prices[{{ $i }}][unit]" class="form-select form-select-sm">
                  @foreach(['طن','كيلو','متر'] as $u)
                    <option value="{{ $u }}" @selected($l->unit===$u || ($l->unit==='كجم' && $u==='كيلو'))>{{ $u }}</option>
                  @endforeach
                </select>
              </td>
              <td><input type="number" step="0.01" name="prices[{{ $i }}][unit_price]"
                         class="form-control form-control-sm" value="{{ $l->unit_price }}"></td>
              <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total,2) : '—' }}</td>
            </tr>
          @endforeach
          </tbody>
          <tfoot><tr class="table-light">
            <td colspan="5"></td>
            <td class="fw-bold">الإجمالي بعد الضريبة</td>
            <td class="num fw-bold">{{ $row->total > 0 ? number_format((float)$row->total,2) : '—' }}</td>
          </tr></tfoot>
        </table>
      </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2 align-items-center">
      <button class="btn btn-plum btn-sm px-4"><i class="bi bi-save" aria-hidden="true"></i> احفظ</button>
      @if($row->readyForFinance())
        <button type="button" class="btn btn-success btn-sm px-3"
                onclick="if(confirm('تنزيل الطلب للحسابات؟')) document.getElementById('toFinance').submit()">
          <i class="bi bi-arrow-left" aria-hidden="true"></i> نزّل للحسابات
        </button>
      @else
        <span class="hint">احفظ المورد وتاريخ التوريد الأول، وبعدها زرار «نزّل للحسابات» هيظهر.</span>
      @endif
    </div>
  </div>
</form>

<form id="toFinance" method="post" action="{{ route('purchase-orders.to-finance',$row) }}" class="d-none">@csrf</form>

@include('partials.comments')

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
