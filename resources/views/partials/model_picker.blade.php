{{--
  منتقي الموديلات بالبحث — تكتب كود أو اسم (من 3 حروف) فتظهر النتايج، تختار، والمختار بيظهر كـ chips.
  الاستخدام: @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => [..ids..], 'onchange' => 'LVM.suggest()'])
  $models: [id => 'CODE — الاسم']
--}}
@php
  $pkName   = $name ?? 'product_model_ids';
  $pkId     = 'mp_' . preg_replace('/[^a-z0-9]/i', '', $pkName);
  $pkPicked = array_map('intval', $picked ?? []);
  $pkChange = $onchange ?? '';
@endphp
<div class="model-picker" id="{{ $pkId }}" data-name="{{ $pkName }}" data-onchange="{{ $pkChange }}">
  <div class="position-relative">
    <input type="text" class="form-control form-control-sm mp-search" autocomplete="off"
           placeholder="اكتب كود الموديل أو اسمه (3 حروف على الأقل)…">
    <div class="mp-results list-group position-absolute w-100 shadow" hidden style="z-index:1050;max-height:260px;overflow:auto"></div>
  </div>
  <div class="mp-chips d-flex flex-wrap gap-1 mt-2">
    @foreach($pkPicked as $id)
      @if(isset($models[$id]))
        <span class="badge bg-light text-dark border mp-chip" data-id="{{ $id }}" style="font-size:.8rem;font-weight:500">
          {{ $models[$id] }}
          <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.55rem" aria-label="شيل"></button>
          <input type="hidden" name="{{ $pkName }}[]" value="{{ $id }}">
        </span>
      @endif
    @endforeach
  </div>
  <div class="hint mp-empty" @if(count($pkPicked)) hidden @endif>مفيش موديلات مختارة لسه</div>
  <script type="application/json" class="mp-data">@json(collect($models)->map(fn ($label, $id) => ['id' => (int) $id, 'label' => $label])->values())</script>
</div>

@once
@push('scripts')
<script>
/* منتقي الموديلات: بحث من 3 حروف بالكود أو الاسم ⇒ نتايج ⇒ اختيار ⇒ chip + hidden input */
(function () {
  function norm(s) { return (s || '').toString().toLowerCase().replace(/[ً-ْ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }

  document.querySelectorAll('.model-picker').forEach(function (box) {
    var data  = JSON.parse(box.querySelector('.mp-data').textContent);
    var name  = box.dataset.name, onchange = box.dataset.onchange;
    var input = box.querySelector('.mp-search'), res = box.querySelector('.mp-results');
    var chips = box.querySelector('.mp-chips'), empty = box.querySelector('.mp-empty');
    var active = -1;

    function picked() { return [...chips.querySelectorAll('.mp-chip')].map(c => Number(c.dataset.id)); }
    function fire() { empty.hidden = picked().length > 0; if (onchange) { try { new Function(onchange)(); } catch (e) {} } }

    function add(item) {
      if (picked().includes(item.id)) return;
      var chip = document.createElement('span');
      chip.className = 'badge bg-light text-dark border mp-chip'; chip.dataset.id = item.id;
      chip.style.cssText = 'font-size:.8rem;font-weight:500';
      chip.innerHTML = item.label + ' <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.55rem" aria-label="شيل"></button>'
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
      if (!hits.length) { res.innerHTML = '<div class="list-group-item small text-muted">مفيش موديل بالاسم أو الكود ده</div>'; res.hidden = false; return; }
      res.innerHTML = hits.map((h, i) => '<button type="button" class="list-group-item list-group-item-action py-1 small" data-i="' + i + '">' + h.label + '</button>').join('');
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
