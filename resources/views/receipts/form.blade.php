@extends('layouts.app')
@section('purpose', 'المراجعة الأخيرة قبل ما القماش يبقى متاح للتشغيل — السليم للمخزن والمرفوض للمرفوضات')
@section('content')
@php
  $lines    = old('lines', ($preset ?? null) ?: ($row->lines?->toArray() ?? []));
  $editable = $row->isEditable() || $mode === 'create';
  $c        = $arrived ?? null;
  $insp     = $c?->inspections?->firstWhere('status', 'approved') ?? $c?->inspections?->first();
  $lab      = $c?->labReports?->firstWhere('status', 'approved') ?? $c?->labReports?->first();
  $po       = $c?->purchaseOrder;
  $sa       = $c?->sourceAddition();
  $num      = fn ($v, $d = 2) => rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'receipt'])

@if(! $c)
  <div class="empty-state">
    <i class="bi bi-inbox ico" aria-hidden="true"></i>
    <div class="t">مفيش رسالة محددة.</div>
    <div class="hint">ادخل من شاشة استلام الخام واختار رسالة خلّصت فحص ومعمل.</div>
    <a href="{{ route('goods-receipts.index') }}" class="btn btn-plum btn-sm mt-2">رجوع للقايمة</a>
  </div>
@else

<div class="note-box mb-3">
  <b>دي مراجعة أخيرة قبل ما القماش يدخل مخزنك.</b>
  كل الأرقام تحت جاية من إذن الإضافة والفحص والمعمل — <b>مفيش حاجة تكتبها</b>.
  بص عليها، ولو تمام دوس «استلم» والقماش يبقى متاح لأوامر الشغل على طول.
</div>

{{-- ① رأس الرسالة --}}
<div class="card mb-3">
  <div class="card-body py-3 d-flex gap-4 flex-wrap align-items-center">
    <div>
      <div class="fw-bold num" style="font-size:1.35rem;color:var(--lv-brand-ink)">{{ $c->consignment_no }}</div>
      <div class="hint">{{ $c->fabricType?->name ?? '—' }} · {{ $c->color?->label ?? $c->color?->code ?? '—' }}</div>
    </div>
    <div class="ms-auto d-flex gap-4 flex-wrap text-center">
      <div><div class="hint">الكمية</div><b class="num">{{ lv_kg($c->total_kg) }}</b></div>
      <div><div class="hint">الأتواب</div><b class="num">{{ (int) $c->rolls_count }} توب</b></div>
      <div><div class="hint">أقل عرض</div><b class="num text-danger">{{ $c->min_width_cm ? lv_num($c->min_width_cm, 2) . ' سم' : '—' }}</b></div>
      <div><div class="hint">البنشر</div><b class="num">{{ $c->avg_gsm ? lv_num($c->avg_gsm, 2) . ' جم/م²' : '—' }}</b></div>
    </div>
  </div>
</div>

{{-- ② كل اللي حصل على الرسالة دي — Labels --}}
<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">مصدر الرسالة</div>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><th style="width:45%">المورد</th><td>{{ $c->supplier?->name ?? '—' }}</td></tr>
          <tr><th>طلب الشراء</th>
            <td>
              @if($po)
                <a href="{{ route('purchase-orders.edit',$po) }}" class="num">{{ $po->po_no }}</a>
                <span class="hint">· توريد {{ $po->delivery_date?->format('Y-m-d') ?? '—' }}</span>
              @else — @endif
            </td></tr>
          <tr><th>إذن الإضافة</th>
            <td>
              @if($sa)
                <a href="{{ route('stock-additions.edit',$sa) }}" class="num">{{ $sa->doc_no }}</a>
                <span class="hint">· {{ $sa->doc_date?->format('Y-m-d') }}{{ $sa->paper_serial ? ' · مسلسل ورقي ' . $sa->paper_serial : '' }}</span>
              @else — @endif
            </td></tr>
          <tr><th>رقم إذن المورد</th>
            <td>@include('partials.supplier_doc', ['sa' => $sa ?: $c?->sourceAddition()])</td></tr>
          <tr><th>المخزن</th><td>{{ $c->warehouse?->name ?? '—' }}</td></tr>
          <tr><th>تاريخ الوصول</th><td class="num">{{ $c->arrival_date?->format('Y-m-d') ?? '—' }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">اللي الجودة عملته</div>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><th style="width:45%">تقرير الفحص</th>
            <td>
              @if($insp)
                <a href="{{ route('inspections.edit',$insp) }}" class="num">{{ $insp->doc_no }}</a>
                <span class="badge bg-{{ $insp->result === 'accepted' ? 'success' : 'warning' }}">{{ $insp->result_name }}</span>
                @if($insp->paper_serial)<div class="hint">مسلسل ورقي <a href="{{ route('inspections.print',$insp) }}" target="_blank" class="num">{{ $insp->paper_serial }}</a> · {{ $insp->doc_date?->format('Y-m-d') }} · {{ $insp->inspector?->name }}</div>@endif
              @else <span class="text-danger">ناقص</span> @endif
            </td></tr>
          <tr><th>الجرد</th>
            <td class="num {{ $insp && $insp->rolls_variance != 0 ? 'text-danger fw-bold' : '' }}">
              {{ $insp ? $insp->counted_rolls . '/' . $insp->declared_rolls . ' — ' . $insp->rolls_variance_label : '—' }}
            </td></tr>
          @php $est = $insp?->weightEstimate(); @endphp
          @if($est)
            <tr><th>الوزن المتوقع من العيّنة</th>
              <td class="num {{ $est['level'] === 'danger' ? 'text-danger fw-bold' : ($est['level'] === 'warn' ? 'text-warning fw-bold' : '') }}">
                {{ lv_kg($est['expected'], false) }} <span class="hint">({{ $est['diff_pct'] >= 0 ? '+' : '' }}{{ lv_num($est['diff_pct'], 1) }}% عن الإذن)</span>
                @if($est['level'] === 'danger')<div class="hint">راجع الوزن مع المخزن قبل الإفراج</div>@endif</td></tr>
          @endif
          <tr><th>العيّنة ونسبة العيوب</th>
            <td class="num">{{ $insp ? $insp->sample_pct . '% · عيوب ' . $insp->defect_pct . '%' : '—' }}</td></tr>
          <tr><th>تقرير المعمل</th>
            <td>
              @if($lab)
                <a href="{{ route('lab-reports.edit',$lab) }}" class="num">{{ $lab->doc_no }}</a>
                <span class="hint">· بنشر {{ lv_num($lab->avg_gsm, 2) }}</span>
                @if($lab->paper_serial)<div class="hint">مسلسل ورقي <a href="{{ route('lab-reports.print',$lab) }}" target="_blank" class="num">{{ $lab->paper_serial }}</a> · {{ $lab->doc_date?->format('Y-m-d') }} · {{ $lab->technician?->name }}</div>@endif
              @else <span class="text-danger">ناقص</span> @endif
            </td></tr>
          <tr><th>الانكماش ومطابقة اللون</th>
            <td class="num">
              {{ $lab ? 'طول ' . lv_num($lab->avg_shrink_len_pct, 2) . '% · عرض ' . lv_num($lab->avg_shrink_width_pct, 2) . '%' : '—' }}
              @if($lab?->has_color_break)<span class="badge bg-danger">فواصل / اختلاف لون</span>@endif
              @if($lab)
                <span class="badge bg-{{ $lab->color_match_ok ? 'success' : 'danger' }}">
                  {{ $lab->color_match_ok ? 'اللون مطابق' : 'اللون مش مطابق' }}
                </span>
              @endif
            </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<form method="post" action="{{ $mode==='create' ? route('goods-receipts.store') : route('goods-receipts.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- كل بيانات الرأس بتتبعت من الرسالة — المستخدم ما بيكتبش حاجة --}}
  <input type="hidden" name="doc_date"             value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
  <input type="hidden" name="consignment_id"       value="{{ old('consignment_id', $row->consignment_id ?? $c->id) }}">
  <input type="hidden" name="warehouse_id"         value="{{ old('warehouse_id', $row->warehouse_id ?? $c->warehouse_id) }}">
  <input type="hidden" name="supplier_id"          value="{{ old('supplier_id', $row->supplier_id ?? $c->supplier_id) }}">
  <input type="hidden" name="purchase_order_id"    value="{{ old('purchase_order_id', $row->purchase_order_id ?? $c->purchase_order_id) }}">
  <input type="hidden" name="stock_addition_id"    value="{{ old('stock_addition_id', $row->stock_addition_id ?? $sa?->id) }}">
  <input type="hidden" name="fabric_inspection_id" value="{{ old('fabric_inspection_id', $row->fabric_inspection_id ?? $insp?->id) }}">

  {{-- ③ الأصناف المستلمة — Labels + قيم مخفية --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>الأصناف اللي هتدخل المخزن</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('goods-receipts.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"
             aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:100px">الكود</th><th>الصنف</th>
          <th style="width:170px">اللون</th><th style="width:90px">العرض</th>
          <th style="width:90px">ع.أتواب</th><th style="width:120px">الكمية</th>
        </tr></thead>
        <tbody>
          @foreach($lines as $i => $l)
            @php
              $l = (array) $l;
              $ft = \App\Models\FabricType::find($l['fabric_type_id'] ?? null);
              $cl = \App\Models\Color::find($l['color_id'] ?? null);
            @endphp
            <tr>
              <td class="num">{{ $i + 1 }}</td>
              <td class="num">{{ $l['item_code'] ?? $ft?->code ?? '—' }}</td>
              <td>{{ $ft?->name ?? '—' }}</td>
              <td>{{ $cl?->label ?? $cl?->code ?? '—' }}</td>
              <td class="num">{{ $l['width_cm'] ?? '—' }}</td>
              <td class="num">{{ (int) ($l['rolls_count'] ?? 0) }}</td>
              <td class="num fw-bold">{{ $num($l['qty'] ?? 0, 3) }} {{ $l['unit'] ?? 'كجم' }}</td>

              <input type="hidden" name="lines[{{ $i }}][item_code]"      value="{{ $l['item_code'] ?? $ft?->code }}">
              <input type="hidden" name="lines[{{ $i }}][fabric_type_id]" value="{{ $l['fabric_type_id'] ?? '' }}">
              <input type="hidden" name="lines[{{ $i }}][color_id]"       value="{{ $l['color_id'] ?? '' }}">
              <input type="hidden" name="lines[{{ $i }}][unit]"           value="{{ $l['unit'] ?? 'كجم' }}">
              <input type="hidden" name="lines[{{ $i }}][width_cm]"       value="{{ $l['width_cm'] ?? '' }}">
              <input type="hidden" name="lines[{{ $i }}][rolls_count]"    value="{{ $l['rolls_count'] ?? 0 }}">
              <input type="hidden" name="lines[{{ $i }}][qty]"            value="{{ $l['qty'] ?? 0 }}">
              <input type="hidden" name="lines[{{ $i }}][consignment_no]" value="{{ $l['consignment_no'] ?? $c->consignment_no }}">
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الأرقام دي هي اللي طلعت من الجرد والفحص — مش اللي المورد قال عليها.
    </div>
  </div>

  {{-- الأتواب اللي الجودة رفضتها في الفحص — جاهزة بتفاصيلها، مفيش حاجة تتكتب تاني --}}
  @php
    $rejRolls = $insp ? $insp->rolls()->where('status', 'rejected')->orderBy('id')->get() : collect();
    $inspRej  = $insp ? \App\Models\GoodsReceiptRejection::where('fabric_inspection_id', $insp->id)->whereNull('goods_receipt_id')->get() : collect();
  @endphp
  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-x-octagon" aria-hidden="true"></i> الأتواب المرفوضة من الفحص
      <span class="hint">— اتسجلت في تقرير الفحص واتخصمت من الرسالة ومن طلب الشراء</span></div>
    @if($rejRolls->count())
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>رقم التوب</th><th>الوزن</th><th>الطول</th><th>العرض</th><th>العيب</th><th>ملاحظات</th></tr></thead>
          <tbody>
          @foreach($rejRolls as $r)
            <tr class="table-danger">
              <td class="num fw-bold">{{ $r->roll_no }}</td>
              <td class="num">{{ $r->net_kg ? lv_kg($r->net_kg, false) : '—' }}</td>
              <td class="num">{{ (float) $r->length_m > 0 ? lv_num($r->length_m, 2) . ' م' : '—' }}</td>
              <td class="num">{{ $r->width_cm ? lv_num($r->width_cm, 2) . ' سم' : '—' }}</td>
              <td>{{ $r->defect_desc ?: '—' }}@if($r->defects_count) <span class="hint">({{ $r->defects_count }})</span>@endif</td>
              <td class="hint">{{ $r->notes }}</td>
            </tr>
          @endforeach
          <tr class="fw-bold">
            <td>{{ $rejRolls->count() }} توب</td>
            <td class="num">{{ lv_kg($insp->rejected_kg, false) }}</td>
            <td colspan="4" class="hint">
              @foreach($inspRej as $rj)
                مطالبة على المورد: <span class="badge bg-{{ $rj->resolution === 'open' ? 'danger' : 'success' }}">{{ $rj->resolution_name }}</span>
                <a href="{{ route('rejections.index') }}">شاشة المرفوضات</a>
              @endforeach
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    @else
      <div class="card-body py-2 text-success"><i class="bi bi-check2-circle" aria-hidden="true"></i>
        الرسالة دي مفيهاش أي أتواب مرفوضة في الفحص — كل الأتواب المفحوصة مطابقة.</div>
    @endif
  </div>

  @if($editable)

    <div class="form-actions">
    <button class="btn btn-plum">
      <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> استلم ونزّل المخزن
    </button>
    <span class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      القماش هيبقى متاح لأوامر الشغل على طول، والكمية المستلمة هتتحدّث في طلب الشراء.
    </span>
    </div>
  @endif
</form>

@if($mode === 'edit' && $row->consignment)
  <a href="{{ route('consignments.show', $row->consignment) }}" class="btn btn-outline-plum btn-sm mt-2">
    <i class="bi bi-box-seam" aria-hidden="true"></i> فتح الرسالة {{ $row->consignment->consignment_no }}
  </a>
@endif


@endif
@endsection
