@extends('layouts.app')
@section('content')
<form method="post" action="{{ route('markers.requests.store') }}">@csrf
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>طلب ماركر جديد</span>
      <a href="{{ route('markers.requests') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="note-box mb-3">
        بتقول للباترونست: "محتاج موديل كذا عند مصنع كذا على عرض كذا"، وهو بيدخل بنفسه ويرفع الماركر
        بطول الفرشة وعدد القطع فيها.
        <b>مهم:</b> العرض هنا لازم يكون <b>أقل عرض</b> في الحوض، مش المتوسط.
      </div>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date', now()->toDateString()) }}" required></div>
        <div class="col-md-4"><label class="form-label">الحوض</label>
          <select name="consignment_id" id="cn" class="form-select form-select-sm" onchange="fillWidth()">
            <option value="">— بدون —</option>
            @foreach($consignments as $c)
              <option value="{{ $c->id }}" data-w="{{ $c->min_width_cm }}" @selected(old('consignment_id')==$c->id)>
                {{ $c->consignment_no }} — {{ $c->color?->code }} @if($c->min_width_cm)(أقل عرض {{ $c->min_width_cm }}){{-- --}}@endif
              </option>
            @endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">المصنع</label>
          <select name="factory_id" class="form-select form-select-sm"><option value="">—</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id')==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label req">عرض القماش (سم)</label>
          <input type="number" step="0.01" name="fabric_width_cm" id="fw" class="form-control form-control-sm" value="{{ old('fabric_width_cm') }}" required></div>
        <div class="col-12"><label class="form-label req">الموديلات المطلوبة</label>
          <textarea name="requested_models" rows="3" class="form-control form-control-sm" required
            placeholder="مثال: نص بادي كات + بادي سابرينا مقاس 1 و 2 — عايز الفرشة تجمع أكتر من موديل">{{ old('requested_models') }}</textarea></div>
        <div class="col-md-3"><label class="form-label">الباترونست</label>
          <select name="assigned_to" class="form-select form-select-sm"><option value="">—</option>
            @foreach($patternists as $k=>$v)<option value="{{ $k }}" @selected(old('assigned_to')==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-3"><label class="form-label">مطلوب في</label>
          <input type="date" name="needed_by" class="form-control form-control-sm" value="{{ old('needed_by') }}"></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes') }}"></div>
      </div>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-plum btn-sm">إرسال الطلب</button></div>
  </div>
</form>
@push('scripts')
<script>
function fillWidth(){
  const o = document.getElementById('cn').selectedOptions[0];
  if (o && o.dataset.w) document.getElementById('fw').value = o.dataset.w;
}
</script>
@endpush
@endsection
