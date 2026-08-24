@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

@foreach($warnings as $w)
  <div class="alert alert-{{ $w['level']==='danger'?'danger':'warning' }} py-2">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $w['text'] }}
  </div>
@endforeach

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((float)$row->total_kg,1) }}</div><div class="l">إجمالي الوزن (كجم)</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ $row->rolls_count }}</div><div class="l">عدد الأتواب</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num text-danger">{{ $row->min_width_cm ?? '—' }}</div><div class="l">أقل عرض (سم) ★</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num text-danger">{{ $row->avg_gsm ?? '—' }}</div><div class="l">متوسط البنشر ★</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num {{ $row->hold_kg > 0 ? 'text-warning' : '' }}">{{ number_format((float)$row->hold_kg,1) }}</div><div class="l">محجوز تحت الفحص</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((float)$row->remaining_kg,1) }}</div><div class="l">متاح للتشغيل (كجم)</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ $row->defect_pct ?? '—' }}</div><div class="l">نسبة العيوب %</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات الحوض</span>
        <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
      </div>
      <table class="table table-sm mb-0">
        <tr><th style="width:140px">رقم الرسالة</th><td class="num fw-bold">{{ $row->consignment_no }}</td></tr>
        <tr><th>تاريخ الوصول</th><td class="num">{{ $row->arrival_date?->format('Y-m-d') }}</td></tr>
        <tr><th>المورد</th><td>{{ $row->supplier?->name ?? '—' }}</td></tr>
        <tr><th>أمر الشراء</th><td class="num">{{ $row->purchaseOrder?->po_no ?? '—' }}</td></tr>
        <tr><th>الخامة</th><td>{{ $row->fabricType?->name ?? '—' }}</td></tr>
        <tr><th>اللون</th><td>{{ $row->color?->label ?? '—' }}</td></tr>
        <tr><th>المخزن</th><td>{{ $row->warehouse?->name ?? '—' }}</td></tr>
        <tr><th>الانكماش</th><td class="num">طول {{ $row->shrink_len_pct ?? '—' }}% · عرض {{ $row->shrink_width_pct ?? '—' }}%</td></tr>
        <tr><th>مطابقة اللون</th><td>
          @if($row->color_match_ok === null) —
          @elseif($row->color_match_ok) <span class="badge bg-success">مطابق</span>
          @else <span class="badge bg-danger">غير مطابق</span> @endif
        </td></tr>
      </table>
      <div class="card-footer bg-white d-flex gap-2 flex-wrap">
        @php $next = $row->nextStep(); @endphp
        @if($next === 'inspection')
          <a href="{{ route('inspections.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-plum">② تقرير فحص</a>
        @endif
        @if($next === 'lab' || !$row->labReports->count())
          <a href="{{ route('lab-reports.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-outline-plum">③ تقرير معمل</a>
        @endif
        @if($next === 'receipt')
          <a href="{{ route('goods-receipts.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-plum">④ إذن استلام (إفراج)</a>
        @endif
        @if($row->is_ready)
          <a href="{{ route('markers.requests.create') }}" class="btn btn-sm btn-outline-plum">طلب ماركر</a>
        @endif
        @if($row->is_ready)
          <a href="{{ route('work-orders.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-success">أمر شغل</a>
        @endif
      </div>
    </div>

    <div class="card">
      <div class="card-header">تعديل يدوي</div>
      <form method="post" action="{{ route('consignments.update',$row) }}" class="card-body">@csrf @method('PUT')
        <div class="row g-2">
          <div class="col-6"><label class="form-label">إجمالي الوزن (كجم)</label>
            <input type="number" step="0.001" name="total_kg" class="form-control form-control-sm" value="{{ $row->total_kg }}"></div>
          <div class="col-6"><label class="form-label">عدد الأتواب</label>
            <input type="number" name="rolls_count" class="form-control form-control-sm" value="{{ $row->rolls_count }}"></div>
          <div class="col-6"><label class="form-label">إجمالي الطول (م)</label>
            <input type="number" step="0.01" name="total_length_m" class="form-control form-control-sm" value="{{ $row->total_length_m }}">
            <div class="hint">مهم لحساب عدد الرِقّات</div></div>
          <div class="col-6"><label class="form-label">الحالة</label>
            <select name="status" class="form-select form-select-sm">
              @foreach(\App\Models\Consignment::STATUSES as $k=>$v)
                <option value="{{ $k }}" @selected($row->status===$k)>{{ $v }}</option>
              @endforeach
            </select></div>
          <div class="col-12"><label class="form-label">ملاحظات</label>
            <input name="notes" class="form-control form-control-sm" value="{{ $row->notes }}"></div>
        </div>
        <button class="btn btn-plum btn-sm mt-3">حفظ</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">أوامر الشغل على الحوض ده</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>أمر الشغل</th><th>المصنع</th><th>الكمية</th><th>القص المتوقع</th>
            <th>المنصرف</th><th>مقصوص</th><th>مستلم</th><th>الحالة</th></tr></thead>
          <tbody>
          @forelse($row->workOrderFabrics as $f)
            @php $w = $f->workOrder; @endphp
            @continue(!$w)
            <tr class="{{ $f->is_governing ? 'table-warning' : '' }}">
              <td class="num">
                <a href="{{ route('work-orders.show',$w) }}">{{ $w->wo_no }}</a>
                @if($f->is_governing)<div><span class="badge bg-warning">حاكمة</span></div>@endif
              </td>
              <td>{{ $w->factory?->name }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$f->planned_qty,2),'0'),'.') }} {{ $f->unit }}</td>
              <td class="num">{{ number_format((int)$f->expected_pieces) }}</td>
              <td class="num">{{ rtrim(rtrim(number_format($f->issued_actual,2),'0'),'.') }}</td>
              <td class="num">{{ number_format((int)$w->cut_pieces) }}</td>
              <td class="num">{{ number_format((int)$w->received_pieces) }}</td>
              <td><span class="badge bg-{{ $w->status_color }}">{{ $w->status_name }}</span></td>
            </tr>
          @empty
            <tr><td colspan="8">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش أوامر شغل على الحوض ده.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">الأتواب ({{ $row->rolls->count() }})</div>
      <div class="table-responsive" style="max-height:380px;overflow:auto">
        <table class="table table-sm mb-0">
          <thead><tr><th>رقم التوب</th><th>الطول</th><th>العرض</th><th>البنشر</th><th>الوزن</th><th>مفحوص</th><th>الحالة</th></tr></thead>
          <tbody>
          @foreach($row->rolls as $r)
            <tr>
              <td class="num">{{ $r->roll_no }}</td>
              <td class="num">{{ $r->length_m ?? '—' }}</td>
              <td class="num">{{ $r->width_cm ?? '—' }}</td>
              <td class="num">{{ $r->gsm ?? '—' }}</td>
              <td class="num">{{ $r->net_kg ?? '—' }}</td>
              <td>@if($r->is_inspected)<i class="bi bi-check-circle text-success" aria-hidden="true"></i>@endif</td>
              <td class="hint">{{ $r->status_name }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
