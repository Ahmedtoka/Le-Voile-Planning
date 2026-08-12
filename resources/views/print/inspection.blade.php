@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo">Le Voile<small>FASHION FORWARD</small></div>
  <div class="doc-title">تقرير فحص قماش</div>
  <div style="width:150px;text-align:left"><span class="serial">{{ $insp->paper_serial ?: $insp->doc_no }}</span></div>
</div>

<table class="meta" style="width:100%">
  <tr><td class="k">الصنــف</td><td>{{ $insp->fabricType?->name }}</td>
      <td class="k">التاريخ</td><td style="width:120px">{{ $insp->doc_date?->format('d / n / Y') }}</td></tr>
  <tr><td class="k">أمر التشغيل</td><td>{{ $insp->workOrder?->wo_no ?: '—' }}</td>
      <td class="k">كود اللون</td><td>{{ $insp->color?->code }} — {{ $insp->color?->name }}</td></tr>
  <tr><td class="k">رقم الرسالة</td><td>{{ $insp->consignment?->consignment_no }}</td>
      <td class="k">اسم المورد</td><td>{{ $insp->supplier?->name }}</td></tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:60px">التوب</th><th style="width:80px">الطــول</th><th style="width:70px">العرض</th>
    <th style="width:70px">البنشر</th><th style="width:65px">العيوب</th><th>العيــــب</th><th style="width:150px">ملاحظــات</th>
  </tr></thead>
  <tbody>
    @foreach($insp->rolls as $r)
      <tr>
        <td>{{ $r->roll_no }}</td>
        <td>{{ rtrim(rtrim(number_format((float)$r->length_m, 2), '0'), '.') }}</td>
        <td>{{ rtrim(rtrim(number_format((float)$r->width_cm, 2), '0'), '.') }}</td>
        <td>{{ $r->gsm ? rtrim(rtrim(number_format((float)$r->gsm,2),'0'),'.') : '' }}</td>
        <td>{{ $r->defects_count }}</td>
        <td class="r">{{ $r->defect_desc }}</td>
        <td class="r">{{ $r->notes }}</td>
      </tr>
    @endforeach
    @for($i = $insp->rolls->count(); $i < 10; $i++)
      <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
  </tbody>
</table>

<table class="meta" style="width:100%;margin-top:8px">
  <tr>
    <td class="k">إجمالي الكمية</td><td>{{ rtrim(rtrim(number_format((float)$insp->total_length_m,2),'0'),'.') }} م</td>
    <td class="k">عدد الأتواب المفحوصة</td><td>{{ $insp->sampled_rolls }} من {{ $insp->total_rolls }} ({{ $insp->sample_pct }}%)</td>
    <td class="k">النتيجة</td><td>{{ $insp->result_name }}</td>
  </tr>
  <tr>
    <td class="k">أقل عرض</td><td><b>{{ $insp->min_width_cm }} سم</b></td>
    <td class="k">متوسط العرض</td><td>{{ $insp->avg_width_cm }} سم</td>
    <td class="k">نسبة العيوب</td><td>{{ $insp->defect_pct }}%</td>
  </tr>
</table>

<div class="terms">ملاحظات: {{ $insp->notes }}</div>

<div class="sigs">
  <div class="sig">الفاحص<div class="line">{{ $insp->inspector?->name }}</div></div>
  <div class="sig">مسؤول الجودة<div class="line"></div></div>
  <div class="sig">مدير المصنع<div class="line"></div></div>
</div>
@endsection
