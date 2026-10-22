@extends('layouts.print')
@section('doc_title', 'تقرير فحص قماش')
@section('doc_serial', $insp->paper_serial ?: $insp->doc_no)
@section('doc')

<table class="meta">
  <tr><th>الصنف</th><td>{{ $insp->fabricType?->name }}</td>
      <th>التاريخ</th><td class="v-num num">{{ $insp->doc_date?->format('d / n / Y') }}</td></tr>
  <tr><th>أمر التشغيل</th><td class="code">{{ $insp->workOrder?->wo_no ?: '—' }}</td>
      <th>كود اللون</th><td>{{ $insp->color?->code }} — {{ $insp->color?->name }}</td></tr>
  <tr><th>رقم الرسالة</th><td class="code">{{ $insp->consignment?->consignment_no }}</td>
      <th>اسم المورد</th><td>{{ $insp->supplier?->name }}</td></tr>
  <tr><th>رقم إذن المورد</th><td colspan="3" class="code">{{ $insp->consignment?->sourceAddition()?->supplier_doc_no ?: '—' }}</td></tr>
</table>

<table class="grid">
  <colgroup>
    <col style="width:48px"><col style="width:62px"><col style="width:58px"><col style="width:56px">
    <col style="width:60px"><col style="width:56px"><col style="width:52px"><col><col style="width:140px">
  </colgroup>
  <thead><tr>
    <th>التوب</th><th>الطول</th><th>العرض</th><th>الحالة</th>
    <th>الوزن</th><th>البنشر</th><th>العيوب</th><th>العيب</th><th>ملاحظات</th>
  </tr></thead>
  <tbody>
    @foreach($insp->rolls as $r)
      <tr>
        <td class="num">{{ $r->roll_no }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$r->length_m, 2), '0'), '.') }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$r->width_cm, 2), '0'), '.') }}</td>
        <td class="{{ $r->status === 'rejected' ? 'bad' : '' }}">{{ $r->status === 'rejected' ? 'مرفوض' : 'سليم' }}</td>
        <td class="num">{{ $r->net_kg ? lv_num($r->net_kg) : '' }}</td>
        <td class="num">{{ $r->gsm ? lv_num($r->gsm, 2) : '' }}</td>
        <td class="num">{{ $r->defects_count }}</td>
        <td class="r">{{ $r->defect_desc }}</td>
        <td class="r">{{ $r->notes }}</td>
      </tr>
    @endforeach
    @for($i = $insp->rolls->count(); $i < 10; $i++)
      <tr class="blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
  </tbody>
</table>

<div class="sec">ملخص الفحص</div>
<table class="meta">
  <tr>
    <th>إجمالي الكمية</th><td><span class="num">{{ rtrim(rtrim(number_format((float)$insp->total_length_m,2),'0'),'.') }}</span> م</td>
    <th>الأتواب المفحوصة</th><td><span class="num">{{ $insp->sampled_rolls }}</span> من <span class="num">{{ $insp->total_rolls }}</span> (<span class="num">{{ $insp->sample_pct }}</span>%)</td>
    <th>النتيجة</th><td><b>{{ $insp->result_name }}</b></td>
  </tr>
  <tr>
    <th>أقل عرض</th><td><b><span class="num">{{ $insp->min_width_cm }}</span> سم</b></td>
    <th>متوسط العرض</th><td><span class="num">{{ $insp->avg_width_cm }}</span> سم</td>
    <th>نسبة العيوب</th><td><span class="num">{{ $insp->defect_pct }}</span>%</td>
  </tr>
  <tr>
    <th>أتواب مرفوضة</th><td colspan="5"><span class="num">{{ (int) $insp->rejected_rolls }}</span> توب
      @if($insp->rejected_kg > 0) بوزن <span class="num">{{ rtrim(rtrim(number_format((float)$insp->rejected_kg,3),'0'),'.') }}</span> كجم — اتخصموا من الرسالة ونزلوا المرتجعات @endif</td>
  </tr>
</table>

<div class="note"><span class="lbl">ملاحظات:</span> {{ $insp->notes }}</div>

<div class="sigs">
  <div class="sig">الفاحص<div class="line">{{ $insp->inspector?->name }}</div></div>
  <div class="sig">مسؤول الجودة<div class="line"></div></div>
  <div class="sig">مدير المصنع<div class="line"></div></div>
</div>
@endsection
