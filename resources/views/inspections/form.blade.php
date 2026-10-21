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

{{-- ① سامري الرسالة — كله Labels وكله لينكات --}}
@if($c)
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2">
      <div class="row g-2 align-items-start">
        <div class="col-md-3 col-6">
          <div class="hint">الرسالة</div>
          <a href="{{ route('consignments.show', $c) }}" class="fw-bold num" style="font-size:1.1rem;color:var(--lv-brand-ink)">{{ $c->consignment_no }}</a>
          <div class="hint">{{ $c->fabricType?->name ?? '—' }} · {{ $c->color?->label ?? $c->color?->code ?? '—' }}</div>
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">وصل في الرسالة دي</div>
          <b class="num">{{ lv_kg($c->total_kg) }}</b>
          <div class="hint num">{{ (int) $c->rolls_count }} توب · {{ $c->arrival_date?->format('Y-m-d') }}</div>
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">المورد</div>
          <b>{{ $c->supplier?->name ?? '—' }}</b>
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">إذن المورد</div>
          @if($sa)
            <a href="{{ route('stock-additions.edit', $sa) }}" class="fw-bold num" title="يفتح إذن الإضافة">{{ $sa->supplier_doc_no ?: '—' }}</a>
          @else <span class="hint">—</span> @endif
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">إذن الإضافة</div>
          @if($sa)
            <a href="{{ route('stock-additions.edit', $sa) }}" class="fw-bold num">{{ $sa->doc_no }}</a>
            <div class="hint num">{{ $sa->paper_serial ? 'مسلسل ورقي ' . $sa->paper_serial . ' · ' : '' }}{{ $sa->doc_date?->format('Y-m-d') }}</div>
          @else <span class="hint">—</span> @endif
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">طلب الشراء</div>
          @if($c->purchaseOrder)
            <a href="{{ route('purchase-orders.edit', $c->purchaseOrder) }}" class="fw-bold num">{{ $c->purchaseOrder->po_no }}</a>
            @if($st)<div class="hint num">مطلوب {{ lv_qty($st['ordered'], $st['unit']) }} · وصل {{ lv_num($st['received']) }}</div>@endif
          @else <span class="hint">بدون طلب</span> @endif
        </div>
        <div class="col-md-3 col-6">
          @if($st && $st['left'] > 0.0001)
            <div class="hint">الباقي على الصنف ده</div>
            <b class="text-danger num">{{ lv_qty($st['left'], $st['unit']) }}</b>
            <div class="hint">{{ $st['eta'] ? 'متوقع ' . $st['eta'] : 'الموعد مش محدد' }}</div>
          @elseif($st)
            <div class="hint">الصنف على الطلب</div><b class="text-success">اكتمل</b>
          @endif
        </div>
        <div class="col-md-3 col-6">
          <div class="hint">نوع الاستلام</div>
          <b>{{ $sa?->receipt_type === 'container' ? 'مباشر — بدون فحص' : 'عادي — يدخل الفحص' }}</b>
        </div>
      </div>
    </div>
  </div>
@endif

<form method="post" id="inspForm" enctype="multipart/form-data"
      action="{{ $mode==='create' ? route('inspections.store') : route('inspections.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ③ الجرد: Labels في ناحية، واللي هتكتبه في ناحية --}}
  <div class="card mb-3">
    <div class="card-header">① الجرد <span class="hint">— كام توب موجود فعلًا مقابل اللي إذن الإضافة قال عليه</span></div>
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
          <div class="col-md-4"><label class="form-label req">الحوض (الرسالة)</label>
            <select name="consignment_id" class="form-select form-select-sm" required><option value="">—</option>
              @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-4"><label class="form-label req">أتواب حسب إذن الإضافة</label>
            <input type="number" min="0" name="declared_rolls" class="form-control form-control-sm" value="{{ old('declared_rolls',$row->declared_rolls) }}" required></div>
        </div>
      @endif

      <div class="row g-3">
        {{-- Labels --}}
        <div class="col-lg-5">
          <div class="p-2 rounded h-100" style="background:var(--lv-offwhite);border:1px solid var(--lv-line)">
            <div class="hint mb-1">من إذن الإضافة — للقراءة</div>
            <table class="table table-sm mb-0" style="background:transparent">
              <tr><th style="width:45%">أتواب حسب الإذن</th><td class="num fw-bold">{{ (int) ($row->declared_rolls ?? $c?->rolls_count) }} توب</td></tr>
              <tr><th>الوزن حسب الإذن</th><td class="num fw-bold">{{ $c ? lv_kg($c->total_kg) : '—' }}</td></tr>
              @php $po = $c?->purchaseOrder; @endphp
              <tr><th>طلبه</th><td>{{ $po?->requester?->name ?? '—' }} <span class="hint num">{{ $po?->requested_at?->format('Y-m-d') }}</span></td></tr>
              <tr><th>سعّره</th><td>{{ $po?->sourcer?->name ?? '—' }} <span class="hint num">{{ $po?->sourced_at?->format('Y-m-d') }}</span></td></tr>
              <tr><th>استلمه</th><td>{{ $sa?->creator?->name ?? '—' }} <span class="hint num">{{ $sa?->doc_date?->format('Y-m-d') }}</span></td></tr>
              <tr><th>فحصه</th><td>{{ $mode === 'edit' && $row->status === 'approved' ? ($row->inspector?->name ?? '—') : '— لسه (' . auth()->user()?->name . ')' }}
                <span class="hint num">{{ $mode === 'edit' && $row->status === 'approved' ? $row->doc_date?->format('Y-m-d') : '' }}</span></td></tr>
            </table>
          </div>
        </div>
        {{-- Inputs --}}
        <div class="col-lg-7">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">المسلسل الورقي للفحص</label>
              <input name="paper_serial" class="form-control form-control-sm" value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="04619"></div>
            <div class="col-md-4"><label class="form-label req">الأتواب المجرودة فعليًا</label>
              <div class="input-group input-group-sm">
                <input type="number" min="1" name="counted_rolls" id="countedRolls" class="form-control" value="{{ old('counted_rolls',$row->counted_rolls) }}" required>
                @if($editable)<button type="button" class="btn btn-plum" id="buildRows"><i class="bi bi-check2" aria-hidden="true"></i> تم</button>@endif
              </div>
              <div class="hint">اكتب العدد ودوس «تم» — سطر لكل توب</div></div>
            <div class="col-md-4"><label class="form-label">الوزن المجرود</label>
              <div class="input-group input-group-sm">
                <input type="number" step="0.001" name="counted_kg" class="form-control" value="{{ old('counted_kg', $row->counted_kg !== null ? lv_num($row->counted_kg) : '') }}" placeholder="{{ $c ? lv_num($c->total_kg) : '' }}">
                <span class="input-group-text">كجم</span>
              </div></div>
          </div>
        </div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">الجرد</div>
            <b class="num {{ $row->rolls_variance != 0 ? 'text-danger' : '' }}">{{ $row->counted_rolls }}/{{ $row->declared_rolls }} — {{ $row->rolls_variance_label }}</b></div>
          <div class="col"><div class="hint">العيّنة</div><b class="num">{{ $row->sampled_rolls }}/{{ $row->total_rolls }} ({{ lv_num($row->sample_pct, 2) }}%)</b></div>
          <div class="col"><div class="hint">أقل عرض</div><b class="num text-danger">{{ $row->min_width_cm ? lv_num($row->min_width_cm, 2) . ' سم' : '—' }}</b></div>
          <div class="col"><div class="hint">متوسط العرض</div><b class="num">{{ $row->avg_width_cm ? lv_num($row->avg_width_cm, 2) . ' سم' : '—' }}</b></div>
          <div class="col"><div class="hint">فرق العرض</div><b class="num {{ $row->width_alert ? 'text-danger' : '' }}">{{ $row->width_spread_cm !== null ? lv_num($row->width_spread_cm, 2) . ' سم' : '—' }}</b></div>
          <div class="col"><div class="hint">نسبة العيوب</div><b class="num">{{ lv_num($row->defect_pct, 2) }}%</b></div>
          <div class="col"><div class="hint">أتواب مرفوضة</div>
            <b class="num {{ $row->rejected_rolls > 0 ? 'text-danger' : '' }}">{{ (int) $row->rejected_rolls }}@if($row->rejected_kg > 0) · {{ lv_kg($row->rejected_kg, false) }}@endif</b></div>
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
        @if($row->rejected_rolls > 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">{{ (int) $row->rejected_rolls }} توب اترفضوا بوزن {{ lv_kg($row->rejected_kg, false) }} — اتخصموا من الرسالة ومن طلب الشراء ونزلوا مخزن المرتجعات كمطالبة على المورد.</div>
        @endif
      </div>
    @endif
  </div>

  {{-- ④ قياس الأتواب + بنشر كل توب --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>② قياس الأتواب
        <span class="hint">— طول وعرض كل توب (أقل عرض هو اللي الماركر هيتبني عليه) + وزن البنشر لكل توب للمعمل — <b>بيتحسب لوحده</b> من وزن التوب ÷ (الطول × العرض)، ولو المعمل قاسه بالبانشر اكتب رقمه مكانه. التوب البايظ علّمه <b>مرفوض</b> واكتب وزنه.</span>
      </span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> توب</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:80px">رقم التوب</th><th style="width:95px">الطول (م)</th>
          <th style="width:95px">العرض (سم)</th><th style="width:90px">الحالة</th><th style="width:95px">وزن التوب (كجم)</th>
          <th style="width:95px">البنشر (جم/م²)</th><th style="width:130px">العيب</th><th style="width:260px">تفاصيل العيب</th>
          <th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($rolls as $i=>$l) @include('inspections.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($rolls)) @include('inspections.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- ⑤ المعمل — مدمج في نفس الورقة --}}
  <div class="card mb-3">
    <div class="card-header">③ المعمل <span class="hint">— الانكماش ومطابقة اللون. البنشر اتكتب فوق لكل توب. لو ما كتبتش حاجة هنا، المعمل يتسجل بعدين من شاشته.</span></div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if($ft)
        <div class="d-flex flex-wrap gap-3 small mb-3 p-2 rounded" style="background:var(--lv-offwhite);border:1px solid var(--lv-line)">
          <b>مواصفة الخامة — {{ $ft->name }}:</b>
          <span>البنشر <b class="num">{{ lv_num($ft->spec_gsm_min, 2) }} – {{ lv_num($ft->spec_gsm_max, 2) }}</b> جم/م²</span>
          <span>أقل عرض <b class="num">{{ lv_num($ft->spec_width_min_cm ?? $ft->spec_width_cm, 2) }}</b> سم</span>
          <span>أقصى انكماش: طول <b class="num">{{ lv_num($ft->max_shrink_len_pct, 2) }}%</b> · عرض <b class="num">{{ lv_num($ft->max_shrink_width_pct, 2) }}%</b></span>
          <span class="hint">اللي يطلع برّه المواصفة بيتعلّم بعد الحفظ</span>
        </div>
      @endif

      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">المسلسل الورقي للمعمل</label>
          <input name="lab[paper_serial]" class="form-control form-control-sm" value="{{ old('lab.paper_serial', $lab?->paper_serial) }}" placeholder="002192"></div>

        <div class="col-12"><b class="small">نسبة الانكماش</b> <span class="hint">— عينتين، وزوّد لو المعمل أخد عينة تأكيد</span></div>
        @php $shown = 2; for ($s = 3; $s <= 4; $s++) if (old("lab.s{$s}_shrink_len_pct", $lab?->{"s{$s}_shrink_len_pct"}) !== null || old("lab.s{$s}_shrink_width_pct", $lab?->{"s{$s}_shrink_width_pct"}) !== null) $shown = $s; @endphp
        @for($s = 1; $s <= 4; $s++)
          <div class="col-md-3 shrink-sample" data-s="{{ $s }}" @if($s > $shown) hidden @endif>
            <label class="form-label">عينة {{ $s }}</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">طول</span>
              <input type="number" step="0.01" name="lab[s{{ $s }}_shrink_len_pct]" class="form-control" value="{{ old("lab.s{$s}_shrink_len_pct", $lab?->{"s{$s}_shrink_len_pct"}) }}" placeholder="%">
              <span class="input-group-text">عرض</span>
              <input type="number" step="0.01" name="lab[s{{ $s }}_shrink_width_pct]" class="form-control" value="{{ old("lab.s{$s}_shrink_width_pct", $lab?->{"s{$s}_shrink_width_pct"}) }}" placeholder="%">
            </div>
          </div>
        @endfor
        <div class="col-12">
          <button type="button" class="btn btn-sm btn-outline-plum py-0" id="addSample" @if($shown >= 4) hidden @endif><i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف عينة</button>
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
            <div class="col-md-4"><label class="form-label">أتواب الفاصل</label>
              <input name="lab[color_break_roll_no]" class="form-control form-control-sm" value="{{ old('lab.color_break_roll_no', $lab?->color_break_roll_no) }}" placeholder="مثال: 007، 012"></div>
            <div class="col-md-8"><label class="form-label">وصف الاختلاف</label>
              <input name="lab[color_break_note]" class="form-control form-control-sm" value="{{ old('lab.color_break_note', $lab?->color_break_note) }}" placeholder="مثال: فاصل بعد 20 متر — الدرجة أغمق"></div>
          </div>
        </div>

        <div class="col-md-4"><label class="form-label">صورة عينة اللون</label>
          <input type="file" name="color_swatch" accept="image/*" class="form-control form-control-sm">
          @if($lab?->color_swatch_path)<a href="{{ asset('storage/'.$lab->color_swatch_path) }}" target="_blank" class="hint">العينة المرفوعة</a>@endif</div>
        <div class="col-md-8"><label class="form-label">ملاحظات المعمل</label>
          <input name="lab[notes]" class="form-control form-control-sm" value="{{ old('lab.notes', $lab?->notes) }}"></div>
      </div>
    </fieldset></div>
    @if($lab && $lab->status === 'approved')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">تقرير المعمل</div><a href="{{ route('lab-reports.edit', $lab) }}" class="num fw-bold">{{ $lab->doc_no }}</a></div>
          <div class="col"><div class="hint">متوسط البنشر</div><b class="num text-danger">{{ lv_num($lab->avg_gsm, 2) }}</b></div>
          <div class="col"><div class="hint">انكماش الطول</div><b class="num">{{ lv_num($lab->avg_shrink_len_pct, 2) }}%</b></div>
          <div class="col"><div class="hint">انكماش العرض</div><b class="num">{{ lv_num($lab->avg_shrink_width_pct, 2) }}%</b></div>
        </div>
        @if($lab->gsm_out_of_spec)<div class="alert alert-warning py-2 mt-2 mb-0 small">متوسط البنشر خارج مواصفة الخامة — قابل للتفاوض مع المورد (خصم) أو يترفض.</div>@endif
        @if($lab->has_color_break)<div class="alert alert-danger py-2 mt-2 mb-0 small">فواصل / اختلاف لون: {{ $lab->color_break_roll_no ? 'أتواب ' . $lab->color_break_roll_no . ' · ' : '' }}{{ $lab->color_break_note }}</div>@endif
      </div>
    @endif
  </div>

  {{-- ④ النتيجة — آخر حاجة بعد ما تشوف كل حاجة --}}
  <div class="card mb-3" id="resultCard">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>④ نتيجة الفحص <span class="hint">— بعد ما قست الأتواب وسجلت المعمل، قرر</span></span>
      <div class="d-flex gap-2">
        @if($mode==='edit')
          <span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>
          <a href="{{ route('inspections.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"
             aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
        @endif
        <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <input type="hidden" name="result" id="resultInput" value="{{ $result }}">
      <div class="d-flex gap-2 flex-wrap" role="group" aria-label="نتيجة الفحص">
        @foreach([
          ['accepted',            'مقبول',          'bi-check-circle',       'ok',     'القماش سليم — يكمّل'],
          ['accepted_with_notes', 'مقبول بملاحظات', 'bi-exclamation-circle', 'warn',   'يكمّل بس لازم تكتب الملاحظة'],
          ['rejected',            'مرفوض',          'bi-x-circle',           'danger', 'ينزل شاشة المرفوضات ويرجع للمورد'],
        ] as [$key, $label, $icon, $tone, $note])
          <button type="button" class="btn btn-sm result-btn" data-result="{{ $key }}"
                  aria-pressed="{{ $result === $key ? 'true' : 'false' }}"
                  style="min-width:190px;text-align:start;
                         border:2px solid {{ $result === $key ? 'var(--lv-'.$tone.')' : 'var(--lv-line)' }};
                         background:{{ $result === $key ? 'var(--lv-'.$tone.'-bg)' : '#fff' }}">
            <span class="d-block fw-bold"><i class="bi {{ $icon }}" aria-hidden="true" style="color:var(--lv-{{ $tone }})"></i> {{ $label }}</span>
            <span class="hint d-block">{{ $note }}</span>
          </button>
        @endforeach
      </div>
      <div class="mt-3" id="notesWrap" hidden>
        <label class="form-label" id="notesLabel">ملاحظات</label>
        <input name="notes" id="notesInput" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}" placeholder="اكتب اللي شفته على القماش">
        <div class="hint" id="notesHint">اختياري — إلا لو النتيجة «مقبول بملاحظات» أو «مرفوض».</div>
      </div>
    </fieldset></div>
  </div>

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ الفحص والمعمل</button>
    <span class="hint"><i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص الفحص على طول — ولو كتبت بنشر للأتواب بيتسجل المعمل كمان والرسالة تنزل استلام الخام. المرفوض بينزل شاشة المرفوضات.</span>
  </div>
  @endif
</form>

<template id="lineTpl">@include('inspections.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>count($rolls) ? max(array_keys($rolls)) + 1 : 1])

@push('scripts')
<script>
window.LVI = {
  /* التوب المرفوض لازم يتوزن — الأتواب مش متساوية الوزن */
  rollStatus: function (sel) {
    var tr = sel.closest('tr'), kg = tr.querySelector('.roll-kg');
    var rej = sel.value === 'rejected';
    tr.classList.toggle('table-danger', rej);
    if (kg) { kg.required = rej; kg.placeholder = rej ? 'إجباري' : 'كجم'; if (rej) kg.focus(); }
  },
  /* البنشر تلقائي = وزن التوب ÷ (الطول × العرض) = جم/م² — لو المعمل قاسه بالبانشر يكتبه مكانه */
  autoGsm: function (el) {
    var tr = el.closest('tr'), g = tr.querySelector('.roll-gsm');
    if (!g || (g.value && g.dataset.auto !== '1')) return;
    var len = parseFloat(tr.querySelector('[name$="[length_m]"]')?.value) || 0;
    var w   = parseFloat(tr.querySelector('[name$="[width_cm]"]')?.value) || 0;
    var kg  = parseFloat(tr.querySelector('.roll-kg')?.value) || 0;
    if (len > 0 && w > 0 && kg > 0) { g.value = (kg * 1000 / (len * w / 100)).toFixed(1); g.dataset.auto = '1'; }
    else if (g.dataset.auto === '1') { g.value = ''; }
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

  // البنشر التلقائي من الطول والعرض والوزن
  document.getElementById('lines').addEventListener('input', function (e) {
    var n = e.target.name || '';
    if (/\[(length_m|width_cm|net_kg)\]$/.test(n)) LVI.autoGsm(e.target);
    if (e.target.classList.contains('roll-gsm')) e.target.dataset.auto = '0';
  });

  // «تم» جنب الأتواب المجرودة ⇒ سطر لكل توب
  var btn = document.getElementById('buildRows'), cnt = document.getElementById('countedRolls');
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
