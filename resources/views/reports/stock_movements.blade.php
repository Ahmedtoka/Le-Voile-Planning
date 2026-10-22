@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>نفس شيت «IN &amp; OUT»: كل حركة داخلة وخارجة بمستندها — استلام مورد، إفراج،
  مرفوض للمرتجعات، صرف مصنع، استلام إنتاج. اضغط رقم الإذن تفتح المستند نفسه.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('io.export.movements', request()->query()) }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل بنفس الفلاتر" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة حركات المخزن">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الإذن أو الرسالة…">
      </div>
      <select name="direction" class="form-select w-short" aria-label="الاتجاه" onchange="this.form.submit()">
        <option value="">داخل وخارج</option>
        <option value="in" @selected(request('direction')==='in')>IN — داخل</option>
        <option value="out" @selected(request('direction')==='out')>OUT — خارج</option>
      </select>
      <select name="source_type" class="form-select" aria-label="العملية" onchange="this.form.submit()">
        <option value="">كل العمليات</option>
        @foreach($operations as $k=>$v)<option value="{{ $k }}" @selected(request('source_type')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="warehouse_id" class="form-select" aria-label="المخزن" onchange="this.form.submit()">
        <option value="">كل المخازن</option>
        @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(request('warehouse_id')==$k)>{{ $v }}</option>@endforeach
      </select>
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
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        @include('partials.th_sort', ['label'=>'التاريخ','col'=>'moved_at'])
        <th>الصنف</th><th>اللون</th><th>رقم الرسالة</th>
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'direction'])
        <th>العملية</th><th>الجهة (المخزن)</th>
        @include('partials.th_sort', ['label'=>'الكمية','col'=>'qty'])
        <th>الوحدة</th><th>رقم الإذن</th><th>الجودة</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num">{{ \Illuminate\Support\Carbon::parse($r->moved_at)->format('Y-m-d') }}</td>
          <td>{{ $r->fabricType?->name ?? $r->accessory?->name ?? '—' }}</td>
          <td>{{ $r->color?->label ?? $r->color?->code ?? '—' }}</td>
          <td class="num hint">
            @if($r->consignment)
              <a href="{{ route('consignments.show', $r->consignment) }}">{{ $r->consignment->consignment_no }}</a>
            @else — @endif
          </td>
          <td>
            @if($r->direction === 'in')
              <span class="badge bg-success">IN داخل</span>
            @else
              <span class="badge bg-warning">OUT خارج</span>
            @endif
          </td>
          <td>{{ $operations[$r->source_type] ?? '—' }}</td>
          <td>{{ $r->warehouse?->name ?? '—' }}</td>
          <td class="num fw-bold">{{ lv_num($r->qty, 2) }}</td>
          <td>{{ $r->unit }}</td>
          <td class="num">{{ $r->reference ?? '—' }}</td>
          <td>
            @if($r->quality_state === 'hold')<span class="badge bg-warning">محجوز</span>
            @elseif($r->quality_state === 'released')<span class="badge bg-success">مفرج</span>
            @elseif($r->quality_state === 'rejected')<span class="badge bg-danger">مرفوض</span>
            @else <span class="hint">—</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="11">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش حركات مطابقة للفلاتر.' : 'مفيش حركات لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو توسّع مدى التاريخ.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint">الحركات بتتسجل تلقائي لحظة اعتماد أي إذن إضافة أو استلام أو صرف.</div>
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
    <div class="hint">كل حركة بتتسجل تلقائيًا لحظة اعتماد مستندها — مفيش إدخال يدوي هنا.</div>
  </div>
</div>
@endsection
