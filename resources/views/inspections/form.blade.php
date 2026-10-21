@extends('layouts.app')
@section('purpose', 'جرد الأتواب وقياس طول وعرض كل توب — أقل عرض هو اللي الماركر هيتبني عليه')
@section('content')
@php
  /* الصفحة بتفتح على توب واحد. عدد السطور بيتولّد من «الأتواب المجرودة فعليًا»
     — مش من عدد الأتواب اللي المورد قال عليها في إذن الإضافة. */
  $rolls    = old('rolls', $row->rolls?->toArray() ?? []);
  $editable = $row->isEditable() || $mode === 'create';
  $result   = old('result', in_array($row->result, ['accepted','accepted_with_notes','rejected'], true) ? $row->result : '');
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'inspection'])

{{-- ① بيانات الرسالة — كلها Labels جاية من إذن الإضافة --}}
@if(($arrived ?? null))
  @php
    $rem = 0; $unit = '';
    foreach (($arrivedPo?->lines ?? collect()) as $l) {
      $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty);
      if ($left > 0.0001) { $rem += $left; $unit = $unit ?: $l->unit; }
    }
  @endphp
  <div class="card mb-3" style="border-color:var(--lv-soft)">
    <div class="card-body py-2 d-flex gap-4 flex-wrap align-items-center">
      <div>
        <div class="fw-bold num" style="font-size:1.15rem;color:var(--lv-brand-ink)">{{ $arrived->consignment_no }}</div>
        <div class="hint">{{ $arrived->fabricType?->name ?? '—' }} · {{ $arrived->color?->label ?? $arrived->color?->code ?? '—' }}</div>
      </div>
      <span><b>وصل في الرسالة دي:</b> <span class="num fw-bold">{{ rtrim(rtrim(number_format((float)$arrived->total_kg,2),'0'),'.') }} كجم</span>
        · <span class="num">{{ (int) $arrived->rolls_count }} توب</span></span>
      <span><b>المورد:</b> {{ $arrived->supplier?->name ?? '—' }}</span>
      @include('partials.supplier_doc', ['sa' => $arrived->sourceAddition()])
      @if($arrivedPo)
        <span><b>الطلب:</b> <span class="num">{{ $arrivedPo->po_no }}</span></span>
        @if($rem > 0)
          <span class="text-danger fw-bold">
            وصول جزئي — باقي {{ rtrim(rtrim(number_format($rem,3),'0'),'.') }} {{ $unit }}
            {{ $arrivedPo->remainder_eta ? '· متوقع ' . $arrivedPo->remainder_eta->format('Y-m-d') : '· الموعد مش محدد' }}
          </span>
        @else
          <span class="text-success fw-bold">الطلب اكتمل</span>
        @endif
      @endif
      <span class="hint ms-auto">افحص اللي وصل دلوقتي — الباقي هيتفحص برسالته لما يوصل</span>
    </div>
  </div>
@endif

<form method="post" id="inspForm"
      action="{{ $mode==='create' ? route('inspections.store') : route('inspections.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  {{-- ② النتيجة — أول قرار في الصفحة --}}
  <div class="card mb-3" id="resultCard">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>① نتيجة الفحص <span class="hint">— ابدأ من هنا</span></span>
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
          ['accepted',            'مقبول',              'bi-check-circle', 'ok',     'القماش سليم — يكمّل للمعمل'],
          ['accepted_with_notes', 'مقبول بملاحظات',     'bi-exclamation-circle', 'warn', 'يكمّل بس لازم تكتب الملاحظة'],
          ['rejected',            'مرفوض',              'bi-x-circle',     'danger', 'ينزل شاشة المرفوضات ويرجع للمورد'],
        ] as [$key, $label, $icon, $tone, $note])
          <button type="button" class="btn btn-sm result-btn" data-result="{{ $key }}"
                  aria-pressed="{{ $result === $key ? 'true' : 'false' }}"
                  style="min-width:190px;text-align:start;
                         border:2px solid {{ $result === $key ? 'var(--lv-'.$tone.')' : 'var(--lv-line)' }};
                         background:{{ $result === $key ? 'var(--lv-'.$tone.'-bg)' : '#fff' }}">
            <span class="d-block fw-bold">
              <i class="bi {{ $icon }}" aria-hidden="true" style="color:var(--lv-{{ $tone }})"></i> {{ $label }}
            </span>
            <span class="hint d-block">{{ $note }}</span>
          </button>
        @endforeach
      </div>

      <div class="mt-3" id="notesWrap">
        <label class="form-label" id="notesLabel">ملاحظات</label>
        <input name="notes" id="notesInput" class="form-control form-control-sm"
               value="{{ old('notes',$row->notes) }}"
               placeholder="اكتب اللي شفته على القماش">
        <div class="hint" id="notesHint">اختياري — إلا لو النتيجة «مقبول بملاحظات» أو «مرفوض».</div>
      </div>
    </fieldset></div>
  </div>

  {{-- ③ الجرد --}}
  <div class="card mb-3">
    <div class="card-header">② الجرد <span class="hint">— كام توب موجود فعلًا مقابل اللي إذن الإضافة قال عليه</span></div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      @if(($arrived ?? null))
        <input type="hidden" name="doc_date"       value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="consignment_id" value="{{ old('consignment_id', $row->consignment_id ?? $arrived->id) }}">
        <input type="hidden" name="fabric_type_id" value="{{ old('fabric_type_id', $row->fabric_type_id ?? $arrived->fabric_type_id) }}">
        <input type="hidden" name="color_id"       value="{{ old('color_id', $row->color_id ?? $arrived->color_id) }}">
        <input type="hidden" name="supplier_id"    value="{{ old('supplier_id', $row->supplier_id ?? $arrived->supplier_id) }}">
        <input type="hidden" name="inspector_id"   value="{{ old('inspector_id', $row->inspector_id ?? auth()->id()) }}">
        <input type="hidden" name="declared_rolls" value="{{ old('declared_rolls', $row->declared_rolls ?? $arrived->rolls_count) }}">
      @else
        <input type="hidden" name="doc_date"     value="{{ old('doc_date', $row->doc_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        <input type="hidden" name="inspector_id" value="{{ old('inspector_id', $row->inspector_id ?? auth()->id()) }}">
        <div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label req">الحوض (الرسالة)</label>
            <select name="consignment_id" class="form-select form-select-sm" required><option value="">—</option>
              @foreach($consignments as $k=>$v)<option value="{{ $k }}" @selected(old('consignment_id',$row->consignment_id)==$k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-md-4"><label class="form-label req">أتواب حسب إذن الإضافة</label>
            <input type="number" min="0" name="declared_rolls" class="form-control form-control-sm"
                   value="{{ old('declared_rolls',$row->declared_rolls) }}" required></div>
        </div>
      @endif

      <div class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label">المسلسل الورقي</label>
          <input name="paper_serial" class="form-control form-control-sm"
                 value="{{ old('paper_serial',$row->paper_serial) }}" placeholder="04619"></div>

        @if(($arrived ?? null))
          <div class="col-md-2"><label class="form-label">أتواب حسب الإذن</label>
            <div class="form-control form-control-sm num" style="background:var(--lv-offwhite)">{{ (int) ($row->declared_rolls ?? $arrived->rolls_count) }}</div>
            <div class="hint">اللي جه في إذن الإضافة</div></div>
        @endif

        <div class="col-md-3"><label class="form-label req">الأتواب المجرودة فعليًا</label>
          <div class="input-group input-group-sm">
            <input type="number" min="1" name="counted_rolls" id="countedRolls" class="form-control"
                   value="{{ old('counted_rolls',$row->counted_rolls) }}" required>
            @if($editable)
              <button type="button" class="btn btn-plum" id="buildRows">
                <i class="bi bi-check2" aria-hidden="true"></i> تم
              </button>
            @endif
          </div>
          <div class="hint">اكتب العدد ودوس «تم» — هيتفتحلك سطر لكل توب تحت</div></div>

        <div class="col-md-2"><label class="form-label">الوزن المجرود (كجم)</label>
          <input type="number" step="0.001" name="counted_kg" class="form-control form-control-sm"
                 value="{{ old('counted_kg',$row->counted_kg) }}"></div>

        <div class="col-md-2"><label class="form-label">الفاحص</label>
          <div class="form-control form-control-sm" style="background:var(--lv-offwhite)">{{ auth()->user()?->name }}</div></div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">الجرد</div>
            <b class="num {{ $row->rolls_variance != 0 ? 'text-danger' : '' }}">
              {{ $row->counted_rolls }}/{{ $row->declared_rolls }} — {{ $row->rolls_variance_label }}</b></div>
          <div class="col"><div class="hint">العيّنة</div><b class="num">{{ $row->sampled_rolls }}/{{ $row->total_rolls }} ({{ $row->sample_pct }}%)</b></div>
          <div class="col"><div class="hint">أقل عرض</div><b class="num text-danger">{{ $row->min_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">متوسط العرض</div><b class="num">{{ $row->avg_width_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">فرق العرض</div>
            <b class="num {{ $row->width_alert ? 'text-danger' : '' }}">{{ $row->width_spread_cm ?? '—' }}</b></div>
          <div class="col"><div class="hint">إجمالي الطول</div><b class="num">{{ number_format((float)$row->total_length_m,1) }} م</b></div>
          <div class="col"><div class="hint">نسبة العيوب</div><b class="num">{{ $row->defect_pct }}%</b></div>
          <div class="col"><div class="hint">أتواب مرفوضة</div>
            <b class="num {{ $row->rejected_rolls > 0 ? 'text-danger' : '' }}">{{ (int) $row->rejected_rolls }}
              @if($row->rejected_kg > 0)· {{ rtrim(rtrim(number_format((float)$row->rejected_kg,3),'0'),'.') }} كجم @endif</b></div>
        </div>
        @if($row->rejected_rolls > 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">
            {{ (int) $row->rejected_rolls }} توب اترفضوا بوزن {{ rtrim(rtrim(number_format((float)$row->rejected_kg,3),'0'),'.') }} كجم —
            اتخصموا من الرسالة ومن طلب الشراء ونزلوا مخزن المرتجعات كمطالبة على المورد.
          </div>
        @endif
        @if($row->rolls_variance != 0)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">
            فرق في الجرد: إذن الإضافة قال {{ $row->declared_rolls }} توب، والمجرود {{ $row->counted_rolls }}.
            راجع مع المورد قبل الإفراج.
          </div>
        @endif
        @if($row->width_alert)
          <div class="alert alert-danger py-2 mt-2 mb-0 small">
            فرق العرض بين الأتواب {{ $row->width_spread_cm }} سم — مش طبيعي لقماش حوض واحد.
          </div>
        @endif
        @if($row->sample_too_small)
          <div class="alert alert-warning py-2 mt-2 mb-0 small">
            العيّنة {{ $row->sample_pct }}% بس من الأتواب — أي أرقام مبنية عليها تقديرية.
          </div>
        @endif
      </div>
    @endif
  </div>

  {{-- ④ القياس --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>③ قياس الأتواب
        <span class="hint">— طول وعرض كل توب. أقل عرض هو اللي الماركر هيتبني عليه.
          التوب البايظ علّمه <b>مرفوض</b> واكتب وزنه — بيتخصم من الرسالة ويروح المرتجعات. البنشر مكانه تقرير المعمل.</span>
      </span>
      @if($editable)<button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> توب</button>@endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:90px">رقم التوب</th><th style="width:100px">الطول (م)</th>
          <th style="width:100px">العرض (سم)</th><th style="width:100px">الحالة</th><th style="width:110px">وزن التوب (كجم)</th>
          <th style="width:90px">عدد العيوب</th>
          <th>وصف العيب</th><th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          @foreach($rolls as $i=>$l) @include('inspections.line',['i'=>$i,'l'=>$l]) @endforeach
          @if(!count($rolls)) @include('inspections.line',['i'=>0,'l'=>[]]) @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)
  <div class="form-actions">
    <button class="btn btn-plum">
      <i class="bi bi-save" aria-hidden="true"></i> حفظ الفحص
    </button>
    <span class="hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      الحفظ بيخلّص الفحص على طول — المقبول بينزل المعمل، والمرفوض بينزل شاشة المرفوضات.
    </span>
  </div>
  @endif
</form>

<template id="lineTpl">@include('inspections.line',['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js',['startIndex'=>count($rolls) ? max(array_keys($rolls)) + 1 : 1])

@push('scripts')
<script>
/* التوب المرفوض لازم يتوزن — الأتواب مش متساوية الوزن */
window.LVI = {
  rollStatus: function (sel) {
    var tr = sel.closest('tr'), kg = tr.querySelector('.roll-kg');
    var rej = sel.value === 'rejected';
    tr.classList.toggle('table-danger', rej);
    if (kg) { kg.required = rej; kg.placeholder = rej ? 'إجباري' : 'اختياري'; if (rej) kg.focus(); }
  }
};
(function () {
  var input = document.getElementById('resultInput');
  if (!input) return;

  var notesInput = document.getElementById('notesInput');
  var notesHint  = document.getElementById('notesHint');
  var notesLabel = document.getElementById('notesLabel');

  var TONE = { accepted: 'ok', accepted_with_notes: 'warn', rejected: 'danger' };

  function paint() {
    document.querySelectorAll('.result-btn').forEach(function (b) {
      var on = b.dataset.result === input.value;
      var tone = TONE[b.dataset.result] || 'line';
      b.style.border     = '2px solid ' + (on ? 'var(--lv-' + tone + ')' : 'var(--lv-line)');
      b.style.background = on ? 'var(--lv-' + tone + '-bg)' : '#fff';
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    // «مقبول بملاحظات» و«مرفوض» — الملاحظة إجبارية
    var must = input.value === 'accepted_with_notes' || input.value === 'rejected';
    if (notesInput) notesInput.required = must;
    if (notesLabel) notesLabel.className = must ? 'form-label req' : 'form-label';
    if (notesHint) {
      notesHint.textContent = input.value === 'rejected'
        ? 'إجباري — اكتب سبب الرفض، هيظهر في شاشة المرفوضات وللمورد.'
        : (must ? 'إجباري — اكتب الملاحظة اللي القماش عدّى بيها.'
                : 'اختياري — إلا لو النتيجة «مقبول بملاحظات» أو «مرفوض».');
    }
  }

  document.querySelectorAll('.result-btn').forEach(function (b) {
    b.addEventListener('click', function () { input.value = b.dataset.result; paint(); });
  });

  // «تم» جنب الأتواب المجرودة ⇒ يفتح سطر لكل توب
  var btn = document.getElementById('buildRows');
  var cnt = document.getElementById('countedRolls');
  if (btn && cnt) {
    btn.addEventListener('click', function () {
      var want = parseInt(cnt.value || '0', 10);
      if (!want || want < 1) { cnt.focus(); return; }
      if (want > 500) { alert('العدد كبير أوي — أقصى حاجة 500 توب في التقرير الواحد.'); return; }

      var body = document.getElementById('lines');
      var have = body.querySelectorAll('tr').length;

      if (want < have) {
        var filled = 0;
        body.querySelectorAll('tr').forEach(function (tr) {
          var len = tr.querySelector('[name*="[length_m]"]');
          var wid = tr.querySelector('[name*="[width_cm]"]');
          if ((len && len.value) || (wid && wid.value)) filled++;
        });
        if (filled > want && !confirm('فيه ' + filled + ' توب متكتوب فيهم قياسات. تقليل العدد لـ ' + want + ' هيمسح الزيادة. تكمّل؟')) return;
        while (body.querySelectorAll('tr').length > want) body.lastElementChild.remove();
      } else {
        for (var i = have; i < want; i++) LV.add('lineTpl', 'lines');
      }

      // رقّم الأتواب 001, 002, ... لو لسه فاضية
      Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr, i) {
        var no = tr.querySelector('[name*="[roll_no]"]');
        if (no && !no.value) no.value = String(i + 1).padStart(3, '0');
      });

      LV.renumber && LV.renumber('lines');
    });
  }

  // ما تبعتش قبل ما تختار نتيجة — أحسن من رحلة للسيرفر ورجوع
  var form = document.getElementById('inspForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (!input.value) {
        e.preventDefault();
        var card = document.getElementById('resultCard');
        if (card) {
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          card.style.boxShadow = '0 0 0 3px var(--lv-danger)';
          setTimeout(function () { card.style.boxShadow = ''; }, 2000);
        }
        alert('اختار نتيجة الفحص الأول: مقبول، مقبول بملاحظات، ولا مرفوض.');
      }
    });
  }

  paint();
})();
</script>
@endpush
@endsection
