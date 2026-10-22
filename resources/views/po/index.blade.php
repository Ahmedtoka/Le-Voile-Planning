@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'request'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الدورة: <b>التخطيط</b> يكتب الأصناف والكميات ← <b>المشتريات</b> تحدد المورد والسعر والوحدة
  وتاريخ التوريد ← <b>الحسابات</b> تعلم بالمستحق — <b>من غير أي اعتماد</b> — وبعدها الطلب
  جاهز يتستقبل عليه أذون إضافة.</span>
</div>

@include('partials.summary')

{{-- تابات المراحل --}}
<div class="d-flex gap-2 flex-wrap mb-3" role="tablist" aria-label="مراحل الطلب">
  <a href="{{ route('purchase-orders.index') }}"
     class="btn btn-sm {{ !request('stage') ? 'btn-plum' : 'btn-outline-plum' }}" @if(!request('stage')) aria-current="page" @endif>
    الكل <span class="badge bg-light text-dark num">{{ $counts->sum() }}</span>
  </a>
  @foreach($stages as $k => $v)
    @continue($k === 'approval')
    @continue(($counts[$k] ?? 0) === 0 && !in_array($k, ['planning','purchasing','finance'], true))
    <a href="{{ route('purchase-orders.index', ['stage' => $k]) }}"
       class="btn btn-sm {{ request('stage') === $k ? 'btn-plum' : 'btn-outline-plum' }}" @if(request('stage') === $k) aria-current="page" @endif>
      {{ $v }} <span class="badge bg-light text-dark num">{{ $counts[$k] ?? 0 }}</span>
    </a>
  @endforeach
</div>

@php
  /* التاب (stage) مش فلتر — المسح بيرجّع لنفس التاب */
  $hasFilters = collect(request()->except(['page','sort','dir','stage']))->filter()->isNotEmpty();
  $clearUrl   = url()->current() . (request('stage') ? '?stage=' . urlencode(request('stage')) : '');
@endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    @if(auth()->user()->can2('po.request'))
      <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> طلب شراء</a>
    @endif

    <form class="filter-bar" method="get" role="search" aria-label="فلترة طلبات الشراء">
      <input type="hidden" name="stage" value="{{ request('stage') }}">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الطلب…">
      </div>
      <select name="supplier_id" class="form-select" aria-label="المورد" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine')) onchange="this.form.submit()">
        <label class="form-check-label" for="mine">طلباتي</label>
      </div>
      <div class="fb-range">
        <label class="hint" for="f-from">من</label>
        <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="form-control">
        <label class="hint" for="f-to">إلى</label>
        <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="form-control">
      </div>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        @include('partials.th_sort', ['label'=>'رقم الطلب','col'=>'po_no'])
        @include('partials.th_sort', ['label'=>'حالة الطلب','col'=>'stage'])
        <th>المورد</th><th>الأصناف</th>
        @include('partials.th_sort', ['label'=>'الإجمالي','col'=>'total'])
        @include('partials.th_sort', ['label'=>'متوقع وصوله','col'=>'delivery_date'])
        <th>آخر أبديت</th><th>مين عمله</th><th style="width:84px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $eta     = $r->delivery_date;
          $etaLate = $eta && $eta->isPast() && in_array($r->stage, ['approved','receiving'], true);
          $lastActor = $r->financer?->name ?? $r->sourcer?->name ?? $r->requester?->name;
          /* ⓘ الإجمالي = مجموع البنود − الخصم + الضريبة (الأرقام من الطلب نفسه) */
          $fxTotal = ['مجموع البنود = ' . number_format((float) $r->subtotal, 2)];
          if ((float) $r->discount_value > 0) $fxTotal[] = '− خصم ' . lv_num($r->discount_pct, 2) . '% = ' . number_format((float) $r->discount_value, 2);
          if ((float) $r->tax_value > 0)      $fxTotal[] = '+ ضريبة ' . lv_num($r->tax_pct, 2) . '% = ' . number_format((float) $r->tax_value, 2);
          $fxTotal[] = 'الإجمالي = ' . number_format((float) $r->total, 2) . ' ' . config('lvplanning.currency');
        @endphp
        <tr class="{{ $etaLate ? 'table-warning' : '' }}">
          <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
          <td>
            <span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span>
            @if($r->stage === 'receiving')
              @php
                $rec = (float) $r->lines->sum('received_qty');
                $tot = (float) $r->lines->sum('qty');
                $pct = $tot > 0 ? round($rec / $tot * 100) : 0;
              @endphp
              <div class="hint num">استلم {{ $pct }}%
                @include('partials.fx', ['t' => 'نسبة الاستلام',
                  'f' => 'الواصل ' . lv_num($rec, 2) . ' ÷ المطلوب ' . lv_num($tot, 2) . ' × 100 = ' . $pct . '%',
                  'why' => 'الباقي على الطلب = المطلوب − الواصل = ' . lv_num(max(0, $tot - $rec), 2)])
              </div>
            @endif
          </td>
          <td>{{ $r->supplier?->name ?? '— لسه' }}</td>
          <td class="num">{{ $r->lines->count() }}
            <span class="hint">({{ lv_num($r->total_qty, 2) }})</span></td>
          <td class="num fw-bold">
            @if($r->total > 0)
              {{ number_format((float)$r->total, 0) }}
              @include('partials.fx', ['t' => 'إجمالي الطلب', 'f' => $fxTotal, 'why' => 'بيتحسب تلقائي أول ما المشتريات تكتب الأسعار'])
            @else — @endif
          </td>
          <td class="num">
            @if($eta)
              {{ $eta->format('Y-m-d') }}
              <div class="hint {{ $etaLate ? 'text-danger fw-bold' : '' }}">
                @if($etaLate)
                  <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                  متأخر {{ (int) $eta->diffInDays(now(), true) }} يوم
                @elseif(in_array($r->stage, ['approved','receiving'], true))
                  باقي {{ (int) $eta->diffInDays(now(), true) }} يوم
                @endif
              </div>
            @else — @endif
          </td>
          <td class="num hint" title="{{ $r->updated_at->format('Y-m-d H:i') }}">
            {{ $r->updated_at->diffForHumans() }}
          </td>
          <td class="hint">
            {{ $r->requester?->name ?? '—' }}
            @if($lastActor && $lastActor !== $r->requester?->name)
              <div>آخر تعديل: {{ $lastActor }}</div>
            @endif
          </td>
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح الطلب" aria-label="فتح الطلب {{ $r->po_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            <a href="{{ route('purchase-orders.print',$r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary btn-icon"
               title="طباعة" aria-label="طباعة الطلب {{ $r->po_no }}"><i class="bi bi-printer" aria-hidden="true"></i></a>
          </div></td>
        </tr>
      @empty
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش طلبات مطابقة للفلاتر.' : (request('stage') ? 'مفيش طلبات في المرحلة دي.' : 'مفيش طلبات شراء لسه.') }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو توسّع مدى التاريخ.</div>
              <a href="{{ $clearUrl }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @elseif(auth()->user()->can2('po.request'))
              <div class="hint mb-2">التخطيط بيبدأ الطلب بالأصناف والكميات، والمشتريات بتكمّل المورد والسعر.</div>
              <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> ابدأ طلب شراء</a>
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
