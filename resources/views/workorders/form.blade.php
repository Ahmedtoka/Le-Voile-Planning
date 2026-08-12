@extends('layouts.app')
@section('content')
<form method="post" action="{{ $mode==='create' ? route('work-orders.store') : route('work-orders.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>{{ $mode==='create' ? 'أمر شغل جديد' : 'تعديل أمر الشغل ' . $row->wo_no }}</span>
          <a href="{{ route('work-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label req">التاريخ</label>
              <input type="date" name="wo_date" class="form-control form-control-sm" value="{{ old('wo_date',$row->wo_date?->format('Y-m-d') ?? $row->wo_date) }}" required></div>

            <div class="col-md-9"><label class="form-label req">الحوض (الرسالة)</label>
              <select name="consignment_id" id="cn" class="form-select form-select-sm" required onchange="recalc()">
                <option value="">— اختر حوض معتمد —</option>
                @foreach($consignments as $c)
                  <option value="{{ $c->id }}" data-w="{{ $c->min_width_cm }}" data-g="{{ $c->avg_gsm }}"
                          data-rem="{{ $c->remaining_kg }}" @selected(old('consignment_id',$row->consignment_id)==$c->id)>
                    {{ $c->consignment_no }} — {{ $c->color?->code }} · أقل عرض {{ $c->min_width_cm }} · بنشر {{ $c->avg_gsm }} · متبقي {{ number_format((float)$c->remaining_kg,1) }} كجم
                  </option>
                @endforeach
              </select>
              @if(!count($consignments))
                <div class="hint text-danger">مفيش أحواض جاهزة — لازم الحوض يتفحص ويجيله تقرير معمل ويتعمد الأول.</div>
              @endif
            </div>

            <div class="col-md-6"><label class="form-label req">الماركر</label>
              <select name="marker_id" id="mk" class="form-select form-select-sm" required onchange="recalc()">
                <option value="">— اختر ماركر معتمد —</option>
                @foreach($markers as $m)
                  <option value="{{ $m->id }}" data-w="{{ $m->marker_width_cm ?: $m->fabric_width_cm }}"
                          data-sl="{{ $m->spread_length_m }}" data-pp="{{ $m->pieces_per_spread }}"
                          @selected(old('marker_id',$row->marker_id)==$m->id)>
                    {{ $m->code }} — عرض {{ $m->fabric_width_cm }} · فرشة {{ $m->spread_length_m }}م · {{ $m->pieces_per_spread }} قطعة
                  </option>
                @endforeach
              </select></div>

            <div class="col-md-3"><label class="form-label req">المصنع</label>
              <select name="factory_id" class="form-select form-select-sm" required><option value="">—</option>
                @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id',$row->factory_id)==$k)>{{ $v }}</option>@endforeach
              </select></div>

            <div class="col-md-3"><label class="form-label">تاريخ التسليم</label>
              <input type="date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date',$row->due_date?->format('Y-m-d')) }}"></div>

            <div class="col-md-3"><label class="form-label req">الكمية المخصصة (كجم)</label>
              <input type="number" step="0.001" name="allocated_kg" id="kg" class="form-control form-control-sm"
                     value="{{ old('allocated_kg',$row->allocated_kg) }}" required onchange="recalc()" oninput="recalc()"></div>
            <div class="col-md-3"><label class="form-label">عدد الأتواب</label>
              <input type="number" name="allocated_rolls" class="form-control form-control-sm" value="{{ old('allocated_rolls',$row->allocated_rolls) }}"></div>
            <div class="col-md-6"><label class="form-label">ملاحظات</label>
              <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
          </div>
        </div>
        <div class="card-footer bg-white">
          <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ أمر الشغل</button>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="calc-box" id="calcBox">
        <div class="fw-bold mb-2"><i class="bi bi-calculator"></i> الحسبة المتوقعة</div>
        <div id="calcBody"><div class="hint">اختار الحوض والماركر والكمية.</div></div>
        <div class="hint mt-2">
          كل الأرقام دي متوقّعة — مبنية على متوسطات عيّنة فحص. الفعلي بييجي من المصنع، والفرق الطبيعي 2-4%.
        </div>
      </div>
      <div id="warnBox" class="mt-2"></div>
    </div>
  </div>
</form>

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function num(v, d = 2) {
  return v === null || v === undefined ? '—' : Number(v).toLocaleString('en-US', {maximumFractionDigits: d});
}

async function recalc() {
  const cn = document.getElementById('cn').value;
  const mk = document.getElementById('mk').value;
  const kg = document.getElementById('kg').value;
  if (!cn || !mk || !kg) return;

  const res = await fetch('{{ route('work-orders.calc') }}', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
    body: JSON.stringify({consignment_id: cn, marker_id: mk, allocated_kg: kg})
  });
  if (!res.ok) return;
  const d = await res.json();

  const body = document.getElementById('calcBody');
  if (!d.ok) {
    body.innerHTML = '<div class="text-danger small">' + (d.errors || []).join('<br>') + '</div>';
  } else {
    body.innerHTML = `
      <div class="kv"><span>وزن الرِقّة</span><b>${num(d.ply_weight_kg, 3)} كجم</b></div>
      <div class="kv"><span>استهلاك القطعة</span><b>${num(d.g_per_piece, 1)} جم</b></div>
      <div class="kv"><span>عدد الرِقّات من التوب</span><b>${d.plies_per_roll ?? '—'}</b></div>
      <div class="kv"><span>الفاقد في التوب</span><b>${num(d.waste_per_roll_m, 2)} م</b></div>
      <div class="kv"><span>إجمالي الفرشات</span><b>${num(d.expected_plies, 0)}</b></div>
      <div class="kv"><span>القطع المتوقعة</span><b class="text-success">${num(d.expected_pieces, 0)}</b></div>`;
  }

  const wb = document.getElementById('warnBox');
  wb.innerHTML = (d.warnings || []).map(w =>
    `<div class="alert alert-${w.level === 'danger' ? 'danger' : 'warning'} py-2 small mb-2">${w.text}</div>`
  ).join('');
}

document.addEventListener('DOMContentLoaded', recalc);
</script>
@endpush
@endsection
