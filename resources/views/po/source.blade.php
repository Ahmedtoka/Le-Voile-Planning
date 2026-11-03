@extends('layouts.app')
@section('purpose', 'سكّن الكمية مع مورد: المورد والكمية والسعر وتاريخ التوريد — والباقي من غير مورد يفضل مستني تسعير')
@section('content')
@php
  $cur = config('lvplanning.currency');
  $unsourcedTotal = (float) $row->lines->sum(fn ($l) => $l->unsourced_qty);
@endphp

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'sourcing'])

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الطلب ممكن يتسكّن على <b>أكتر من مورد وأكتر من مرة</b>: اكتب لكل صنف الكمية اللي هتتسكّن مع المورد ده وسعرها،
  واضغط <b>«سكّن»</b>. اللي اتسكّن بيبقى جاهز للاستلام فورًا، والباقي من غير مورد يفضل في «مستنية تسعير» لحد ما تسكّنه بعدين.</span>
</div>

{{-- التسكينات اللي اتعملت قبل كده --}}
@if($row->sourcings->count())
  <div class="card mb-3">
    <div class="card-header"><span>التسكينات اللي اتعملت على الطلب <span class="pill pill-muted num">{{ $row->sourcings->count() }}</span></span></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>المورد</th><th>توريد</th><th>الدفع</th><th>الأصناف</th><th>الإجمالي ({{ $cur }})</th><th>سكّنها</th></tr></thead>
        <tbody>
        @foreach($row->sourcings as $s)
          <tr>
            <td class="num">{{ $s->seq }}</td>
            <td>{{ $s->supplier?->name }}</td>
            <td class="num">{{ $s->delivery_date?->format('Y-m-d') }}</td>
            <td>{{ $s->payment_method }}</td>
            <td class="hint">
              @foreach($s->lines as $sl)
                {{ $sl->poLine?->fabricType?->name }} {{ $sl->poLine?->color?->code }}: <b class="num">{{ lv_num($sl->qty, 3) }} {{ $sl->unit }}</b> × {{ number_format((float) $sl->unit_price, 2) }}
                @if($sl->received_qty > 0)<span class="pill pill-ok">وصل {{ lv_num($sl->received_qty, 3) }}</span>@endif
                @if(!$loop->last) · @endif
              @endforeach
            </td>
            <td class="num fw-bold">{{ number_format((float) $s->total, 2) }}</td>
            <td class="hint">{{ $s->sourcer?->name }} <span class="num">{{ $s->sourced_at?->format('Y-m-d') }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<form method="post" action="{{ route('purchase-orders.sourcing', $row) }}">@csrf
  <div class="card mb-3">
    <div class="card-header">
      <span>{{ $row->sourcings->count() ? 'تسكينة جديدة — الباقي ' . lv_num($unsourcedTotal, 3) : 'تسعير الطلب' }} <span class="num">{{ $row->po_no }}</span></span>
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
          @if($row->target_pieces)<div><div class="k">المستهدف</div><div class="v num">{{ number_format($row->target_pieces) }} قطعة</div></div>@endif
          @if($row->models_label)
            <div><div class="k">للموديلات</div><div class="v">{{ $row->models_label }}</div></div>
          @endif
          @if($row->planning_note)
            <div><div class="k">سبب الطلب</div><div class="v">«{{ $row->planning_note }}»</div></div>
          @endif
        </div>
      </div>

      @error('prices')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

      {{-- اللي بتكتبه --}}
      <div class="row g-3">
        <div class="col-md-5"><label class="form-label req" for="sup">المورد</label>
          <select name="supplier_id" id="sup" class="form-select" required onchange="fillSupplier()">
            <option value="">— اختر —</option>
            @foreach($suppliers as $s)
              <option value="{{ $s->id }}" data-code="{{ $s->code }}" data-person="{{ $s->contact_person }}"
                      data-phone="{{ $s->phone }}" data-addr="{{ $s->address }}" data-terms="{{ $s->payment_terms }}"
                      @selected(old('supplier_id')==$s->id)>{{ $s->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req" for="delivery_date">تاريخ التوريد</label>
          <input type="date" name="delivery_date" id="delivery_date" class="form-control w-date"
                 value="{{ old('delivery_date') }}" required></div>
        <div class="col-md-4"><label class="form-label" for="warehouse_id">المخزن</label>
          <select name="warehouse_id" id="warehouse_id" class="form-select"><option value="">—</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id', $row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
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
              <option value="{{ $k }}" @selected(old('payment_method')===$k)>{{ $v }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label" for="discount_pct">الخصم</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="discount_pct" id="discount_pct" class="form-control"
                   value="{{ old('discount_pct', 0) }}" placeholder="مثال: 0" oninput="LVS.calc()">
            <span class="input-group-text">%</span>
          </div></div>
        <div class="col-md-2"><label class="form-label" for="tax_pct">الضريبة</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="tax_pct" id="tax_pct" class="form-control"
                   value="{{ old('tax_pct', 14) }}" placeholder="مثال: 14" oninput="LVS.calc()">
            <span class="input-group-text">%</span>
          </div></div>
        <div class="col-md-4"><label class="form-label" for="notes">ملاحظة على التسكينة</label>
          <input name="notes" id="notes" class="form-control" value="{{ old('notes') }}" placeholder="اختياري — مثال: الباقي مع مورد تاني الأسبوع الجاي"></div>
      </div>

      <div class="section-head"><h2>الأصناف والكميات والأسعار</h2><span class="hint">الصنف واللون من الطلب — اكتب الكمية اللي هتتسكّن مع المورد ده وسعر الوحدة. سيب الكمية صفر للصنف اللي مش هيتسكّن دلوقتي</span></div>
      <div class="table-responsive">
        <table class="table table-sm line-table mb-0" id="srcLines">
          <thead><tr><th style="width:35px">م</th><th>الصنف</th><th>اللون</th><th style="width:110px">المطلوب</th>
            <th style="width:120px">اتسكّن قبل كده</th><th class="req" style="width:140px">يتسكّن دلوقتي</th>
            <th class="req" style="width:150px">سعر الوحدة ({{ $cur }})</th><th style="width:140px">الإجمالي ({{ $cur }})</th></tr></thead>
          <tbody>
          @foreach($row->lines as $i => $l)
            @php $left = $l->unsourced_qty; $old = old('prices.' . $i); @endphp
            <tr class="{{ $left <= 0 ? 'text-muted' : '' }}" data-left="{{ $left }}">
              <td class="text-center row-no">{{ $i+1 }}</td>
              <td>{{ $l->fabricType?->name }}
                @if($l->notes)<div class="hint">{{ $l->notes }}</div>@endif</td>
              <td>{{ $l->color?->label ?? $l->color?->name }}</td>
              <td class="num">{{ lv_num($l->qty, 3) }} <span class="hint">{{ $l->unit }}</span></td>
              <td class="num">{{ $l->sourced_qty > 0 ? lv_num($l->sourced_qty, 3) : '—' }}
                @if($l->sourced_qty > 0 && $left <= 0)<div class="pill pill-ok">اتسكّن كله</div>@endif</td>
              <td>
                <input type="hidden" name="prices[{{ $i }}][id]" value="{{ $l->id }}">
                @if($left > 0)
                  <div class="input-group input-group-sm">
                    <input type="number" step="0.001" min="0" max="{{ $left }}" name="prices[{{ $i }}][qty]" class="form-control s-qty"
                           value="{{ $old['qty'] ?? '' }}" placeholder="0" aria-label="الكمية اللي هتتسكّن — الباقي {{ lv_num($left, 3) }}" oninput="LVS.calc()">
                    <span class="input-group-text">{{ $l->unit }}</span>
                  </div>
                  <div class="hint">الباقي من غير مورد <span class="num">{{ lv_num($left, 3) }}</span></div>
                @else
                  <input type="hidden" name="prices[{{ $i }}][qty]" value="0">
                  <span class="hint">—</span>
                @endif
              </td>
              <td class="num">
                @if($left > 0)
                  {{-- السعر للمورد ده — آخر سعر (من مورد تاني غالبًا) لابل للمقارنة بس، مش قيمة جاهزة --}}
                  <input type="number" step="0.01" min="0" name="prices[{{ $i }}][unit_price]" class="form-control form-control-sm s-price"
                         value="{{ $old['unit_price'] ?? '' }}" placeholder="0" aria-label="سعر الوحدة" oninput="LVS.calc()">
                  @if($l->unit_price > 0)<div class="hint">آخر سعر <span class="num">{{ lv_num($l->unit_price, 2) }}</span></div>@endif
                @else — @endif
              </td>
              <td class="num s-total">—</td>
            </tr>
          @endforeach
          </tbody>
          <tfoot>
            <tr><td colspan="6"></td><td>قبل الخصم</td><td class="num" id="sSub">—</td></tr>
            <tr><td colspan="6"></td><td>الإجمالي بعد الخصم والضريبة</td><td class="num fw-bold" id="sTot">—</td></tr>
          </tfoot>
        </table>
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-check2" aria-hidden="true"></i> سكّن مع المورد</button>
      <span class="hint">
        اللي اتسكّن يبقى مستني الاستلام في المخزن فورًا والحسابات توصلها الترانزاكشن —
        والباقي من غير مورد يفضل هنا لحد ما تسكّنه.
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
/* الإجماليات لايف: الكمية × السعر لكل سطر، ثم الخصم والضريبة على التسكينة دي */
window.LVS = (function () {
  var nf = function (v) { return Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
  function calc() {
    var sub = 0;
    document.querySelectorAll('#srcLines tbody tr').forEach(function (tr) {
      var q = Number(tr.querySelector('.s-qty')?.value) || 0, p = Number(tr.querySelector('.s-price')?.value) || 0;
      var t = q * p; sub += t;
      var cell = tr.querySelector('.s-total'); if (cell) cell.textContent = t > 0 ? nf(t) : '—';
      var left = Number(tr.dataset.left) || 0;
      tr.classList.toggle('table-danger', q > left + 0.0005);
    });
    var d = Number(document.getElementById('discount_pct').value) || 0, x = Number(document.getElementById('tax_pct').value) || 0;
    var tot = (sub - sub * d / 100) * (1 + x / 100);
    document.getElementById('sSub').textContent = sub > 0 ? nf(sub) : '—';
    document.getElementById('sTot').textContent = sub > 0 ? nf(tot) : '—';
  }
  return {calc: calc};
})();
document.addEventListener('DOMContentLoaded', function () { fillSupplier(); LVS.calc(); });
</script>
@endpush
@endsection
