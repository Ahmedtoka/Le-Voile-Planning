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
      <a href="{{ route('markers.requests') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="note-box mb-3">
        بتقول للباترونست: «محتاج الموديلات دي على الرسالة دي». <b>العرض (أقل عرض من الفحص) والبنشر (من المعمل)</b>
        بينزلوا لوحدهم ومش بيتعدلوا. المصنع مش هنا — بيتحدد في أمر الشغل وممكن يتغير.
        لو فيه ماركر قديم لنفس الموديلات على نفس العرض، السيستم هيقترحه وتقدر تستخدمه على طول.
      </div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label req">الرسالة (الحوض)</label>
          <input class="form-control form-control-sm mb-1" placeholder="فلتر: كود / خامة / لون" oninput="LVM.filter(this.value)">
          <select name="consignment_id" id="cn" class="form-select form-select-sm" required onchange="LVM.pick()">
            <option value="">— اختر رسالة —</option>
            @foreach($consignments as $c)
              <option value="{{ $c->id }}" data-w="{{ $c->min_width_cm }}" data-g="{{ $c->avg_gsm }}" data-break="{{ $c->has_color_break ? 1 : 0 }}"
                      data-note="{{ $c->color_break_note }}"
                      data-search="{{ strtolower($c->consignment_no . ' ' . ($c->fabricType?->name ?? '') . ' ' . ($c->color?->code ?? '') . ' ' . ($c->color?->name ?? '')) }}"
                      @selected(old('consignment_id', $row->consignment_id) == $c->id)>
                {{ $c->consignment_no }} — {{ $c->fabricType?->name }} · {{ $c->color?->code }}
                (متاح {{ rtrim(rtrim(number_format((float)$c->remaining_kg,1),'0'),'.') }} كجم)
              </option>
            @endforeach
          </select>
          <div id="breakWarn" class="alert alert-danger py-1 mt-1 small" hidden></div>
        </div>
        <div class="col-md-2"><label class="form-label">أقل عرض (سم)</label>
          <div class="form-control form-control-sm num fw-bold" id="fw" style="background:var(--lv-offwhite)">—</div>
          <div class="hint">من تقرير الفحص — مقفول</div></div>
        <div class="col-md-2"><label class="form-label">البنشر (جم/م²)</label>
          <div class="form-control form-control-sm num fw-bold" id="fg" style="background:var(--lv-offwhite)">—</div>
          <div class="hint">من تقرير المعمل — مقفول</div></div>
        <div class="col-md-2"><label class="form-label">نسخ الماركر المطبوعة</label>
          <input type="number" min="0" name="copies" class="form-control form-control-sm" value="{{ old('copies', $row->copies ?? 2) }}"></div>

        <div class="col-12">
          <label class="form-label req">الموديلات المطلوبة في الفرشة <span class="hint">— علّم كل الموديلات اللي عايز الماركر يجمعها</span></label>
          <div id="modelsBox">
            @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', $picked ?? []), 'onchange' => 'LVM.suggest()'])
          </div>
        </div>
        <div class="col-md-6"><label class="form-label">المقاسات / تفاصيل</label>
          <input name="sizes_note" class="form-control form-control-sm" value="{{ old('sizes_note') }}" placeholder="مثال: مقاس 1 و 2 — نص بادي كات + بادي سابرينا"></div>
        <div class="col-md-3"><label class="form-label">الباترونست</label>
          <select name="assigned_to" class="form-select form-select-sm"><option value="">— كل الباترونستات —</option>
            @foreach($patternists as $k=>$v)<option value="{{ $k }}" @selected(old('assigned_to')==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">مطلوب في</label>
          <input type="date" name="needed_by" class="form-control form-control-sm" value="{{ old('needed_by') }}"></div>
        <div class="col-12"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes') }}"></div>
      </div>

      {{-- الماركرات القديمة المقترحة --}}
      <div id="suggestBox" class="mt-3" hidden>
        <div class="section-head"><h2 style="font-size:.95rem">ماركرات قديمة ممكن تنفع <span class="hint">— نفس الموديلات على نفس العرض أو قريب منه</span></h2></div>
        <div id="suggestList" class="d-flex flex-wrap gap-2"></div>
        <div class="hint mt-1">لو اخترت واحد، الطلب بيتقفل عليه على طول من غير ما يروح للباترونست. ولو عايز تعشيق أحسن، ابعت الطلب عادي.</div>
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> إرسال الطلب للباترونست</button>
    </div>
  </div>
</form>

@push('scripts')
<script>
window.LVM = (function () {
  const sel = document.getElementById('cn');
  let timer = null;
  function pick() {
    const o = sel.selectedOptions[0];
    document.getElementById('fw').textContent = o && o.dataset.w ? o.dataset.w : '—';
    document.getElementById('fg').textContent = o && o.dataset.g ? o.dataset.g : '—';
    const bw = document.getElementById('breakWarn');
    bw.hidden = !(o && o.dataset.break === '1');
    if (!bw.hidden) bw.textContent = 'الرسالة دي فيها فواصل / اختلاف لون: ' + (o.dataset.note || '') + ' — خد بالك في التعشيق.';
    suggest();
  }
  function filter(v) {
    v = (v || '').toLowerCase().trim();
    [...sel.options].forEach(o => { if (!o.value) return; o.hidden = v && !o.dataset.search.includes(v); });
  }
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
        <div class="p-2 rounded" style="border:1px solid var(--lv-line);min-width:260px;background:#fff">
          <div class="fw-bold num">${m.code} ${m.exact ? '<span class="badge bg-success">نفس العرض</span>' : '<span class="badge bg-warning text-dark">عرض ' + m.width + '</span>'}</div>
          <div class="hint">فرشة ${m.spread} م · ${m.pieces} قطعة${m.efficiency !== null ? ' · كفاءة ' + m.efficiency + '%' : ''}</div>
          <div class="hint">${m.models}</div>
          <div class="d-flex gap-1 mt-1">
            <button type="button" class="btn btn-sm btn-success py-0" onclick="LVM.use(${m.id}, '${m.code}')">استخدم الماركر ده</button>
            <a href="${m.url}" target="_blank" class="btn btn-sm btn-outline-secondary py-0">افتحه</a>
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
