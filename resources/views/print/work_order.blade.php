@extends('layouts.print')
@section('doc')
{{-- ورقة أمر الشغل اللي بتروح للمصنع — بنفس ترتيب الورقة الأصلية --}}
<div class="doc-head">
  <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt="Le Voile"><small>FASHION FORWARD</small></div>
  <div class="doc-title" style="font-size:15px">{{ $wo->product_title ?: 'أمر شغل' }}</div>
  <div style="width:160px;text-align:left">
    <span class="serial">{{ $wo->qb_code ?: $wo->wo_no }}</span>
    <div style="font-size:10px">كود Q.B</div>
  </div>
</div>

<table class="meta" style="width:100%">
  <tr><td class="k">التاريخ</td><td>{{ $wo->wo_date?->format('Y/n/j') }}</td>
      <td class="k">كود المنتج</td><td colspan="3">{{ $wo->product_code ?: '—' }}</td></tr>
  <tr><td class="k">تشغيل مصنع</td><td>{{ $wo->factory?->name }}</td>
      <td class="k">رقم أمر الشغل</td><td><b>{{ $wo->wo_no }}</b></td>
      <td class="k">تاريخ الاستلام</td><td>{{ $wo->receive_date?->format('Y/n/j') ?? '—' }}</td></tr>
  @foreach($wo->fabrics as $i => $f)
    <tr><td class="k">الخامة {{ $i + 1 }}</td><td colspan="5">{{ $f->fabricType?->name }} — {{ $f->color?->name ?? $f->color?->code }}</td></tr>
  @endforeach
</table>

<div style="font-weight:700;margin:10px 0 4px">كمية الخام المنصرف من المخزن</div>
<table class="grid">
  <thead><tr>
    <th style="width:110px">الخامة</th><th style="width:200px">رقم الرسالة</th><th style="width:90px">اللون</th>
    <th style="width:110px">كمية متر / كيلو</th><th style="width:130px">القص المتوقع بالقطعة</th>
  </tr></thead>
  <tbody>
    @foreach($wo->fabrics as $f)
      <tr>
        <td>{{ $f->fabricType?->name }}</td>
        <td>{{ $f->consignment?->consignment_no }}</td>
        <td>{{ $f->color?->code }}</td>
        <td>{{ rtrim(rtrim(number_format((float) $f->planned_qty, 3), '0'), '.') }}</td>
        <td><b>{{ number_format((int) $f->expected_pieces) }}</b>@if($f->is_governing) ★ @endif</td>
      </tr>
    @endforeach
    <tr>
      <td colspan="3"><b>إجمالي</b></td>
      <td><b>{{ rtrim(rtrim(number_format((float) $wo->fabrics->sum('planned_qty'), 3), '0'), '.') }}</b></td>
      <td><b>{{ number_format($wo->target_qty) }}</b></td>
    </tr>
  </tbody>
</table>

<div style="font-weight:700;margin:10px 0 4px">ملاحظات خاصة بقسم القص</div>
<table class="grid">
  <thead><tr>
    <th style="width:30px">م</th><th style="width:150px">بيان</th>
    @foreach($wo->fabrics as $f)<th>{{ $f->fabricType?->name }}</th>@endforeach
  </tr></thead>
  <tbody>
    @php
      $rows = [
        ['طول الفرشة',        fn ($f) => $f->spread_length_m . ($f->spread_length_safe_m ? ' م / ' . $f->spread_length_safe_m . ' م بالأمان' : ' م')],
        ['عرض القماش',        fn ($f) => $f->fabric_width_m ?: '—'],
        ['عدد الرقات',        fn ($f) => $f->plies ?: '—'],
        ['عدد القطع في الفرشة', fn ($f) => $f->pieces_per_spread ?: '—'],
        ['استهلاك القطعة',    fn ($f) => $f->consumption_per_piece ? rtrim(rtrim(number_format((float) $f->consumption_per_piece, 4), '0'), '.') : '—'],
        ['وزن الراق',         fn ($f) => $f->ply_weight_kg ? rtrim(rtrim(number_format((float) $f->ply_weight_kg, 3), '0'), '.') : '—'],
        ['وزن البنشر',        fn ($f) => $f->gsm_kg_m2 ? rtrim(rtrim(number_format((float) $f->gsm_kg_m2, 4), '0'), '.') : '—'],
      ];
    @endphp
    @foreach($rows as $i => [$label, $fn])
      <tr>
        <td>{{ $i + 1 }}</td><td class="r">{{ $label }}</td>
        @foreach($wo->fabrics as $f)<td>{{ $fn($f) }}</td>@endforeach
      </tr>
    @endforeach
    <tr>
      <td>8</td><td class="r">عدد نسخ الماركر المرسلة</td>
      <td colspan="{{ max(1, $wo->fabrics->count()) }}"><b>{{ $wo->marker_copies }}</b></td>
    </tr>
  </tbody>
</table>

<div style="font-weight:700;margin:10px 0 4px">بيانات تخص المنتج</div>
<table class="grid">
  <thead><tr><th style="width:30px">م</th><th style="width:220px">بيان</th><th>الكمية</th><th style="width:90px">الوحدة</th></tr></thead>
  <tbody>
    @forelse($wo->accessoryRequirements as $i => $a)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td class="r">{{ $a->accessory?->name }}</td>
        <td>{{ rtrim(rtrim(number_format((float) $a->required_qty, 2), '0'), '.') }}</td>
        <td>{{ $a->accessory?->unit }}</td>
      </tr>
    @empty
      <tr><td>1</td><td class="r">—</td><td></td><td></td></tr>
    @endforelse
    <tr><td>{{ $wo->accessoryRequirements->count() + 1 }}</td><td class="r">باركود التكويد</td>
        <td colspan="2">{{ $wo->barcode ?: '' }}</td></tr>
    <tr><td>{{ $wo->accessoryRequirements->count() + 2 }}</td><td class="r">ملاحظات</td>
        <td colspan="2" class="r">{{ $wo->cutting_notes }}</td></tr>
  </tbody>
</table>

<div class="terms">
  • يُلتزم بالبيانات الموضحة — وخاصة الكميات المطلوبة.<br>
  • يُرجع لإدارة التخطيط في حال تغيّر أي بيان متفق عليه، أو عدم وضوح أي معلومة.<br>
  • أي زيادة في طول الفرشة بتقلل عدد الرقات وعدد القطع.<br>
  • استلام الباركود من إدارة الاستوك كنترول.
</div>

<div class="sigs">
  <div class="sig">إدارة التخطيط<div class="line">{{ $wo->planner?->name }}</div></div>
  <div class="sig">قسم القص<div class="line"></div></div>
  <div class="sig">المصنع<div class="line">{{ $wo->factory?->name }}</div></div>
</div>
@endsection
