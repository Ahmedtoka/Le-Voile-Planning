@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'cut'])

@include('partials.approval_box')

<div class="note-box mb-3">
  <b>طول الفرشة الفعلي هو أهم رقم هنا.</b>
  المخطط كان {{ $wo->governingFabric()?->effective_spread ?? '—' }} متر. لو المصنع فرش على أكتر — ولو 5 سنتيمتر —
  بياكل من كل رِقّة ويقلل عدد الرِقّات، وعدد القطع بينزل. سجّل الرقم الفعلي زي ما هو.
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
        <a href="{{ route('work-orders.show',$wo) }}" class="btn btn-sm btn-outline-secondary py-0">أمر الشغل</a>
      </div>
    </div>
    <div class="card-body"><fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control form-control-sm" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-3"><label class="form-label req">طول الفرشة الفعلي (م)</label>
          <input type="number" step="0.001" name="actual_spread_length_m" class="form-control form-control-sm"
                 value="{{ old('actual_spread_length_m',$row->actual_spread_length_m) }}" required>
          <div class="hint">المخطط: {{ $wo->governingFabric()?->effective_spread ?? '—' }}</div></div>
        <div class="col-md-2"><label class="form-label">عدد الرِقّات الفعلي</label>
          <input type="number" name="actual_plies" class="form-control form-control-sm" value="{{ old('actual_plies',$row->actual_plies) }}"></div>
        <div class="col-md-2"><label class="form-label">القماش المستهلك (كجم)</label>
          <input type="number" step="0.001" name="used_kg" class="form-control form-control-sm" value="{{ old('used_kg',$row->used_kg) }}"></div>
        <div class="col-md-3"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>

        <div class="col-12">
          <label class="form-label">سبب الفرق (إجباري لو الانحراف خارج الحدود)</label>
          <textarea name="variance_reason" rows="2" class="form-control form-control-sm"
            placeholder="مثلًا: القماش كان فيه جزء عريض واتعمل له تعشيقة تانية">{{ old('variance_reason',$row->variance_reason) }}</textarea>
        </div>
      </div>
    </fieldset></div>

    @if($mode==='edit')
      <div class="card-footer bg-white">
        <div class="row text-center g-2">
          <div class="col"><div class="hint">المستهدف</div><b class="num">{{ number_format($wo->target_qty) }}</b></div>
          <div class="col"><div class="hint">المقصوص</div><b class="num">{{ number_format((int)$row->total_pieces) }}</b></div>
          <div class="col"><div class="hint">الانحراف</div>
            <b class="num text-{{ ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$row->variance_flag] ?? 'muted' }}">
              {{ $row->variance_pct !== null ? $row->variance_pct.'%' : '—' }}</b></div>
          <div class="col"><div class="hint">فرق طول الفرشة</div><b class="num">{{ $row->spread_deviation_cm ?? '—' }} سم</b></div>
          <div class="col"><div class="hint">استهلاك القطعة الفعلي</div>
            <b class="num">{{ $row->actual_kg_per_piece ? number_format((float)$row->actual_kg_per_piece*1000,1).' جم' : '—' }}</b></div>
        </div>
      </div>
    @endif
  </div>

  <div class="card mb-3">
    <div class="card-header">الكميات المقصوصة</div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th>الموديل</th><th>المقاس</th><th>المخطط</th><th style="width:130px">المقصوص فعلي</th><th>ملاحظات</th></tr></thead>
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
            <td><input type="number" name="lines[{{ $i }}][qty]" value="{{ $existing['qty'] ?? $wl->planned_qty }}" @disabled(!$editable)></td>
            <td><input name="lines[{{ $i }}][notes]" value="{{ $existing['notes'] ?? '' }}" @disabled(!$editable)></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save" aria-hidden="true"></i> حفظ واحتساب الانحراف</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('cut-declarations.submit',$row) }}" class="d-none">@csrf</form>
@endif
@endsection
