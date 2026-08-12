@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo">Le Voile<small>FASHION FORWARD</small></div>
  <div class="doc-title">تقرير انكماش قماش ومطابقة ألوان</div>
  <div style="width:150px;text-align:left"><span class="serial">{{ $lab->paper_serial ?: $lab->doc_no }}</span></div>
</div>

<table class="grid">
  <thead><tr>
    <th style="width:150px">الرسالة</th><th style="width:150px">اســم المورد</th><th>اسم الخامة</th>
    <th style="width:110px">اللون</th><th style="width:100px">التاريخ</th><th style="width:120px">وزن البنشر</th>
  </tr></thead>
  <tbody>
    <tr>
      <td>{{ $lab->consignment?->consignment_no }}</td>
      <td>{{ $lab->supplier?->name }}</td>
      <td>{{ $lab->fabricType?->name }}</td>
      <td>{{ $lab->color?->name }}</td>
      <td>{{ $lab->doc_date?->format('Y/n/j') }}</td>
      <td rowspan="{{ max(1, $lab->readings->count()) }}" style="vertical-align:top">
        @foreach($lab->readings as $r)
          <div>{{ $r->roll_no }}: {{ rtrim(rtrim(number_format((float)$r->gsm,2),'0'),'.') }}</div>
        @endforeach
        <div style="border-top:1px solid #000;margin-top:3px;padding-top:3px"><b>متوسط: {{ $lab->avg_gsm }}</b></div>
      </td>
    </tr>
    <tr>
      <td colspan="5" class="r" style="height:190px;vertical-align:top">
        مكان مقارنة اللون
        @if($lab->color_swatch_path)
          <div style="margin-top:6px"><img src="{{ asset('storage/'.$lab->color_swatch_path) }}" style="max-height:150px"></div>
        @endif
      </td>
    </tr>
  </tbody>
</table>

<div style="margin-top:8px;font-weight:600">نسبة الانكماش</div>
<table class="grid">
  <tr><td style="width:80px">عينة 1</td>
      <td>طول {{ $lab->s1_shrink_len_pct ?? '' }} %</td>
      <td>عرض {{ $lab->s1_shrink_width_pct ?? '' }} %</td></tr>
  <tr><td>عينة 2</td>
      <td>طول {{ $lab->s2_shrink_len_pct ?? '' }} %</td>
      <td>عرض {{ $lab->s2_shrink_width_pct ?? '' }} %</td></tr>
  <tr><td><b>المتوسط</b></td>
      <td><b>طول {{ $lab->avg_shrink_len_pct ?? '' }} %</b></td>
      <td><b>عرض {{ $lab->avg_shrink_width_pct ?? '' }} %</b></td></tr>
</table>

<table class="meta" style="width:100%;margin-top:8px">
  <tr><td class="k">مطابقة اللون</td>
      <td>{{ $lab->color_match_ok === null ? '—' : ($lab->color_match_ok ? 'مطابق' : 'غير مطابق') }}</td>
      <td class="k">ملاحظات</td><td>{{ $lab->notes }}</td></tr>
</table>

<div class="sigs">
  <div class="sig">توقيع فنى المعمل<div class="line">{{ $lab->technician?->name }}</div></div>
  <div class="sig">مسؤول الجودة<div class="line"></div></div>
</div>
@endsection
