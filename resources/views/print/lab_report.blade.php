@extends('layouts.print')
@section('doc_title', 'تقرير انكماش قماش ومطابقة ألوان')
@section('doc_serial', $lab->paper_serial ?: $lab->doc_no)
@section('doc')

<table class="grid">
  <colgroup>
    <col style="width:130px"><col style="width:140px"><col><col style="width:105px">
    <col style="width:85px"><col style="width:120px">
  </colgroup>
  <thead><tr>
    <th>الرسالة</th><th>اسم المورد</th><th>اسم الخامة</th>
    <th>اللون</th><th>التاريخ</th><th>وزن البنشر</th>
  </tr></thead>
  <tbody>
    <tr>
      <td class="code">{{ $lab->consignment?->consignment_no }}</td>
      <td>{{ $lab->supplier?->name }}</td>
      <td>{{ $lab->fabricType?->name }}</td>
      <td>{{ $lab->color?->name }}</td>
      <td class="num">{{ $lab->doc_date?->format('Y/n/j') }}</td>
      <td rowspan="2" class="top">
        @foreach($lab->readings as $r)
          <div><span class="num">{{ $r->roll_no }}</span>: <span class="num">{{ rtrim(rtrim(number_format((float)$r->gsm,2),'0'),'.') }}</span></div>
        @endforeach
        <span class="cell-sep">متوسط: <span class="num">{{ $lab->avg_gsm }}</span></span>
      </td>
    </tr>
    <tr>
      <td colspan="5" class="tall">
        مكان مقارنة اللون
        @if($lab->color_swatch_path)
          <div class="swatch"><img src="{{ asset('storage/'.$lab->color_swatch_path) }}" alt="عينة اللون"></div>
        @endif
      </td>
    </tr>
  </tbody>
</table>

<div class="sec">نسبة الانكماش</div>
<table class="grid">
  <colgroup><col style="width:90px"><col><col></colgroup>
  <thead><tr><th>العينة</th><th>الانكماش بالطول</th><th>الانكماش بالعرض</th></tr></thead>
  <tbody>
    <tr><td>عينة 1</td>
        <td>طول <span class="num">{{ $lab->s1_shrink_len_pct ?? '' }}</span> %</td>
        <td>عرض <span class="num">{{ $lab->s1_shrink_width_pct ?? '' }}</span> %</td></tr>
    <tr><td>عينة 2</td>
        <td>طول <span class="num">{{ $lab->s2_shrink_len_pct ?? '' }}</span> %</td>
        <td>عرض <span class="num">{{ $lab->s2_shrink_width_pct ?? '' }}</span> %</td></tr>
    <tr><td>عينة 3</td>
        <td>طول <span class="num">{{ $lab->s3_shrink_len_pct ?? '' }}</span> %</td>
        <td>عرض <span class="num">{{ $lab->s3_shrink_width_pct ?? '' }}</span> %</td></tr>
    <tr><td>عينة 4</td>
        <td>طول <span class="num">{{ $lab->s4_shrink_len_pct ?? '' }}</span> %</td>
        <td>عرض <span class="num">{{ $lab->s4_shrink_width_pct ?? '' }}</span> %</td></tr>
    <tr class="total"><td>المتوسط</td>
        <td>طول <span class="num">{{ $lab->avg_shrink_len_pct ?? '' }}</span> %</td>
        <td>عرض <span class="num">{{ $lab->avg_shrink_width_pct ?? '' }}</span> %</td></tr>
  </tbody>
</table>

<div class="sec">المطابقة والمواصفات</div>
<table class="meta">
  <tr><th>مطابقة اللون</th>
      <td>
        @if($lab->color_match_ok === null) — @elseif($lab->color_match_ok) <span class="ok">مطابق</span> @else <span class="bad">غير مطابق</span> @endif
      </td>
      <th>ملاحظات</th><td>{{ $lab->notes }}</td></tr>
  <tr><th>فواصل / اختلاف لون</th>
      <td colspan="3">{{ $lab->has_color_break ? 'نعم — ' . ($lab->color_break_roll_no ? 'أتواب ' . $lab->color_break_roll_no . ' · ' : '') . $lab->color_break_note : 'لا' }}</td></tr>
  @foreach($lab->specChecks() as $c)
    <tr><th>{{ $c['label'] }}</th><td>المواصفة <span class="num">{{ $c['spec'] }}</span></td><th>الفعلي</th>
        <td><span class="num">{{ $c['actual'] }}</span>
          @if($c['ok'] === null)@elseif($c['ok']) <span class="ok">✓</span> @else <span class="bad">✗ خارج المواصفة</span> @endif
        </td></tr>
  @endforeach
</table>

<div class="sigs">
  <div class="sig">توقيع فني المعمل<div class="line">{{ $lab->technician?->name }}</div></div>
  <div class="sig">مسؤول الجودة<div class="line"></div></div>
</div>
@endsection
