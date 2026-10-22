@extends('layouts.app')
@section('purpose', 'الفعلي من المصنع: طول الفرشة والقطع — والسيستم بيحسب الانحراف عن المتوقع')
@section('content')
@php
  $lines    = old('lines', $row->lines?->toArray() ?? []);
  $editable = $row->isEditable() || $mode==='create';
  $gov      = $wo->governingFabric();
  $plannedM = $gov?->effective_spread;
  $target   = (int) $wo->target_qty;
  $n        = fn ($v, $d = 3) => lv_num($v, $d);
  /* الانحراف تراكمي: القص المعتمد قبل البيان ده + البيان ده مقابل المستهدف (نفس CutDeclarationController::recompute) */
  $before   = (int) \App\Models\CutDeclaration::where('work_order_id', $wo->id)
                ->when($row->id, fn ($q) => $q->where('id', '!=', $row->id))
                ->where('status', 'approved')->sum('total_pieces');
  $cumHere  = $before + (int) ($row->total_pieces ?? 0);
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'cut'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    <b>طول الفرشة الفعلي هو أهم رقم هنا.</b>
    المخطط كان <b class="num">{{ $plannedM ? $n($plannedM) : '—' }}</b> متر. لو المصنع فرش على أكتر — ولو 5 سنتيمتر —
    بياكل من كل رِقّة ويقلل عدد الرِقّات، وعدد القطع بينزل. سجّل الرقم الفعلي زي ما هو.
  </span>
</div>

<form method="post" action="{{ $mode==='create' ? route('cut-declarations.store') : route('cut-declarations.update',$row) }}">
  @csrf @if($mode==='edit') @method('PUT') @endif
  <input type="hidden" name="work_order_id" value="{{ $wo->id }}">
  <input type="hidden" name="factory_id" value="{{ $wo->factory_id }}">

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ $title }}</span>
      <div class="d-flex gap-2">
        @if($mode==='edit')<span class="badge bg-{{ $row->status_color }} align-self-center">{{ $row->status_label }}</span>@endif
        <a href="{{ route('work-orders.show',$wo) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> أمر الشغل</a>
      </div>
    </div>
    <div class="card-body">
      {{-- اللي السيستم عارفه من أمر الشغل — للقراءة --}}
      <div class="ro-box mb-3">
        <div class="ro-title">من أمر الشغل <a href="{{ route('work-orders.show',$wo) }}" class="num">{{ $wo->wo_no }}</a> — للقراءة</div>
        <div class="sum-grid">
          <div><div class="k">المنتج</div><div class="v">{{ $wo->product_title ?: '—' }}</div></div>
          <div><div class="k">المصنع</div><div class="v">{{ $wo->factory?->name ?? '—' }}</div></div>
          <div><div class="k">طول الفرشة المخطط</div><div class="v num">{{ $plannedM ? $n($plannedM) . ' م' : '—' }}</div></div>
          <div><div class="k">قطع الفرشة</div><div class="v num">{{ $gov?->pieces_per_spread ?? '—' }}</div></div>
          <div><div class="k">الرِقّات المخططة</div><div class="v num">{{ $gov?->plies ?? $gov?->calc_plies ?? '—' }}</div></div>
          <div><div class="k">المستهدف</div><div class="v num">{{ number_format($target) }} قطعة</div></div>
          <div><div class="k">اتقص قبل كده (معتمد)</div><div class="v num">{{ number_format($before) }} قطعة</div></div>
        </div>
      </div>

      <fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-3"><label class="form-label req">طول الفرشة الفعلي</label>
          <div class="input-group">
            <input type="number" step="0.001" min="0" name="actual_spread_length_m" id="actSp" class="form-control"
                   value="{{ old('actual_spread_length_m',$row->actual_spread_length_m) }}" required
                   placeholder="مثال: {{ $plannedM ? $n($plannedM) : '3.07' }}" oninput="LVC.dev()">
            <span class="input-group-text">م</span>
          </div>
          <div class="hint" id="spDev" data-planned="{{ $plannedM ?? '' }}">المخطط: {{ $plannedM ? $n($plannedM) . ' م' : '—' }}</div></div>
        <div class="col-md-2"><label class="form-label">عدد الرِقّات الفعلي</label>
          <div class="input-group">
            <input type="number" min="0" name="actual_plies" class="form-control" value="{{ old('actual_plies',$row->actual_plies) }}" placeholder="مثال: {{ $gov?->plies ?? $gov?->calc_plies ?? 149 }}">
            <span class="input-group-text">رِقّة</span>
          </div></div>
        <div class="col-md-2"><label class="form-label">القماش المستهلك</label>
          <div class="input-group">
            <input type="number" step="0.001" min="0" name="used_kg" class="form-control" value="{{ old('used_kg',$row->used_kg) }}" placeholder="مثال: 152.5">
            <span class="input-group-text">كجم</span>
          </div></div>
        <div class="col-md-3"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="اختياري"></div>

        <div class="col-12">
          <label class="form-label">سبب الفرق <span class="hint">— إجباري لو الانحراف خارج الحدود (فوق {{ (float) config('lvplanning.variance.warn_pct', 4) }}%)</span></label>
          <textarea name="variance_reason" rows="2" class="form-control"
            placeholder="مثال: القماش كان فيه جزء عريض واتعمل له تعشيقة تانية">{{ old('variance_reason',$row->variance_reason) }}</textarea>
        </div>
      </div>
      </fieldset>
    </div>

    @if($mode==='edit')
      @php $vTone = ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$row->variance_flag] ?? 'muted'; @endphp
      <div class="card-footer bg-white">
        <div class="big-nums justify-content-around">
          <div><div class="hint">المستهدف</div><div class="bn-v num">{{ number_format($target) }}</div></div>
          <div><div class="hint">المقصوص في البيان ده</div><div class="bn-v num">{{ number_format((int)$row->total_pieces) }}</div></div>
          <div><div class="hint">الانحراف
              @if($row->variance_pct !== null && $target > 0)
                @include('partials.fx', ['t' => 'الانحراف',
                  'f' => ['الانحراف % = (الفعلي − المتوقع) ÷ المتوقع × 100',
                          '(' . number_format($cumHere) . ' − ' . number_format($target) . ') ÷ ' . number_format($target) . ' × 100 = ' . $row->variance_pct . '%'],
                  'why' => 'الفعلي تراكمي: المعتمد قبل كده ' . number_format($before) . ' + البيان ده ' . number_format((int) $row->total_pieces) . ' · ≤2% أخضر · ≤4% أصفر · فوقها أحمر ولازم سبب'])
              @endif
            </div>
            <div class="bn-v num text-{{ $vTone }}">{{ $row->variance_pct !== null ? $row->variance_pct.'%' : '—' }}</div></div>
          <div><div class="hint">فرق طول الفرشة
              @if($row->spread_deviation_cm !== null)
                @include('partials.fx', ['t' => 'فرق طول الفرشة',
                  'f' => '(الفعلي ' . $n($row->actual_spread_length_m) . ' − المخطط ' . $n($plannedM) . ') × 100 = ' . $n($row->spread_deviation_cm, 2) . ' سم',
                  'why' => 'كل سنتيمتر زيادة بياكل من كل رِقّة — أكبر فاقد صامت'])
              @endif
            </div>
            <div class="bn-v num {{ $row->spread_deviation_cm !== null && abs($row->spread_deviation_cm) >= 5 ? 'text-danger' : '' }}">{{ $row->spread_deviation_cm !== null ? $n($row->spread_deviation_cm, 2) . ' سم' : '—' }}</div></div>
          <div><div class="hint">استهلاك القطعة الفعلي
              @if($row->actual_kg_per_piece)
                @include('partials.fx', ['t' => 'استهلاك القطعة الفعلي',
                  'f' => 'المستهلك ' . $n($row->used_kg) . ' كجم ÷ المقصوص ' . number_format((int) $row->total_pieces) . ' = ' . $n($row->actual_kg_per_piece, 5) . ' كجم = ' . $n((float) $row->actual_kg_per_piece * 1000, 1) . ' جم',
                  'why' => $gov?->consumption_per_piece ? 'المتوقع كان ' . $n((float) $gov->consumption_per_piece * 1000, 1) . ' جم' : null])
              @endif
            </div>
            <div class="bn-v num">{{ $row->actual_kg_per_piece ? $n((float)$row->actual_kg_per_piece*1000,1).' جم' : '—' }}</div></div>
        </div>
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header">الكميات المقصوصة <span class="hint">— المخطط من أمر الشغل، واكتب الفعلي لكل موديل</span></div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th>الموديل</th><th>المقاس</th><th>المخطط</th><th class="req" style="width:130px">المقصوص فعلي</th><th>ملاحظات</th></tr></thead>
        <tbody>
        @foreach($wo->lines as $i => $wl)
          @php
            $existing = collect($lines)->first(fn($x) =>
                ($x['product_model_id'] ?? null) == $wl->product_model_id && ($x['size_id'] ?? null) == $wl->size_id);
          @endphp
          <tr>
            <td>{{ $wl->productModel?->name }}
              <input type="hidden" name="lines[{{ $i }}][product_model_id]" value="{{ $wl->product_model_id }}"></td>
            <td>{{ $wl->size?->name ?? 'كل المقاسات' }}
              <input type="hidden" name="lines[{{ $i }}][size_id]" value="{{ $wl->size_id }}"></td>
            <td class="num">{{ number_format($wl->planned_qty) }}</td>
            <td class="num"><input type="number" min="0" name="lines[{{ $i }}][qty]" value="{{ $existing['qty'] ?? $wl->planned_qty }}" @disabled(!$editable) aria-label="المقصوص فعلي"></td>
            <td><input name="lines[{{ $i }}][notes]" value="{{ $existing['notes'] ?? '' }}" @disabled(!$editable) placeholder="اختياري" aria-label="ملاحظات"></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @if($editable || ($mode==='edit' && $row->isEditable()))
  <div class="form-actions">
  @if($editable)<button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ واحتساب الانحراف</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success" onclick="if(confirm('بيان القص هيتسجل ويتحدث عليه أمر الشغل. متأكد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> سجّل القص</button>
  @endif
  <div class="fa-end">
    <a href="{{ route('work-orders.show',$wo) }}" class="btn btn-outline-secondary">إلغاء</a>
  </div>
  </div>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('cut-declarations.submit',$row) }}" class="d-none">@csrf</form>
@endif

@push('scripts')
<script>
/* الفرق عن المخطط بالسنتيمتر — بيتحدث وإنت بتكتب، و ⓘ بالحسبة */
window.LVC = (function () {
  var nf = v => Number(v).toLocaleString('en-US', {maximumFractionDigits: 2});
  function dev() {
    var out = document.getElementById('spDev'), inp = document.getElementById('actSp');
    if (!out || !inp) return;
    var planned = Number(out.dataset.planned) || 0, actual = Number(inp.value) || 0;
    if (!planned) return;
    if (!actual) { out.className = 'hint'; out.textContent = 'المخطط: ' + nf(planned) + ' م'; return; }
    var cm = Math.round((actual - planned) * 10000) / 100;
    out.className = 'hint ' + (Math.abs(cm) >= 5 ? 'text-danger fw-bold' : (cm > 0 ? 'text-warning' : 'text-success'));
    out.innerHTML = LVX.esc('المخطط ' + nf(planned) + ' م · الفرق ' + (cm > 0 ? '+' : '') + nf(cm) + ' سم')
      + LVX.fx('فرق طول الفرشة', ['(الفعلي ' + nf(actual) + ' − المخطط ' + nf(planned) + ') × 100 = ' + nf(cm) + ' سم'],
               'كل سنتيمتر زيادة بياكل من كل رِقّة وبيقلل القطع');
    LVX.popovers(out);
  }
  document.addEventListener('DOMContentLoaded', dev);
  return {dev};
})();
</script>
@endpush
@endsection
