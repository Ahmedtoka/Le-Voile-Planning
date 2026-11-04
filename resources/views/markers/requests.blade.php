@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'mreq'])

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('markers.requests.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> طلب ماركر</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة طلبات الماركر">
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
      <thead><tr><th>رقم الطلب</th><th>التاريخ</th><th>الحوض</th><th>عرض / بنشر</th>
        <th style="min-width:220px">المطلوب</th><th>نسخ</th><th>الباترونست</th><th>مطلوب في</th><th>الحالة</th>
        <th style="width:130px">الماركر</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php $late = $r->needed_by && $r->needed_by->isPast() && !in_array($r->status, ['delivered','cancelled'], true); @endphp
        <tr class="{{ $late ? 'table-warning' : '' }}">
          <td class="num fw-bold">{{ $r->doc_no }}</td>
          <td class="num">{{ $r->doc_date?->format('Y-m-d') }}</td>
          <td class="num">{{ $r->consignment?->consignment_no ?? '—' }}
            @if($r->consignment?->has_color_break)<i class="bi bi-exclamation-triangle-fill text-danger" title="فواصل / اختلاف لون" aria-label="فواصل / اختلاف لون"></i>@endif</td>
          <td class="num fw-bold">{{ $r->fabric_width_cm }} <span class="hint">/ {{ $r->avg_gsm ?? '—' }}</span></td>
          <td class="hint" style="white-space:normal">
            @forelse($r->models as $m)<div class="text-nowrap">{{ $m->label }}</div>@empty {{ Str::limit($r->models_label, 70) }} @endforelse
            @if($r->sizes_note)<div>{{ $r->sizes_note }}</div>@endif
            @if($r->suggestedMarker)<div class="text-success">اتقفل على ماركر قديم {{ $r->suggestedMarker->code }}</div>@endif
            {{-- الكمية اللي المخزن أكّدها — الباترونيست يعرف الماركر هيتقص على كام --}}
            @if($pq = $r->preparationRequest)
              <div class="mt-1">@if($pq->status === 'weighed' || $pq->status === 'used')<span class="pill pill-ok">المخزن وزن {{ lv_num($pq->qty_for_work_order) }} {{ $r->consignment?->unit_label }}</span>
              @elseif($pq->status === 'open')<span class="pill pill-warn">مستني وزن المخزن — تقريبي {{ lv_num($pq->requested_qty) }}</span>@endif</div>
            @endif</td>
          <td class="num">{{ $r->copies }}</td>
          <td>{{ $r->patternist?->name ?? '—' }}</td>
          <td class="num {{ $late ? 'text-danger fw-bold' : '' }}">{{ $r->needed_by?->format('Y-m-d') ?? '—' }}</td>
          <td><span class="badge bg-{{ $r->status==='delivered'?'success':($r->status==='cancelled'?'secondary':'warning') }}">{{ $r->status_name }}</span></td>
          <td class="text-nowrap text-end"><div class="row-actions">
            @if($r->marker)
              <a href="{{ route('markers.edit',$r->marker) }}" class="btn btn-sm btn-outline-plum"
                 title="فتح الماركر" aria-label="فتح الماركر {{ $r->marker->code }}"><i class="bi bi-grid-3x3" aria-hidden="true"></i> <span class="num">{{ $r->marker->code }}</span></a>
            @else
              <a href="{{ route('markers.create', ['request_id'=>$r->id]) }}" class="btn btn-sm btn-plum"
                 title="ارفع الماركر للطلب ده" aria-label="ارفع الماركر للطلب {{ $r->doc_no }}"><i class="bi bi-upload" aria-hidden="true"></i> ارفع الماركر</a>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="10">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش طلبات ماركر مطابقة للفلاتر.' : 'مفيش طلبات ماركر لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint mb-2">التخطيط بيطلب الماركر من الباترونست على حوض معيّن بعرضه وبنشره.</div>
              <a href="{{ route('markers.requests.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> اطلب ماركر</a>
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
@endsection
