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

{{-- ① السامري — جدول عريض في النص: الرسالة | إذن الإضافة | طلب الشراء | الفحص | المعمل. كله Labels وكله لينكات --}}
@php
  $st  = $c->orderLineStatus();
  $over = $st ? (float) $st['received'] - (float) $st['ordered'] : 0;
  $est = $insp?->weightEstimate();
@endphp
<style>
  .sum-sec{display:grid;grid-template-columns:150px 1fr;gap:10px;align-items:start;padding:10px 0;border-bottom:1px dashed var(--lv-line)}
  .sum-sec:last-child{border-bottom:0}
  .sum-sec .t{font-weight:700;color:var(--lv-brand-ink);padding-top:4px;font-size:var(--fs-sm)}
  .sum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px;text-align:center;font-size:var(--fs-sm)}
  .sum-grid .k{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.2}
  .sum-grid .v{font-weight:600;font-size:var(--fs-sm)}
  .sum-grid a.v{font-size:var(--fs-sm)}
  @media (max-width:768px){.sum-sec{grid-template-columns:1fr}}
</style>

{{-- الأرقام اللي هتدخل المخزن — كبيرة في الأول --}}
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="hint">الرسالة</div>
        <a href="{{ route('consignments.show', $c) }}" class="fw-bold num" style="font-size:1.35rem;color:var(--lv-brand-ink)">{{ $c->consignment_no }}</a>
        <div class="hint">{{ $c->fabricType?->name ?? '—' }} · {{ $c->color?->label ?? $c->color?->code ?? '—' }}</div>
      </div>
      <div class="d-flex gap-4 flex-wrap text-center">
        <div><div class="hint">الكمية اللي هتدخل</div><b class="num" style="font-size:1.25rem">{{ lv_kg($c->total_kg) }}</b></div>
        <div><div class="hint">الأتواب</div><b class="num" style="font-size:1.25rem">{{ (int) $c->rolls_count }}</b></div>
        <div><div class="hint">أقل عرض</div><b class="num text-danger" style="font-size:1.25rem">{{ $c->min_width_cm ? lv_num($c->min_width_cm, 2) . ' سم' : '—' }}</b></div>
        <div><div class="hint">البنشر</div><b class="num" style="font-size:1.25rem">{{ $c->avg_gsm ? lv_num($c->avg_gsm, 2) : '—' }} <span class="hint">جم/م²</span></b></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3" style="border-color:var(--lv-soft)">
  <div class="card-body py-1">

    <div class="sum-sec">
      <div class="t"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> إذن الإضافة</div>
      <div class="sum-grid">
        <div><div class="k">رقم الإذن</div>@if($sa)<a href="{{ route('stock-additions.edit', $sa) }}" class="v num">{{ $sa->doc_no }}</a>@else <div class="v">—</div> @endif</div>
        <div><div class="k">تاريخ إذن الإضافة</div><div class="v num">{{ $sa?->doc_date?->format('Y-m-d') ?? '—' }}</div></div>
        <div><div class="k">مسلسل إذن الإضافة (الورقي)</div>@if($sa)<a href="{{ route('stock-additions.print', $sa) }}" target="_blank" class="v num">{{ $sa->paper_serial ?: '—' }}</a>@else <div class="v">—</div> @endif</div>
        <div><div class="k">إذن المورد</div>@if($sa)<a href="{{ route('stock-additions.edit', $sa) }}" class="v num">{{ $sa->supplier_doc_no ?: '—' }}</a>@else <div class="v">—</div> @endif</div>
        <div><div class="k">المورد</div><div class="v">{{ $c->supplier?->name ?? '—' }}</div></div>
        <div><div class="k">المخزن</div><div class="v">{{ $sa?->warehouse?->name ?? $c->warehouse?->name ?? '—' }}</div></div>
        <div><div class="k">تاريخ الوصول</div><div class="v num">{{ $c->arrival_date?->format('Y-m-d') ?? '—' }}</div></div>
      </div>
    </div>

    <div class="sum-sec">
      <div class="t"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> طلب الشراء</div>
      <div>
        <div class="sum-grid">
          <div><div class="k">رقم الطلب</div>@if($po)<a href="{{ route('purchase-orders.edit', $po) }}" class="v num">{{ $po->po_no }}</a>@else <div class="v">بدون طلب</div> @endif</div>
          <div><div class="k">تاريخ الطلب</div><div class="v num">{{ $po?->po_date?->format('Y-m-d') ?? '—' }}</div></div>
          <div><div class="k">توريد متوقع</div><div class="v num">{{ $po?->delivery_date?->format('Y-m-d') ?? '—' }}</div></div>
          <div><div class="k">طلبه</div><div class="v">{{ $po?->requester?->name ?? '—' }}</div><div class="k num">{{ $po?->requested_at?->format('Y-m-d') }}</div></div>
          <div><div class="k">سعّره</div><div class="v">{{ $po?->sourcer?->name ?? '—' }}</div><div class="k num">{{ $po?->sourced_at?->format('Y-m-d') }}</div></div>
          <div><div class="k">استلمه (إذن الإضافة)</div><div class="v">{{ $sa?->creator?->name ?? '—' }}</div><div class="k num">{{ $sa?->doc_date?->format('Y-m-d') }}</div></div>
        </div>
        @if($st)
          <div class="mt-2 p-1 rounded text-center d-flex flex-wrap justify-content-center gap-3 align-items-center small"
               style="background:{{ $st['left'] > 0.0001 ? 'var(--lv-warn-bg)' : ($over > 0.0001 ? 'var(--lv-danger-bg)' : 'var(--lv-ok-bg)') }}">
            <span class="hint">الصنف ده على الطلب:</span>
            <span>مطلوب <b class="num">{{ lv_qty($st['ordered'], $st['unit']) }}</b></span>
            @if($st['left'] > 0.0001)
              <span>وصل لحد دلوقتي <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
              <span class="text-danger">باقي <b class="num">{{ lv_qty($st['left'], $st['unit']) }}</b> — {{ $st['eta'] ? 'متوقع ' . $st['eta'] : 'الموعد مش محدد' }}</span>
            @elseif($over > 0.0001)
              <span>وصل <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
              <span class="text-danger fw-bold">زيادة <b class="num">{{ lv_qty($over, $st['unit']) }}</b> — جوه نسبة الزيادة {{ lv_num($st['line']->tolerance_pct, 2) }}%</span>
            @else
              <span>وصل <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
              <span class="text-success fw-bold"><i class="bi bi-check2-circle" aria-hidden="true"></i> الصنف اكتمل على الطلب بالكامل</span>
            @endif
          </div>
        @endif
      </div>
    </div>

    <div class="sum-sec">
      <div class="t"><i class="bi bi-search" aria-hidden="true"></i> الفحص</div>
      <div class="sum-grid">
        <div><div class="k">تقرير الفحص</div>@if($insp)<a href="{{ route('inspections.edit', $insp) }}" class="v num">{{ $insp->doc_no }}</a>@else <div class="v text-danger">ناقص</div> @endif</div>
        <div><div class="k">المسلسل الورقي</div>@if($insp?->paper_serial)<a href="{{ route('inspections.print', $insp) }}" target="_blank" class="v num">{{ $insp->paper_serial }}</a>@else <div class="v">—</div> @endif</div>
        <div><div class="k">النتيجة</div><div class="v">@if($insp)<span class="badge bg-{{ $insp->result === 'accepted' ? 'success' : 'warning' }}">{{ $insp->result_name }}</span>@else — @endif</div></div>
        <div><div class="k">الجرد</div><div class="v num {{ $insp && $insp->rolls_variance != 0 ? 'text-danger' : '' }}">{{ $insp ? $insp->counted_rolls . '/' . $insp->declared_rolls : '—' }}</div><div class="k">{{ $insp?->rolls_variance_label }}</div></div>
        <div><div class="k">العيّنة · العيوب</div><div class="v num">{{ $insp ? lv_num($insp->sample_pct, 1) . '% · ' . lv_num($insp->defect_pct, 2) . '%' : '—' }}</div></div>
        <div><div class="k">الوزن المتوقع من العيّنة</div>
          @if($est)<div class="v num {{ $est['level'] === 'danger' ? 'text-danger' : ($est['level'] === 'warn' ? 'text-warning' : '') }}">{{ lv_kg($est['expected'], false) }}</div>
            <div class="k">{{ $est['diff_pct'] >= 0 ? '+' : '' }}{{ lv_num($est['diff_pct'], 1) }}% عن الإذن{{ $est['level'] === 'danger' ? ' — راجع الوزن' : '' }}</div>
          @else <div class="v">—</div> @endif</div>
        <div><div class="k">فحصه</div><div class="v">{{ $insp?->inspector?->name ?? '—' }}</div><div class="k num">{{ $insp?->doc_date?->format('Y-m-d') }}</div></div>
      </div>
    </div>

    <div class="sum-sec">
      <div class="t"><i class="bi bi-thermometer-half" aria-hidden="true"></i> المعمل</div>
      <div class="sum-grid">
        <div><div class="k">تقرير المعمل</div>@if($lab)<a href="{{ route('lab-reports.edit', $lab) }}" class="v num">{{ $lab->doc_no }}</a>@else <div class="v text-danger">ناقص</div> @endif</div>
        <div><div class="k">المسلسل الورقي</div>@if($lab?->paper_serial)<a href="{{ route('lab-reports.print', $lab) }}" target="_blank" class="v num">{{ $lab->paper_serial }}</a>@else <div class="v">—</div> @endif</div>
        <div><div class="k">متوسط البنشر</div><div class="v num {{ $lab?->gsm_out_of_spec ? 'text-danger' : '' }}">{{ $lab ? lv_num($lab->avg_gsm, 2) . ' جم/م²' : '—' }}</div>@if($lab?->gsm_out_of_spec)<div class="k text-danger">خارج المواصفة</div>@endif</div>
        <div><div class="k">الانكماش طول · عرض</div><div class="v num">{{ $lab ? lv_num($lab->avg_shrink_len_pct, 2) . '% · ' . lv_num($lab->avg_shrink_width_pct, 2) . '%' : '—' }}</div></div>
        <div><div class="k">مطابقة اللون</div><div class="v">@if($lab)<span class="badge bg-{{ $lab->color_match_ok ? 'success' : 'danger' }}">{{ $lab->color_match_ok ? 'مطابق' : 'مش مطابق' }}</span>@else — @endif</div></div>
        <div><div class="k">فواصل / اختلاف لون</div><div class="v">@if($lab?->has_color_break)<span class="badge bg-danger">نعم</span><div class="k">{{ $lab->color_break_roll_no ? 'أتواب ' . $lab->color_break_roll_no : '' }} {{ $lab->color_break_note }}</div>@elseif($lab)<span class="text-success">لا</span>@else — @endif</div></div>
        <div><div class="k">سجّله</div><div class="v">{{ $lab?->technician?->name ?? '—' }}</div><div class="k num">{{ $lab?->doc_date?->format('Y-m-d') }}</div></div>
      </div>
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
