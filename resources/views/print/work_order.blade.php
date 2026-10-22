@extends('layouts.print')
{{-- ورقة أمر الشغل اللي بتروح للمصنع — بنفس ترتيب الورقة الأصلية --}}
@section('doc_title', 'أمر شغل')
@if($wo->product_title)@section('doc_subtitle', $wo->product_title)@endif
@section('doc_serial', $wo->qb_code ?: $wo->wo_no)
@section('doc_serial_note', 'كود Q.B')
@section('doc')

<table class="meta">
  <tr><th>التاريخ</th><td class="num">{{ $wo->wo_date?->format('Y/n/j') }}</td>
      <th>كود المنتج</th><td colspan="3" class="code">{{ $wo->product_code ?: '—' }}</td></tr>
  <tr><th>تشغيل مصنع</th><td>{{ $wo->factory?->name }}</td>
      <th>رقم أمر الشغل</th><td><b class="code">{{ $wo->wo_no }}</b>
        @if((int) $wo->revision_no > 1) <span class="small">(نسخة {{ $wo->revision_no }})</span> @endif
      </td>
      <th>تاريخ الاستلام</th><td><span class="num">{{ $wo->receive_date?->format('Y/n/j') ?? '—' }}</span>
        <span class="small">· الحالة: {{ $wo->status_name }}</span></td></tr>
  @foreach($wo->fabrics as $i => $f)
    <tr><th>الخامة {{ $i + 1 }}</th>
        <td colspan="3">{{ $f->fabricType?->name }} — {{ $f->color?->name ?? $f->color?->code }}</td>
        <th>رقم الرسالة</th><td class="code">{{ $f->consignment?->consignment_no ?? '—' }}</td></tr>
  @endforeach
</table>

@php $photoModel = $wo->lines->first(fn ($l) => $l->productModel?->photo_url)?->productModel; @endphp
@if($photoModel)
  <table class="meta">
    <tr>
      <td class="photo-cell">
        <img class="photo" src="{{ $photoModel->photo_url }}" alt="{{ $photoModel->name }}">
        <span class="small">صورة الموديل</span>
      </td>
      <th>الموديل</th><td>{{ $photoModel->name }}</td>
      <th>F.P CODE</th><td class="code">{{ $photoModel->fp_code ?: '—' }}</td>
      <th>الباركود</th><td class="code">{{ $photoModel->barcode ?: ($wo->barcode ?: '—') }}</td>
    </tr>
  </table>
@endif

@if($wo->lines->count())
<div class="sec">الموديلات — القص المتوقع ونصيب كل موديل من الاستهلاك</div>
<table class="grid">
  <colgroup>
    <col style="width:28px"><col><col style="width:80px"><col style="width:80px">
    <col style="width:95px"><col style="width:95px"><col style="width:75px"><col style="width:80px">
  </colgroup>
  <thead><tr>
    <th>م</th><th>الموديل</th><th>المقاس</th>
    <th>قطعة في الفرشة</th><th>استهلاك القطعة</th>
    <th>قص متوقع بالقطعة</th><th>بالدستة</th><th>كجم</th>
  </tr></thead>
  <tbody>
    @foreach($wo->lines as $i => $l)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td class="r">
          @if($l->productModel?->photo_url)<img class="thumb" src="{{ $l->productModel->photo_url }}" alt="">@endif
          {{ $l->productModel?->name }}@if($l->productModel?->code) — <span class="code">{{ $l->productModel->code }}</span>@endif</td>
        <td>{{ $l->size?->name ?? 'الكل' }}</td>
        <td class="num">{{ $l->qty_per_spread }}</td>
        <td class="num">{{ $l->consumption_per_piece ? rtrim(rtrim(number_format((float) $l->consumption_per_piece, 5), '0'), '.') : '—' }}</td>
        <td class="num"><b>{{ number_format((int) $l->planned_qty) }}</b></td>
        <td class="num">{{ number_format((int) $l->planned_qty / 12, 2) }}</td>
        <td class="num">{{ $l->planned_kg ? rtrim(rtrim(number_format((float) $l->planned_kg, 3), '0'), '.') : '—' }}</td>
      </tr>
    @endforeach
    @if($wo->lines->count() > 1)
      <tr class="total">
        <td colspan="3" class="r">إجمالي</td>
        <td class="num">{{ (int) $wo->lines->sum('qty_per_spread') }}</td>
        <td></td>
        <td class="num">{{ number_format((int) $wo->lines->sum('planned_qty')) }}</td>
        <td class="num">{{ number_format((int) $wo->lines->sum('planned_qty') / 12, 2) }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float) $wo->lines->sum('planned_kg'), 3), '0'), '.') }}</td>
      </tr>
    @endif
  </tbody>
</table>
@endif

<div class="sec">كمية الخام المنصرف من المخزن</div>
<table class="grid">
  <colgroup>
    <col style="width:120px"><col><col style="width:90px"><col style="width:110px"><col style="width:130px">
  </colgroup>
  <thead><tr>
    <th>الخامة</th><th>رقم الرسالة</th><th>اللون</th>
    <th>كمية متر / كيلو</th><th>القص المتوقع بالقطعة</th>
  </tr></thead>
  <tbody>
    @foreach($wo->fabrics as $f)
      <tr>
        <td>{{ $f->fabricType?->name }}</td>
        <td class="code">{{ $f->consignment?->consignment_no }}</td>
        <td class="code">{{ $f->color?->code }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float) $f->planned_qty, 3), '0'), '.') }}</td>
        <td class="num"><b>{{ number_format((int) $f->expected_pieces) }}</b>@if($f->is_governing) <span class="star" title="الخامة الحاكمة">★</span>@endif</td>
      </tr>
    @endforeach
    <tr class="total">
      <td colspan="3" class="r">إجمالي</td>
      <td class="num">{{ rtrim(rtrim(number_format((float) $wo->fabrics->sum('planned_qty'), 3), '0'), '.') }}</td>
      <td class="num">{{ number_format($wo->target_qty) }}</td>
    </tr>
  </tbody>
</table>

<div class="sec">ملاحظات خاصة بقسم القص</div>
<table class="grid">
  <colgroup>
    <col style="width:28px"><col style="width:150px">
    @foreach($wo->fabrics as $f)<col>@endforeach
    @if($wo->fabrics->isEmpty())<col>@endif
  </colgroup>
  <thead><tr>
    <th>م</th><th>بيان</th>
    @foreach($wo->fabrics as $f)<th>{{ $f->fabricType?->name }}</th>@endforeach
    @if($wo->fabrics->isEmpty())<th></th>@endif
  </tr></thead>
  <tbody>
    @php
      $rows = [
        ['طول الفرشة',        fn ($f) => $f->spread_length_m . ($f->spread_length_safe_m ? ' م / ' . $f->spread_length_safe_m . ' م بالأمان' : ' م')],
        ['عرض القماش (سم)',   fn ($f) => $f->fabric_width_m ? rtrim(rtrim(number_format((float) $f->fabric_width_m * 100, 1), '0'), '.') : '—'],
        ['فواصل / اختلاف لون', fn ($f) => $f->consignment?->has_color_break ? ('نعم — ' . $f->consignment->color_break_note) : 'لا'],
        ['عدد الرقات',        fn ($f) => $f->plies ?: '—'],
        ['عدد القطع في الفرشة', fn ($f) => $f->pieces_per_spread ?: '—'],
        ['استهلاك القطعة',    fn ($f) => $f->consumption_per_piece ? rtrim(rtrim(number_format((float) $f->consumption_per_piece, 4), '0'), '.') : '—'],
        ['وزن الراق',         fn ($f) => $f->ply_weight_kg ? rtrim(rtrim(number_format((float) $f->ply_weight_kg, 3), '0'), '.') : '—'],
        ['وزن البنشر',        fn ($f) => $f->gsm_kg_m2 ? rtrim(rtrim(number_format((float) $f->gsm_kg_m2, 4), '0'), '.') : '—'],
      ];
    @endphp
    @foreach($rows as $i => [$label, $fn])
      <tr>
        <td class="num">{{ $i + 1 }}</td><td class="r">{{ $label }}</td>
        @foreach($wo->fabrics as $f)<td>{{ $fn($f) }}</td>@endforeach
        @if($wo->fabrics->isEmpty())<td></td>@endif
      </tr>
    @endforeach
    <tr>
      <td class="num">9</td><td class="r">عدد نسخ الماركر المرسلة</td>
      <td colspan="{{ max(1, $wo->fabrics->count()) }}" class="num"><b>{{ $wo->marker_copies }}</b></td>
    </tr>
  </tbody>
</table>

<div class="sec">بيانات تخص المنتج</div>
<table class="grid">
  <colgroup>
    <col style="width:28px"><col style="width:200px"><col><col style="width:140px"><col style="width:80px">
  </colgroup>
  <thead><tr><th>م</th><th>بيان</th><th>الكمية</th><th>كود الإكسسوار</th><th>الوحدة</th></tr></thead>
  <tbody>
    @forelse($wo->accessoryRequirements as $i => $a)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td class="r">{{ $a->accessory?->name }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float) $a->required_qty, 3), '0'), '.') }}</td>
        <td class="code">{{ $a->accessory?->code }}</td>
        <td>{{ $a->accessory?->unit }}</td>
      </tr>
    @empty
      <tr><td class="num">1</td><td class="r">—</td><td></td><td></td><td></td></tr>
    @endforelse
    <tr><td class="num">{{ $wo->accessoryRequirements->count() + 1 }}</td><td class="r">باركود التكويد</td>
        <td colspan="3" class="code">{{ $wo->barcode ?: 'استلام الباركود من إدارة الاستوك كنترول' }}</td></tr>
    <tr><td class="num">{{ $wo->accessoryRequirements->count() + 2 }}</td><td class="r">ملاحظات</td>
        <td colspan="3" class="r">{{ $wo->cutting_notes }}</td></tr>
  </tbody>
</table>

<div class="terms">
  <ul>
    <li>يُلتزم بالبيانات الموضحة — وخاصة الكميات المطلوبة.</li>
    <li>يُرجع لإدارة التخطيط في حال تغيّر أي بيان متفق عليه، أو عدم وضوح أي معلومة.</li>
    <li>أي زيادة في طول الفرشة بتقلل عدد الرقات وعدد القطع.</li>
    <li>استلام الباركود من إدارة الاستوك كنترول.</li>
  </ul>
</div>

<div class="sigs">
  <div class="sig">إدارة التخطيط<div class="line">{{ $wo->planner?->name }}</div></div>
  <div class="sig">المخزن (صرف الخام)<div class="line"></div></div>
  <div class="sig">قسم القص<div class="line"></div></div>
  <div class="sig">المصنع<div class="line">{{ $wo->factory?->name }}</div></div>
</div>
@endsection
