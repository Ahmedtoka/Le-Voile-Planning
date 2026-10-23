@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'sourcing'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span><b>مستنية تسعير</b>: افتح الطلب، حدد المورد والسعر والوحدة وتاريخ التوريد واضغط «احفظ» —
  الطلب يتعلّم <b>«اتسعّر — مستني الاستلام»</b> والحسابات توصلها الترانزاكشن.
  <b>اتسعّرت</b>: تتابع منها اللي مستني القماش يوصل.</span>
</div>

@include('partials.summary')

{{-- فلتر الحالة --}}
<div class="d-flex gap-2 flex-wrap mb-3" role="tablist" aria-label="حالة التسعير">
  <a href="{{ route('purchasing.queue', ['state' => 'pending']) }}"
     class="btn btn-sm {{ $state === 'pending' ? 'btn-plum' : 'btn-outline-plum' }}" @if($state === 'pending') aria-current="page" @endif>
    مستنية تسعير <span class="badge bg-light text-dark num">{{ $counts['pending'] }}</span>
  </a>
  <a href="{{ route('purchasing.queue', ['state' => 'priced']) }}"
     class="btn btn-sm {{ $state === 'priced' ? 'btn-plum' : 'btn-outline-plum' }}" @if($state === 'priced') aria-current="page" @endif>
    اتسعّرت <span class="badge bg-light text-dark num">{{ $counts['priced'] }}</span>
  </a>
  <a href="{{ route('purchasing.queue', ['state' => 'all']) }}"
     class="btn btn-sm {{ $state === 'all' ? 'btn-plum' : 'btn-outline-plum' }}" @if($state === 'all') aria-current="page" @endif>
    الكل <span class="badge bg-light text-dark num">{{ $counts['pending'] + $counts['priced'] }}</span>
  </a>
</div>

@php
  /* التاب (state) مش فلتر — المسح بيرجّع لنفس التاب */
  $hasFilters = collect(request()->except(['page','sort','dir','state']))->filter()->isNotEmpty();
  $clearUrl   = route('purchasing.queue', ['state' => $state]);
@endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة طابور التسعير">
      <input type="hidden" name="state" value="{{ $state }}">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الطلب…">
      </div>
      <select name="requested_by" class="form-select" aria-label="طلبه مين" onchange="this.form.submit()">
        <option value="">كل الطالبين</option>
        @foreach($requesters as $k=>$v)<option value="{{ $k }}" @selected(request('requested_by')==$k)>{{ $v }}</option>@endforeach
      </select>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        @include('partials.th_sort', ['label'=>'رقم الطلب','col'=>'po_no'])
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'stage'])
        <th>طلبه مين</th>
        @include('partials.th_sort', ['label'=>'إمتى','col'=>'po_date'])
        <th>الأصناف</th><th>المورد</th>
        @include('partials.th_sort', ['label'=>'تاريخ التوريد','col'=>'delivery_date'])
        @include('partials.th_sort', ['label'=>'الإجمالي','col'=>'total'])
        <th style="width:110px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $pending = $r->stage === 'purchasing' || $r->hasUnsourcedQty();
          $partial = $r->stage !== 'purchasing' && $r->hasUnsourcedQty();
          $days    = (int) ($r->requested_at?->diffInDays(now(), true) ?? 0);
          $fxTotal = ['مجموع البنود = ' . number_format((float) $r->subtotal, 2)];
          if ((float) $r->discount_value > 0) $fxTotal[] = '− خصم ' . lv_num($r->discount_pct, 2) . '% = ' . number_format((float) $r->discount_value, 2);
          if ((float) $r->tax_value > 0)      $fxTotal[] = '+ ضريبة ' . lv_num($r->tax_pct, 2) . '% = ' . number_format((float) $r->tax_value, 2);
          $fxTotal[] = 'الإجمالي = ' . number_format((float) $r->total, 2) . ' ' . config('lvplanning.currency');
        @endphp
        <tr class="{{ $pending && $days > 3 ? 'table-warning' : '' }}">
          <td class="num fw-bold">
            <a href="{{ $pending ? route('purchasing.source',$r) : route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a>
          </td>
          <td>
            <span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span>
            @if($partial)<div class="hint text-warning fw-bold">مسكّن جزئي — باقي {{ lv_num($r->lines->sum(fn ($l) => $l->unsourced_qty), 3) }} من غير مورد</div>@endif
            @if($pending && $days > 0)
              <div class="hint {{ $days > 3 ? 'text-danger fw-bold' : '' }}">مستني {{ $days }} يوم</div>
            @endif
          </td>
          <td>
            {{ $r->requester?->name ?? '—' }}
            @if($r->planning_note)<div class="hint">{{ Str::limit($r->planning_note, 40) }}</div>@endif
          </td>
          <td class="num">{{ $r->requested_at?->format('Y-m-d') }}</td>
          <td class="hint">
            @foreach($r->lines->take(2) as $l)
              {{ $l->fabricType?->name }} {{ $l->color?->name }}
              ({{ lv_num($l->qty, 2) }} {{ $l->unit }})@if(!$loop->last) · @endif
            @endforeach
            @if($r->lines->count() > 2) … +{{ $r->lines->count() - 2 }} @endif
          </td>
          <td>{{ $r->sourcings->count() > 1 ? $r->sourcings->pluck('supplier.name')->filter()->unique()->implode(' + ') : ($r->supplier?->name ?? '—') }}</td>
          <td class="num">
            @if($r->delivery_date)
              {{ $r->delivery_date->format('Y-m-d') }}
              @if($r->stage === 'approved')
                <div class="hint {{ $r->delivery_date->isPast() ? 'text-danger fw-bold' : '' }}">{{ $r->delivery_date->isPast() ? 'متأخر' : 'باقي' }}
                  {{ (int) $r->delivery_date->diffInDays(now(), true) }} يوم</div>
              @endif
            @else — @endif
          </td>
          <td class="num fw-bold">
            @if($r->total > 0)
              {{ number_format((float)$r->total, 0) }}
              @include('partials.fx', ['t' => 'إجمالي الطلب', 'f' => $fxTotal, 'why' => 'بيتحسب تلقائي من الأسعار اللي كتبتها'])
            @else — @endif
          </td>
          <td class="text-nowrap text-end"><div class="row-actions">
            @if($pending)
              <a href="{{ route('purchasing.source',$r) }}" class="btn btn-sm btn-plum"
                 title="حدد المورد والسعر وتاريخ التوريد" aria-label="سعّر الطلب {{ $r->po_no }}">
                <i class="bi bi-tag" aria-hidden="true"></i> {{ $partial ? 'سكّن الباقي' : 'سعّر' }}
              </a>
            @else
              <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
                 title="فتح الطلب" aria-label="فتح الطلب {{ $r->po_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi {{ $state === 'pending' && !$hasFilters ? 'bi-check-circle' : 'bi-inbox' }} ico" aria-hidden="true"></i>
            <div class="t">
              @if($hasFilters) مفيش طلبات مطابقة للفلاتر.
              @elseif($state === 'pending') مفيش طلبات مستنية تسعير — كله متسعّر.
              @else مفيش طلبات هنا.
              @endif
            </div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر.</div>
              <a href="{{ $clearUrl }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @elseif($state === 'pending')
              <div class="hint">أول ما التخطيط يطلب خامة جديدة هتنزل هنا.</div>
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
