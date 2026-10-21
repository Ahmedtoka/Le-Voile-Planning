@extends('layouts.app')
@section('purpose', 'التخطيط يطلب كمية تقريبية من رسالة، والمخزن يوزن الأتواب ويرد بالفعلي — الفعلي هو اللي يدخل أمر الشغل')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'prep'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <b>بدل الورقة والواتساب:</b> التخطيط يختار الرسالة ويكتب الكمية <b>التقريبية</b> اللي محتاجها (200 كيلو مثلًا)،
  والكمية بتتحجز على الرسالة فورًا. المخزن يوزن الأتواب فعليًا ويرد بالوزن <b>الفعلي</b> (195 أو 210) —
  وده اللي بيدخل أمر الشغل لأنه بيفرق في عدد القطع. الرسالة ممكن تتقسم على أكتر من مصنع،
  وآخر تقسيمة «الباقي» بتاخد اللي فاضل من غير وزن.
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:170px" placeholder="رقم الطلب / الرسالة…">
      <select name="status" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    @if($canAsk)
      <a href="{{ route('preparation-requests.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> طلب تحضير</a>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <th>الطلب</th><th>التاريخ</th><th>الرسالة</th><th>الموديلات</th><th>المصنع</th>
        <th>المطلوب (تقريبي)</th><th>الفعلي (المخزن)</th><th>الفرق</th><th>الحالة</th><th>أمر الشغل</th><th></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr class="{{ $r->status === 'open' ? 'table-warning' : '' }}">
          <td class="num fw-bold">{{ $r->doc_no }}<div class="hint">{{ $r->requester?->name }}</div></td>
          <td class="num">{{ $r->doc_date?->format('Y-m-d') }}</td>
          <td class="num">
            @if($r->consignment)
              <a href="{{ route('consignments.show', $r->consignment) }}">{{ $r->consignment->consignment_no }}</a>
              <div class="hint">{{ $r->consignment->fabricType?->name }} · {{ $r->consignment->color?->code }}
                @if($r->consignment->has_color_break)<i class="bi bi-exclamation-triangle-fill text-danger" title="فواصل / اختلاف لون"></i>@endif</div>
            @else — @endif
          </td>
          <td class="hint">{{ Str::limit($r->models_label, 50) ?: '—' }}</td>
          <td>{{ $r->factory?->name ?? '— يتحدد في أمر الشغل' }}</td>
          <td class="num">{{ $r->is_remainder ? 'الباقي كله' : rtrim(rtrim(number_format((float)$r->requested_qty,3),'0'),'.') . ' كجم' }}</td>
          <td class="num fw-bold">{{ $r->actual_qty !== null ? rtrim(rtrim(number_format((float)$r->actual_qty,3),'0'),'.') . ' كجم' : '—' }}
            @if($r->actual_rolls)<div class="hint">{{ $r->actual_rolls }} توب</div>@endif
            @if($r->store_note)<div class="hint">{{ $r->store_note }}</div>@endif</td>
          <td class="num">
            @if($r->actual_qty !== null && !$r->is_remainder)
              @php $d = round((float)$r->actual_qty - (float)$r->requested_qty, 3); @endphp
              <span class="{{ $d != 0 ? 'text-warning fw-bold' : '' }}">{{ $d >= 0 ? '+' : '' }}{{ $d }}</span>
            @else — @endif
          </td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td class="num">
            @if($r->workOrder)
              <a href="{{ route('work-orders.show', $r->workOrder) }}">{{ $r->workOrder->wo_no }}</a>
            @elseif($r->status === 'weighed' && $canAsk)
              <a href="{{ route('work-orders.create', ['preparation_request_id' => $r->id]) }}" class="btn btn-sm btn-success py-0">
                <i class="bi bi-hammer" aria-hidden="true"></i> أمر شغل</a>
            @else — @endif
          </td>
          <td class="text-nowrap">
            @if($r->status === 'open' && $canWeigh)
              <button class="btn btn-sm btn-plum py-0" data-bs-toggle="modal" data-bs-target="#w{{ $r->id }}">
                <i class="bi bi-speedometer2" aria-hidden="true"></i> اوزن ورد</button>
            @endif
            @if(in_array($r->status, ['open','weighed'], true) && $canAsk)
              <form method="post" action="{{ route('preparation-requests.cancel', $r) }}" class="d-inline"
                    onsubmit="return confirm('إلغاء الطلب؟ الحجز هيرجع للرسالة.')">@csrf
                <button class="btn btn-sm btn-outline-secondary py-0" title="إلغاء"><i class="bi bi-x" aria-hidden="true"></i></button>
              </form>
            @endif
          </td>
        </tr>

        @if($r->status === 'open' && $canWeigh)
        <div class="modal fade" id="w{{ $r->id }}"><div class="modal-dialog"><div class="modal-content">
          <form method="post" action="{{ route('preparation-requests.weigh', $r) }}">@csrf
            <div class="modal-header">
              <h6 class="modal-title">وزن طلب التحضير {{ $r->doc_no }}</h6>
              <button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
              <div class="hint mb-2">
                الرسالة <b class="num">{{ $r->consignment?->consignment_no }}</b> ·
                المطلوب {{ $r->is_remainder ? '<b>الباقي كله</b>' : '<b class="num">' . rtrim(rtrim(number_format((float)$r->requested_qty,3),'0'),'.') . ' كجم</b> تقريبًا' }}
                @if($r->models_label) · للموديلات: {{ $r->models_label }} @endif
              </div>
              @if($r->is_remainder)
                <div class="alert alert-info py-2 small">آخر تقسيمة — بتاخد الباقي الفعلي على الرسالة من غير وزن. أكّد وبس.</div>
              @else
                <label class="form-label req">الوزن الفعلي للأتواب اللي اتفصلت (كجم)</label>
                <input type="number" step="0.001" min="0" name="actual_qty" class="form-control form-control-sm mb-2" required
                       placeholder="اوزن مجموعة أتواب قريبة من المطلوب واكتب وزنهم الفعلي">
              @endif
              <label class="form-label">عدد الأتواب</label>
              <input type="number" min="0" name="actual_rolls" class="form-control form-control-sm mb-2">
              <label class="form-label">ملاحظة المخزن</label>
              <input name="store_note" class="form-control form-control-sm" placeholder="مثال: أتواب 3 لـ 8">
            </div>
            <div class="modal-footer"><button class="btn btn-plum btn-sm">تسجيل الوزن</button></div>
          </form>
        </div></div></div>
        @endif
      @empty
        <tr><td colspan="11">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش طلبات تحضير. التخطيط بيطلبها من شاشة الرسالة أو من هنا.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
