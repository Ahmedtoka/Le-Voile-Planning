@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt="Le Voile"><small>FASHION FORWARD</small></div>
  <div class="doc-title">اذن استلام خام</div>
  <div style="width:170px;text-align:left">
    <span class="serial">{{ $gr->paper_serial ?: $gr->doc_no }}</span>
    <div style="font-size:11px">{{ $gr->warehouse?->name }}</div>
  </div>
</div>

<table class="meta" style="width:100%">
  <tr>
    <td class="k">التاريخ</td><td style="width:130px">{{ $gr->doc_date?->format('d / n / Y') }}</td>
    <td class="k">وارد من</td><td>{{ $gr->supplier?->name }}</td>
    <td class="k">كود المورد</td><td style="width:90px">{{ $gr->supplier?->code }}</td>
    <td class="k">أمر المشتريات</td><td style="width:110px">{{ $gr->purchaseOrder?->po_no ?: '—' }}</td>
  </tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:90px">الكود</th><th>الصنـــف</th><th style="width:100px">اللون</th>
    <th style="width:60px">الوحدة</th><th style="width:60px">العرض</th><th style="width:65px">ع.أتواب</th>
    <th style="width:80px">الكمية</th><th style="width:160px">رقم الرسالة</th>
  </tr></thead>
  <tbody>
    @foreach($gr->lines as $l)
      <tr>
        <td>{{ $l->item_code }}</td>
        <td class="r">{{ $l->fabricType?->name }}</td>
        <td>{{ $l->color?->name ?? $l->color?->code }}</td>
        <td>{{ $l->unit }}</td>
        <td>{{ $l->width_cm ? rtrim(rtrim(number_format((float)$l->width_cm,2),'0'),'.') : '' }}</td>
        <td>{{ $l->rolls_count }}</td>
        <td>{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->consignment_no }}</td>
      </tr>
    @endforeach
    @for($i = $gr->lines->count(); $i < 12; $i++)
      <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr>
      <td colspan="5"><b>الإجمالي</b></td>
      <td><b>{{ $gr->total_rolls }}</b></td>
      <td><b>{{ rtrim(rtrim(number_format((float)$gr->total_qty, 3), '0'), '.') }}</b></td>
      <td></td>
    </tr>
  </tbody>
</table>

<div class="sigs">
  <div class="sig">مندوب المورد<div class="line">{{ $gr->supplier_rep }}</div></div>
  <div class="sig">أمين المخزن<div class="line"></div></div>
  <div class="sig">مدير المخزن<div class="line"></div></div>
  <div class="sig">مراقب المخزون<div class="line"></div></div>
</div>
@endsection
