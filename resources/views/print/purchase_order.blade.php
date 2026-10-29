@extends('layouts.print')
@section('doc_title', 'طلب شراء')
@section('doc_serial', $po->po_no)
@section('doc')

<div class="cols">
  <table class="meta">
    <tr><th>رقم المورد</th><td class="code">{{ $po->supplier?->code }}</td></tr>
    <tr><th>اسم المورد</th><td>{{ $po->supplier?->name }}</td></tr>
    <tr><th>الشخص المسؤول</th><td>{{ $po->supplier?->contact_person }}</td></tr>
    <tr><th>العنوان</th><td>{{ $po->supplier?->address }}</td></tr>
    <tr><th>رقم التليفون</th><td class="num">{{ $po->supplier?->phone }}</td></tr>
    <tr><th>طريقة الدفع</th><td>{{ $po->payment_method ?: $po->supplier?->payment_terms }}</td></tr>
  </table>
  <table class="meta">
    <tr><th>رقم أمر الشراء</th><td class="code">{{ $po->po_no }}</td></tr>
    <tr><th>التاريخ</th><td class="num">{{ $po->po_date?->format('d/n/Y') }}</td></tr>
    <tr><th>إدارة المشتريات</th><td>{{ $po->sourcer?->name ?? '' }}</td></tr>
    <tr><th>اسم الموظف</th><td>{{ $po->employee?->name }}</td></tr>
    <tr><th>تاريخ التوريد</th><td class="num">{{ $po->delivery_date?->format('d/n/Y') }}</td></tr>
    <tr><th>مكان التسليم</th><td>{{ $po->warehouse?->name ?? '' }}</td></tr>
  </table>
</div>

<table class="grid">
  <colgroup>
    <col style="width:28px"><col style="width:78px"><col><col style="width:68px"><col style="width:50px">
    <col style="width:70px"><col style="width:78px"><col style="width:72px"><col style="width:130px">
  </colgroup>
  <thead><tr>
    <th>م</th><th>كود اللون</th><th>اسم الصنف</th>
    <th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th>
    <th>الإجمالي</th><th>نسبة الزيادة المسموح بها %</th><th>ملاحظات</th>
  </tr></thead>
  <tbody>
    @foreach($po->lines as $i => $l)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td>{{ $l->color?->name ?? $l->color?->code }}</td>
        <td class="r">{{ $l->fabricType?->name ?? $l->item_name }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->unit }}</td>
        <td class="num">{{ $l->unit_price > 0 ? number_format((float)$l->unit_price, 2) : '-' }}</td>
        <td class="num">{{ $l->line_total > 0 ? number_format((float)$l->line_total, 2) : '-' }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$l->tolerance_pct, 2), '0'), '.') }}%</td>
        <td class="r">{{ $l->notes }}</td>
      </tr>
    @endforeach
    @for($i = $po->lines->count(); $i < 14; $i++)
      <tr class="blank"><td class="num">{{ $i + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td>-</td><td></td><td></td></tr>
    @endfor
    <tr class="total">
      <td colspan="3" class="r">الإجمالي</td>
      <td class="num">{{ rtrim(rtrim(number_format((float)$po->total_qty, 3), '0'), '.') }}</td>
      <td colspan="2"></td>
      <td class="num">{{ $po->subtotal > 0 ? number_format((float)$po->subtotal, 2) : '-' }}</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

<div class="cols-end">
  <div>
    <div class="terms">
      <ul>
        <li>يلتزم المورد بكتابة رقم أمر الشراء على إذن التسليم والفاتورة</li>
        <li>يتم التوريد طبقًا للمواصفة القياسية المعتمدة</li>
        <li>أي زيادة أو نقص خارج النسبة المسموح بها أعلاه تُرفض عند الاستلام</li>
      </ul>
    </div>
    <div class="approval">اعتماد الإدارة المالية<div class="line"></div></div>
  </div>
  <div>
    <table class="totals">
      <tr><td class="k">الإجمالي</td><td colspan="2" class="num">{{ $po->subtotal > 0 ? number_format((float)$po->subtotal, 2) : '-' }}</td></tr>
      <tr><td class="k">قيمة الخصم</td><td class="num">{{ rtrim(rtrim(number_format((float)$po->discount_pct,2),'0'),'.') }}%</td>
          <td class="num">{{ $po->discount_value > 0 ? number_format((float)$po->discount_value, 2) : '-' }}</td></tr>
      <tr><td class="k">الضريبة</td><td class="num">{{ rtrim(rtrim(number_format((float)$po->tax_pct,2),'0'),'.') }}%</td>
          <td class="num">{{ $po->tax_value > 0 ? number_format((float)$po->tax_value, 2) : '-' }}</td></tr>
      <tr class="grand"><td class="k">إجمالي القيمة بعد الضريبة</td><td colspan="2" class="num">{{ $po->total > 0 ? number_format((float)$po->total, 2) : '-' }}</td></tr>
    </table>
    <div class="sigs">
      <div class="sig">توقيع المشتريات<div class="line"></div></div>
      <div class="sig">توقيع مدير المشتريات<div class="line"></div></div>
    </div>
  </div>
</div>
@endsection
