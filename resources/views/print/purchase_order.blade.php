@extends('layouts.print')
@php
  /* ورقة أمر الشراء لمورد واحد: التسكينة بمورده وسطوره وأسعاره وإجماليه.
     طلب اتشرى من موردين = ورقتين (PO-…/1 و PO-…/2) — كل مورد بياخد ورقته. */
  $src  = $src ?? null;
  $sup  = $src?->supplier ?? $po->supplier;
  $no   = $src ? $src->label : $po->po_no;
  $rows = $src
      ? $src->lines->map(fn ($l) => (object) [
            'color' => $l->poLine?->color, 'item' => $l->poLine?->fabricType?->name ?? $l->poLine?->item_name,
            'qty' => (float) $l->qty, 'unit' => $l->unit, 'price' => (float) $l->unit_price, 'total' => (float) $l->line_total,
            'tol' => (float) $l->poLine?->tolerance_pct, 'notes' => $l->poLine?->notes])
      : $po->lines->map(fn ($l) => (object) [
            'color' => $l->color, 'item' => $l->fabricType?->name ?? $l->item_name,
            'qty' => (float) $l->qty, 'unit' => $l->unit, 'price' => (float) $l->unit_price, 'total' => (float) $l->line_total,
            'tol' => (float) $l->tolerance_pct, 'notes' => $l->notes]);
  $sub     = $src ? (float) $src->subtotal : (float) $po->subtotal;
  $discPct = $src ? (float) $src->discount_pct : (float) $po->discount_pct;
  $taxPct  = $src ? (float) $src->tax_pct : (float) $po->tax_pct;
  $disc    = $src ? round($sub * $discPct / 100, 2) : (float) $po->discount_value;
  $total   = $src ? (float) $src->total : (float) $po->total;
  $tax     = $src ? round($total - $sub + $disc, 2) : (float) $po->tax_value;
  $qtyTot  = (float) $rows->sum('qty');
  $pct     = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
@section('doc_title', 'أمر شراء')
@section('doc_serial', $no)
@section('doc')

<div class="cols">
  <table class="meta">
    <tr><th>رقم المورد</th><td class="code">{{ $sup?->code }}</td></tr>
    <tr><th>اسم المورد</th><td>{{ $sup?->name }}</td></tr>
    <tr><th>الشخص المسؤول</th><td>{{ $sup?->contact_person }}</td></tr>
    <tr><th>العنوان</th><td>{{ $sup?->address }}</td></tr>
    <tr><th>رقم التليفون</th><td class="num">{{ $sup?->phone }}</td></tr>
    <tr><th>طريقة الدفع</th><td>{{ ($src?->payment_method ?: $po->payment_method) ?: $sup?->payment_terms }}</td></tr>
  </table>
  <table class="meta">
    <tr><th>رقم أمر الشراء</th><td class="code">{{ $no }}</td></tr>
    <tr><th>التاريخ</th><td class="num">{{ ($src?->sourced_at ?? $po->po_date)?->format('d/n/Y') }}</td></tr>
    <tr><th>إدارة المشتريات</th><td>{{ ($src?->sourcer ?? $po->sourcer)?->name ?? '' }}</td></tr>
    <tr><th>اسم الموظف</th><td>{{ $po->employee?->name }}</td></tr>
    <tr><th>تاريخ التوريد</th><td class="num">{{ ($src?->delivery_date ?? $po->delivery_date)?->format('d/n/Y') }}</td></tr>
    <tr><th>مكان التسليم</th><td>{{ ($src?->warehouse ?? $po->warehouse)?->name ?? '' }}</td></tr>
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
    @foreach($rows->values() as $i => $l)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td>{{ $l->color?->name ?? $l->color?->code }}</td>
        <td class="r">{{ $l->item }}</td>
        <td class="num">{{ rtrim(rtrim(number_format($l->qty, 3), '0'), '.') }}</td>
        <td>{{ $l->unit }}</td>
        <td class="num">{{ $l->price > 0 ? number_format($l->price, 2) : '-' }}</td>
        <td class="num">{{ $l->total > 0 ? number_format($l->total, 2) : '-' }}</td>
        <td class="num">{{ $pct($l->tol) }}%</td>
        <td class="r">{{ $l->notes }}</td>
      </tr>
    @endforeach
    @for($i = $rows->count(); $i < 14; $i++)
      <tr class="blank"><td class="num">{{ $i + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td>-</td><td></td><td></td></tr>
    @endfor
    <tr class="total">
      <td colspan="3" class="r">الإجمالي</td>
      <td class="num">{{ rtrim(rtrim(number_format($qtyTot, 3), '0'), '.') }}</td>
      <td colspan="2"></td>
      <td class="num">{{ $sub > 0 ? number_format($sub, 2) : '-' }}</td>
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
      <tr><td class="k">الإجمالي</td><td colspan="2" class="num">{{ $sub > 0 ? number_format($sub, 2) : '-' }}</td></tr>
      <tr><td class="k">قيمة الخصم</td><td class="num">{{ $pct($discPct) }}%</td>
          <td class="num">{{ $disc > 0 ? number_format($disc, 2) : '-' }}</td></tr>
      <tr><td class="k">الضريبة</td><td class="num">{{ $pct($taxPct) }}%</td>
          <td class="num">{{ $tax > 0 ? number_format($tax, 2) : '-' }}</td></tr>
      <tr class="grand"><td class="k">إجمالي القيمة بعد الضريبة</td><td colspan="2" class="num">{{ $total > 0 ? number_format($total, 2) : '-' }}</td></tr>
    </table>
    <div class="sigs">
      <div class="sig">توقيع المشتريات<div class="line"></div></div>
      <div class="sig">توقيع مدير المشتريات<div class="line"></div></div>
    </div>
  </div>
</div>
@endsection
