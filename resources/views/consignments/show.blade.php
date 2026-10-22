@extends('layouts.app')
@section('purpose', 'كل حاجة عن الرسالة دي: مصدرها، أتوابها، رصيدها المحجوز والمتاح، وأوامر الشغل اللي اتصرفت منها')
@section('content')
@php
  $n        = fn ($v, $d = 3) => lv_num($v, $d);
  $total    = (float) $row->total_kg;
  $released = (float) ($row->released_kg ?? 0);
  $alloc    = (float) ($row->allocated_kg ?? 0);
  $reserved = (float) ($row->reserved_kg ?? 0);
  $hold     = (float) ($row->hold_kg ?? 0);
  $remain   = (float) $row->remaining_kg;
  $rejected = $released > 0 ? max(0, $total - $released) : 0;
  $onHold   = in_array($row->status, ['under_inspection', 'inspected', 'lab_done'], true);
@endphp

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

@foreach($warnings as $w)
  <div class="alert alert-{{ $w['level']==='danger'?'danger':'warning' }} py-2">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $w['text'] }}
  </div>
@endforeach

<style>
/* ⓘ فوق كارت KPI (الكارت نفسه من partials.kpi) */
.kpi-fx{position:absolute;inset-inline-end:12px;inset-block-start:12px;z-index:2}
</style>
{{-- السامري: نفس كروت partials.summary بس كل رقم محسوب جنبه ⓘ بحسبته --}}
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-lg">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value' => number_format($total,1), 'label' => 'إجمالي الوزن', 'sub' => 'كجم', 'note' => $row->rolls_count . ' توب'])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'إجمالي الوزن',
        'f' => 'مجموع أوزان الأتواب في إذن الإضافة = ' . $n($total) . ' كجم (' . (int) $row->rolls_count . ' توب)',
        'why' => 'الرقم اللي وصل من المورد — قبل الفحص والرفض'])</span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value' => $row->min_width_cm ?? '—', 'label' => 'أقل عرض ★', 'sub' => 'سم', 'tone' => 'brand',
        'note' => 'ده العرض اللي الماركر بيتعمل عليه'])
      @if($row->min_width_cm)
        <span class="kpi-fx">@include('partials.fx', ['t' => 'أقل عرض',
          'f' => 'أقل عرض = أضيق توب اتقاس في الفحص = ' . $n($row->min_width_cm, 2) . ' سم',
          'why' => 'الماركر لازم يتحط على أضيق عرض — لو اتحط على المتوسط، الأتواب الضيقة هيتحرق جنبها'])</span>
      @endif
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value' => $row->avg_gsm ?? '—', 'label' => 'متوسط البنشر ★', 'tone' => 'brand', 'sub' => $row->avg_gsm ? 'جم/م²' : null,
        'note' => 'العيوب: ' . ($row->defect_pct !== null ? $row->defect_pct . '%' : '—')])
      @if($row->avg_gsm)
        <span class="kpi-fx">@include('partials.fx', ['t' => 'متوسط البنشر',
          'f' => ['متوسط قراءات وزن المتر المربع في تقرير المعمل = ' . $n($row->avg_gsm, 2) . ' جم/م²',
                  $row->defect_pct !== null ? 'نسبة العيوب = الأتواب المعيبة ÷ المقاسة × 100 = ' . $row->defect_pct . '%' : ''],
          'why' => 'البنشر بيطلع وينزل جوه نفس التوب — المتوسط هو الأقرب للحقيقة'])</span>
      @endif
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value' => number_format($hold,1), 'label' => 'محجوز تحت الفحص', 'sub' => 'كجم', 'tone' => $hold > 0 ? 'warn' : 'ink'])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'محجوز تحت الفحص',
        'f' => $onHold
          ? 'الرسالة لسه تحت الفحص ⇒ كل وزنها ' . $n($total) . ' كجم محجوز لحد إذن الاستلام (الإفراج)'
          : 'اتفرج عن الرسالة ⇒ مفيش محجوز تحت الفحص (0 كجم)',
        'why' => 'مفيش تشغيل على قماش لسه ما اتفحصش'])</span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value' => number_format($remain,1), 'label' => 'متاح للتشغيل', 'sub' => 'كجم',
        'tone' => $remain > 0 ? 'ok' : 'ink',
        'note' => $reserved > 0 ? 'محجوز لطلبات تحضير: ' . number_format($reserved,1) . ' كجم' : null])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'المتاح للتشغيل',
        'f' => ['المتاح = الإجمالي − المرفوض − المصروف لأوامر الشغل − المحجوز لطلبات التحضير',
                $onHold
                  ? 'لسه تحت الفحص ⇒ المفرج عنه 0 كجم (بيتحدد بإذن الاستلام بعد الفحص)'
                  : 'الإجمالي ' . $n($total) . ' − المرفوض ' . $n($rejected) . ' = المفرج عنه ' . $n($released) . ' كجم',
                'المفرج عنه ' . $n($released) . ' − أوامر الشغل ' . $n($alloc) . ' − محجوز لطلبات التحضير ' . $n($reserved) . ' = ' . $n($remain) . ' كجم'],
        'why' => $onHold ? 'لسه تحت الفحص — المفرج عنه 0 لحد إذن الاستلام' : 'المتاح بيتحدّث مع كل أمر شغل أو طلب تحضير'])</span>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات الحوض</span>
        <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
      </div>
      <table class="table table-sm kv mb-0">
        <tr><th>رقم الرسالة</th><td class="num fw-bold">{{ $row->consignment_no }}</td></tr>
        <tr><th>تاريخ الوصول</th><td class="num">{{ $row->arrival_date?->format('Y-m-d') }} <span class="hint">(دخول تحت الفحص)</span></td></tr>
        <tr><th>تاريخ الإفراج</th><td class="num">{{ $row->released_at?->format('Y-m-d') ?? '— لسه' }} <span class="hint">(الإضافة بعد الفحص)</span></td></tr>
        @if($row->has_color_break)
          <tr><th class="text-danger">فواصل / اختلاف لون</th><td class="text-danger fw-bold">{{ $row->color_break_note ?: 'نعم — راجع تقرير المعمل' }}</td></tr>
        @endif
        <tr><th>المورد</th><td>{{ $row->supplier?->name ?? '—' }}</td></tr>
        <tr><th>أمر الشراء</th><td class="num">
          @if($row->purchaseOrder)
            <a href="{{ route('purchase-orders.edit', $row->purchaseOrder) }}">{{ $row->purchaseOrder->po_no }}</a>
            @php $st = $row->orderLineStatus(); @endphp
            @if($st)
              <div class="hint">مطلوب من الصنف ده <b>{{ $n($st['ordered']) }} {{ $st['unit'] }}</b> · وصل <b>{{ $n($st['received']) }}</b>
                @if($st['left'] > 0.0001)
                  · <span class="text-danger fw-bold">باقي {{ $n($st['left']) }} {{ $st['unit'] }}</span>
                  @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . $n($st['ordered']) . ' − الواصل ' . $n($st['received']) . ' = ' . $n($st['left']) . ' ' . $st['unit']])
                  {{ $st['eta'] ? '· متوقع ' . $st['eta'] : '' }}
                @else · <span class="text-success">اكتمل</span> @endif
              </div>
            @endif
          @else — @endif
        </td></tr>
        <tr><th>إذن المورد</th><td>@include('partials.supplier_doc', ['sa' => $row->sourceAddition()])</td></tr>
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
      <div class="card-footer bg-white">
        @php $next = $row->nextStep(); @endphp
        <div class="actions-bar">
          @if($next === 'inspection')
            <a href="{{ route('inspections.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-plum"><i class="bi bi-search" aria-hidden="true"></i> ② تقرير فحص</a>
          @endif
          @if($next === 'lab' || !$row->labReports->count())
            <a href="{{ route('lab-reports.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-thermometer-half" aria-hidden="true"></i> ③ تقرير معمل</a>
          @endif
          @if($next === 'receipt')
            <a href="{{ route('goods-receipts.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-plum"><i class="bi bi-truck" aria-hidden="true"></i> ④ إذن استلام (إفراج)</a>
          @endif
          @if($row->is_ready)
            <a href="{{ route('preparation-requests.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-plum"><i class="bi bi-speedometer2" aria-hidden="true"></i> طلب تحضير (تقسيم)</a>
            <a href="{{ route('markers.requests.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-envelope-paper" aria-hidden="true"></i> طلب ماركر</a>
            <a href="{{ route('work-orders.create', ['consignment_id'=>$row->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-hammer" aria-hidden="true"></i> أمر شغل</a>
          @endif
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">تعديل يدوي <span class="hint">— للتصحيح بس، الأرقام الطبيعية بتيجي من الإذون</span></div>
      <form method="post" action="{{ route('consignments.update',$row) }}">@csrf @method('PUT')
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6"><label class="form-label">إجمالي الوزن</label>
              <div class="input-group">
                <input type="number" step="0.001" min="0" name="total_kg" class="form-control" value="{{ $row->total_kg }}" placeholder="مثال: 1250.5">
                <span class="input-group-text">كجم</span>
              </div></div>
            <div class="col-6"><label class="form-label">عدد الأتواب</label>
              <div class="input-group">
                <input type="number" min="0" name="rolls_count" class="form-control" value="{{ $row->rolls_count }}" placeholder="مثال: 40">
                <span class="input-group-text">توب</span>
              </div></div>
            <div class="col-6"><label class="form-label">إجمالي الطول</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" name="total_length_m" class="form-control" value="{{ $row->total_length_m }}" placeholder="مثال: 1200">
                <span class="input-group-text">م</span>
              </div>
              <div class="hint">مهم لحساب عدد الرِقّات</div></div>
            <div class="col-6"><label class="form-label">الحالة</label>
              <select name="status" class="form-select">
                @foreach(\App\Models\Consignment::STATUSES as $k=>$v)
                  <option value="{{ $k }}" @selected($row->status===$k)>{{ $v }}</option>
                @endforeach
              </select></div>
            <div class="col-12"><label class="form-label">ملاحظات</label>
              <input name="notes" class="form-control" value="{{ $row->notes }}" placeholder="اختياري"></div>
          </div>
        </div>
        <div class="card-footer form-actions m-0">
          <button class="btn btn-outline-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ التعديل</button>
        </div>
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
              <td class="num">{{ $n($f->planned_qty, 2) }} {{ $f->unit }}</td>
              <td class="num">{{ number_format((int)$f->expected_pieces) }}
                @if($f->plies && $f->pieces_per_spread)
                  @include('partials.fx', ['t' => 'القص المتوقع', 'f' => 'الرِقّات ' . (int) $f->plies . ' × قطع الفرشة ' . (int) $f->pieces_per_spread . ' = ' . number_format((int) $f->plies * (int) $f->pieces_per_spread) . ' قطعة'])
                @endif
              </td>
              <td class="num">{{ $n($f->issued_actual, 2) }}</td>
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
          <thead><tr><th>رقم التوب</th><th>الطول (م)</th><th>العرض (سم)</th><th>البنشر (جم/م²)</th><th>الوزن (كجم)</th><th>مفحوص</th><th>الحالة</th></tr></thead>
          <tbody>
          @foreach($row->rolls as $r)
            <tr>
              <td class="num">{{ $r->roll_no }}</td>
              <td class="num">{{ $r->length_m ?? '—' }}</td>
              <td class="num">{{ $r->width_cm ?? '—' }}</td>
              <td class="num">{{ $r->gsm ?? '—' }}
                @if($r->gsm && $r->net_kg && $r->length_m && $r->width_cm)
                  @include('partials.fx', ['t' => 'بنشر التوب', 'f' => 'الوزن ' . $n($r->net_kg) . ' كجم × 1000 ÷ (الطول ' . $n($r->length_m, 2) . ' م × العرض ' . $n((float) $r->width_cm / 100, 3) . ' م) = ' . $n($r->gsm, 1) . ' جم/م²'])
                @endif
              </td>
              <td class="num">{{ $r->net_kg ?? '—' }}</td>
              <td>@if($r->is_inspected)<i class="bi bi-check-circle text-success" aria-hidden="true"></i><span class="visually-hidden">مفحوص</span>@endif</td>
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
