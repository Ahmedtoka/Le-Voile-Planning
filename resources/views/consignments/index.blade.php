@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span><b>الحوض (الرسالة)</b> هو وحدة الشغل الأساسية: مجموعة أتواب اتنسجت واتصبغت مع بعض ⇒ نفس اللون
  ونفس البنشر. ممنوع تخلط حوضين في قطعة واحدة. الحوض بيتولّد من إذن الإضافة، وبيفضل
  <b>محجوز</b> لحد ما يتفحص وييجي له تقرير معمل ويتفرج عنه بإذن استلام.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('io.export.consignments') }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة الأحواض">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الرسالة…">
      </div>
      <select name="status" class="form-select" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="color_id" class="form-select" aria-label="اللون" onchange="this.form.submit()">
        <option value="">كل الألوان</option>
        @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(request('color_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="supplier_id" class="form-select" aria-label="المورد" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="ready" value="1" id="rdy" @checked(request('ready')) onchange="this.form.submit()">
        <label class="form-check-label" for="rdy">الجاهز للتشغيل بس</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="all" value="1" id="allc" @checked(request('all')) onchange="this.form.submit()">
        <label class="form-check-label" for="allc" title="الرسايل اللي رصيدها صفر مخفية افتراضيًا">اعرض اللي خلصت كمان</label>
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
        <th>رقم الرسالة</th><th>إذن المورد</th><th>الوصول</th><th>الإفراج</th><th>المورد</th><th>الخامة</th><th>اللون</th>
        <th>الوزن</th><th>الأتواب</th><th>أقل عرض</th><th>البنشر</th><th>محجوز</th><th>متاح</th><th>الحالة</th>
        <th style="width:84px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold"><a href="{{ route('consignments.show',$r) }}">{{ $r->consignment_no }}</a></td>
          <td class="num">@php $sdx = $r->sourceAddition(); @endphp
            @if($sdx)<a href="{{ route('stock-additions.edit',$sdx) }}" title="إذن الإضافة {{ $sdx->doc_no }}">{{ $sdx->supplier_doc_no ?: '—' }}</a>@else — @endif</td>
          <td class="num">{{ $r->arrival_date?->format('Y-m-d') }}</td>
          <td class="num">{{ $r->released_at?->format('Y-m-d') ?? '—' }}</td>
          <td>{{ $r->supplier?->name ?? '—' }}</td>
          <td>{{ $r->fabricType?->name ?? '—' }}</td>
          <td>{{ $r->color?->code ?? '—' }}
            @if($r->has_color_break)<i class="bi bi-exclamation-triangle-fill text-danger" title="فواصل / اختلاف لون: {{ $r->color_break_note }}" aria-label="فواصل / اختلاف لون"></i>@endif</td>
          <td class="num">{{ lv_num($r->total_kg, 1) }}</td>
          <td class="num">{{ $r->rolls_count }}</td>
          <td class="num {{ $r->min_width_cm ? 'fw-bold' : 'text-muted' }}">{{ $r->min_width_cm ?? '—' }}</td>
          <td class="num {{ $r->avg_gsm ? 'fw-bold' : 'text-muted' }}">{{ $r->avg_gsm ?? '—' }}</td>
          <td class="num {{ $r->hold_kg > 0 ? 'text-warning fw-bold' : 'text-muted' }}">{{ $r->hold_kg > 0 ? lv_num($r->hold_kg, 1) : '—' }}</td>
          <td class="num {{ $r->remaining_kg > 0 ? 'fw-bold' : 'text-muted' }}">
            {{ lv_num($r->remaining_kg, 1) }}
            @if((float) $r->total_kg > 0)
              @include('partials.fx', ['t' => 'المتاح للتشغيل', 'f' => [
                'إجمالي الرسالة ' . lv_num($r->total_kg, 1) . ' كجم' . ((float) $r->hold_kg > 0 ? ' — منها ' . lv_num($r->hold_kg, 1) . ' كجم محجوزة تحت الفحص' : ''),
                'مفرج عنه ' . lv_num($r->released_kg ?? 0, 1) . ' − مخصص لأوامر شغل ' . lv_num($r->allocated_kg ?? 0, 1) . ' − محجوز بطلبات تحضير ' . lv_num($r->reserved_kg ?? 0, 1) . ' = ' . lv_num($r->remaining_kg, 1) . ' كجم',
              ], 'why' => 'المتاح = اللي اتفرج عنه بإذن الاستلام ولسه ما اتخصصش لأمر شغل ولا اتحجز بطلب تحضير'])
            @endif
          </td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route('consignments.show',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح الرسالة" aria-label="فتح الرسالة {{ $r->consignment_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            @php $next = $r->nextStep(); @endphp
            @if($next === 'inspection')
              <a href="{{ route('inspections.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-warning btn-icon"
                 title="محتاج فحص" aria-label="افحص الرسالة {{ $r->consignment_no }}"><i class="bi bi-search" aria-hidden="true"></i></a>
            @elseif($next === 'lab')
              <a href="{{ route('lab-reports.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-warning btn-icon"
                 title="محتاج معمل" aria-label="تقرير معمل للرسالة {{ $r->consignment_no }}"><i class="bi bi-thermometer-half" aria-hidden="true"></i></a>
            @elseif($next === 'receipt')
              <a href="{{ route('goods-receipts.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-success btn-icon"
                 title="محتاج إذن استلام (إفراج)" aria-label="إذن استلام للرسالة {{ $r->consignment_no }}"><i class="bi bi-truck" aria-hidden="true"></i></a>
            @elseif($r->is_ready)
              <a href="{{ route('work-orders.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-success btn-icon"
                 title="أمر شغل" aria-label="أمر شغل من الرسالة {{ $r->consignment_no }}"><i class="bi bi-hammer" aria-hidden="true"></i></a>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="15">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش أحواض مطابقة للفلاتر.' : 'مفيش أحواض لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو توسّع مدى التاريخ.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint mb-2">الحوض بيتولّد لوحده من إذن الإضافة.</div>
              @if(Route::has('stock-additions.create'))
                <a href="{{ route('stock-additions.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> إذن إضافة جديد</a>
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
@endsection
