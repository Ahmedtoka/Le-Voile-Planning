@extends('layouts.app')
@section('purpose', 'التخطيط يطلب كمية تقريبية من رسالة، والمخزن يوزن الأتواب ويرد بالفعلي — الفعلي هو اللي يدخل أمر الشغل')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'prep'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span><b>بدل الورقة والواتساب:</b> التخطيط يختار الرسالة ويكتب الكمية <b>التقريبية</b> اللي محتاجها (200 كيلو مثلًا)،
  والكمية بتتحجز على الرسالة فورًا. المخزن يوزن الأتواب فعليًا ويرد بالوزن <b>الفعلي</b> (195 أو 210) —
  وده اللي بيدخل أمر الشغل لأنه بيفرق في عدد القطع. الرسالة ممكن تتقسم على أكتر من مصنع،
  وآخر تقسيمة «الباقي» بتاخد اللي فاضل من غير وزن.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    @if($canAsk)
      <a href="{{ route('preparation-requests.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> طلب تحضير</a>
    @endif

    <form class="filter-bar" method="get" role="search" aria-label="فلترة طلبات التحضير">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الطلب / الرسالة…">
      </div>
      <select name="status" class="form-select" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        <th>الطلب</th><th>التاريخ</th><th>الرسالة</th><th>الموديلات</th><th>المصنع</th>
        <th>المطلوب (تقريبي)</th><th>الفعلي (المخزن)</th><th>الفرق</th><th>الحالة</th><th>طلب الماركر</th><th>أمر الشغل</th>
        <th style="width:150px"><span class="visually-hidden">إجراءات</span></th>
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
                @if($r->consignment->has_color_break)<i class="bi bi-exclamation-triangle-fill text-danger" title="فواصل / اختلاف لون" aria-label="فواصل / اختلاف لون"></i>@endif</div>
            @else — @endif
          </td>
          <td class="hint">{{ Str::limit($r->models_label, 50) ?: '—' }}</td>
          <td>{{ $r->factory?->name ?? '— يتحدد في أمر الشغل' }}</td>
          <td class="num">{{ $r->is_remainder ? 'الباقي كله' : lv_num($r->requested_qty) . ' كجم' }}</td>
          <td class="num fw-bold">{{ $r->actual_qty !== null ? lv_num($r->actual_qty) . ' كجم' : '—' }}
            @if($r->actual_rolls)<div class="hint">{{ $r->actual_rolls }} توب</div>@endif
            @if($r->store_note)<div class="hint">{{ $r->store_note }}</div>@endif</td>
          <td class="num">
            @if($r->actual_qty !== null && !$r->is_remainder)
              @php $d = round((float)$r->actual_qty - (float)$r->requested_qty, 3); @endphp
              <span class="{{ $d != 0 ? 'text-warning fw-bold' : '' }}">{{ $d >= 0 ? '+' : '' }}{{ lv_num($d) }}</span>
              @include('partials.fx', ['t' => 'فرق الوزن',
                'f' => 'الفعلي ' . lv_num($r->actual_qty) . ' − المطلوب ' . lv_num($r->requested_qty) . ' = ' . ($d >= 0 ? '+' : '') . lv_num($d) . ' كجم',
                'why' => 'المطلوب تقريبي والفعلي هو وزن الأتواب اللي اتفصلت — الفعلي هو اللي بيدخل أمر الشغل'])
            @else — @endif
          </td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td class="num">
            @if($r->markerRequest)
              <a href="{{ route('markers.requests') }}">{{ $r->markerRequest->doc_no }}</a>
              <div><span class="badge bg-{{ $r->markerRequest->status === 'delivered' ? 'success' : 'warning' }}">{{ $r->markerRequest->status_name }}</span></div>
            @else <span class="hint">—</span> @endif
          </td>
          <td class="num">
            @if($r->workOrder)
              <a href="{{ route('work-orders.show', $r->workOrder) }}">{{ $r->workOrder->wo_no }}</a>
            @elseif($r->status === 'weighed' && $canAsk)
              <a href="{{ route('work-orders.create', ['preparation_request_id' => $r->id]) }}" class="btn btn-sm btn-success">
                <i class="bi bi-hammer" aria-hidden="true"></i> أمر شغل</a>
            @else — @endif
          </td>
          <td class="text-nowrap text-end"><div class="row-actions">
            @if($r->status === 'open' && $canWeigh)
              <button type="button" class="btn btn-sm btn-plum" data-bs-toggle="modal" data-bs-target="#w{{ $r->id }}">
                <i class="bi bi-speedometer2" aria-hidden="true"></i> اوزن ورد</button>
            @endif
            @if(in_array($r->status, ['open','weighed'], true) && $canAsk)
              <form method="post" action="{{ route('preparation-requests.cancel', $r) }}"
                    onsubmit="return confirm('إلغاء الطلب؟ الحجز هيرجع للرسالة.')">@csrf
                <button class="btn btn-sm btn-outline-danger btn-icon" title="إلغاء الطلب" aria-label="إلغاء الطلب {{ $r->doc_no }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
              </form>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="12">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش طلبات تحضير مطابقة للفلاتر.' : 'مفيش طلبات تحضير لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint mb-2">التخطيط بيطلبها من شاشة الرسالة أو من هنا.</div>
              @if($canAsk)
                <a href="{{ route('preparation-requests.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> اطلب تحضير</a>
              @endif
            @endif
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    @if($rows->total() > 0)
      <div class="hint num">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} من {{ number_format($rows->total()) }}</div>
    @endif
  </div>
</div>

{{-- بوب أبات الوزن برّه الجدول — جوه <tbody> المتصفح بيطلّع المحتوى --}}
@foreach($rows as $r)
        @if($r->status === 'open' && $canWeigh)
        <div class="modal fade" id="w{{ $r->id }}" tabindex="-1" aria-labelledby="w{{ $r->id }}-t"><div class="modal-dialog"><div class="modal-content">
          <form method="post" action="{{ route('preparation-requests.weigh', $r) }}">@csrf
            <div class="modal-header">
              <h6 class="modal-title" id="w{{ $r->id }}-t">وزن طلب التحضير {{ $r->doc_no }}</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
              @php $cc = $r->consignment; @endphp
              <div class="text-center p-3 mb-3 rounded" style="background:var(--lv-offwhite);border:1px solid var(--lv-line)">
                <div class="hint">المطلوب تحضيره</div>
                <div class="fw-bold num" style="font-size:2rem;color:var(--lv-brand-ink);line-height:1.2">
                  {{ $r->is_remainder ? 'الباقي كله' : lv_kg($r->requested_qty, false) }}</div>
                <div class="hint">{{ $r->is_remainder ? 'آخر تقسيمة — من غير وزن' : 'تقريبي — اوزن أتواب قريبة من الرقم ده واكتب الفعلي' }}</div>
              </div>
              <table class="table table-sm kv mb-3">
                <tbody>
                  <tr><th>الرسالة</th><td class="num fw-bold">{{ $cc?->consignment_no }}</td></tr>
                  <tr><th>الصنف · اللون</th><td>{{ $cc?->fabricType?->name }} · {{ $cc?->color?->label ?? $cc?->color?->code }}</td></tr>
                  <tr><th>المتاح على الرسالة</th><td class="num">{{ $cc ? lv_kg($cc->remaining_kg + $r->reserved_qty, false) : '—' }} <span class="hint">({{ (int) $cc?->rolls_count }} توب)</span></td></tr>
                  <tr><th>الموديلات</th><td>{{ $r->models_label ?: '—' }}</td></tr>
                  <tr><th>المصنع</th><td>{{ $r->factory?->name ?? '— يتحدد في أمر الشغل' }}</td></tr>
                  <tr><th>طلبه</th><td>{{ $r->requester?->name }} <span class="hint num">{{ $r->doc_date?->format('Y-m-d') }}</span></td></tr>
                  @if($r->notes)<tr><th>ملاحظات التخطيط</th><td>{{ $r->notes }}</td></tr>@endif
                </tbody>
              </table>
              @if($r->is_remainder)
                <div class="alert alert-info py-2 small">بتاخد الباقي الفعلي على الرسالة من غير وزن. أكّد وبس.</div>
              @else
                <label class="form-label req" for="w{{ $r->id }}-qty">الوزن الفعلي للأتواب اللي اتفصلت</label>
                <div class="input-group mb-2">
                  <input type="number" step="0.001" min="0" name="actual_qty" id="w{{ $r->id }}-qty" class="form-control" required placeholder="مثال: 195">
                  <span class="input-group-text">كجم</span>
                </div>
              @endif
              <div class="row g-2">
                <div class="col-4"><label class="form-label" for="w{{ $r->id }}-rolls">عدد الأتواب</label>
                  <input type="number" min="0" name="actual_rolls" id="w{{ $r->id }}-rolls" class="form-control"></div>
                <div class="col-8"><label class="form-label" for="w{{ $r->id }}-note">ملاحظة المخزن</label>
                  <input name="store_note" id="w{{ $r->id }}-note" class="form-control" placeholder="مثال: أتواب 3 لـ 8"></div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
              <button class="btn btn-plum btn-sm"><i class="bi bi-speedometer2" aria-hidden="true"></i> تسجيل الوزن</button>
            </div>
          </form>
        </div></div></div>
        @endif
@endforeach

@endsection
