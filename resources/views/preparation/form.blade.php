@extends('layouts.app')
@section('purpose', 'اختار الرسالة واكتب الكمية التقريبية — المخزن هيوزن ويرد بالفعلي')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'prep'])

<form method="post" action="{{ route('preparation-requests.store') }}">@csrf
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>طلب تحضير جديد <span class="hint">— التاريخ بياخد تاريخ اليوم</span></span>
      <a href="{{ route('preparation-requests.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req">الرسالة (الحوض)</label>
          <input class="form-control form-control-sm mb-1" id="cnFilter" placeholder="فلتر: اكتب جزء من الكود أو الخامة أو اللون (مثال 0805)"
                 oninput="LVP.filter(this.value)">
          <select name="consignment_id" id="cn" class="form-select form-select-sm" required onchange="LVP.pick()">
            <option value="">— اختر رسالة —</option>
            @foreach($consignments as $c)
              <option value="{{ $c->id }}" data-rem="{{ $c->remaining_kg }}" data-break="{{ $c->has_color_break ? 1 : 0 }}"
                      data-search="{{ strtolower($c->consignment_no . ' ' . ($c->fabricType?->name ?? '') . ' ' . ($c->color?->code ?? '') . ' ' . ($c->color?->name ?? '')) }}"
                      @selected(old('consignment_id', $pick?->id) == $c->id)>
                {{ $c->consignment_no }} — {{ $c->fabricType?->name }} · {{ $c->color?->code }}
                (متاح {{ rtrim(rtrim(number_format((float)$c->remaining_kg,1),'0'),'.') }} كجم · وصل {{ $c->arrival_date?->format('Y-m-d') }})
              </option>
            @endforeach
          </select>
          <div class="hint">مرتبة بالأقدم أولًا — القديم يخرج الأول. المتاح = المفرج عنه ناقص أوامر الشغل والطلبات المفتوحة.</div>
          <div id="breakWarn" class="alert alert-danger py-1 mt-1 small" hidden>الرسالة دي فيها فواصل / اختلاف لون — خد بالك في الماركر والقص.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label req">الكمية المطلوبة (كجم تقريبي)</label>
          <input type="number" step="0.001" min="0" name="requested_qty" id="qty" class="form-control form-control-sm"
                 value="{{ old('requested_qty') }}" placeholder="مثال: 200">
          <div class="hint">المتاح: <b class="num" id="remLbl">—</b> كجم</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">ولا الباقي كله؟</label>
          <div class="form-check mt-1">
            <input type="hidden" name="is_remainder" value="0">
            <input class="form-check-input" type="checkbox" name="is_remainder" value="1" id="rem" @checked(old('is_remainder'))
                   onchange="document.getElementById('qty').disabled = this.checked">
            <label class="form-check-label" for="rem">آخر تقسيمة — خد الباقي من غير وزن</label>
          </div>
          <div class="hint">الفرق بالزيادة أو النقص على الرسالة</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">المصنع (اختياري)</label>
          <select name="factory_id" class="form-select form-select-sm"><option value="">— يتحدد في أمر الشغل —</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id')==$k)>{{ $v }}</option>@endforeach
          </select>
          <div class="hint">ممكن يتغير بعدين — مش بيأثر على الوزن</div>
        </div>
        <div class="col-md-8">
          <label class="form-label">ملاحظات للمخزن</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes') }}" placeholder="مثال: محتاجه بكرة الصبح">
        </div>

        <div class="col-12">
          <label class="form-label">الموديلات اللي هتتشغل من الكمية دي <span class="hint">(للمخزن على سبيل العلم، وبتتنقل لطلب الماركر وأمر الشغل)</span></label>
          @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', [])])
          <input name="models_note" class="form-control form-control-sm mt-1" value="{{ old('models_note') }}" placeholder="أو اكتب الموديلات نصًا">
        </div>

        <div class="col-12">
          <div class="form-check">
            <input type="hidden" name="also_marker" value="0">
            <input class="form-check-input" type="checkbox" name="also_marker" value="1" id="am" checked>
            <label class="form-check-label" for="am"><b>واطلب الماركر من الباترونست في نفس الوقت</b> — طلب الماركر بيتعمل لوحده مع الحفظ بنفس الرسالة والموديلات (العرض والبنشر من الجودة)</label>
          </div>
        </div>
      </div>

      @if($openOnPick->count())
        <div class="alert alert-warning py-2 mt-3 small mb-0">
          فيه {{ $openOnPick->count() }} طلب تحضير مفتوح على الرسالة دي:
          @foreach($openOnPick as $o)
            <span class="badge bg-light text-dark">{{ $o->doc_no }} · {{ $o->is_remainder ? 'الباقي' : rtrim(rtrim(number_format((float)$o->requested_qty,1),'0'),'.') . ' كجم' }}{{ $o->factory ? ' · ' . $o->factory->name : '' }}</span>
          @endforeach
        </div>
      @endif
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> اطلب من المخزن</button>
      <span class="hint">الكمية بتتحجز على الرسالة فورًا، والمخزن يوصله إشعار يوزن ويرد.</span>
    </div>
  </div>
</form>

@push('scripts')
<script>
window.LVP = (function () {
  const sel = document.getElementById('cn');
  function pick() {
    const o = sel.selectedOptions[0];
    document.getElementById('remLbl').textContent = o && o.dataset.rem ? Number(o.dataset.rem).toLocaleString('en-US', {maximumFractionDigits: 3}) : '—';
    document.getElementById('breakWarn').hidden = !(o && o.dataset.break === '1');
  }
  function filter(v) {
    v = (v || '').toLowerCase().trim();
    [...sel.options].forEach(o => { if (!o.value) return; o.hidden = v && !o.dataset.search.includes(v); });
  }
  document.addEventListener('DOMContentLoaded', pick);
  return {pick, filter};
})();
</script>
@endpush
@endsection
