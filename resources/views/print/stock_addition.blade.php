@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo">Le Voile<small>FASHION FORWARD</small></div>
  <div class="doc-title">إذن إضافة</div>
  <div style="width:150px;text-align:left"><span class="serial">{{ $sa->paper_serial ?: $sa->doc_no }}</span></div>
</div>

<table class="meta" style="width:100%">
  <tr>
    <td class="k">التاريخ</td><td style="width:140px">{{ $sa->doc_date?->format('d / n / Y') }}</td>
    <td class="k">إسم المورد</td><td>{{ $sa->supplier?->name }}</td>
    <td class="k">إسم المخزن</td><td style="width:140px">{{ $sa->warehouse?->code }} — {{ $sa->warehouse?->name }}</td>
  </tr>
  <tr>
    <td class="k">رقم الرسالة</td><td colspan="5">{{ $sa->consignment_no ?: $sa->consignment?->consignment_no }}</td>
  </tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:35px">م</th><th style="width:110px">كــود الصنــف</th><th>اســم الصنف</th>
    <th style="width:90px">الكميــة</th><th style="width:70px">الوحــدة</th><th style="width:180px">ملاحظات</th>
  </tr></thead>
  <tbody>
    @foreach($sa->lines as $i => $l)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $l->item_code }}</td>
        <td class="r">{{ $l->item_name ?: ($l->fabricType?->name ?? $l->accessory?->name) }}
          @if($l->color) — {{ $l->color->name }} @endif</td>
        <td>{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->unit }}</td>
        <td class="r">{{ $l->notes }}</td>
      </tr>
    @endforeach
    @for($i = $sa->lines->count(); $i < 20; $i++)
      <tr><td>{{ $i + 1 }}</td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr><td colspan="3"><b>الإجمالى</b></td>
        <td><b>{{ rtrim(rtrim(number_format((float)$sa->total_qty, 3), '0'), '.') }}</b></td>
        <td colspan="2"></td></tr>
  </tbody>
</table>

<div class="sigs">
  <div class="sig">توقيع المورد<div class="line"></div></div>
  <div class="sig">توقيع أمين المخزن<div class="line"></div></div>
</div>
@endsection
