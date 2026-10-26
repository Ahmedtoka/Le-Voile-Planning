@extends('layouts.app')
@section('purpose', 'اختار رسالة أو أكتر واكتب الكمية التقريبية لكل واحدة والموديلات — المخزن هيوزن كل رسالة لوحدها ويرد بالفعلي، وطلب الماركر بيتعمل معاه')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'prep'])

@php
  $preId = (int) ($pick?->id ?? 0);
  $oldItems = old('items', []);
@endphp

<form method="post" action="{{ route('preparation-requests.store') }}" id="prepForm">@csrf
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>طلب تحضير جديد <span class="hint">— التاريخ بياخد تاريخ اليوم · ممكن أكتر من رسالة في نفس الطلب</span></span>
      <a href="{{ route('preparation-requests.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      {{-- ① الفلتر: خامة / لون / بحث ⇐ الرسايل المتاحة بالأقدم أولًا --}}
      <div class="row g-2 align-items-end mb-2">
        <div class="col-md-3"><label class="form-label" for="pf-fab">الخامة</label>
          <select id="pf-fab" class="form-select" onchange="LVP.filter()"><option value="">كل الخامات</option>
            @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label" for="pf-col">اللون</label>
          <select id="pf-col" class="form-select" onchange="LVP.filter()"><option value="">كل الألوان</option>
            @foreach($colors as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-4"><label class="form-label" for="pf-q">بحث</label>
          <input id="pf-q" class="form-control" placeholder="رقم الرسالة أو الخامة أو اللون (مثال: أبيض أو 0805)…" oninput="LVP.filter()"></div>
        <div class="col-md-2 hint pb-2"><span id="pf-count" class="num">{{ $consignments->count() }}</span> رسالة متاحة · الأقدم أولًا</div>
      </div>

      @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

      <div class="table-responsive">
        <table class="table table-sm line-table mb-0" id="pfTable">
          <thead><tr>
            <th style="width:40px"><span class="visually-hidden">اختيار</span></th>
            <th>الرسالة</th><th>الخامة</th><th>اللون</th><th style="width:110px">المتاح</th><th style="width:70px">الأتواب</th><th style="width:100px">وصلت</th>
            <th style="width:170px">الكمية المطلوبة</th><th style="width:110px">الباقي كله</th><th style="width:200px">هيفضل على الرسالة</th>
          </tr></thead>
          <tbody>
          @forelse($consignments as $c)
            @php
              $o = $oldItems[$c->id] ?? null;
              $on = $o ? !empty($o['on']) : ($c->id === $preId);
              $u = $c->unit_label;
            @endphp
            <tr class="pf-row {{ $on ? 'table-active' : '' }}" data-id="{{ $c->id }}" data-fab="{{ $c->fabric_type_id }}" data-col="{{ $c->color_id }}"
                data-rem="{{ (float) $c->remaining_kg }}" data-unit="{{ $u }}"
                data-search="{{ mb_strtolower($c->consignment_no . ' ' . ($c->fabricType?->name ?? '') . ' ' . ($c->color?->code ?? '') . ' ' . ($c->color?->name ?? '')) }}">
              <td class="text-center"><input type="checkbox" class="form-check-input pf-on" name="items[{{ $c->id }}][on]" value="1" @checked($on) onchange="LVP.toggle(this)" aria-label="اختار الرسالة {{ $c->consignment_no }}"></td>
              <td class="num fw-bold">{{ $c->consignment_no }}
                @if($c->has_color_break)<div class="text-danger hint"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> فواصل / اختلاف لون</div>@endif</td>
              <td>{{ $c->fabricType?->name }}</td>
              <td>{{ $c->color?->label ?? $c->color?->code }}</td>
              <td class="num">{{ lv_num($c->remaining_kg) }} {{ $u }}
                @if(!$c->is_meter && (float) $c->gross_kg > 0)<div class="hint" style="white-space:nowrap">كيس {{ lv_num($c->bag_g, 1) }}{{ $c->fabric_form === 'open' ? ' + ماسورة ' . lv_num($c->tube_g, 1) : '' }} جم/توب</div>@endif</td>
              <td class="num">{{ (int) $c->rolls_count }}</td>
              <td class="num hint">{{ $c->arrival_date?->format('Y-m-d') }}</td>
              <td>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.001" min="0" name="items[{{ $c->id }}][qty]" class="form-control pf-qty" value="{{ $o['qty'] ?? '' }}" placeholder="تقريبي" oninput="LVP.calc(this)" @disabled(!$on || !empty($o['rem']))>
                  <span class="input-group-text">{{ $u }}</span>
                </div>
              </td>
              <td class="text-center">
                <input type="hidden" name="items[{{ $c->id }}][rem]" value="0">
                <input type="checkbox" class="form-check-input pf-rem" name="items[{{ $c->id }}][rem]" value="1" @checked(!empty($o['rem'])) onchange="LVP.rem(this)" @disabled(!$on) aria-label="الباقي كله من {{ $c->consignment_no }}">
                <div class="hint">من غير وزن</div>
              </td>
              <td class="pf-left small fw-bold">—</td>
            </tr>
          @empty
            <tr><td colspan="10"><div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i><div class="t">مفيش رسايل جاهزة للتشغيل فيها رصيد.</div></div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="hint mt-2">
        علّم على رسالة أو أكتر واكتب لكل واحدة الكمية التقريبية (أو «الباقي كله» لآخر تقسيمة). كل رسالة هتنزل للمخزن كسطر لوحده يتوزن لوحده —
        والكمية بتتحجز على الرسالة فورًا. <b id="pf-sel" class="num">0</b> رسالة مختارة.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="row g-3">
        {{-- ② الموديلات — بحث، ولو مش موجود ضيفه --}}
        <div class="col-12">
          <label class="form-label req">الموديلات اللي هتتشغل من الكمية دي <span class="hint">— اكتب كود أو اسم، ولو الموديل مش موجود ضيفه من نفس المكان</span></label>
          @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', []), 'allowNew' => true])
        </div>

        {{-- ③ المصنع والملاحظات في الآخر --}}
        <div class="col-md-4">
          <label class="form-label">المصنع (اختياري)</label>
          <select name="factory_id" class="form-select"><option value="">— يتحدد في أمر الشغل —</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id')==$k)>{{ $v }}</option>@endforeach
          </select>
          <div class="hint">ممكن يتغير بعدين — مش بيأثر على الوزن</div>
        </div>
        <div class="col-md-8">
          <label class="form-label">ملاحظات للمخزن</label>
          <input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="مثال: محتاجه بكرة الصبح">
        </div>

        <div class="col-12">
          <div class="form-check">
            <input type="hidden" name="also_marker" value="0">
            <input class="form-check-input" type="checkbox" name="also_marker" value="1" id="am" checked>
            <label class="form-check-label" for="am"><b>واطلب الماركر من الباترونست في نفس الوقت</b> — طلب ماركر لكل رسالة بنفس الموديلات (عرض التشغيل والبنشر من الجودة)</label>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> اطلب من المخزن</button>
      <span class="hint">الكميات بتتحجز على الرسايل فورًا، والمخزن يوصله إشعار يوزن كل رسالة ويرد.</span>
      <div class="fa-end">
        <a href="{{ route('preparation-requests.index') }}" class="btn btn-outline-secondary">إلغاء</a>
      </div>
    </div>
  </div>
</form>

@push('scripts')
<script>
window.LVP = (function () {
  var nf = function (v) { return Number(v).toLocaleString('en-US', {maximumFractionDigits: 1}); };
  function rows() { return [...document.querySelectorAll('#pfTable .pf-row')]; }
  function norm(s) { return (s || '').toString().toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }
  function filter() {
    var f = document.getElementById('pf-fab').value, c = document.getElementById('pf-col').value, q = norm(document.getElementById('pf-q').value.trim());
    var n = 0;
    rows().forEach(function (tr) {
      var on = tr.querySelector('.pf-on')?.checked;
      var show = on || ((!f || tr.dataset.fab === f) && (!c || tr.dataset.col === c) && (!q || norm(tr.dataset.search).includes(q)));
      tr.hidden = !show; if (show) n++;
    });
    document.getElementById('pf-count').textContent = n;
  }
  function toggle(cb) {
    var tr = cb.closest('tr'), q = tr.querySelector('.pf-qty'), r = tr.querySelector('.pf-rem');
    tr.classList.toggle('table-active', cb.checked);
    if (r) r.disabled = !cb.checked;
    if (q) { q.disabled = !cb.checked || (r && r.checked); if (cb.checked && !q.disabled) q.focus(); }
    calc(q); count();
  }
  function rem(cb) {
    var tr = cb.closest('tr'), q = tr.querySelector('.pf-qty');
    if (q) { q.disabled = cb.checked; if (cb.checked) q.value = ''; }
    calc(q);
  }
  function calc(inp) {
    var tr = inp ? inp.closest('tr') : null; if (!tr) return;
    var out = tr.querySelector('.pf-left'), on = tr.querySelector('.pf-on')?.checked, remAll = tr.querySelector('.pf-rem')?.checked;
    var r = Number(tr.dataset.rem) || 0, q = Number(tr.querySelector('.pf-qty')?.value) || 0, u = tr.dataset.unit;
    if (!on) { out.textContent = '—'; out.className = 'pf-left small fw-bold'; return; }
    if (remAll) { out.textContent = 'هياخد الباقي كله (' + nf(r) + ' ' + u + ') — الرسالة هتخلص'; out.className = 'pf-left small fw-bold text-warning'; return; }
    if (!q) { out.textContent = 'اكتب الكمية'; out.className = 'pf-left small fw-bold hint'; return; }
    var left = r - q;
    if (left < -0.0005) { out.textContent = 'أكتر من المتاح بـ ' + nf(-left) + ' ' + u; out.className = 'pf-left small fw-bold text-danger'; }
    else { out.textContent = nf(left) + ' ' + u + (left < 0.0005 ? ' — هتخلص' : ''); out.className = 'pf-left small fw-bold ' + (left < 0.0005 ? 'text-warning' : 'text-success'); }
  }
  function count() { document.getElementById('pf-sel').textContent = rows().filter(function (tr) { return tr.querySelector('.pf-on')?.checked; }).length; }
  document.addEventListener('DOMContentLoaded', function () { rows().forEach(function (tr) { calc(tr.querySelector('.pf-qty')); }); count(); });
  document.getElementById('prepForm').addEventListener('submit', function (e) {
    if (!rows().some(function (tr) { return tr.querySelector('.pf-on')?.checked; })) { e.preventDefault(); alert('علّم على رسالة واحدة على الأقل.'); }
  });
  return {filter: filter, toggle: toggle, rem: rem, calc: calc};
})();
</script>
@endpush
@endsection
