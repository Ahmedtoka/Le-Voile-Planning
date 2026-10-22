@extends('layouts.app')
@section('purpose', 'اختار الرسالة واكتب الكمية التقريبية والموديلات — المخزن هيوزن ويرد بالفعلي، وطلب الماركر بيتعمل معاه')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'prep'])

<form method="post" action="{{ route('preparation-requests.store') }}">@csrf
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>طلب تحضير جديد <span class="hint">— التاريخ بياخد تاريخ اليوم</span></span>
      <a href="{{ route('preparation-requests.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        {{-- ① الرسالة — خانة بحث واحدة --}}
        <div class="col-12">
          <label class="form-label req">الرسالة (الحوض)</label>
          @include('partials.consignment_picker', ['name' => 'consignment_id', 'consignments' => $consignments, 'value' => old('consignment_id', $pick?->id), 'onchange' => 'LVP.pick()'])
        </div>

        {{-- ② الكمية --}}
        <div class="col-md-4">
          <label class="form-label req">الكمية المطلوبة</label>
          <div class="input-group">
            <input type="number" step="0.001" min="0" name="requested_qty" id="qty" class="form-control" value="{{ old('requested_qty') }}" placeholder="تقريبي — مثال: 200" oninput="LVP.calc()">
            <span class="input-group-text">كجم</span>
          </div>
          <div class="hint">المتاح على الرسالة: <b class="num" id="remLbl">—</b></div>
          <div id="leftLbl" class="small mt-1 fw-bold" hidden></div>
        </div>
        <div class="col-md-8">
          <label class="form-label">ولا الباقي كله؟</label>
          <div class="form-check mt-1">
            <input type="hidden" name="is_remainder" value="0">
            <input class="form-check-input" type="checkbox" name="is_remainder" value="1" id="rem" @checked(old('is_remainder'))
                   onchange="document.getElementById('qty').disabled = this.checked">
            <label class="form-check-label" for="rem">آخر تقسيمة — خد الباقي من غير وزن</label>
          </div>
          <div class="hint">الفرق بالزيادة أو النقص على الرسالة</div>
        </div>

        {{-- ③ الموديلات — بحث، ولو مش موجود ضيفه --}}
        <div class="col-12">
          <label class="form-label req">الموديلات اللي هتتشغل من الكمية دي <span class="hint">— اكتب كود أو اسم، ولو الموديل مش موجود ضيفه من نفس المكان</span></label>
          @include('partials.model_picker', ['name' => 'product_model_ids', 'models' => $models, 'picked' => old('product_model_ids', []), 'allowNew' => true])
        </div>

        {{-- ④ المصنع والملاحظات في الآخر --}}
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
            <label class="form-check-label" for="am"><b>واطلب الماركر من الباترونست في نفس الوقت</b> — بيتعمل لوحده مع الحفظ بنفس الرسالة والموديلات (العرض والبنشر من الجودة)</label>
          </div>
        </div>
      </div>

      @if($openOnPick->count())
        <div class="alert alert-warning py-2 mt-3 small mb-0">
          فيه {{ $openOnPick->count() }} طلب تحضير مفتوح على الرسالة دي:
          @foreach($openOnPick as $o)
            <span class="badge bg-light text-dark">{{ $o->doc_no }} · {{ $o->is_remainder ? 'الباقي' : lv_kg($o->requested_qty, false) }}{{ $o->factory ? ' · ' . $o->factory->name : '' }}</span>
          @endforeach
        </div>
      @endif
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> اطلب من المخزن</button>
      <span class="hint">الكمية بتتحجز على الرسالة فورًا، والمخزن يوصله إشعار يوزن ويرد.</span>
      <div class="fa-end">
        <a href="{{ route('preparation-requests.index') }}" class="btn btn-outline-secondary">إلغاء</a>
      </div>
    </div>
  </div>
</form>

@push('scripts')
<script>
window.LVP = (function () {
  var nf = v => Number(v).toLocaleString('en-US', {maximumFractionDigits: 1});
  function rem() { var v = document.querySelector('[name=consignment_id]'); return v && v.value && v.dataset.rem !== undefined ? Number(v.dataset.rem) : null; }
  function pick() {
    var r = rem();
    document.getElementById('remLbl').textContent = r !== null ? nf(r) + ' كجم' : '—';
    calc();
  }
  /* مع كل رقم تكتبه: هيفضل على الرسالة كام بعد الطلب ده — و ⓘ بالحسبة */
  function calc() {
    var out = document.getElementById('leftLbl'), q = Number(document.getElementById('qty').value) || 0, r = rem();
    if (r === null || !q) { out.hidden = true; return; }
    var left = r - q;
    out.hidden = false;
    var fx = LVX.fx('الباقي على الرسالة', ['الباقي = المتاح − المطلوب', 'المتاح ' + nf(r) + ' − المطلوب ' + nf(q) + ' = ' + nf(left) + ' كجم'],
                    'الكمية بتتحجز فورًا — والمخزن يرد بالوزن الفعلي');
    if (left < -0.0005) {
      out.className = 'small mt-1 fw-bold text-danger';
      out.innerHTML = LVX.esc('أكتر من المتاح بـ ' + nf(-left) + ' كجم — قلّل الكمية أو اختار «الباقي كله»') + fx;
    } else {
      out.className = 'small mt-1 fw-bold ' + (left < 0.0005 ? 'text-warning' : 'text-success');
      out.innerHTML = LVX.esc('هيفضل على الرسالة بعد الطلب ده: ' + nf(left) + ' كجم' + (left < 0.0005 ? ' — الرسالة هتخلص' : '')) + fx;
    }
    LVX.popovers(out);
  }
  document.addEventListener('DOMContentLoaded', pick);
  return {pick, calc};
})();
</script>
@endpush
@endsection
