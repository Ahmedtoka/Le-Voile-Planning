{{--
  منتقي الموديلات بالبحث — تكتب كود أو اسم (من 3 حروف) فتظهر النتايج، تختار، والمختار بيظهر كـ chips.
  الاستخدام: @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => [..ids..], 'onchange' => 'LVM.suggest()'])
  $models: [id => 'CODE — الاسم']
  ملحوظة: نص الموديل لازم يفضل أول عقدة جوه الـ chip (LVW بيقرا childNodes[0]).
--}}
@php
  $pkName   = $name ?? 'product_model_ids';
  $pkId     = 'mp_' . preg_replace('/[^a-z0-9]/i', '', $pkName);
  $pkPicked = array_map('intval', $picked ?? []);
  $pkChange = $onchange ?? '';
  $pkNew    = !empty($allowNew) && Route::has('product-models.quick');
@endphp
<div class="model-picker" id="{{ $pkId }}" data-name="{{ $pkName }}" data-onchange="{{ $pkChange }}" data-new="{{ $pkNew ? route('product-models.quick') : '' }}">
  <div class="position-relative mp-wrap">
    <i class="bi bi-search mp-ico" aria-hidden="true"></i>
    <input type="text" class="form-control mp-search" autocomplete="off" aria-label="بحث عن موديل"
           placeholder="اكتب كود الموديل أو اسمه (3 حروف على الأقل)…">
    <div class="mp-results list-group position-absolute w-100 shadow" hidden></div>
  </div>
  <div class="mp-chips d-flex flex-wrap gap-1 mt-2" aria-live="polite">
    @foreach($pkPicked as $id)
      @if(isset($models[$id]))
        <span class="badge bg-primary mp-chip" data-id="{{ $id }}">{{ $models[$id] }}<button type="button" class="btn-close mp-x" aria-label="شيل {{ $models[$id] }}" title="شيل"></button><input type="hidden" name="{{ $pkName }}[]" value="{{ $id }}"></span>
      @endif
    @endforeach
  </div>
  <div class="hint mp-empty" @if(count($pkPicked)) hidden @endif>مفيش موديلات مختارة لسه</div>
  <script type="application/json" class="mp-data">@json(collect($models)->map(fn ($label, $id) => ['id' => (int) $id, 'label' => $label])->values())</script>
</div>

@once
<style>
  /* chips الموديلات المختارة: شريحة بلون البراند الخفيف + زرار شيل واضح */
  .model-picker .mp-wrap .mp-ico{position:absolute;inset-inline-start:11px;top:50%;transform:translateY(-50%);color:var(--lv-muted);font-size:.9rem;pointer-events:none}
  .model-picker .mp-search{padding-inline-start:32px}
  .model-picker .mp-results{z-index:1050;max-height:260px;overflow:auto;border-radius:var(--r-md);border:1px solid var(--lv-line)}
  .model-picker .mp-results .list-group-item{font-size:var(--fs-sm);padding:.45rem .75rem}
  .model-picker .mp-results .list-group-item.active{background:var(--lv-tint);color:var(--lv-brand-ink);border-color:var(--lv-line)}
  .model-picker .mp-chip{font-size:var(--fs-sm);font-weight:600;padding:.3em .5em .3em .75em;gap:6px;max-width:100%;white-space:normal;text-align:start}
  .model-picker .mp-chip .btn-close{width:.6em;height:.6em;padding:.2em;margin:0;opacity:.6;background-size:.6em;border-radius:50%;flex:0 0 auto}
  .model-picker .mp-chip .btn-close:hover,.model-picker .mp-chip .btn-close:focus-visible{opacity:1;background-color:rgba(157,25,126,.15)}
</style>
@push('scripts')
<script>
/* منتقي الموديلات: بحث من 3 حروف بالكود أو الاسم ⇒ نتايج ⇒ اختيار ⇒ chip + hidden input */
(function () {
  function norm(s) { return (s || '').toString().toLowerCase().replace(/[ً-ْ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }
  function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  document.querySelectorAll('.model-picker').forEach(function (box) {
    var data  = JSON.parse(box.querySelector('.mp-data').textContent);
    var name  = box.dataset.name, onchange = box.dataset.onchange;
    var input = box.querySelector('.mp-search'), res = box.querySelector('.mp-results');
    var newUrl = box.dataset.new || '';
    var CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* موديل مش موجود؟ يتحفظ في البيانات الأساسية على طول ويتضاف للاختيار */
    async function createNew(name) {
      if (!newUrl) return;
      var code = prompt('كود الموديل الجديد «' + name + '» (سيبه فاضي والسيستم يولّد كود):', '');
      if (code === null) return;
      var r = await fetch(newUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                                   body: JSON.stringify({name: name, code: code})});
      var j = await r.json().catch(() => ({}));
      if (!r.ok) { alert(j.message || 'ما اتضافش'); return; }
      data.push({id: j.id, label: j.label});
      add({id: j.id, label: j.label}); input.value = ''; render(''); input.focus();
    }
    var chips = box.querySelector('.mp-chips'), empty = box.querySelector('.mp-empty');
    var active = -1;

    function picked() { return [...chips.querySelectorAll('.mp-chip')].map(c => Number(c.dataset.id)); }
    function fire() { empty.hidden = picked().length > 0; if (onchange) { try { new Function(onchange)(); } catch (e) {} } }

    function add(item) {
      if (picked().includes(item.id)) return;
      var chip = document.createElement('span');
      chip.className = 'badge bg-primary mp-chip'; chip.dataset.id = item.id;
      /* النص أول عقدة — LVW بيعتمد على كده */
      chip.innerHTML = esc(item.label) + '<button type="button" class="btn-close mp-x" aria-label="شيل ' + esc(item.label) + '" title="شيل"></button>'
                     + '<input type="hidden" name="' + name + '[]" value="' + item.id + '">';
      chips.appendChild(chip);
      fire();
    }

    chips.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-close'); if (!btn) return;
      btn.closest('.mp-chip').remove(); fire();
    });

    function render(q) {
      var n = norm(q);
      if (n.length < 3) { res.hidden = true; res.innerHTML = ''; return; }
      var have = picked();
      var hits = data.filter(d => !have.includes(d.id) && norm(d.label).includes(n)).slice(0, 30);
      if (!hits.length) {
        res.innerHTML = '<div class="list-group-item small text-muted">مفيش موديل بالاسم أو الكود ده</div>'
          + (newUrl ? '<button type="button" class="list-group-item list-group-item-action py-1 small text-success mp-new"><i class="bi bi-plus-circle"></i> ضيف «' + esc(q) + '» كموديل جديد</button>' : '');
        res.hidden = false;
        var nb = res.querySelector('.mp-new'); if (nb) nb.addEventListener('mousedown', function (e) { e.preventDefault(); createNew(q.trim()); });
        return;
      }
      res.innerHTML = hits.map((h, i) => '<button type="button" class="list-group-item list-group-item-action py-1 small" data-i="' + i + '">' + esc(h.label) + '</button>').join('');
      res.hidden = false; active = -1;
      res.querySelectorAll('button').forEach((b, i) => b.addEventListener('mousedown', function (e) { e.preventDefault(); add(hits[i]); input.value = ''; render(''); input.focus(); }));
      res._hits = hits;
    }

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('focus', () => render(input.value));
    input.addEventListener('blur', () => setTimeout(() => { res.hidden = true; }, 150));
    input.addEventListener('keydown', function (e) {
      var items = res.querySelectorAll('button'); if (res.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); }
      else if (e.key === 'Enter') { e.preventDefault(); var i = active >= 0 ? active : 0; add(res._hits[i]); input.value = ''; render(''); return; }
      else return;
      items.forEach((b, i) => b.classList.toggle('active', i === active));
    });
  });
})();
</script>
@endpush
@endonce
