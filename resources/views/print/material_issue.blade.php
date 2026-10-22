@extends('layouts.print')
@section('doc_tagline', 'IN LOVE OF HIJAB')
@section('doc_title', 'إذن صرف خام')
@section('doc_serial', $mi->paper_serial ?: $mi->doc_no)
@section('doc_serial_note', $mi->warehouse?->name ?? '')
@section('doc')

<table class="meta">
  <tr>
    <th>التاريخ</th><td class="v-num num">{{ $mi->doc_date?->format('Y / n / j') }}</td>
    <th>منصرف إلى</th><td>{{ $mi->issued_to ?: $mi->factory?->name }}</td>
    <th>رقم أمر الشغل</th>
    <td class="code v-lg">{{ $mi->lines->pluck('workOrder.wo_no')->filter()->unique()->implode('، ') ?: '—' }}</td>
  </tr>
</table>

<table class="grid">
  <colgroup>
    <col style="width:90px"><col><col style="width:70px"><col style="width:52px"><col style="width:52px">
    <col style="width:58px"><col style="width:75px"><col style="width:150px"><col style="width:70px">
  </colgroup>
  <thead><tr>
    <th>الكود</th><th>الصنف</th><th>اللون</th><th>الوحدة</th><th>العرض</th>
    <th>ع. أتواب</th><th>الكمية</th><th>رقم الرسالة</th><th>أمر الشغل</th>
  </tr></thead>
  <tbody>
    @foreach($mi->lines as $l)
      <tr>
        <td class="code">{{ $l->item_code }}</td>
        <td class="r">{{ $l->fabricType?->name }}</td>
        <td class="code">{{ $l->color?->code }}</td>
        <td>{{ $l->unit }}</td>
        <td class="num">{{ $l->width_cm ? rtrim(rtrim(number_format((float) $l->width_cm, 2), '0'), '.') : '' }}</td>
        <td class="num">{{ $l->rolls_count ?: '' }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float) $l->qty, 3), '0'), '.') }}</td>
        <td class="code">{{ $l->consignment_no }}</td>
        <td class="code">{{ $l->workOrder?->wo_no }}</td>
      </tr>
    @endforeach
    @for($i = $mi->lines->count(); $i < 12; $i++)
      <tr class="blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total">
      <td colspan="5" class="r">الإجمالي</td>
      <td class="num">{{ $mi->total_rolls }}</td>
      <td class="num">{{ rtrim(rtrim(number_format((float) $mi->total_qty, 3), '0'), '.') }}</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

@if($mi->notes)<div class="note"><span class="lbl">ملاحظات:</span> {{ $mi->notes }}</div>@endif

<div class="sigs">
  <div class="sig">المستلم<div class="line">{{ $mi->receiver_name }}</div></div>
  <div class="sig">أمين المخزن<div class="line"></div></div>
  <div class="sig">مدير المخزن<div class="line"></div></div>
  <div class="sig">مراقب المخزون<div class="line"></div></div>
</div>
@endsection
