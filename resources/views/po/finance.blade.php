@extends('layouts.app')
@section('purpose', 'راجع المستحق وطريقة الدفع لأمر الشراء ده وسجّل علمك — الطلب أصلًا ماشي عند المورد والمخزن')
@section('content')
@php
  $cur  = config('lvplanning.currency');
  /* أمر الشراء = تسكينة: مورد واحد بسطوره وأسعاره وخصمه وضريبته */
  $sub  = (float) $src->subtotal;
  $disc = round($sub * (float) $src->discount_pct / 100, 2);
  $tot  = (float) $src->total;
  $tax  = round($tot - $sub + $disc, 2);
  $pct  = fn ($v) => lv_num($v, 2);
  $orders = $row->sourcings;
@endphp

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'finance'])

@include('partials.po_stepper')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>المشتريات خلّصت التسعير. راجع المستحق وطريقة الدفع لأمر الشراء ده، واضغط <b>«علمت»</b> —
  مفيش اعتماد ولا توقيعات.
  @if($orders->count() > 1) الطلب <b class="num">{{ $row->po_no }}</b> اتشرى من <b>{{ $orders->count() }} موردين</b> — كل أمر بمورده وأسعاره، وبتعلم بكل واحد لوحده.@endif
  </span>
</div>

<form method="post" action="{{ route('finance.supplier-order.ack', $src) }}">@csrf
<div class="card mb-3">
  <div class="card-header">
    <span>علم الحسابات — أمر الشراء <span class="num">{{ $src->label }}</span></span>
    <div class="d-flex gap-2 ms-auto">
      <a href="{{ route('purchase-orders.print', [$row, 'order' => $src->seq]) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon"
         title="طباعة أمر الشراء ده" aria-label="طباعة أمر الشراء {{ $src->label }}"><i class="bi bi-printer" aria-hidden="true"></i></a>
      <a href="{{ route('finance.payables') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع للحسابات</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>number_format($tot, 2),
          'label'=>'المستحق المتوقع ('.$cur.')', 'tone'=>'brand',
          'note'=>'قبل الخصم ' . number_format($sub, 2) . ' − خصم ' . number_format($disc, 2) . ' + ضريبة ' . number_format($tax, 2)])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$src->supplier?->name ?? '—', 'label'=>'المورد',
          'note'=>$src->supplier?->payment_terms])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$src->delivery_date?->format('Y-m-d') ?? '—', 'label'=>'تاريخ التوريد',
          'note'=>$src->warehouse ? 'التسليم في ' . $src->warehouse->name : null])
      </div>
      <div class="col-md-3">
        @include('partials.kpi', ['value'=>$src->payment_method ?: '—', 'label'=>'طريقة الدفع'])
      </div>
    </div>

    <div class="ro-box mb-3">
      <div class="ro-title">من الطلب والتسعير — للقراءة</div>
      <div class="sum-grid">
        <div><div class="k">طلب الشراء</div><div class="v"><a href="{{ route('purchase-orders.edit', $row) }}" class="num">{{ $row->po_no }}</a></div></div>
        <div><div class="k">طلبه</div><div class="v">{{ $row->requester?->name ?? '—' }}</div><div class="k num">{{ $row->requested_at?->format('Y-m-d') }}</div></div>
        <div><div class="k">سكّنه</div><div class="v">{{ $src->sourcer?->name ?? '—' }}</div><div class="k num">{{ $src->sourced_at?->format('Y-m-d') }}</div></div>
        @if($row->models_label)<div><div class="k">للموديلات</div><div class="v">{{ $row->models_label }}</div></div>@endif
        @if($src->notes)<div><div class="k">ملاحظة التسكين</div><div class="v">«{{ $src->notes }}»</div></div>@endif
      </div>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm mb-0">
        <thead><tr><th>م</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة ({{ $cur }})</th><th>الإجمالي ({{ $cur }})</th></tr></thead>
        <tbody>
        @foreach($src->lines as $i => $l)
          <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $l->poLine?->fabricType?->name }}</td>
            <td>{{ $l->poLine?->color?->name }}</td>
            <td class="num">{{ lv_num($l->qty, 3) }}
              @if($l->poLine && (float) $l->poLine->qty > (float) $l->qty + 0.0005)
                <div class="hint">من {{ lv_num($l->poLine->qty, 3) }} على الطلب</div>
              @endif</td>
            <td>{{ $l->unit }}</td>
            <td class="num">{{ number_format((float) $l->unit_price, 2) }}</td>
            <td class="num">{{ number_format((float) $l->line_total, 2) }}
              @include('partials.fx', ['t' => 'إجمالي السطر',
                'f' => 'الكمية ' . lv_num($l->qty, 3) . ' ' . $l->unit . ' × سعر الوحدة ' . number_format((float) $l->unit_price, 2) . ' = ' . number_format((float) $l->line_total, 2) . ' ' . $cur])
            </td>
          </tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="6" class="text-start">قبل الخصم</td>
              <td class="num">{{ number_format($sub, 2) }}
                @include('partials.fx', ['t' => 'الإجمالي قبل الخصم', 'f' => 'مجموع إجماليات سطور الأمر = ' . number_format($sub, 2) . ' ' . $cur])</td></tr>
          <tr><td colspan="6" class="text-start">الخصم {{ $pct($src->discount_pct) }}%</td>
              <td class="num">{{ number_format($disc, 2) }}
                @include('partials.fx', ['t' => 'قيمة الخصم', 'f' => number_format($sub, 2) . ' × ' . $pct($src->discount_pct) . '% = ' . number_format($disc, 2) . ' ' . $cur])</td></tr>
          <tr><td colspan="6" class="text-start">الضريبة {{ $pct($src->tax_pct) }}%</td>
              <td class="num">{{ number_format($tax, 2) }}
                @include('partials.fx', ['t' => 'قيمة الضريبة', 'f' => '(' . number_format($sub, 2) . ' − ' . number_format($disc, 2) . ') × ' . $pct($src->tax_pct) . '% = ' . number_format($tax, 2) . ' ' . $cur, 'why' => 'الضريبة على المبلغ بعد الخصم'])</td></tr>
          <tr><td colspan="6" class="text-start">الإجمالي بعد الضريبة</td>
              <td class="num fw-bold">{{ number_format($tot, 2) }}
                @include('partials.fx', ['t' => 'الإجمالي بعد الضريبة',
                  'f' => number_format($sub, 2) . ' − خصم ' . number_format($disc, 2) . ' + ضريبة ' . number_format($tax, 2) . ' = ' . number_format($tot, 2) . ' ' . $cur])</td></tr>
        </tfoot>
      </table>
    </div>

    @if($orders->count() > 1)
      {{-- باقي أوامر نفس الطلب — كل مورد وإجماليه وحالة علمه --}}
      <div class="mb-3">
        <div class="fw-bold mb-2">أوامر الشراء على الطلب <span class="num">{{ $row->po_no }}</span></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>أمر الشراء</th><th>المورد</th><th>توريد</th><th>الإجمالي ({{ $cur }})</th><th>علم الحسابات</th></tr></thead>
            <tbody>
            @foreach($orders as $o)
              <tr class="{{ $o->id === $src->id ? 'table-active' : '' }}">
                <td class="num fw-bold">{{ $o->label }}</td>
                <td>{{ $o->supplier?->name }}</td>
                <td class="num">{{ $o->delivery_date?->format('Y-m-d') ?? '—' }}</td>
                <td class="num">{{ number_format((float) $o->total, 2) }}</td>
                <td>
                  @if($o->finance_at)<span class="badge bg-success">اتشاف <span class="num">{{ $o->finance_at->format('Y-m-d') }}</span></span>
                  @elseif($o->id === $src->id)<span class="hint">بتعلم بيه دلوقتي</span>
                  @else<a href="{{ route('finance.supplier-order', $o) }}">لسه — افتحه</a>@endif
                </td>
              </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="3" class="text-start">إجمالي الطلب كله</td><td class="num fw-bold">{{ number_format((float) $orders->sum('total'), 2) }}</td><td></td></tr></tfoot>
          </table>
        </div>
      </div>
    @endif

    @if($src->finance_at)
      <div class="ro-box">
        <div class="ro-title">علم الحسابات</div>
        <div class="v">{{ $src->financer?->name ?? '—' }} <span class="hint num">{{ $src->finance_at->format('Y-m-d H:i') }}</span>
          @if($src->finance_note)<div class="hint">«{{ $src->finance_note }}»</div>@endif</div>
      </div>
    @else
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label" for="finance_note">ملاحظة الحسابات <span class="hint">(اختياري)</span></label>
          <textarea name="finance_note" id="finance_note" rows="2" class="form-control"
                    placeholder="مثال: هيتصرف على دفعتين — الأولى مع التوريد">{{ old('finance_note') }}</textarea>
        </div>
      </div>
    @endif
  </div>
  <div class="card-footer form-actions m-0">
    @unless($src->finance_at)
      <button class="btn btn-plum"><i class="bi bi-check2" aria-hidden="true"></i> علمت</button>
      <span class="hint">تسجيل علم بأمر الشراء ده بس — الطلب أصلًا ماشي عند المورد والمخزن.</span>
    @endunless
    <div class="fa-end"><a href="{{ route('finance.payables') }}" class="btn btn-outline-secondary">رجوع</a></div>
  </div>
</div>
</form>

@endsection
