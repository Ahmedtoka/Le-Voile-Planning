@extends('layouts.app')
@section('purpose', 'جرد الأتواب وقياس كل توب + قراءات المعمل في نفس الورقة — أقل عرض هو اللي الماركر هيتبني عليه')
@section('content')
@php
  /* الصفحة بتفتح على توب واحد. عدد السطور بيتولّد من «الأتواب المجرودة فعليًا». */
  $rolls    = old('rolls', $row->rolls?->toArray() ?? []);
  $editable = $row->isEditable() || $mode === 'create';
  $result   = old('result', in_array($row->result, ['accepted','accepted_with_notes','rejected'], true) ? $row->result : '');
  $lab      = $lab ?? null;                 // تقرير المعمل المدمج (لو موجود)
  $c        = $arrived ?? null;
  $st       = $c?->orderLineStatus();
  $sa       = $c?->sourceAddition();
  $ft       = $c?->fabricType;
  $oldS = fn ($k) => old($k, $lab?->{$k});
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'inspection'])
@php $meter = (bool) ($c?->is_meter ?? false); @endphp

{{-- ① سامري الرسالة — جدول عريض في النص، تلات مجموعات: الرسالة | إذن الإضافة | طلب الشراء --}}
@if($c)
  @php $over = $st ? (float) $st['received'] - (float) $st['ordered'] : 0; $po = $c->purchaseOrder; @endphp
  <div class="ro-box mb-3">
    <div class="ro-title">بيانات الرسالة — للقراءة</div>

      <div class="sum-sec">
        <div class="t"><i class="bi bi-box-seam" aria-hidden="true"></i> الرسالة</div>
        <div class="sum-grid">
          <div><div class="k">رقم الرسالة</div><a href="{{ route('consignments.show', $c) }}" class="v num">{{ $c->consignment_no }}</a></div>
          <div><div class="k">تاريخ الوصول</div><div class="v num">{{ $c->arrival_date?->format('Y-m-d') }}</div></div>
          <div><div class="k">الصنف</div><div class="v">{{ $c->fabricType?->name ?? '—' }}</div></div>
          <div><div class="k">اللون</div><div class="v">{{ $c->color?->label ?? $c->color?->code ?? '—' }}</div></div>
          <div><div class="k">وصل في الرسالة دي (صافي)</div><div class="v num">{{ $c->is_meter ? lv_num($c->total_kg, 2) . ' م' : lv_kg($c->total_kg) }}</div>
            <div class="k">{{ (int) $c->rolls_count }} توب{{ !$c->is_meter && (float) $c->gross_kg > 0 ? ' · قائم ' . lv_num($c->gross_kg) . ' − كيس وماسورة ' . lv_num($c->packaging_kg) : '' }}</div></div>
          <div><div class="k">نوع الاستلام</div><div class="v">{{ $sa?->receipt_type === 'container' ? 'مباشر — بدون فحص' : 'عادي — يدخل الفحص' }}</div></div>
        </div>
      </div>

      <div class="sum-sec">
        <div class="t"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> إذن الإضافة</div>
        <div class="sum-grid">
          <div><div class="k">رقم الإذن</div>@if($sa)<a href="{{ route('stock-additions.edit', $sa) }}" class="v num">{{ $sa->doc_no }}</a>@else <div class="v">—</div> @endif</div>
          <div><div class="k">تاريخ إذن الإضافة</div><div class="v num">{{ $sa?->doc_date?->format('Y-m-d') ?? '—' }}</div></div>
          <div><div class="k">مسلسل إذن الإضافة (الورقي)</div>@if($sa)<a href="{{ route('stock-additions.print', $sa) }}" target="_blank" class="v num" title="يفتح الورقة للطباعة">{{ $sa->paper_serial ?: '—' }}</a>@else <div class="v">—</div> @endif</div>
          <div><div class="k">إذن المورد</div>@if($sa)<a href="{{ route('stock-additions.edit', $sa) }}" class="v num">{{ $sa->supplier_doc_no ?: '—' }}</a>@else <div class="v">—</div> @endif</div>
          <div><div class="k">المورد</div><div class="v">{{ $c->supplier?->name ?? '—' }}</div></div>
          <div><div class="k">المخزن</div><div class="v">{{ $sa?->warehouse?->name ?? $c->warehouse?->name ?? '—' }}</div></div>
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
            @php $stTone = $st['left'] > 0.0001 ? 'warning' : ($over > 0.0001 ? 'danger' : 'success'); @endphp
            <div class="alert alert-{{ $stTone }} py-1 px-2 mt-2 mb-0 text-center d-flex flex-wrap justify-content-center gap-3 align-items-center small">
              <span class="hint">الصنف ده على الطلب:</span>
              <span>مطلوب <b class="num">{{ lv_qty($st['ordered'], $st['unit']) }}</b></span>
              @if($st['left'] > 0.0001)
                <span>وصل لحد دلوقتي <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
                <span class="text-danger">باقي <b class="num">{{ lv_qty($st['left'], $st['unit']) }}</b>
                  @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . lv_qty($st['ordered'], $st['unit']) . ' − الواصل ' . lv_qty($st['received'], $st['unit']) . ' = ' . lv_qty($st['left'], $st['unit'])])
                  — {{ $st['eta'] ? 'متوقع ' . $st['eta'] : 'الموعد مش محدد' }}</span>
              @elseif($over > 0.0001)
                @php $overPct = $over / max(0.0001, (float) $st['ordered']) * 100; @endphp
                <span>وصل <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
                <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                  زيادة <b class="num">{{ lv_qty($over, $st['unit']) }}</b> ({{ lv_num($overPct, 1) }}%)
                  @include('partials.fx', ['t' => 'الزيادة على الطلب', 'f' => ['الواصل ' . lv_qty($st['received'], $st['unit']) . ' − المطلوب ' . lv_qty($st['ordered'], $st['unit']) . ' = ' . lv_qty($over, $st['unit']), 'الزيادة ' . lv_num($over, 3) . ' ÷ المطلوب ' . lv_num($st['ordered'], 3) . ' × 100 = ' . lv_num($overPct, 1) . '%'], 'why' => 'المسموح لحد نسبة الزيادة ' . lv_num($st['line']->tolerance_pct, 2) . '%'])
                  — جوه نسبة الزيادة {{ lv_num($st['line']->tolerance_pct, 2) }}%</span>
              @else
                <span>وصل <b class="num">{{ lv_qty($st['received'], $st['unit']) }}</b></span>
                <span class="text-success fw-bold"><i class="bi bi-check2-circle" aria-hidden="true"></i> الصنف اكتمل على الطلب بالكامل</span>
              @endif
            </div>
          @endif
        </div>
      </div>

  </div>
@endif

<form method="post" id="inspForm" enctype="multipart/form-data"
      action="{{ $mode==='create' ? route('inspections.store') : route('inspections.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ③ الجرد: Labels في ناحية، واللي هتكتبه في ناحية --}}
  <div class="card mb-3">
    <div class="card-header"><span>① الجرد <span class="hint">— كام توب موجود فعلًا مقابل اللي إذن الإضافة قال عليه</span></span></div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if($c)
        <input type="hidden" name="doc_date"       value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="consignment_id" value="{{ old('consignment_id', $row->consignment_id ?? $c->id) }}">
        <input type="hidden" name="fabric_type_id" value="{{ old('fabric_type_id', $row->fabric_type_id ?? $c->fabric_type_id) }}">
        <input type="hidden" name="color_id"       value="{{ old('color_id', $row->color_id ?? $c->color_id) }}">
        <input type="hidden" name="supplier_id"    value="{{ old('supplier_id', $row->supplier_id ?? $c->supplier_id) }}">
        <input type="hidden" name="inspector_id"   value="{{ old('inspector_id', $row->inspector_id ?? auth()->id()) }}">
        <input type="hidden" name="declared_rolls" value="{{ old('declared_rolls', $row->declared_rolls ?? $c->rolls_count) }}">
      @else
        <input type="hidden" name="doc_date"     value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="inspector_id" value="{{ old('inspector_id', $row->inspector_id ?? auth()->id()) }}">
        <div class="row g-3 mb-3">
          <div class="col-md-5"><label class="form-label req" for="consignment_id">الحوض (الرسالة)</label>
            <select name="consignment_id" id="consignment_id" class="form-select" required><option value="">—</option>
              @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label req" for="declared_rolls">أتواب حسب إذن الإضافة</label>
            <div class="input-group">
              <input type="number" min="0" step="1" name="declared_rolls" id="declared_rolls" class="form-control" value="{{ old('declared_rolls',$row->declared_rolls) }}" required placeholder="40">
              <span class="input-group-text">توب</span>
            </div></div>
        </div>
      @endif

      <div class="row g-3">
        {{-- Labels --}}
        <div class="col-lg-5">
          <div class="ro-box h-100">
            <div class="ro-title">من إذن الإضافة — للقراءة</div>
            <table class="table table-sm kv mb-0">
              <tr><th>أتواب حسب الإذن</th><td class="num">{{ (int) ($row->declared_rolls ?? $c?->rolls_count) }} توب</td></tr>
              @if($c && !$meter && (float) $c->gross_kg > 0)
                {{-- الصافي والقائم مفصّلين سطر سطر بالمعادلة — عشان نتأكد من كل رقم --}}
                <tr><th>الوزن القائم (على الميزان)</th><td class="num">{{ lv_kg($c->gross_kg, false) }}</td></tr>
                <tr><th>الشكل</th><td>{{ $c->fabric_form === 'open' ? 'مفتوح — كيس وماسورة' : ($c->fabric_form === 'closed' ? 'مقفول — كيس بس' : '—') }}</td></tr>
                <tr><th>وزن الكيس الواحد</th><td class="num">{{ lv_num($c->bag_g, 1) }} جم</td></tr>
                @if($c->fabric_form === 'open')<tr><th>وزن الماسورة الواحدة</th><td class="num">{{ lv_num($c->tube_g, 1) }} جم</td></tr>@endif
                <tr><th>خصم الكيس والماسورة</th><td class="num text-danger">− {{ lv_num($c->packaging_kg) }} كجم
                  <div class="hint">({{ lv_num($c->bag_g, 1) }}{{ $c->fabric_form === 'open' ? ' + ' . lv_num($c->tube_g, 1) : '' }}) جم × {{ (int) $c->rolls_count }} توب ÷ 1000</div></td></tr>
                <tr><th>الوزن الصافي (الرصيد)</th><td class="num fw-bold text-success">{{ lv_kg($c->total_kg) }}
                  <div class="hint">{{ lv_num($c->gross_kg) }} − {{ lv_num($c->packaging_kg) }} = {{ lv_num($c->total_kg) }} كجم</div></td></tr>
              @else
                <tr><th>{{ $meter ? 'الأمتار حسب الإذن' : 'الوزن حسب الإذن (صافي)' }}</th><td class="num">{{ $c ? ($meter ? lv_num($c->total_kg, 2) . ' م' : lv_kg($c->total_kg)) : '—' }}</td></tr>
              @endif
              <tr><th>فحصه</th><td>{{ $mode === 'edit' && $row->status === 'approved' ? ($row->inspector?->name ?? '—') : '— لسه (' . auth()->user()?->name . ')' }}
                <span class="hint num">{{ $mode === 'edit' && $row->status === 'approved' ? $row->doc_date?->format('Y-m-d') : '' }}</span></td></tr>
            </table>
          </div>
        </div>
        {{-- Inputs --}}
        <div class="col-lg-7">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label req" for="countedRolls">الأتواب المجرودة فعليًا</label>
              <div class="input-group">
                <input type="number" min="1" step="1" name="counted_rolls" id="countedRolls" class="form-control" value="{{ old('counted_rolls',$row->counted_rolls) }}" required placeholder="40">
                <span class="input-group-text">توب</span>
                @if($editable)<button type="button" class="btn btn-outline-plum" id="buildRows"><i class="bi bi-check2" aria-hidden="true"></i> تم</button>@endif
              </div>
              <div class="hint">اكتب العدد ودوس «تم» — سطر لكل توب</div></div>
            <div class="col-md-4"><label class="form-label" for="counted_kg">{{ $meter ? 'الأمتار المجرودة' : 'الوزن المجرود' }}</label>
              <div class="input-group">
                <input type="number" step="0.001" min="0" name="counted_kg" id="counted_kg" class="form-control" value="{{ old('counted_kg', $row->counted_kg !== null ? rtrim(rtrim(number_format((float) $row->counted_kg, 3, '.', ''), '0'), '.') : '') }}" placeholder="{{ $c ? 'مثال: ' . lv_num($c->total_kg) : 'مثال: 7600' }}">
                <span class="input-group-text">{{ $meter ? 'م' : 'كجم' }}</span>
              </div>
              <div class="hint">{{ $meter ? 'اختياري — مجموع الأمتار المكتوبة على الأتواب' : 'اختياري — الفحص ما بيوزنش؛ لو سيبته فاضي بنقارن بوزن الإذن' }}</div></div>
            <div class="col-md-4"><label class="form-label" for="paper_serial">المسلسل الورقي للفحص</label>
              <input name="paper_serial" id="paper_serial" class="form-control w-short" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="مثال: 04619" inputmode="numeric"></div>
          </div>
        </div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      @php
        /* أرقام المعادلات من الأتواب المحمّلة مع التقرير — من غير استعلام جديد */
        $okRolls  = $row->relationLoaded('rolls') ? $row->rolls->where('status', '!=', 'rejected') : collect();
        $okWidths = $okRolls->pluck('width_cm')->filter(fn ($v) => (float) $v > 0)->map(fn ($v) => (float) $v);
        $okMaxW   = $row->max_width_cm ?? ($okWidths->count() ? $okWidths->max() : null);
        $est      = $row->weightEstimate();
      @endphp
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">الجرد</div>
            <b class="num {{ $row->rolls_variance != 0 ? 'text-danger' : '' }}">{{ $row->counted_rolls }}/{{ $row->declared_rolls }} — {{ $row->rolls_variance_label }}</b>
            @include('partials.fx', ['t' => 'فرق الجرد', 'f' => 'المجرود ' . (int) $row->counted_rolls . ' − حسب الإذن ' . (int) $row->declared_rolls . ' = ' . ($row->rolls_variance > 0 ? '+' : '') . (int) $row->rolls_variance . ' توب'])</div>
          <div class="col"><div class="hint">العيّنة</div><b class="num">{{ $row->sampled_rolls }}/{{ $row->total_rolls }} ({{ lv_num($row->sample_pct, 2) }}%)</b>
            @include('partials.fx', ['t' => 'نسبة العيّنة', 'f' => 'المقاسة ' . (int) $row->sampled_rolls . ' ÷ إجمالي الأتواب ' . (int) $row->total_rolls . ' × 100 = ' . lv_num($row->sample_pct, 2) . '%', 'why' => 'أقل عيّنة مقبولة ' . lv_num(config('lvplanning.min_inspection_sample_pct', 15), 0) . '%'])</div>
          <div class="col"><div class="hint">أقل عرض</div><b class="num text-danger">{{ $row->min_width_cm ? lv_num($row->min_width_cm, 2) . ' سم' : '—' }}</b>
            @if($row->min_width_cm)
              @include('partials.fx', ['t' => 'أقل عرض', 'f' => 'أضيق توب مقاس بين ' . $okWidths->count() . ' توب سليم = ' . lv_num($row->min_width_cm, 2) . ' سم', 'why' => 'الماركر بيتبني على أضيق توب عشان كل الأتواب تقطع'])
            @endif</div>
          <div class="col"><div class="hint">متوسط العرض</div><b class="num">{{ $row->avg_width_cm ? lv_num($row->avg_width_cm, 2) . ' سم' : '—' }}</b>
            @if($row->avg_width_cm && $okWidths->count())
              @include('partials.fx', ['t' => 'متوسط العرض', 'f' => 'مجموع الأعراض ' . lv_num($okWidths->sum(), 2) . ' سم ÷ ' . $okWidths->count() . ' توب = ' . lv_num($row->avg_width_cm, 2) . ' سم'])
            @endif</div>
          <div class="col"><div class="hint">فرق العرض</div><b class="num {{ $row->width_alert ? 'text-danger' : '' }}">{{ $row->width_spread_cm !== null ? lv_num($row->width_spread_cm, 2) . ' سم' : '—' }}</b>
            @if($row->width_spread_cm !== null)
              @include('partials.fx', ['t' => 'فرق العرض', 'f' => 'أعرض توب ' . lv_num($okMaxW, 2) . ' − أضيق توب ' . lv_num($row->min_width_cm, 2) . ' = ' . lv_num($row->width_spread_cm, 2) . ' سم', 'why' => 'فوق ' . lv_num(config('lvplanning.width_spread_alert_cm', 5), 0) . ' سم بيتعلّم بالأحمر'])
            @endif</div>
          <div class="col"><div class="hint">نسبة العيوب</div><b class="num">{{ lv_num($row->defect_pct, 2) }}%</b>
            @include('partials.fx', ['t' => 'نسبة العيوب', 'f' => 'عدد العيوب ' . (int) $row->total_defects . ' ÷ إجمالي الطول المقاس ' . lv_num($row->total_length_m, 2) . ' م × 100 = ' . lv_num($row->defect_pct, 2) . '%', 'why' => 'يعني عيب لكل 100 متر'])</div>
          <div class="col"><div class="hint">أتواب مرفوضة</div>
            <b class="num {{ $row->rejected_rolls > 0 ? 'text-danger' : '' }}">{{ (int) $row->rejected_rolls }}@if($row->rejected_kg > 0) · {{ $meter ? lv_num($row->rejected_kg, 2) . ' م' : lv_kg($row->rejected_kg, false) . ($row->rejected_packaging_kg > 0 ? ' صافي' : '') }}@endif</b></div>
          @if($est)
            <div class="col"><div class="hint">الوزن المتوقع من العيّنة</div>
              <b class="num {{ $est['level'] === 'danger' ? 'text-danger' : ($est['level'] === 'warn' ? 'text-warning' : '') }}">{{ lv_kg($est['expected'], false) }} ({{ $est['diff_pct'] >= 0 ? '+' : '' }}{{ lv_num($est['diff_pct'], 1) }}%)</b>
              @include('partials.fx', ['t' => 'الوزن المتوقع من العيّنة', 'f' => ['متوسط وزن التوب ' . lv_num($est['avg'], 3) . ' كجم × ' . (int) ($row->counted_rolls ?: $row->total_rolls) . ' توب = ' . lv_num($est['expected'], 3) . ' كجم', '(المتوقع ' . lv_num($est['expected'], 3) . ' − وزن الإذن ' . lv_num($est['declared'], 3) . ') ÷ ' . lv_num($est['declared'], 3) . ' × 100 = ' . lv_num($est['diff_pct'], 1) . '%'], 'why' => 'من عيّنة ' . $est['sample'] . ' توب موزونة — تنبيه بس'])</div>
          @endif
        </div>
        @if($row->rolls_variance != 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">فرق في الجرد: إذن الإضافة قال {{ $row->declared_rolls }} توب، والمجرود {{ $row->counted_rolls }}. راجع مع المورد قبل الإفراج.</div>
        @endif
        @if($row->width_alert)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">فرق العرض بين الأتواب {{ lv_num($row->width_spread_cm, 2) }} سم — مش طبيعي لقماش حوض واحد.</div>
        @endif
        @if($row->sample_too_small)
          <div class="alert alert-warning py-2 mt-2 mb-0 small">العيّنة {{ lv_num($row->sample_pct, 2) }}% بس من الأتواب — أي أرقام مبنية عليها تقديرية.</div>
        @endif
        @if($est && $est['level'] !== 'ok')
          <div class="alert alert-{{ $est['level'] === 'danger' ? 'danger' : 'warning' }} py-2 mt-2 mb-0 small">
            الوزن المتوقع من العيّنة {{ lv_kg($est['expected'], false) }} مقابل {{ lv_kg($est['declared'], false) }} على الإذن — فرق {{ $est['diff_pct'] >= 0 ? '+' : '' }}{{ lv_num($est['diff_pct'], 1) }}%
            {{ $est['level'] === 'danger' ? '· راجع الوزن مع المخزن قبل الإفراج' : '· أكبر من الطبيعي' }}</div>
        @endif
        @if($row->rejected_rolls > 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">{{ (int) $row->rejected_rolls }} توب اترفضوا
            @if($meter) بـ {{ lv_num($row->rejected_kg, 2) }} م — اتخصموا من الرسالة ومن طلب الشراء ونزلوا مخزن المرتجعات كمطالبة على المورد.
            @elseif($row->rejected_packaging_kg > 0) بوزن قائم {{ lv_kg($row->rejected_gross_kg, false) }} − كيس وماسورة {{ lv_kg($row->rejected_packaging_kg, false) }} = صافي {{ lv_kg($row->rejected_kg, false) }} — الصافي اتخصم من الرسالة، والقائم اتخصم من طلب الشراء ونزل مخزن المرتجعات كمطالبة على المورد.
            @else بوزن {{ lv_kg($row->rejected_kg, false) }} — اتخصموا من الرسالة ومن طلب الشراء ونزلوا مخزن المرتجعات كمطالبة على المورد.
            @endif</div>
        @endif
      </div>
    @endif
  </div>

  {{-- ④ قياس الأتواب + بنشر كل توب --}}
  <div class="card mb-3">
    <div class="card-header">
      <span>② قياس الأتواب
        @if($meter)
          <span class="hint">— الرسالة <b>بالمتر</b>: اكتب الأمتار المكتوبة على كل توب والعرض والحالة. مفيش وزن ولا بنشر. التوب البايظ علّمه <b>مرفوض</b> — أمتاره بتتخصم من الرسالة والطلب. العيّنة على الأقل {{ lv_num(lv_setting('min_inspection_sample_pct', 10), 0) }}% من الأتواب.</span>
        @else
          <span class="hint">— طول وعرض كل توب (أقل عرض هو اللي الماركر هيتبني عليه) + وزن البنشر لكل توب للمعمل — <b>بيتحسب لوحده</b> من وزن التوب ÷ (الطول × العرض)، ولو المعمل قاسه بالبانشر اكتب رقمه مكانه. التوب البايظ علّمه <b>مرفوض</b> واكتب وزنه <b>قائم</b> زي ما طلع على الميزان — السيستم بيخصم الكيس والماسورة قبل ما يخصمه من الرصيد. العيّنة على الأقل {{ lv_num(lv_setting('min_inspection_sample_pct', 10), 0) }}% من الأتواب.</span>
        @endif
      </span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum ms-auto" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> توب</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:90px">رقم التوب</th><th style="width:130px">{{ $meter ? 'الأمتار المكتوبة' : 'الطول (م)' }}</th>
          <th style="width:120px">العرض (سم)</th><th style="width:95px">الحالة</th>
          @unless($meter)
          <th style="width:125px">وزن التوب (كجم)</th>
          <th style="width:125px">البنشر (جم/م²)
            @include('partials.fx', ['t' => 'البنشر للتوب', 'f' => 'مثال: وزن التوب 19 كجم × 1000 ÷ (الطول 50 م × العرض 1.75 م) = 217.1 جم/م²', 'why' => 'بيتحسب لوحده لكل توب من الطول والعرض والوزن — لو المعمل قاسه بالبانشر اكتب رقمه مكانه'])</th>
          @endunless
          <th style="width:130px">العيب</th><th style="width:240px">تفاصيل العيب</th>
          <th>ملاحظات</th><th style="width:44px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($rolls as $i=>$l) @include('inspections.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($rolls)) @include('inspections.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
    {{-- الوزن المتوقع من العيّنة مقابل وزن الإذن — تنبيه بس --}}
    <div class="card-footer bg-white" id="estWrap" hidden
         data-declared="{{ (float) ($row->counted_kg ?: $c?->total_kg ?: 0) }}"
         data-warn="{{ config('lvplanning.weight_estimate.warn_pct', 5) }}" data-danger="{{ config('lvplanning.weight_estimate.danger_pct', 10) }}"
         data-minsample="{{ lv_setting('min_inspection_sample_pct', 10) }}" data-meter="{{ $meter ? 1 : 0 }}"
         data-rolls="{{ (int) ($row->declared_rolls ?? $c?->rolls_count) }}" data-addkg="{{ (float) ($c?->total_kg ?: 0) }}">
      <div id="estText" class="small"></div>
    </div>
  </div>

  {{-- ⑤ المعمل — مدمج في نفس الورقة --}}
  <div class="card mb-3">
    <div class="card-header"><span>③ المعمل <span class="hint">— الانكماش ومطابقة اللون. البنشر اتكتب فوق لكل توب. لو ما كتبتش حاجة هنا، المعمل يتسجل بعدين من شاشته.</span></span></div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if($ft)
        <div class="ro-box mb-3">
          <div class="ro-title">مواصفة الخامة — {{ $ft->name }} — للقراءة</div>
          <div class="sum-grid">
            <div><div class="k">البنشر (جم/م²)</div><div class="v num">{{ lv_num($ft->spec_gsm_min, 2) }} – {{ lv_num($ft->spec_gsm_max, 2) }}</div></div>
            <div><div class="k">أقل عرض (سم)</div><div class="v num">{{ lv_num($ft->spec_width_min_cm ?? $ft->spec_width_cm, 2) }}</div></div>
            <div><div class="k">أقصى انكماش طول</div><div class="v num">{{ lv_num($ft->max_shrink_len_pct, 2) }}%</div></div>
            <div><div class="k">أقصى انكماش عرض</div><div class="v num">{{ lv_num($ft->max_shrink_width_pct, 2) }}%</div></div>
          </div>
          <div class="hint mt-1">اللي يطلع برّه المواصفة بيتعلّم بعد الحفظ</div>
        </div>
      @endif

      <div class="row g-3">
        <div class="col-12"><b class="small">نسبة الانكماش</b> <span class="hint">— عينتين، وزوّد لو المعمل أخد عينة تأكيد</span></div>
        @php $shown = 2; for ($s = 3; $s <= 4; $s++) if (old("lab.s{$s}_shrink_len_pct", $lab?->{"s{$s}_shrink_len_pct"}) !== null || old("lab.s{$s}_shrink_width_pct", $lab?->{"s{$s}_shrink_width_pct"}) !== null) $shown = $s; @endphp
        @for($s = 1; $s <= 4; $s++)
          <div class="col-md-6 col-xl-3 shrink-sample" data-s="{{ $s }}" @if($s > $shown) hidden @endif>
            <label class="form-label">عينة {{ $s }}</label>
            <div class="input-group">
              <span class="input-group-text">طول</span>
              <input type="number" step="0.01" min="0" name="lab[s{{ $s }}_shrink_len_pct]" class="form-control" value="{{ old("lab.s{$s}_shrink_len_pct", $lab?->{"s{$s}_shrink_len_pct"}) }}" placeholder="3" aria-label="عينة {{ $s }} — انكماش الطول %">
              <span class="input-group-text">%</span>
              <span class="input-group-text">عرض</span>
              <input type="number" step="0.01" min="0" name="lab[s{{ $s }}_shrink_width_pct]" class="form-control" value="{{ old("lab.s{$s}_shrink_width_pct", $lab?->{"s{$s}_shrink_width_pct"}) }}" placeholder="2" aria-label="عينة {{ $s }} — انكماش العرض %">
              <span class="input-group-text">%</span>
            </div>
          </div>
        @endfor
        <div class="col-12">
          <button type="button" class="btn btn-sm btn-outline-plum" id="addSample" @if($shown >= 4) hidden @endif><i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف عينة</button>
        </div>

        <div class="col-md-4">
          <div class="form-check">
            <input type="hidden" name="lab[color_match_ok]" value="0">
            <input class="form-check-input" type="checkbox" name="lab[color_match_ok]" value="1" id="cm" @checked(old('lab.color_match_ok', $lab?->color_match_ok))>
            <label class="form-check-label" for="cm">اللون مطابق للعينة المعتمدة</label>
          </div>
          <div class="form-check mt-1">
            <input type="hidden" name="lab[has_color_break]" value="0">
            <input class="form-check-input" type="checkbox" name="lab[has_color_break]" value="1" id="cb" @checked(old('lab.has_color_break', $lab?->has_color_break))
                   onchange="document.getElementById('cbWrap').hidden = !this.checked">
            <label class="form-check-label fw-bold" for="cb">فيه فواصل / اختلاف لون داخل الرسالة</label>
            <div class="hint">مهم جدًا — بيوصل للتخطيط والباترونست والقص والمصنع</div>
          </div>
        </div>
        <div class="col-md-8" id="cbWrap" @if(!old('lab.has_color_break', $lab?->has_color_break)) hidden @endif>
          <div class="row g-2">
            <div class="col-md-4"><label class="form-label" for="lab_cbr">أتواب الفاصل</label>
              <input name="lab[color_break_roll_no]" id="lab_cbr" class="form-control" value="{{ old('lab.color_break_roll_no', $lab?->color_break_roll_no) }}" placeholder="مثال: 007، 012"></div>
            <div class="col-md-8"><label class="form-label" for="lab_cbn">وصف الاختلاف</label>
              <input name="lab[color_break_note]" id="lab_cbn" class="form-control" value="{{ old('lab.color_break_note', $lab?->color_break_note) }}" placeholder="مثال: فاصل بعد 20 متر — الدرجة أغمق"></div>
          </div>
        </div>

        <div class="col-md-3"><label class="form-label" for="lab_ps">المسلسل الورقي للمعمل</label>
          <input name="lab[paper_serial]" id="lab_ps" class="form-control w-short" value="{{ old('lab.paper_serial', $lab?->paper_serial) }}" placeholder="مثال: 002192" inputmode="numeric"></div>
        <div class="col-md-4"><label class="form-label" for="color_swatch">صورة عينة اللون</label>
          <input type="file" name="color_swatch" id="color_swatch" accept="image/*" class="form-control">
          @if($lab?->color_swatch_path)<a href="{{ asset('storage/'.$lab->color_swatch_path) }}" target="_blank" class="hint">العينة المرفوعة</a>@endif</div>
        <div class="col-md-5"><label class="form-label" for="lab_notes">ملاحظات المعمل <span class="hint">(اختياري)</span></label>
          <input name="lab[notes]" id="lab_notes" class="form-control" value="{{ old('lab.notes', $lab?->notes) }}" placeholder="مثال: البنشر على الحد الأدنى للمواصفة"></div>
      </div>
    </fieldset></div>
    @if($lab && $lab->status === 'approved')
      @php
        $labG   = $lab->relationLoaded('readings') ? $lab->readings->pluck('gsm')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0) : null;
        $shL    = collect([$lab->s1_shrink_len_pct, $lab->s2_shrink_len_pct, $lab->s3_shrink_len_pct, $lab->s4_shrink_len_pct])->filter(fn ($v) => $v !== null && $v !== '');
        $shW    = collect([$lab->s1_shrink_width_pct, $lab->s2_shrink_width_pct, $lab->s3_shrink_width_pct, $lab->s4_shrink_width_pct])->filter(fn ($v) => $v !== null && $v !== '');
      @endphp
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">تقرير المعمل</div><a href="{{ route('lab-reports.edit', $lab) }}" class="num fw-bold">{{ $lab->doc_no }}</a></div>
          <div class="col"><div class="hint">متوسط البنشر</div><b class="num text-danger">{{ lv_num($lab->avg_gsm, 2) }}</b>
            @if($lab->avg_gsm !== null)
              @include('partials.fx', ['t' => 'متوسط البنشر', 'f' => $labG && $labG->count()
                  ? 'مجموع القراءات ' . lv_num($labG->sum(), 2) . ' ÷ ' . $labG->count() . ' قراءة = ' . lv_num($lab->avg_gsm, 2) . ' جم/م²'
                  : 'متوسط قراءات الأتواب (أقل ' . lv_num($lab->min_gsm, 2) . ' – أعلى ' . lv_num($lab->max_gsm, 2) . ') = ' . lv_num($lab->avg_gsm, 2) . ' جم/م²',
                'why' => 'المواصفة ' . lv_num($ft?->spec_gsm_min, 2) . ' – ' . lv_num($ft?->spec_gsm_max, 2)])
            @endif</div>
          <div class="col"><div class="hint">انكماش الطول</div><b class="num">{{ lv_num($lab->avg_shrink_len_pct, 2) }}%</b>
            @if($shL->count())
              @include('partials.fx', ['t' => 'متوسط انكماش الطول', 'f' => '(' . $shL->map(fn ($v) => lv_num($v, 2))->implode(' + ') . ') ÷ ' . $shL->count() . ' = ' . lv_num($lab->avg_shrink_len_pct, 2) . '%'])
            @endif</div>
          <div class="col"><div class="hint">انكماش العرض</div><b class="num">{{ lv_num($lab->avg_shrink_width_pct, 2) }}%</b>
            @if($shW->count())
              @include('partials.fx', ['t' => 'متوسط انكماش العرض', 'f' => '(' . $shW->map(fn ($v) => lv_num($v, 2))->implode(' + ') . ') ÷ ' . $shW->count() . ' = ' . lv_num($lab->avg_shrink_width_pct, 2) . '%'])
            @endif</div>
        </div>
        @if($lab->gsm_out_of_spec)<div class="alert alert-warning py-2 mt-2 mb-0 small">متوسط البنشر خارج مواصفة الخامة — قابل للتفاوض مع المورد (خصم) أو يترفض.</div>@endif
        @if($lab->has_color_break)<div class="alert alert-danger py-2 mt-2 mb-0 small">فواصل / اختلاف لون: {{ $lab->color_break_roll_no ? 'أتواب ' . $lab->color_break_roll_no . ' · ' : '' }}{{ $lab->color_break_note }}</div>@endif
      </div>
    @endif
  </div>

  {{-- ④ النتيجة — آخر حاجة بعد ما تشوف كل حاجة --}}
  <div class="card mb-3" id="resultCard">
    <div class="card-header">
      <span>④ نتيجة الفحص <span class="hint">— بعد ما قست الأتواب وسجلت المعمل، قرر</span>
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }}">{{ $row->status_label }}</span>@endif
      </span>
      <div class="d-flex gap-2 ms-auto">
        @if($mode==='edit')
          <a href="{{ route('inspections.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon"
             aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <input type="hidden" name="result" id="resultInput" value="{{ $result }}">
      <div class="d-flex gap-2 flex-wrap result-group" role="group" aria-label="نتيجة الفحص" data-error-host="result">
        @foreach([
          ['accepted',            'مقبول',          'bi-check-circle',       'ok',     'القماش سليم — يكمّل'],
          ['accepted_with_notes', 'مقبول بملاحظات', 'bi-exclamation-circle', 'warn',   'يكمّل بس لازم تكتب الملاحظة'],
          ['rejected',            'مرفوض',          'bi-x-circle',           'danger', 'ينزل شاشة المرفوضات ويرجع للمورد'],
        ] as [$key, $label, $icon, $tone, $note])
          <button type="button" class="btn result-btn" data-result="{{ $key }}"
                  aria-pressed="{{ $result === $key ? 'true' : 'false' }}"
                  style="border:2px solid {{ $result === $key ? 'var(--lv-'.$tone.')' : 'var(--lv-line)' }};
                         background:{{ $result === $key ? 'var(--lv-'.$tone.'-bg)' : '#fff' }}">
            <span class="d-block fw-bold"><i class="bi {{ $icon }}" aria-hidden="true" style="color:var(--lv-{{ $tone }})"></i> {{ $label }}</span>
            <span class="hint d-block">{{ $note }}</span>
          </button>
        @endforeach
      </div>
      <div class="mt-3" id="notesWrap" hidden>
        <label class="form-label" id="notesLabel" for="notesInput">ملاحظات</label>
        <input name="notes" id="notesInput" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="اكتب اللي شفته على القماش">
        <div class="hint" id="notesHint">اختياري — إلا لو النتيجة «مقبول بملاحظات» أو «مرفوض».</div>
      </div>
    </fieldset></div>
  </div>

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ الفحص والمعمل</button>
    <span class="hint"><i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص الفحص على طول — ولو كتبت بنشر للأتواب بيتسجل المعمل كمان والرسالة تنزل استلام الخام. المرفوض بينزل شاشة المرفوضات.</span>
    <div class="fa-end"><a href="{{ route('inspections.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
  </div>
  @endif
</form>

<template id="lineTpl">@include('inspections.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>count($rolls) ? max(array_keys($rolls)) + 1 : 1])

<style>
  /* خانات جدول الأتواب عريضة ومقروءة */
  #lines td input[type=number] { min-width: 110px; width: 100%; font-size: var(--fs-sm); }
  #lines td select { min-width: 90px; width: 100%; font-size: var(--fs-sm); }
  #lines td input[name$="[roll_no]"] { width: 80px; min-width: 80px; }
  #lines td input[name$="[defects_count]"] { min-width: 64px; width: 64px; }
  #lines td input[name$="[defect_desc]"], #lines td input[name$="[notes]"] { width: 100%; min-width: 160px; }
  .table-responsive:has(#lines) { overflow-x: auto; }
  /* زراير نتيجة الفحص: كارت صغير بسطرين */
  .result-btn{min-width:190px;min-height:0;display:block;text-align:start;white-space:normal;padding:.5rem .8rem;border-radius:10px}
  .result-btn .hint{white-space:normal}
  /* عينات الانكماش: 6 خانات في input-group واحد — تتكسر سطرين على الموبايل */
  .shrink-sample .input-group{flex-wrap:nowrap}
  .shrink-sample .input-group .form-control{min-width:64px}
  @media (max-width:600px){
    .result-btn{min-width:100%}
    .shrink-sample .input-group{flex-wrap:wrap}
    .shrink-sample .input-group>*{flex:1 1 30%}
  }
</style>
@push('scripts')
<script>
window.LVI = {
  /* التوب المرفوض لازم يتوزن — الأتواب مش متساوية الوزن */
  rollStatus: function (sel) {
    var tr = sel.closest('tr'), kg = tr.querySelector('.roll-kg');
    var rej = sel.value === 'rejected';
    tr.classList.toggle('table-danger', rej);
    if (kg) { kg.required = rej; kg.placeholder = rej ? 'قائم — إجباري' : 'مثال: 19.5'; if (rej) kg.focus(); }
  },
  /* البنشر تلقائي = وزن التوب ÷ (الطول × العرض) = جم/م² — لو المعمل قاسه بالبانشر يكتبه مكانه */
  autoGsm: function (el) {
    var tr = el.closest('tr'), g = tr.querySelector('.roll-gsm');
    if (!g || (g.value && g.dataset.auto !== '1')) return;
    var len = parseFloat(tr.querySelector('[name$="[length_m]"]')?.value) || 0;
    var w   = parseFloat(tr.querySelector('[name$="[width_cm]"]')?.value) || 0;
    var kg  = parseFloat(tr.querySelector('.roll-kg')?.value) || 0;
    if (len > 0 && w > 0 && kg > 0) {
      g.value = (kg * 1000 / (len * w / 100)).toFixed(1); g.dataset.auto = '1';
      g.title = 'اتحسب لوحده: ' + kg + ' كجم × 1000 ÷ (' + len + ' م × ' + (w / 100) + ' م) = ' + g.value + ' جم/م²';
    }
    else if (g.dataset.auto === '1') { g.value = ''; g.title = ''; }
  },
  /* تنبيه الوحدات: العرض بالسنتيمتر (175 مش 1.75) والطول بالمتر */
  units: function (el) {
    var n = el.name || '', v = parseFloat(el.value);
    var bad = false, msg = '';
    if (/\[width_cm\]$/.test(n) && v > 0 && v < 50)  { bad = true; msg = 'العرض بالسنتيمتر — يعني 175 مش 1.75'; }
    if (/\[length_m\]$/.test(n) && v > 0 && v < 1)   { bad = true; msg = 'الطول بالمتر — توب 50 متر يتكتب 50'; }
    el.classList.toggle('is-invalid', bad);
    el.title = msg;
    var tr = el.closest('tr'), w = tr.querySelector('.unit-warn');
    if (!w) { w = document.createElement('div'); w.className = 'unit-warn invalid-feedback d-block'; w.style.cssText = 'white-space:nowrap'; el.parentElement.appendChild(w); }
    w.textContent = bad ? msg : '';
    if (!bad && el.parentElement.contains(w) && !tr.querySelector('.is-invalid')) w.textContent = '';
  },
  /* تفاصيل العيب تظهر بس لما تختار عيب */
  defect: function (sel) {
    var tr = sel.closest('tr'), w = tr.querySelector('.roll-dwrap');
    if (!w) return;
    w.hidden = !sel.value;
    if (sel.value) { var cnt = w.querySelector('input[type=number]'); if (cnt && !cnt.value) cnt.value = 1; cnt && cnt.focus(); }
    else { w.querySelectorAll('input').forEach(i => i.value = ''); }
  }
};
(function () {
  var input = document.getElementById('resultInput');
  if (!input) return;
  var notesInput = document.getElementById('notesInput'), notesHint = document.getElementById('notesHint'), notesLabel = document.getElementById('notesLabel');
  var TONE = { accepted: 'ok', accepted_with_notes: 'warn', rejected: 'danger' };

  function paint() {
    document.querySelectorAll('.result-btn').forEach(function (b) {
      var on = b.dataset.result === input.value, tone = TONE[b.dataset.result] || 'line';
      b.style.border = '2px solid ' + (on ? 'var(--lv-' + tone + ')' : 'var(--lv-line)');
      b.style.background = on ? 'var(--lv-' + tone + '-bg)' : '#fff';
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    var must = input.value === 'accepted_with_notes' || input.value === 'rejected';
    var wrap = document.getElementById('notesWrap'); if (wrap) wrap.hidden = !input.value;
    if (notesInput) { notesInput.required = must; if (must) notesInput.focus(); }
    if (notesLabel) notesLabel.className = must ? 'form-label req' : 'form-label';
    if (notesHint) notesHint.textContent = input.value === 'rejected'
      ? 'إجباري — اكتب سبب الرفض، هيظهر في شاشة المرفوضات وللمورد.'
      : (must ? 'إجباري — اكتب الملاحظة اللي القماش عدّى بيها.' : 'اختياري — إلا لو النتيجة «مقبول بملاحظات» أو «مرفوض».');
  }
  document.querySelectorAll('.result-btn').forEach(function (b) { b.addEventListener('click', function () { input.value = b.dataset.result; paint(); }); });

  // البنشر التلقائي من الطول والعرض والوزن + الوزن المتوقع من العيّنة
  document.getElementById('lines').addEventListener('input', function (e) {
    var n = e.target.name || '';
    if (/\[(length_m|width_cm)\]$/.test(n)) LVI.units(e.target);
    if (/\[(length_m|width_cm|net_kg)\]$/.test(n)) LVI.autoGsm(e.target);
    if (e.target.classList.contains('roll-gsm')) e.target.dataset.auto = '0';
    estimate();
  });
  document.getElementById('lines').addEventListener('change', estimate);
  var ckg = document.querySelector('[name=counted_kg]'); if (ckg) ckg.addEventListener('input', estimate);
  var cnt = document.getElementById('countedRolls'); if (cnt) cnt.addEventListener('input', estimate);

  /* الوزن المتوقع = متوسط وزن التوب في العيّنة × الأتواب المجرودة — مقارنة بوزن الإذن (تنبيه بس) */
  function estimate() {
    var wrap = document.getElementById('estWrap'), out = document.getElementById('estText');
    if (!wrap) return;
    var ws = [];
    document.querySelectorAll('#lines tr').forEach(function (tr) {
      if (tr.querySelector('.roll-status')?.value === 'rejected') return;
      var kg = parseFloat(tr.querySelector('.roll-kg')?.value) || 0;
      if (kg > 0) ws.push(kg);
    });
    var n = parseInt(cnt?.value || '0', 10) || 0;
    var declared = parseFloat(ckg?.value) || parseFloat(wrap.dataset.declared) || 0;
    if (!ws.length || !n || !declared) { wrap.hidden = true; return; }
    var avg = ws.reduce((a, b) => a + b, 0) / ws.length, exp = avg * n, diff = (exp - declared) / declared * 100;
    var warn = parseFloat(wrap.dataset.warn), danger = parseFloat(wrap.dataset.danger);
    var tone = Math.abs(diff) >= danger ? 'danger' : (Math.abs(diff) >= warn ? 'warning' : 'success');
    var nf = function (v, d) { return Number(v).toLocaleString('en-US', {maximumFractionDigits: d}); };
    var rank = {success: 0, warning: 1, danger: 2};
    var worse = function (t) { if (rank[t] > rank[tone]) tone = t; };
    var toneOf = function (pct) { return Math.abs(pct) >= danger ? 'danger' : (Math.abs(pct) >= warn ? 'warning' : 'success'); };

    /* الوزن الإجمالي ممكن يطلع مظبوط بالصدفة (5 أتواب × 99 كجم ≈ 100 توب × 5 كجم) —
       فبنقارن كمان عدد الأتواب، ووزن التوب الواحد مع اللي على الإذن. */
    var extra = [];
    var addRolls = parseInt(wrap.dataset.rolls || '0', 10) || 0;
    if (addRolls > 0 && n !== addRolls) {
      var gapPct = (n - addRolls) / addRolls * 100;
      worse(toneOf(gapPct) === 'success' ? 'warning' : toneOf(gapPct));
      extra.push('<b>عدد الأتواب:</b> المجرود ' + n + ' من ' + addRolls + ' توب على الإذن — '
        + (n < addRolls ? '<b>ناقص ' + (addRolls - n) + '</b>' : '<b>زيادة ' + (n - addRolls) + '</b>'));
    }
    var addKg = parseFloat(wrap.dataset.addkg) || 0;
    if (wrap.dataset.meter !== '1' && addRolls > 0 && addKg > 0) {
      var perRoll = addKg / addRolls, rollPct = (avg - perRoll) / perRoll * 100, rt = toneOf(rollPct);
      worse(rt);
      if (rt !== 'success') {
        extra.push('<b>وزن التوب:</b> متوسط العيّنة ' + nf(avg, 2) + ' كجم مقابل ' + nf(perRoll, 2)
          + ' كجم للتوب على الإذن (' + nf(addKg, 1) + ' ÷ ' + addRolls + ') — فرق <b>' + (rollPct >= 0 ? '+' : '') + nf(rollPct, 1) + '%</b>');
      }
    }
    var small = (ws.length / n * 100) < parseFloat(wrap.dataset.minsample);
    var fx = (window.LVX && LVX.fx) ? LVX.fx('الوزن المتوقع من العيّنة', [
      'مجموع أوزان العيّنة ' + nf(ws.reduce((a, b) => a + b, 0), 3) + ' كجم ÷ ' + ws.length + ' توب = متوسط التوب ' + nf(avg, 3) + ' كجم',
      'متوسط التوب ' + nf(avg, 3) + ' كجم × ' + n + ' توب مجرود = ' + nf(exp, 3) + ' كجم',
      '(المتوقع ' + nf(exp, 3) + ' − وزن الإذن ' + nf(declared, 3) + ') ÷ ' + nf(declared, 3) + ' × 100 = ' + (diff >= 0 ? '+' : '') + nf(diff, 1) + '%'
    ], 'تنبيه بس — الفحص عيّنة، والفرق الطبيعي لحد ' + wrap.dataset.warn + '%') : '';
    out.className = 'small alert alert-' + tone + ' py-2 mb-0';
    out.innerHTML = '<b>الوزن المتوقع من العيّنة:</b> ' + nf(exp, 1) + ' كجم ' + fx + ' '
      + '<span class="hint">(متوسط التوب ' + nf(avg, 2) + ' كجم × ' + n + ' توب — عيّنة ' + ws.length + ' توب)</span>'
      + ' مقابل <b>' + nf(declared, 1) + ' كجم</b> على الإذن — فرق <b>' + (diff >= 0 ? '+' : '') + nf(diff, 1) + '%</b>'
      + (tone === 'danger' ? ' · <b>راجع ' + (extra.length ? 'العدد والوزن' : 'الوزن') + ' مع المخزن قبل الإفراج</b>' : tone === 'warning' ? ' · فرق أكبر من الطبيعي' : ' · في الحدود الطبيعية')
      + (small ? ' <span class="hint">· تقديري — العيّنة أقل من ' + wrap.dataset.minsample + '%</span>' : '')
      + extra.map(function (x) { return '<div class="mt-1">' + x + '</div>'; }).join('');
    wrap.hidden = false;
    if (window.LVX && LVX.popovers) LVX.popovers(out);
  }
  estimate();

  // «تم» جنب الأتواب المجرودة ⇒ سطر لكل توب
  var btn = document.getElementById('buildRows');
  if (btn && cnt) {
    btn.addEventListener('click', function () {
      var want = parseInt(cnt.value || '0', 10);
      if (!want || want < 1) { cnt.focus(); return; }
      if (want > 500) { alert('العدد كبير أوي — أقصى حاجة 500 توب في التقرير الواحد.'); return; }
      var body = document.getElementById('lines'), have = body.querySelectorAll('tr').length;
      if (want < have) {
        var filled = 0;
        body.querySelectorAll('tr').forEach(function (tr) {
          var len = tr.querySelector('[name*="[length_m]"]'), wid = tr.querySelector('[name*="[width_cm]"]');
          if ((len && len.value) || (wid && wid.value)) filled++;
        });
        if (filled > want && !confirm('فيه ' + filled + ' توب متكتوب فيهم قياسات. تقليل العدد لـ ' + want + ' هيمسح الزيادة. تكمّل؟')) return;
        while (body.querySelectorAll('tr').length > want) body.lastElementChild.remove();
      } else {
        for (var i = have; i < want; i++) LV.add('lineTpl', 'lines');
      }
      Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr, i) {
        var no = tr.querySelector('[name*="[roll_no]"]');
        if (no && !no.value) no.value = String(i + 1).padStart(3, '0');
      });
      LV.renumber && LV.renumber('lines');
    });
  }

  // عينات الانكماش: 2 ظاهرين، «ضيف عينة» لحد 4
  var addS = document.getElementById('addSample');
  if (addS) addS.addEventListener('click', function () {
    var next = [...document.querySelectorAll('.shrink-sample[hidden]')][0];
    if (next) { next.hidden = false; next.querySelector('input').focus(); }
    if (!document.querySelector('.shrink-sample[hidden]')) addS.hidden = true;
  });

  var form = document.getElementById('inspForm');
  if (form) form.addEventListener('submit', function (e) {
    if (!input.value) {
      e.preventDefault();
      var card = document.getElementById('resultCard');
      if (card) { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); card.style.boxShadow = '0 0 0 3px var(--lv-danger)'; setTimeout(function () { card.style.boxShadow = ''; }, 2000); }
      alert('اختار نتيجة الفحص الأول: مقبول، مقبول بملاحظات، ولا مرفوض.');
    }
  });
  paint();
})();
</script>
@endpush
@endsection
