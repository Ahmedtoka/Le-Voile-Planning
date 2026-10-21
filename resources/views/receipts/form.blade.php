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
  $sa       = $c?->stockAdditions?->first();
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
      <div><div class="hint">الكمية</div><b class="num">{{ $num($c->total_kg) }} كجم</b></div>
      <div><div class="hint">الأتواب</div><b class="num">{{ (int) ($insp?->counted_rolls ?: $c->rolls_count) }}</b></div>
      <div><div class="hint">أقل عرض</div><b class="num text-danger">{{ $c->min_width_cm ?: '—' }} سم</b></div>
      <div><div class="hint">البنشر</div><b class="num">{{ $c->avg_gsm ?: '—' }}</b></div>
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
                <span class="hint">· {{ $sa->doc_date?->format('Y-m-d') }}</span>
              @else — @endif
            </td></tr>
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
              @else <span class="text-danger">ناقص</span> @endif
            </td></tr>
          <tr><th>الجرد</th>
            <td class="num {{ $insp && $insp->rolls_variance != 0 ? 'text-danger fw-bold' : '' }}">
              {{ $insp ? $insp->counted_rolls . '/' . $insp->declared_rolls . ' — ' . $insp->rolls_variance_label : '—' }}
            </td></tr>
          <tr><th>العيّنة ونسبة العيوب</th>
            <td class="num">{{ $insp ? $insp->sample_pct . '% · عيوب ' . $insp->defect_pct . '%' : '—' }}</td></tr>
          <tr><th>تقرير المعمل</th>
            <td>
              @if($lab)
                <a href="{{ route('lab-reports.edit',$lab) }}" class="num">{{ $lab->doc_no }}</a>
                <span class="hint">· بنشر {{ $lab->avg_gsm }}</span>
              @else <span class="text-danger">ناقص</span> @endif
            </td></tr>
          <tr><th>الانكماش ومطابقة اللون</th>
            <td class="num">
              {{ $lab ? 'طول ' . $lab->avg_shrink_len_pct . '% · عرض ' . $lab->avg_shrink_width_pct . '%' : '—' }}
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

  @if($editable)
    {{-- رفض جزئي / تعليق قبل الإفراج — اختياري. الحفظ = إفراج، فلازم يتسجل هنا
         مع نفس الحفظ علشان المرفوض والمعلّق يتخصموا من المتاح للتشغيل. --}}
    <details class="card mb-3" @if(old('rejects')) open @endif>
      <summary class="card-header" style="cursor:pointer">
        <i class="bi bi-x-octagon" aria-hidden="true"></i> فيه أتواب مرفوضة أو معلّقة؟ <span class="hint">(اختياري — سيبه فاضي لو الرسالة كلها سليمة)</span>
      </summary>
      <div class="card-body pb-1">
        <div class="hint mb-2">
          المرفوض بيروح مخزن المرتجعات كمطالبة على المورد، والمعلّق بيستنى قرار التخطيط والمشتريات.
          الاتنين بيتخصموا من اللي هيتفرج عنه — والباقي بس هو اللي يبقى متاح لأوامر الشغل.
          <br><b>نوع السبب مهم:</b> العرض/البنشر/الانكماش المختلف <b>قابل للتفاوض</b> (ممكن يتقبل بخصم من المورد)،
          أما اختلاف اللون والفواصل <b>مش قابل للتفاوض</b> — بيرجع للمورد.
        </div>
        <table class="table table-sm align-middle mb-2">
          <thead><tr><th style="width:130px">النوع</th><th style="width:80px">أتواب</th><th style="width:110px">الوزن (كجم)</th><th style="width:150px">الجهة</th><th style="width:190px">نوع السبب</th><th>السبب</th></tr></thead>
          <tbody>
          @for($r = 0; $r < 3; $r++)
            <tr>
              <td><select name="rejects[{{ $r }}][kind]" class="form-select form-select-sm">
                <option value="rejected" @selected(old("rejects.$r.kind") === 'rejected')>مرفوض</option>
                <option value="on_hold" @selected(old("rejects.$r.kind") === 'on_hold')>معلّق لحين الرد</option>
              </select></td>
              <td><input type="number" min="0" name="rejects[{{ $r }}][rolls_count]" value="{{ old("rejects.$r.rolls_count") }}" class="form-control form-control-sm"></td>
              <td><input type="number" step="0.001" min="0" name="rejects[{{ $r }}][qty]" value="{{ old("rejects.$r.qty") }}" class="form-control form-control-sm"></td>
              <td><select name="rejects[{{ $r }}][party]" class="form-select form-select-sm">
                <option value="quality">مصلحة الجودة</option>
                <option value="planning" @selected(old("rejects.$r.party") === 'planning')>إدارة التخطيط</option>
                <option value="purchasing" @selected(old("rejects.$r.party") === 'purchasing')>إدارة المشتريات</option>
              </select></td>
              <td><select name="rejects[{{ $r }}][reason_category]" class="form-select form-select-sm">
                @foreach(\App\Models\GoodsReceiptRejection::CATEGORIES as $ck => $cv)
                  <option value="{{ $ck }}" @selected(old("rejects.$r.reason_category", 'other') === $ck)>{{ $cv }}</option>
                @endforeach
              </select></td>
              <td><input name="rejects[{{ $r }}][reason]" value="{{ old("rejects.$r.reason") }}" class="form-control form-control-sm"
                         placeholder="مثال: بيهم تل غريبة وتناسخ · لون غير مطابق"></td>
            </tr>
          @endfor
          </tbody>
        </table>
      </div>
    </details>

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

{{-- ④ رفض جزئي أو تعليق لون — استثناء، مش خطوة عادية --}}
@if($mode === 'edit')
  @php $rejections = $row->consignment?->rejections()->where('goods_receipt_id', $row->id)->get() ?? collect(); @endphp
  <div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-x-octagon" aria-hidden="true"></i> رفض جزئي أو تعليق لون</span>
      <span class="hint">{{ $rejections->count() }} بند</span>
    </div>

    <div class="card-body pb-2">
      <div class="hint mb-3">
        الحالة العادية إن الرفض بيتسجّل في <b>تقرير الفحص</b>. القسم ده للاستثناء:
        رفض أتواب بعينها من رسالة سليمة — «تم رفض عدد 2 توب كود 1132 بوزنه 8.36 كيلو».
        المرفوض ما بيدخلش المخزون وما بيتحسبش على طلب الشراء.
      </div>

      @forelse($rejections as $rj)
        <div class="d-flex align-items-start gap-2 mb-2 pb-2" style="border-bottom:1px dashed var(--lv-line)">
          <span class="badge bg-{{ $rj->kind === 'on_hold' ? 'warning' : 'danger' }}">{{ $rj->kind_name }}</span>
          <div class="flex-grow-1">
            <b style="font-size:.85rem">{{ $rj->label }}</b>
            <div class="hint">{{ $rj->party_name }} — {{ $rj->reason }}</div>
          </div>
          <span class="badge bg-{{ $rj->resolution === 'open' ? 'secondary' : 'success' }}">{{ $rj->resolution_name }}</span>
          @if($rj->resolution === 'open' && $row->isEditable())
            <form method="post" action="{{ route('rejections.destroy', $rj) }}" onsubmit="return confirm('حذف البند؟')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-link text-danger p-0" style="font-size:.75rem">حذف</button>
            </form>
          @endif
        </div>
      @empty
        <div class="text-muted small py-2">مفيش رفض ولا تعليق على الإذن ده.</div>
      @endforelse
    </div>

    @if($row->isEditable())
      <div class="card-footer bg-white">
        <form method="post" action="{{ route('rejections.store', $row) }}" class="row g-2">@csrf
          <div class="col-md-2">
            <label class="form-label req">النوع</label>
            <select name="kind" class="form-select form-select-sm" required>
              <option value="rejected">مرفوض</option>
              <option value="on_hold">معلّق لحين الرد</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">كود اللون</label>
            <input name="color_code" class="form-control form-control-sm" placeholder="1132">
          </div>
          <div class="col-md-2">
            <label class="form-label">وصف الحوض</label>
            <input name="lot_label" class="form-control form-control-sm" placeholder="الحوض الأخضر">
          </div>
          <div class="col-md-1">
            <label class="form-label req">أتواب</label>
            <input type="number" name="rolls_count" class="form-control form-control-sm" value="0" required>
          </div>
          <div class="col-md-2">
            <label class="form-label req">الوزن</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.001" name="qty" class="form-control" required>
              <select name="unit" class="form-select" style="max-width:75px">
                <option value="كجم">كجم</option><option value="متر">متر</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label req">الجهة</label>
            <select name="party" class="form-select form-select-sm" required>
              <option value="quality">مصلحة الجودة</option>
              <option value="planning">إدارة التخطيط</option>
              <option value="purchasing">إدارة المشتريات</option>
            </select>
          </div>
          <div class="col-md-10">
            <label class="form-label req">السبب</label>
            <input name="reason" class="form-control form-control-sm" required
                   placeholder="مثال: بيهم تل غريبة وتناسخ · لون غير مطابق">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-danger btn-sm w-100">تسجيل</button>
          </div>
        </form>
      </div>
    @endif
  </div>
@endif

@endif
@endsection
