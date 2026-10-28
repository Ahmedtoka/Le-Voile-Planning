@extends('layouts.app')
@section('purpose', 'استلام القطع التامة على دفعات — أمر الشغل بيتقفل لوحده لما يكمل')
@section('content')
@php
  $lines    = old('lines', $row->lines?->toArray() ?? []);
  $editable = $row->isEditable() || $mode==='create';
  $cut      = (int) $wo->cut_pieces; $recv = (int) $wo->received_pieces; $out = (int) $wo->outstanding_pieces;
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'receive'])

<form method="post" action="{{ $mode==='create' ? route('production-receipts.store') : route('production-receipts.update',$row) }}">
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
          <div><div class="k">مقصوص (معتمد)</div><div class="v num">{{ number_format($cut) }}</div></div>
          <div><div class="k">مستلم قبل كده (سليم)</div><div class="v num">{{ number_format($recv) }}@if((int)$wo->rejected_pieces > 0) <span class="hint">· مرفوض {{ number_format((int)$wo->rejected_pieces) }}</span>@endif</div></div>
          <div><div class="k">لسه على المصنع</div>
            <div class="v num {{ $out ? 'text-warning' : 'text-success' }}">{{ number_format($out) }}
              @include('partials.fx', ['t' => 'لسه على المصنع', 'f' => ['المتبقي = المقصوص − المستلم السليم', number_format($cut) . ' − ' . number_format($recv) . ' = ' . number_format($out) . ' قطعة'],
                'why' => 'المرفوض من المصنع مش بيتحسب ضمن المستلم — لما يوصل صفر أمر الشغل بيتقفل لوحده بعد اعتماد الاستلام'])
            </div></div>
        </div>
      </div>

      <fieldset @disabled(!$editable)>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="doc_date" class="form-control" value="{{ old('doc_date',$row->doc_date?->format('Y-m-d') ?? $row->doc_date) }}" required></div>
        <div class="col-md-4"><label class="form-label req">المخزن</label>
          <select name="warehouse_id" class="form-select" required><option value="">— اختر مخزن التام —</option>
            @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(old('warehouse_id',$row->warehouse_id)==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control" value="{{ old('notes',$row->notes) }}" placeholder="اختياري"></div>
      </div>
      </fieldset>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">الكميات المستلمة <span class="hint">— المتبقي لكل موديل = مقصوص − مستلم سابقًا</span></div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr><th>الموديل</th><th>المقاس</th><th>مقصوص</th><th>مستلم سابقًا</th><th>متبقي</th>
          <th class="req" style="width:120px">الاستلام دلوقتي</th><th style="width:110px">مرفوض</th><th>ملاحظات</th></tr></thead>
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
            <td class="num fw-bold">{{ number_format($wl->remaining_qty) }}
              @include('partials.fx', ['t' => 'المتبقي', 'f' => 'مقصوص ' . number_format($wl->cut_qty) . ' − مستلم سابقًا ' . number_format($wl->received_qty) . ' = ' . number_format($wl->remaining_qty) . ' قطعة'])
            </td>
            <td class="num"><input type="number" min="0" name="lines[{{ $i }}][qty]" value="{{ $existing['qty'] ?? 0 }}" max="{{ $wl->remaining_qty }}" @disabled(!$editable) aria-label="الاستلام دلوقتي"></td>
            <td class="num"><input type="number" min="0" name="lines[{{ $i }}][rejected_qty]" value="{{ $existing['rejected_qty'] ?? 0 }}" @disabled(!$editable) aria-label="مرفوض"></td>
            <td><input name="lines[{{ $i }}][notes]" value="{{ $existing['notes'] ?? '' }}" @disabled(!$editable) placeholder="اختياري" aria-label="ملاحظات"></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      لما المتبقي يوصل صفر، أمر الشغل بيتقفل تلقائيًا بعد اعتماد الاستلام.
    </div>
  </div>

  @if($editable || ($mode==='edit' && $row->isEditable()))
  <div class="form-actions">
  @if($editable)<button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>@endif
  @if($mode==='edit' && $row->isEditable())
    <button type="button" class="btn btn-success" onclick="if(confirm('الكمية هتدخل مخزن التام وتتخصم من المتبقي على المصنع. متأكد؟')) document.getElementById('submitForm').submit()"><i class="bi bi-send" aria-hidden="true"></i> استلم الإنتاج</button>
  @endif
  <div class="fa-end">
    <a href="{{ route('work-orders.show',$wo) }}" class="btn btn-outline-secondary">إلغاء</a>
  </div>
  </div>
  @endif
</form>
@if($mode==='edit' && $row->isEditable())
  <form id="submitForm" method="post" action="{{ route('production-receipts.submit',$row) }}" class="d-none">@csrf</form>
@endif
@endsection
