@extends('layouts.app')
@section('content')
@php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; @endphp

@include('partials.approval_box')

<form method="post" action="{{ $mode==='create' ? route('production-receipts.store') : route('production-receipts.update',$row) }}">
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
        <div class="col-md-4"><label class="form-label req">المخزن</label>
          <select name="warehouse_id" class="form-select form-select-sm" required><option value="">—</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="{{ old('notes',$row->notes) }}"></div>
      </div>
    </fieldset></div>
  </div>

  <div class="card mb-3">
    <div class="card-header">الكميات المستلمة</div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th>الموديل</th><th>المقاس</th><th>مقصوص</th><th>مستلم سابقًا</th><th>متبقي</th>
          <th style="width:120px">الاستلام دلوقتي</th><th style="width:110px">مرفوض</th><th>ملاحظات</th></tr></thead>
        <tbody>
        @foreach($wo->lines as $i => $wl)
          @php
            $existing = collect($lines)->first(fn($x) =>
                ($x['product_model_id'] ?? null) == $wl->product_model_id && ($x['size_id'] ?? null) == $wl->size_id);
          @endphp
          <tr>
            <td>{{ $wl->productModel?->name }}
              <input type="hidden" name="lines[{{ $i }}][product_model_id]" value="{{ $wl->product_model_id }}">
              <input type="hidden" name="lines[{{ $i }}][color_id]" value="{{ $wo->governingFabric()?->color_id }}"></td>
            <td>{{ $wl->size?->name ?? 'كل المقاسات' }}
              <input type="hidden" name="lines[{{ $i }}][size_id]" value="{{ $wl->size_id }}"></td>
            <td class="num">{{ number_format($wl->cut_qty) }}</td>
            <td class="num">{{ number_format($wl->received_qty) }}</td>
            <td class="num fw-bold">{{ number_format($wl->remaining_qty) }}</td>
            <td><input type="number" name="lines[{{ $i }}][qty]" value="{{ $existing['qty'] ?? 0 }}" max="{{ $wl->remaining_qty }}" @disabled(!$editable)></td>
            <td><input type="number" name="lines[{{ $i }}][rejected_qty]" value="{{ $existing['rejected_qty'] ?? 0 }}" @disabled(!$editable)></td>
            <td><input name="lines[{{ $i }}][notes]" value="{{ $existing['notes'] ?? '' }}" @disabled(!$editable)></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle"></i>
      لما المتبقي يوصل صفر، أمر الشغل بيتقفل تلقائيًا بعد اعتماد الاستلام.
    </div>
  </div>

  @if($editable)<button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('إرسال للاعتماد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send"></i> إرسال للاعتماد</button>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('production-receipts.submit',$row) }}" class="d-none">@csrf</form>
@endif
@if($mode === 'edit')
  @include('partials.comments')
@endif

@endsection
