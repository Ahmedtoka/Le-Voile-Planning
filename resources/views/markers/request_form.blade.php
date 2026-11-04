@extends('layouts.app')
@section('purpose', 'اختار الرسالة والموديلات — العرض والبنشر بينزلوا من الجودة، والسيستم يقترح ماركر قديم لو موجود')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'mreq'])

<form method="post" action="{{ route('markers.requests.store') }}" id="mrForm">@csrf
  <input type="hidden" name="doc_date" value="{{ old('doc_date', now()->toDateString()) }}">
  <input type="hidden" name="preparation_request_id" value="{{ old('preparation_request_id', $row->preparation_request_id) }}">
  <input type="hidden" name="use_marker_id" id="useMarker" value="">
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>طلب ماركر جديد</span>
      <a href="{{ route('markers.requests') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      <div class="note-box mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>
          بتقول للباترونست: «محتاج الموديلات دي على الرسالة دي». <b>العرض (أقل عرض من الفحص) والبنشر (من المعمل)</b>
          بينزلوا لوحدهم ومش بيتعدلوا. المصنع مش هنا — بيتحدد في أمر الشغل وممكن يتغير.
          لو فيه ماركر قديم لنفس الموديلات على نفس العرض، السيستم هيقترحه وتقدر تستخدمه على طول.
        </span>
      </div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label req">الرسالة (الحوض)</label>
          @include('partials.consignment_picker', ['name' => 'consignment_id', 'consignments' => $consignments, 'value' => old('consignment_id', $row->consignment_id), 'onchange' => 'LVM.pick()'])
          <div id="breakWarn" class="alert alert-danger py-1 mt-1 small" hidden></div>
        </div>
        <div class="col-md-2"><label class="form-label">عرض التشغيل</label>
          <div class="input-group">
            <div class="form-control ro num" id="fw" aria-live="polite">—</div>
            <span class="input-group-text">سم</span>
          </div>
          <div class="hint">أقل عرض من الفحص − {{ lv_num(lv_setting('width_margin_cm_per_side', 2.5), 2) }} سم من كل جانب — مقفول</div></div>
        <div class="col-md-2"><label class="form-label">البنشر</label>
          <div class="input-group">
            <div class="form-control ro num" id="fg" aria-live="polite">—</div>
            <span class="input-group-text">جم/م²</span>
          </div>
          <div class="hint">من تقرير المعمل — مقفول</div></div>
        <div class="col-md-2"><label class="form-label">نسخ الماركر المطبوعة</label>
          <div class="input-group">
            <input type="number" min="0" name="copies" class="form-control" value="{{ old('copies', $row->copies ?? 2) }}" placeholder="مثال: 2">
            <span class="input-group-text">نسخة</span>
          </div></div>

        <div class="col-12">
          <label class="form-label req">الموديلات المطلوبة في الفرشة <span class="hint">— علّم كل الموديلات اللي عايز الماركر يجمعها</span></label>
          <div id="modelsBox">
            @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', $picked ?? []), 'onchange' => 'LVM.suggest()', 'allowNew' => true])
          </div>
        </div>
        {{-- المقاسات: سطر لكل موديل + مقاس من دروب داون مقاسات الموديل (مش كلام حر) --}}
        <div class="col-12">
          <label class="form-label req">المقاسات لكل موديل <span class="hint">— اختار المقاس من القايمة، ولو الموديل ليه أكتر من مقاس في نفس الفرشة دوس «+ مقاس»</span></label>
          <div class="table-responsive">
            <table class="table table-sm line-table mb-0" id="mrSizes">
              <thead><tr><th>الموديل</th><th style="width:240px">المقاس</th><th style="width:170px"><span class="visually-hidden">إجراءات</span></th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="hint" id="mrSizesEmpty">اختار الموديلات فوق وهتظهر هنا عشان تحدد مقاساتها.</div>
        </div>
        <div class="col-md-3"><label class="form-label">الباترونست</label>
          <select name="assigned_to" class="form-select"><option value="">— كل الباترونستات —</option>
            @foreach($patternists as $k=>$v)<option value="{{ $k }}" @selected(old('assigned_to')==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">مطلوب في</label>
          <input type="date" name="needed_by" class="form-control" value="{{ old('needed_by') }}"></div>
        <div class="col-12"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="اختياري"></div>
      </div>

      {{-- الماركرات القديمة المقترحة --}}
      <div id="suggestBox" class="mt-3" hidden>
        <div class="section-head"><h2>ماركرات قديمة ممكن تنفع</h2><span class="hint">— نفس الموديلات على نفس العرض أو قريب منه</span></div>
        <div id="suggestList" class="d-flex flex-wrap gap-2"></div>
        <div class="hint mt-1">لو اخترت واحد، الطلب بيتقفل عليه على طول من غير ما يروح للباترونست. ولو عايز تعشيق أحسن، ابعت الطلب عادي.</div>
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> إرسال الطلب للباترونست</button>
      <div class="fa-end">
        <a href="{{ route('markers.requests') }}" class="btn btn-outline-secondary">إلغاء</a>
      </div>
    </div>
  </div>
</form>

<style>
  .mk-sug{border:1px solid var(--lv-line);border-radius:var(--r-md);background:var(--lv-white);padding:10px 12px;min-width:260px;flex:1 1 260px;max-width:360px}
  .mk-sug .mk-code{font-weight:700;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
</style>

@push('scripts')
<script>
/* مقاسات طلب الماركر: صف لكل (موديل + مقاس). الموديل اللي اتشال من فوق صفوفه بتتشال،
   والجديد بياخد صف بمقاسه الأول. المقاسات من مقاسات الموديل نفسه، ولو ملهوش ⇒ كل المقاسات. */
window.LVMS = (function () {
  const MODEL_SIZES = @json($modelSizes ?? []), ALL = @json($allSizes ?? []);
  const OLD = @json(array_values(old('lines', [])));
  let n = 0;
  const body = () => document.querySelector('#mrSizes tbody');
  const picked = () => [...document.querySelectorAll('#modelsBox .mp-chip')].map(c => ({id: c.dataset.id, label: c.childNodes[0].textContent.trim()}));
  const sizesOf = id => (MODEL_SIZES[id] && MODEL_SIZES[id].length) ? MODEL_SIZES[id] : ALL;
  function row(m, sizeId) {
    const i = n++, tr = document.createElement('tr');
    tr.dataset.model = m.id;
    tr.innerHTML = '<td class="fw-bold">' + LVX.esc(m.label) + '<input type="hidden" name="lines[' + i + '][product_model_id]" value="' + m.id + '"></td>'
      + '<td><select name="lines[' + i + '][size_id]" required aria-label="المقاس — ' + LVX.esc(m.label) + '"><option value="">— المقاس —</option>'
      + sizesOf(m.id).map(s => '<option value="' + s[0] + '"' + (String(s[0]) === String(sizeId) ? ' selected' : '') + '>' + LVX.esc(s[1]) + '</option>').join('')
      + '</select></td>'
      + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-plum" onclick="LVMS.more(this)" title="مقاس تاني لنفس الموديل"><i class="bi bi-plus-lg" aria-hidden="true"></i> مقاس</button> '
      + '<button type="button" class="btn btn-sm btn-outline-danger btn-icon" onclick="LVMS.drop(this)" aria-label="شيل المقاس" title="شيل"><i class="bi bi-x" aria-hidden="true"></i></button></td>';
    return tr;
  }
  function sync() {
    const want = picked(), b = body(); if (!b) return;
    [...b.querySelectorAll('tr')].forEach(tr => { if (!want.find(w => w.id === tr.dataset.model)) tr.remove(); });
    want.forEach(m => {
      if (b.querySelector('tr[data-model="' + m.id + '"]')) return;
      const olds = OLD.filter(o => String(o.product_model_id) === String(m.id));
      if (olds.length) olds.forEach(o => b.appendChild(row(m, o.size_id)));
      else {
        const only = sizesOf(m.id);
        b.appendChild(row(m, only.length === 1 ? only[0][0] : null));   // مقاس واحد بس؟ بيتختار لوحده
      }
    });
    document.getElementById('mrSizesEmpty').hidden = !!b.querySelector('tr');
  }
  function more(btn) {
    const tr = btn.closest('tr'), m = {id: tr.dataset.model, label: tr.querySelector('td').childNodes[0].textContent.trim()};
    tr.after(row(m, null));
  }
  function drop(btn) {
    const tr = btn.closest('tr'), id = tr.dataset.model;
    if (body().querySelectorAll('tr[data-model="' + id + '"]').length > 1) tr.remove();   // آخر مقاس للموديل بيتشال مع الموديل نفسه من فوق
  }
  document.addEventListener('DOMContentLoaded', () => { sync(); new MutationObserver(sync).observe(document.querySelector('#modelsBox .mp-chips'), {childList: true}); });
  return {sync, more, drop};
})();
window.LVM = (function () {
  const sel = document.querySelector('[name=consignment_id]');
  let timer = null;
  function pick() {
    const o = sel.value ? sel : null;
    document.getElementById('fw').textContent = o && o.dataset.w ? o.dataset.w : '—';
    document.getElementById('fg').textContent = o && o.dataset.g ? o.dataset.g : '—';
    const bw = document.getElementById('breakWarn');
    bw.hidden = !(o && o.dataset.break === '1');
    if (!bw.hidden) bw.textContent = 'الرسالة دي فيها فواصل / اختلاف لون: ' + (o.dataset.note || '') + ' — خد بالك في التعشيق.';
    suggest();
  }
  function filter() {}
  function suggest() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const cid = sel.value;
      const models = [...document.querySelectorAll('#modelsBox input[type=hidden]')].map(i => i.value);
      const box = document.getElementById('suggestBox'), list = document.getElementById('suggestList');
      if (!cid) { box.hidden = true; return; }
      const qs = new URLSearchParams({consignment_id: cid}); models.forEach(m => qs.append('models[]', m));
      const res = await fetch('{{ route('markers.suggest') }}?' + qs.toString(), {headers: {'Accept': 'application/json'}});
      if (!res.ok) { box.hidden = true; return; }
      const d = await res.json();
      if (!d.length) { box.hidden = true; return; }
      list.innerHTML = d.map(m => `
        <div class="mk-sug">
          <div class="mk-code num">${m.code} ${m.exact ? '<span class="badge bg-success">نفس العرض</span>' : '<span class="badge bg-warning">عرض ' + m.width + ' سم</span>'}</div>
          <div class="hint">فرشة ${m.spread} م · ${m.pieces} قطعة${m.efficiency !== null ? ' · كفاءة ' + m.efficiency + '%' : ''}</div>
          <div class="hint">${m.models}</div>
          <div class="d-flex gap-1 mt-2">
            <button type="button" class="btn btn-sm btn-success" onclick="LVM.use(${m.id}, '${m.code}')"><i class="bi bi-check2" aria-hidden="true"></i> استخدم الماركر ده</button>
            <a href="${m.url}" target="_blank" class="btn btn-sm btn-outline-secondary">افتحه</a>
          </div>
        </div>`).join('');
      box.hidden = false;
    }, 250);
  }
  function use(id, code) {
    if (!confirm('الطلب هيتقفل على الماركر ' + code + ' من غير ما يروح للباترونست. تمام؟')) return;
    document.getElementById('useMarker').value = id;
    document.getElementById('mrForm').submit();
  }
  document.addEventListener('DOMContentLoaded', pick);
  return {pick, filter, suggest, use};
})();
</script>
@endpush
@endsection
