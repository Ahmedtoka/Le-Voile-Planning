@extends('layouts.print')
@section('doc_title', 'إذن استلام خام')
@section('doc_serial', $gr->paper_serial ?: $gr->doc_no)
@section('doc_serial_note', $gr->warehouse?->name ?? '')
@section('doc')

<table class="meta">
  <tr>
    <th>التاريخ</th><td class="v-num num">{{ $gr->doc_date?->format('d / n / Y') }}</td>
    <th>وارد من</th><td>{{ $gr->supplier?->name }}</td>
    <th>كود المورد</th><td class="code v-sm">{{ $gr->supplier?->code }}</td>
    <th>أمر المشتريات</th><td class="code v-md">{{ $gr->purchaseOrder?->po_no ?: '—' }}</td>
  </tr>
</table>

<table class="grid">
  <colgroup>
    <col style="width:90px"><col><col style="width:95px"><col style="width:55px">
    <col style="width:55px"><col style="width:60px"><col style="width:80px"><col style="width:150px">
  </colgroup>
  <thead><tr>
    <th>الكود</th><th>الصنف</th><th>اللون</th><th>الوحدة</th>
    <th>العرض</th><th>ع. أتواب</th><th>الكمية</th><th>رقم الرسالة</th>
  </tr></thead>
  <tbody>
    @foreach($gr->lines as $l)
      <tr>
        <td class="code">{{ $l->item_code }}</td>
        <td class="r">{{ $l->fabricType?->name }}</td>
        <td>{{ $l->color?->name ?? $l->color?->code }}</td>
        <td>{{ $l->unit }}</td>
        <td class="num">{{ $l->width_cm ? rtrim(rtrim(number_format((float)$l->width_cm,2),'0'),'.') : '' }}</td>
        <td class="num">{{ $l->rolls_count }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td class="code">{{ $l->consignment_no }}</td>
      </tr>
    @endforeach
    @for($i = $gr->lines->count(); $i < 12; $i++)
      <tr class="blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total">
      <td colspan="5" class="r">الإجمالي</td>
      <td class="num">{{ $gr->total_rolls }}</td>
      <td class="num">{{ rtrim(rtrim(number_format((float)$gr->total_qty, 3), '0'), '.') }}</td>
      <td></td>
    </tr>
  </tbody>
</table>

@php $rej = $gr->consignment?->rejections()->where('goods_receipt_id', $gr->id)->get() ?? collect(); @endphp
@if($rej->count())
  <div class="box warn">
    @foreach($rej as $r)
      <div>
        <span class="mark">✱</span>
        تم {{ $r->kind === 'on_hold' ? 'تعليق' : 'رفض' }}
        {{ $r->lot_label ?: ('كود ' . ($r->color_code ?: $r->color?->code)) }}
        عدد <span class="num">{{ $r->rolls_count }}</span> توب بوزن
        <span class="num">{{ rtrim(rtrim(number_format((float) $r->qty, 3), '0'), '.') }}</span> {{ $r->unit }}
        — {{ $r->party_name }}{{ $r->reason ? ' (' . $r->reason . ')' : '' }}
      </div>
    @endforeach
  </div>
@endif

<div class="sigs">
  <div class="sig">مندوب المورد<div class="line">{{ $gr->supplier_rep }}</div></div>
  <div class="sig">أمين المخزن<div class="line"></div></div>
  <div class="sig">مدير المخزن<div class="line"></div></div>
  <div class="sig">مراقب المخزون<div class="line"></div></div>
</div>
@endsection
