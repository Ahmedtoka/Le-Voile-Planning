{{--
  منتقي رسالة واحدة بالبحث — اكتب جزء من رقم الرسالة أو الخامة أو اللون (من حرفين) وتختار.
  الاستخدام: @include('partials.consignment_picker', ['name' => 'consignment_id', 'consignments' => $consignments, 'value' => $id, 'onchange' => 'LVP.pick()'])
  بيكتب على الـ hidden input داتا الرسالة المختارة: data-rem / data-w / data-g / data-break / data-note / data-label
--}}
@php
  $cpName  = $name ?? 'consignment_id';
  $cpId    = 'cp_' . preg_replace('/[^a-z0-9]/i', '', $cpName);
  $cpVal   = (int) ($value ?? 0);
  $cpItems = collect($consignments)->map(fn ($c) => [
      'id'     => (int) $c->id,
      'no'     => $c->consignment_no,
      'label'  => $c->consignment_no . ' — ' . ($c->fabricType?->name ?? '') . ' · ' . ($c->color?->label ?? $c->color?->code ?? ''),
      'search' => mb_strtolower($c->consignment_no . ' ' . ($c->fabricType?->name ?? '') . ' ' . ($c->color?->code ?? '') . ' ' . ($c->color?->name ?? '')),
      'rem'    => (float) $c->remaining_kg,
      'rolls'  => (int) $c->rolls_count,
      'date'   => $c->arrival_date?->format('Y-m-d'),
      'w'      => $c->min_width_cm,
      'g'      => $c->avg_gsm,
      'break'  => $c->has_color_break ? 1 : 0,
      'note'   => $c->color_break_note,
  ])->values();
  $cpSel = $cpItems->firstWhere('id', $cpVal);
@endphp
<div class="cons-picker" id="{{ $cpId }}" data-onchange="{{ $onchange ?? '' }}">
  <input type="hidden" name="{{ $cpName }}" class="cp-value" value="{{ $cpVal ?: '' }}" @if($cpSel) data-rem="{{ $cpSel['rem'] }}" data-w="{{ $cpSel['w'] }}" data-g="{{ $cpSel['g'] }}" data-break="{{ $cpSel['break'] }}" data-note="{{ $cpSel['note'] }}" data-label="{{ $cpSel['label'] }}" @endif>
  <div class="position-relative cp-wrap">
    <input type="text" class="form-control form-control-sm cp-search" autocomplete="off"
           placeholder="اكتب رقم الرسالة أو الخامة أو اللون (مثال 0805 أو إنترلوك أو 001)…" value="">
    <div class="cp-results list-group position-absolute w-100 shadow" hidden style="z-index:1050;max-height:280px;overflow:auto"></div>
  </div>
  <div class="cp-selected mt-2 p-2 rounded d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:var(--lv-offwhite);border:1px solid var(--lv-line)" @if(!$cpSel) hidden @endif>
    <div>
      <div class="hint">الرسالة المختارة</div>
      <b class="num cp-label">{{ $cpSel['label'] ?? '' }}</b>
      <div class="hint cp-meta">@if($cpSel)متاح {{ lv_kg($cpSel['rem'], false) }} · {{ $cpSel['rolls'] }} توب · وصل {{ $cpSel['date'] }}@endif</div>
      <div class="text-danger small cp-break" @if(!($cpSel['break'] ?? 0)) hidden @endif>فواصل / اختلاف لون: {{ $cpSel['note'] ?? '' }}</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary py-0 cp-clear">غيّر</button>
  </div>
  <div class="hint mt-1">مرتبة بالأقدم أولًا — القديم يخرج الأول. المتاح = المفرج عنه ناقص أوامر الشغل والطلبات المفتوحة.</div>
  <script type="application/json" class="cp-data">@json($cpItems)</script>
</div>

@once
@push('scripts')
<script>
(function () {
  function norm(s) { return (s || '').toString().toLowerCase().replace(/[ً-ْ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }
  var nf = function (v) { return Number(v).toLocaleString('en-US', {maximumFractionDigits: 1}); };

  document.querySelectorAll('.cons-picker').forEach(function (box) {
    var data = JSON.parse(box.querySelector('.cp-data').textContent), onchange = box.dataset.onchange;
    var val = box.querySelector('.cp-value'), input = box.querySelector('.cp-search'), res = box.querySelector('.cp-results');
    var sel = box.querySelector('.cp-selected'), wrap = box.querySelector('.cp-wrap');

    function fire() { if (onchange) { try { new Function(onchange)(); } catch (e) {} } val.dispatchEvent(new Event('change', {bubbles: true})); }

    function choose(it) {
      val.value = it.id;
      val.dataset.rem = it.rem; val.dataset.w = it.w || ''; val.dataset.g = it.g || ''; val.dataset.break = it.break; val.dataset.note = it.note || ''; val.dataset.label = it.label;
      sel.querySelector('.cp-label').textContent = it.label;
      sel.querySelector('.cp-meta').textContent = 'متاح ' + nf(it.rem) + ' كجم · ' + it.rolls + ' توب · وصل ' + (it.date || '');
      var b = sel.querySelector('.cp-break'); b.hidden = !it.break; b.textContent = it.break ? 'فواصل / اختلاف لون: ' + (it.note || '') : '';
      sel.hidden = false; input.value = ''; res.hidden = true; wrap.hidden = true;
      fire();
    }

    function render(q) {
      var n = norm(q);
      if (n.length < 2) { res.hidden = true; res.innerHTML = ''; return; }
      var hits = data.filter(d => norm(d.search).includes(n)).slice(0, 25);
      if (!hits.length) { res.innerHTML = '<div class="list-group-item small text-muted">مفيش رسالة مفرج عنها بالكود أو الخامة أو اللون ده</div>'; res.hidden = false; return; }
      res.innerHTML = hits.map((h, i) => '<button type="button" class="list-group-item list-group-item-action py-1 small" data-i="' + i + '">'
        + '<b class="num">' + h.no + '</b> — ' + h.label.split(' — ')[1]
        + ' <span class="hint">· متاح ' + nf(h.rem) + ' كجم · ' + h.rolls + ' توب · ' + (h.date || '') + '</span>'
        + (h.break ? ' <span class="badge bg-danger">فواصل</span>' : '') + '</button>').join('');
      res.hidden = false;
      res.querySelectorAll('button').forEach((b, i) => b.addEventListener('mousedown', function (e) { e.preventDefault(); choose(hits[i]); }));
    }

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('focus', () => render(input.value));
    input.addEventListener('blur', () => setTimeout(() => { res.hidden = true; }, 150));
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var b = res.querySelector('button'); if (b) b.dispatchEvent(new MouseEvent('mousedown')); } });
    box.querySelector('.cp-clear').addEventListener('click', function () {
      val.value = ''; ['rem', 'w', 'g', 'break', 'note', 'label'].forEach(k => delete val.dataset[k]);
      sel.hidden = true; wrap.hidden = false; input.focus(); fire();
    });
    if (val.value) wrap.hidden = true;
  });
})();
</script>
@endpush
@endonce
