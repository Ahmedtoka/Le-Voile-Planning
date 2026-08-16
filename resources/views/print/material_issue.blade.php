@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt="Le Voile"><small>IN LOVE OF HIJAB</small></div>
  <div class="doc-title">اذن صرف خام</div>
  <div style="width:170px;text-align:left">
    <span class="serial">{{ $mi->paper_serial ?: $mi->doc_no }}</span>
    <div style="font-size:11px">{{ $mi->warehouse?->name }}</div>
  </div>
</div>

<table class="meta" style="width:100%">
  <tr>
    <td class="k">التاريخ</td><td style="width:150px">{{ $mi->doc_date?->format('Y / n / j') }}</td>
    <td class="k">منصرف إلى</td><td>{{ $mi->issued_to ?: $mi->factory?->name }}</td>
    <td class="k">رقم أمر الشغل</td>
    <td style="width:150px">{{ $mi->lines->pluck('workOrder.wo_no')->filter()->unique()->implode('، ') ?: '—' }}</td>
  </tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:95px">الكود</th><th>الصنــف</th><th style="width:80px">اللون</th>
    <th style="width:60px">الوحدة</th><th style="width:60px">العرض</th><th style="width:60px">ع.أتواب</th>
    <th style="width:80px">الكمية</th><th style="width:180px">رقم الرسالة</th><th style="width:70px">أمر الشغل</th>
  </tr></thead>
  <tbody>
    @foreach($mi->lines as $l)
      <tr>
        <td>{{ $l->item_code }}</td>
        <td class="r">{{ $l->fabricType?->name }}</td>
        <td>{{ $l->color?->code }}</td>
        <td>{{ $l->unit }}</td>
        <td>{{ $l->width_cm ? rtrim(rtrim(number_format((float) $l->width_cm, 2), '0'), '.') : '' }}</td>
        <td>{{ $l->rolls_count ?: '' }}</td>
        <td>{{ rtrim(rtrim(number_format((float) $l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->consignment_no }}</td>
        <td>{{ $l->workOrder?->wo_no }}</td>
      </tr>
    @endforeach
    @for($i = $mi->lines->count(); $i < 12; $i++)
      <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr>
      <td colspan="5"><b>الإجمالي</b></td>
      <td><b>{{ $mi->total_rolls }}</b></td>
      <td><b>{{ rtrim(rtrim(number_format((float) $mi->total_qty, 3), '0'), '.') }}</b></td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

@if($mi->notes)<div class="terms">ملاحظات: {{ $mi->notes }}</div>@endif

<div class="sigs">
  <div class="sig">المستلم<div class="line">{{ $mi->receiver_name }}</div></div>
  <div class="sig">أمين المخزن<div class="line"></div></div>
  <div class="sig">مدير المخزن<div class="line"></div></div>
  <div class="sig">مراقب المخزون<div class="line"></div></div>
</div>
@endsection
