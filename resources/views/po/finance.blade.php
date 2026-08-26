@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'finance'])

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  المشتريات خلّصت التسعير. راجع المستحق وطريقة الدفع، واضغط <b>«علمت»</b> —
  مفيش اعتماد ولا توقيعات. بعدها الطلب عند المورد، وهتتابع الرصيد وتصفّيه
  دفعات أو كاش حسب الاتفاق.
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>علم الحسابات — {{ $row->po_no }}</span>
    <a href="{{ route('finance.payables') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع للحسابات</a>
  </div>
  <div class="card-body">
    <div class="row g-3 text-center mb-3">
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>number_format((float)$row->total, 2),
          'label'=>'المستحق المتوقع ('.config('lvplanning.currency').')', 'tone'=>'brand'])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->supplier?->name ?? '—', 'label'=>'المورد',
          'note'=>$row->supplier?->payment_terms])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->delivery_date?->format('Y-m-d') ?? '—', 'label'=>'تاريخ التوريد'])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->payment_method ?: '—', 'label'=>'طريقة الدفع'])
      </div>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm mb-0">
        <thead><tr><th>م</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
        <tbody>
        @foreach($row->lines as $i => $l)
          <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $l->fabricType?->name }}</td>
            <td>{{ $l->color?->name }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty,3),'0'),'.') }}</td>
            <td>{{ $l->unit }}</td>
            <td class="num">{{ number_format((float)$l->unit_price,2) }}</td>
            <td class="num">{{ number_format((float)$l->line_total,2) }}</td>
          </tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="6" class="text-start fw-bold">الخصم {{ rtrim(rtrim(number_format((float)$row->discount_pct,2),'0'),'.') }}%</td>
              <td class="num">{{ number_format((float)$row->discount_value,2) }}</td></tr>
          <tr><td colspan="6" class="text-start fw-bold">الضريبة {{ rtrim(rtrim(number_format((float)$row->tax_pct,2),'0'),'.') }}%</td>
              <td class="num">{{ number_format((float)$row->tax_value,2) }}</td></tr>
          <tr class="table-light"><td colspan="6" class="text-start fw-bold">الإجمالي بعد الضريبة</td>
              <td class="num fw-bold">{{ number_format((float)$row->total,2) }}</td></tr>
        </tfoot>
      </table>
    </div>

    <form method="post" action="{{ route('purchase-orders.finance-ack',$row) }}">@csrf
      <label class="form-label">ملاحظة الحسابات</label>
      <textarea name="finance_note" rows="2" class="form-control form-control-sm mb-2"
                placeholder="مثال: هيتصرف على دفعتين — الأولى مع التوريد"></textarea>
      <button class="btn btn-plum btn-sm px-4"><i class="bi bi-check2" aria-hidden="true"></i> علمت</button>
      <span class="hint ms-2">تسجيل علم بس — الطلب أصلًا ماشي عند المورد والمخزن.</span>
    </form>
  </div>
</div>

@endsection
