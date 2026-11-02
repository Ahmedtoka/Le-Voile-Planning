@extends('layouts.app')
@section('purpose', 'راجع المستحق وطريقة الدفع وسجّل علمك — الطلب أصلًا ماشي عند المورد والمخزن')
@section('content')
@php $cur = config('lvplanning.currency'); @endphp

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'finance'])

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>المشتريات خلّصت التسعير. راجع المستحق وطريقة الدفع، واضغط <b>«علمت»</b> —
  مفيش اعتماد ولا توقيعات. بعدها الطلب عند المورد، وهتتابع الرصيد وتصفّيه
  دفعات أو كاش حسب الاتفاق.</span>
</div>

<form method="post" action="{{ route('purchase-orders.finance-ack',$row) }}">@csrf
<div class="card mb-3">
  <div class="card-header">
    <span>علم الحسابات — <span class="num">{{ $row->po_no }}</span></span>
    <a href="{{ route('finance.payables') }}" class="btn btn-sm btn-outline-secondary ms-auto">
      <i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع للحسابات</a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>number_format((float)$row->total, 2),
          'label'=>'المستحق المتوقع ('.$cur.')', 'tone'=>'brand',
          'note'=>'قبل الخصم ' . number_format((float)$row->subtotal, 2) . ' − خصم ' . number_format((float)$row->discount_value, 2) . ' + ضريبة ' . number_format((float)$row->tax_value, 2)])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->supplier?->name ?? '—', 'label'=>'المورد',
          'note'=>$row->supplier?->payment_terms])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->delivery_date?->format('Y-m-d') ?? '—', 'label'=>'تاريخ التوريد',
          'note'=>$row->warehouse ? 'التسليم في ' . $row->warehouse->name : null])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$row->payment_method ?: '—', 'label'=>'طريقة الدفع'])
      </div>
    </div>

    <div class="ro-box mb-3">
      <div class="ro-title">من التسعير — للقراءة</div>
      <div class="sum-grid">
        <div><div class="k">طلبه</div><div class="v">{{ $row->requester?->name ?? '—' }}</div><div class="k num">{{ $row->requested_at?->format('Y-m-d') }}</div></div>
        <div><div class="k">سعّره</div><div class="v">{{ $row->sourcer?->name ?? '—' }}</div><div class="k num">{{ $row->sourced_at?->format('Y-m-d') }}</div></div>
        @if($row->models_label)<div><div class="k">للموديلات</div><div class="v">{{ $row->models_label }}</div></div>@endif
        @if($row->planning_note)<div><div class="k">سبب الطلب</div><div class="v">«{{ $row->planning_note }}»</div></div>@endif
      </div>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm mb-0">
        <thead><tr><th>م</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة ({{ $cur }})</th><th>الإجمالي ({{ $cur }})</th></tr></thead>
        <tbody>
        @foreach($row->lines as $i => $l)
          <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $l->fabricType?->name }}</td>
            <td>{{ $l->color?->name }}</td>
            <td class="num">{{ lv_num($l->qty, 3) }}</td>
            <td>{{ $l->unit }}</td>
            <td class="num">{{ number_format((float)$l->unit_price,2) }}</td>
            <td class="num">{{ number_format((float)$l->line_total,2) }}
              @include('partials.fx', ['t' => 'إجمالي السطر',
                'f' => 'الكمية ' . lv_num($l->qty, 3) . ' ' . $l->unit . ' × سعر الوحدة ' . number_format((float)$l->unit_price, 2) . ' = ' . number_format((float)$l->line_total, 2) . ' ' . $cur])
            </td>
          </tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="6" class="text-start">قبل الخصم</td>
              <td class="num">{{ number_format((float)$row->subtotal,2) }}
                @include('partials.fx', ['t' => 'الإجمالي قبل الخصم', 'f' => 'مجموع إجماليات السطور = ' . number_format((float)$row->subtotal, 2) . ' ' . $cur])</td></tr>
          <tr><td colspan="6" class="text-start">الخصم {{ lv_num($row->discount_pct, 2) }}%</td>
              <td class="num">{{ number_format((float)$row->discount_value,2) }}
                @include('partials.fx', ['t' => 'قيمة الخصم', 'f' => number_format((float)$row->subtotal, 2) . ' × ' . lv_num($row->discount_pct, 2) . '% = ' . number_format((float)$row->discount_value, 2) . ' ' . $cur])</td></tr>
          <tr><td colspan="6" class="text-start">الضريبة {{ lv_num($row->tax_pct, 2) }}%</td>
              <td class="num">{{ number_format((float)$row->tax_value,2) }}
                @include('partials.fx', ['t' => 'قيمة الضريبة', 'f' => '(' . number_format((float)$row->subtotal, 2) . ' − ' . number_format((float)$row->discount_value, 2) . ') × ' . lv_num($row->tax_pct, 2) . '% = ' . number_format((float)$row->tax_value, 2) . ' ' . $cur, 'why' => 'الضريبة على المبلغ بعد الخصم'])</td></tr>
          <tr><td colspan="6" class="text-start">الإجمالي بعد الضريبة</td>
              <td class="num">{{ number_format((float)$row->total,2) }}
                @include('partials.fx', ['t' => 'الإجمالي بعد الضريبة',
                  'f' => number_format((float)$row->subtotal, 2) . ' − خصم ' . number_format((float)$row->discount_value, 2) . ' + ضريبة ' . number_format((float)$row->tax_value, 2) . ' = ' . number_format((float)$row->total, 2) . ' ' . $cur])</td></tr>
        </tfoot>
      </table>
    </div>

    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label" for="finance_note">ملاحظة الحسابات <span class="hint">(اختياري)</span></label>
        <textarea name="finance_note" id="finance_note" rows="2" class="form-control"
                  placeholder="مثال: هيتصرف على دفعتين — الأولى مع التوريد">{{ old('finance_note') }}</textarea>
      </div>
    </div>
  </div>
  <div class="card-footer form-actions m-0">
    <button class="btn btn-plum"><i class="bi bi-check2" aria-hidden="true"></i> علمت</button>
    <span class="hint">تسجيل علم بس — الطلب أصلًا ماشي عند المورد والمخزن.</span>
    <div class="fa-end"><a href="{{ route('finance.payables') }}" class="btn btn-outline-secondary">رجوع</a></div>
  </div>
</div>
</form>

@endsection
