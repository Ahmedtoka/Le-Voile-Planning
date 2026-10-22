@extends('layouts.print')
@section('doc_title', 'إذن إضافة')
@section('doc_serial', $sa->paper_serial ?: $sa->doc_no)
@section('doc')

<table class="meta">
  <tr>
    <th>التاريخ</th><td class="v-num num">{{ $sa->doc_date?->format('d / n / Y') }}</td>
    <th>اسم المورد</th><td>{{ $sa->supplier?->name }}</td>
    <th>اسم المخزن</th><td class="v-lg">{{ $sa->warehouse?->code }} — {{ $sa->warehouse?->name }}</td>
  </tr>
  <tr>
    <th>رقم الرسالة</th><td class="code">{{ $sa->consignment_no ?: $sa->consignment?->consignment_no }}</td>
    <th>رقم إذن المورد</th><td class="code">{{ $sa->supplier_doc_no ?: '—' }}</td>
    <th>الحالة</th><td>{{ $sa->receipt_type === 'container' ? 'استلام مباشر — بدون فحص' : 'تحت الفحص' }}</td>
  </tr>
</table>

<table class="grid">
  <colgroup>
    <col style="width:32px"><col style="width:100px"><col><col style="width:62px">
    <col style="width:85px"><col style="width:62px"><col style="width:150px">
  </colgroup>
  <thead><tr>
    <th>م</th><th>كود الصنف</th><th>اسم الصنف</th>
    <th>ع. أتواب</th><th>الكمية</th><th>الوحدة</th><th>ملاحظات</th>
  </tr></thead>
  <tbody>
    @foreach($sa->lines as $i => $l)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td class="code">{{ $l->item_code }}</td>
        <td class="r">{{ $l->item_name ?: ($l->fabricType?->name ?? $l->accessory?->name) }}
          @if($l->color) — {{ $l->color->name }} @endif</td>
        <td class="num">{{ $l->rolls_count ?: '' }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->unit }}</td>
        <td class="r">{{ $l->notes }}</td>
      </tr>
    @endforeach
    @for($i = $sa->lines->count(); $i < 20; $i++)
      <tr class="blank"><td class="num">{{ $i + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total">
      <td colspan="3" class="r">الإجمالي</td>
      <td class="num">{{ $sa->total_rolls }}</td>
      <td class="num">{{ rtrim(rtrim(number_format((float)$sa->total_qty, 3), '0'), '.') }}</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

<div class="sigs">
  <div class="sig">توقيع المورد<div class="line"></div></div>
  <div class="sig">توقيع أمين المخزن<div class="line"></div></div>
</div>
@endsection
