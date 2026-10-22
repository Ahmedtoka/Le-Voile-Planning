@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>نفس شيت «رصيد القماش — ON Hand»: رصيد كل رسالة لوحدها. <b>المتاح</b> = مفرج عنه
  وغير مخصص لأمر شغل · <b>المحجوز</b> = وصل ولسه تحت الفحص. التقرير بيقرا من نفس
  الأحواض اللي الشاشات شغالة بيها — فمفيش رقمين مختلفين.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('io.export.onhand', request()->query()) }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل بنفس الفلاتر" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة رصيد القماش">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رسالة أو صنف أو لون…">
      </div>
      <select name="fabric_type_id" class="form-select" aria-label="الخامة" onchange="this.form.submit()">
        <option value="">كل الخامات</option>
        @foreach($fabrics as $k=>$v)<option value="{{ $k }}" @selected(request('fabric_type_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="status" class="form-select" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="all" value="1" id="all"
               @checked(request('all')) onchange="this.form.submit()">
        <label class="form-check-label" for="all">اعرض الصفري كمان</label>
      </div>
      <div class="fb-range">
        <label class="hint" for="f-from">وصلت من</label>
        <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="form-control">
        <label class="hint" for="f-to">إلى</label>
        <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="form-control">
      </div>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        <th>كود الصنف</th><th>الصنف</th><th>اللون</th>
        @include('partials.th_sort', ['label'=>'العرض','col'=>'min_width_cm'])
        @include('partials.th_sort', ['label'=>'رقم الرسالة','col'=>'consignment_no'])
        @include('partials.th_sort', ['label'=>'وصلت','col'=>'arrival_date'])
        @include('partials.th_sort', ['label'=>'الأتواب','col'=>'rolls_count'])
        @include('partials.th_sort', ['label'=>'إجمالي (كجم)','col'=>'total_kg'])
        @include('partials.th_sort', ['label'=>'محجوز','col'=>'hold_kg'])
        @include('partials.th_sort', ['label'=>'متاح','col'=>'remaining_kg'])
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'status'])
        <th style="width:60px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $total = (float) $r->total_kg; $hold = (float) $r->hold_kg; $avail = (float) $r->remaining_kg;
          $other = max(0, $total - $hold - $avail); // منصرف لأوامر شغل + محجوز بطلبات تحضير + مرفوض
        @endphp
        <tr>
          <td class="num hint">{{ $r->fabricType?->code ?? '—' }}</td>
          <td>{{ $r->fabricType?->name ?? '—' }}</td>
          <td>{{ $r->color?->label ?? $r->color?->code ?? '—' }}</td>
          <td class="num">{{ $r->min_width_cm ?: '—' }}</td>
          <td class="num fw-bold"><a href="{{ route('consignments.show',$r) }}">{{ $r->consignment_no }}</a></td>
          <td class="num">{{ $r->arrival_date?->format('Y-m-d') }}</td>
          <td class="num">{{ (int) $r->rolls_count }}</td>
          <td class="num">{{ lv_num($total, 2) }}</td>
          <td class="num {{ $hold > 0 ? 'text-warning fw-bold' : 'hint' }}">
            {{ $hold > 0 ? lv_num($hold, 2) : '—' }}</td>
          <td class="num fw-bold {{ $avail > 0 ? 'text-success' : 'hint' }}">
            {{ lv_num($avail, 2) }}
            @if($total > 0)
              @include('partials.fx', ['t' => 'المتاح للتشغيل', 'f' => [
                'إجمالي الرسالة ' . lv_num($total, 2) . ' − محجوز تحت الفحص ' . lv_num($hold, 2) . ' − منصرف/محجوز بطلبات/مرفوض ' . lv_num($other, 2) . ' = متاح ' . lv_num($avail, 2) . ' كجم',
                'مفرج عنه ' . lv_num($r->released_kg ?? 0, 2) . ' − مخصص لأوامر شغل ' . lv_num($r->allocated_kg ?? 0, 2) . ' − محجوز بطلبات تحضير ' . lv_num($r->reserved_kg ?? 0, 2) . ' = ' . lv_num($avail, 2) . ' كجم',
              ], 'why' => 'المتاح + المحجوز + المنصرف والمرفوض = إجمالي الرسالة'])
            @endif
          </td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route('consignments.show',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح الرسالة" aria-label="فتح الرسالة {{ $r->consignment_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
          </div></td>
        </tr>
      @empty
        <tr><td colspan="12">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش رسايل مطابقة للفلاتر.' : 'مفيش رسايل ليها رصيد.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو علّم «اعرض الصفري كمان».</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint">الرصيد بيتبني من أذون الإضافة — أول رسالة توصل هتظهر هنا.</div>
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
    <div class="hint">المتاح + المحجوز + المنصرف والمرفوض = إجمالي الرسالة.</div>
  </div>
</div>
@endsection
