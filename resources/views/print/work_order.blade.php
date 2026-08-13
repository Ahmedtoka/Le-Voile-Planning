@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt="Le Voile"><small>FASHION FORWARD</small></div>
  <div class="doc-title">أمر شغل</div>
  <div style="width:150px;text-align:left"><span class="serial">{{ $wo->wo_no }}</span></div>
</div>

<table class="meta" style="width:100%">
  <tr><td class="k">التاريخ</td><td>{{ $wo->wo_date?->format('d / n / Y') }}</td>
      <td class="k">المصنع</td><td>{{ $wo->factory?->name }}</td>
      <td class="k">تاريخ التسليم</td><td>{{ $wo->due_date?->format('d / n / Y') ?: '—' }}</td></tr>
  <tr><td class="k">الحوض (الرسالة)</td><td>{{ $wo->consignment?->consignment_no }}</td>
      <td class="k">اللون</td><td>{{ $wo->consignment?->color?->code }} — {{ $wo->consignment?->color?->name }}</td>
      <td class="k">الماركر</td><td>{{ $wo->marker?->code }}</td></tr>
  <tr><td class="k">الكمية المخصصة</td><td>{{ number_format((float)$wo->allocated_kg, 2) }} كجم</td>
      <td class="k">عرض القماش</td><td>{{ $wo->input_min_width_cm }} سم</td>
      <td class="k">وزن البنشر</td><td>{{ $wo->input_avg_gsm }} جم/م²</td></tr>
  <tr><td class="k">طول الفرشة</td><td><b>{{ $wo->input_spread_length_m }} متر</b></td>
      <td class="k">قطع الفرشة</td><td>{{ $wo->input_pieces_per_spread }}</td>
      <td class="k">استهلاك القطعة</td><td>{{ $wo->kg_per_piece ? number_format((float)$wo->kg_per_piece*1000, 1) . ' جم' : '—' }}</td></tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:35px">م</th><th>الموديل</th><th style="width:110px">المقاس</th>
    <th style="width:110px">عدد القطع في الفرشة</th><th style="width:110px">الكمية المطلوبة</th><th style="width:150px">ملاحظات</th>
  </tr></thead>
  <tbody>
    @foreach($wo->lines as $i => $l)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td class="r">{{ $l->productModel?->name }}</td>
        <td>{{ $l->size?->name ?? 'كل المقاسات' }}</td>
        <td>{{ $l->qty_per_spread }}</td>
        <td><b>{{ number_format($l->planned_qty) }}</b></td>
        <td></td>
      </tr>
    @endforeach
    <tr><td colspan="4"><b>الإجمالي المتوقع</b></td>
        <td><b>{{ number_format((int)$wo->expected_pieces) }}</b></td><td></td></tr>
  </tbody>
</table>

<div class="terms">
  • يُفرش على طول الفرشة المحدد أعلاه بالظبط. أي زيادة في الطول بتقلل عدد الرِقّات وعدد القطع.<br>
  • يُلتزم بالماركر المرسل وبعرض القماش المحدد.<br>
  • يُبلَّغ فورًا بأي اختلاف في عرض القماش أو مشكلة في الخامة قبل البدء في القص.<br>
  • يُرسل بيان القص فور الانتهاء موضحًا طول الفرشة الفعلي وعدد الرِقّات.<br>
  • ملاحظات: {{ $wo->notes }}
</div>

<div class="sigs">
  <div class="sig">التخطيط<div class="line"></div></div>
  <div class="sig">مدير الإنتاج<div class="line"></div></div>
  <div class="sig">المصنع<div class="line"></div></div>
</div>
@endsection
