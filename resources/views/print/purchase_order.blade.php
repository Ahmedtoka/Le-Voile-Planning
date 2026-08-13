@extends('layouts.print')
@section('doc')
<div class="doc-head">
  <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt="Le Voile"><small>FASHION FORWARD</small></div>
  <div class="doc-title">طلب شراء</div>
  <div style="width:150px;text-align:left"><span class="serial">{{ $po->po_no }}</span></div>
</div>

<table style="width:100%"><tr>
  <td style="width:48%;vertical-align:top">
    <table class="meta" style="width:100%">
      <tr><td class="k">رقم المــورد</td><td>{{ $po->supplier?->code }}</td></tr>
      <tr><td class="k">اسم المــورد</td><td>{{ $po->supplier?->name }}</td></tr>
      <tr><td class="k">الشخص المسؤول</td><td>{{ $po->supplier?->contact_person }}</td></tr>
      <tr><td class="k">العنـــوان</td><td>{{ $po->supplier?->address }}</td></tr>
      <tr><td class="k">رقم التليفـون</td><td>{{ $po->supplier?->phone }}</td></tr>
      <tr><td class="k">طريقة الدفع</td><td>{{ $po->payment_method ?: $po->supplier?->payment_terms }}</td></tr>
    </table>
  </td>
  <td style="width:4%"></td>
  <td style="width:48%;vertical-align:top">
    <table class="meta" style="width:100%">
      <tr><td class="k">رقم امر الشراء</td><td>{{ $po->po_no }}</td></tr>
      <tr><td class="k">التاريــخ</td><td>{{ $po->po_date?->format('d/n/Y') }}</td></tr>
      <tr><td class="k">ادارة المشتريات</td><td></td></tr>
      <tr><td class="k">اسم الموظف</td><td>{{ $po->employee?->name }}</td></tr>
      <tr><td class="k">تاريخ التوريد</td><td>{{ $po->delivery_date?->format('d/n/Y') }}</td></tr>
      <tr><td class="k">مكان التسليم</td><td>{{ $po->delivery_place }}</td></tr>
    </table>
  </td>
</tr></table>

<table class="grid">
  <thead><tr>
    <th style="width:30px">م</th><th style="width:80px">كود اللون</th><th>اســم الصنف</th>
    <th style="width:70px">الكمية</th><th style="width:55px">الوحدة</th><th style="width:75px">سعر الوحدة</th>
    <th style="width:80px">الاجمــالى</th><th style="width:75px">نسبة الزيادة المسموح بها %</th><th style="width:150px">ملاحظــات</th>
  </tr></thead>
  <tbody>
    @foreach($po->lines as $i => $l)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $l->color?->name ?? $l->color?->code }}</td>
        <td class="r">{{ $l->fabricType?->name ?? $l->item_name }}</td>
        <td>{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->unit }}</td>
        <td>{{ $l->unit_price > 0 ? number_format((float)$l->unit_price, 2) : '-' }}</td>
        <td>{{ $l->line_total > 0 ? number_format((float)$l->line_total, 2) : '-' }}</td>
        <td>{{ rtrim(rtrim(number_format((float)$l->tolerance_pct, 2), '0'), '.') }}%</td>
        <td class="r">{{ $l->notes }}</td>
      </tr>
    @endforeach
    @for($i = $po->lines->count(); $i < 14; $i++)
      <tr><td>{{ $i + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td>-</td><td></td><td></td></tr>
    @endfor
    <tr>
      <td colspan="2"></td><td><b>اجمالي</b></td>
      <td><b>{{ rtrim(rtrim(number_format((float)$po->total_qty, 3), '0'), '.') }}</b></td>
      <td colspan="2"></td><td><b>{{ $po->subtotal > 0 ? number_format((float)$po->subtotal, 2) : '-' }}</b></td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

<table style="width:100%;margin-top:8px"><tr>
  <td style="vertical-align:top">
    <div class="terms">
      • يلتزم المورد بكتابة رقم امر الشراء على اذن التسليم والفاتورة<br>
      • يتم التوريد طبقا للمواصفة القياسية المعتمدة<br>
      • أي زيادة أو نقص خارج النسبة المسموح بها أعلاه تُرفض عند الاستلام
    </div>
    <div style="margin-top:22px;text-align:center;font-weight:600">اعتماد الادارة المالية</div>
    <div style="text-align:center">..................................</div>
  </td>
  <td style="width:300px;vertical-align:top">
    <table class="totals" style="width:100%">
      <tr><td class="k">الاجمالي</td><td colspan="2">{{ $po->subtotal > 0 ? number_format((float)$po->subtotal, 2) : '-' }}</td></tr>
      <tr><td class="k">قيمة الخصم</td><td>{{ rtrim(rtrim(number_format((float)$po->discount_pct,2),'0'),'.') }}%</td>
          <td>{{ $po->discount_value > 0 ? number_format((float)$po->discount_value, 2) : '-' }}</td></tr>
      <tr><td class="k">الضريبــــة</td><td>{{ rtrim(rtrim(number_format((float)$po->tax_pct,2),'0'),'.') }}%</td>
          <td>{{ $po->tax_value > 0 ? number_format((float)$po->tax_value, 2) : '-' }}</td></tr>
      <tr><td class="k">اجمالي القيمة بعد الضريبة</td><td colspan="2"><b>{{ $po->total > 0 ? number_format((float)$po->total, 2) : '-' }}</b></td></tr>
    </table>
    <div class="sigs">
      <div class="sig">توقيع المشتريات<div class="line"></div></div>
      <div class="sig">توقيع مدير المشتريات<div class="line"></div></div>
    </div>
  </td>
</tr></table>
@endsection
