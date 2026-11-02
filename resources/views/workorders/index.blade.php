@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>أمر الشغل هو <b>ورقة المصنع</b>: منتج واحد ممكن يتعمل من أكتر من خامة، وكل خامة ليها
  رسالتها وحسبتها. <b>الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج.</b>
  الفعلي بييجي من بيان القص، والفرق الطبيعي 2-4%.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('io.export.work-orders') }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>
    <a href="{{ route('work-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> أمر شغل</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة أوامر الشغل">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الأمر…">
      </div>
      <select name="status" class="form-select" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="factory_id" class="form-select" aria-label="المصنع" onchange="this.form.submit()">
        <option value="">كل المصانع</option>
        @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(request('factory_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="variance_flag" class="form-select" aria-label="الانحراف" onchange="this.form.submit()">
        <option value="">كل الانحرافات</option>
        @foreach(\App\Models\WorkOrder::VARIANCE_FLAGS as $k=>$v)<option value="{{ $k }}" @selected(request('variance_flag')===$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="late" value="1" id="lt" @checked(request('late')) onchange="this.form.submit()">
        <label class="form-check-label" for="lt">المتأخر بس</label>
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
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        @include('partials.th_sort', ['label'=>'رقم الأمر','col'=>'wo_no'])
        @include('partials.th_sort', ['label'=>'التاريخ','col'=>'wo_date'])
        <th>المنتج</th><th>الرسالة</th><th>المصنع</th><th>كجم</th><th>متوقع</th>
        @include('partials.th_sort', ['label'=>'مقصوص','col'=>'cut_pieces'])
        @include('partials.th_sort', ['label'=>'مستلم','col'=>'received_pieces'])
        <th>متبقي</th>
        @include('partials.th_sort', ['label'=>'الانحراف','col'=>'variance_pct'])
        @include('partials.th_sort', ['label'=>'التسليم','col'=>'due_date'])
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'status'])
        <th style="width:84px"><span class="visually-hidden">إجراءات</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          /* ⓘ المتوقع: كل خامة بتدي قطع متوقعة، والأقل هي الحاكمة — إلا لو المخطط اعتمد كمية */
          $fxTarget = [];
          foreach ($r->fabrics as $wf) {
            $fxTarget[] = ($wf->consignment?->consignment_no ?? 'خامة') . ': ' . number_format((int) $wf->expected_pieces) . ' قطعة متوقعة';
          }
          if ($r->approved_qty) {
            $fxTarget[] = 'اعتماد المخطط = ' . number_format($r->target_qty) . ' قطعة';
          } elseif ($r->fabrics->count() > 1) {
            $fxTarget[] = 'الحاكمة = أقل خامة = ' . number_format($r->target_qty) . ' قطعة';
          } else {
            $fxTarget[] = 'المتوقع = ' . number_format($r->target_qty) . ' قطعة';
          }
          if ($r->fabric_gap > 0) {
            $fxTarget[] = 'فرق الخامات = أعلى − الحاكمة = ' . number_format($r->fabric_gap) . ' قطعة';
          }
          $cut = (int) $r->cut_pieces; $rec = (int) $r->received_pieces; $tgt = (int) $r->target_qty;
        @endphp
        <tr class="{{ $r->is_late ? 'table-warning' : '' }}">
          <td class="num fw-bold"><a href="{{ route('work-orders.show',$r) }}">{{ $r->wo_no }}</a></td>
          <td class="num">{{ $r->wo_date?->format('Y-m-d') }}</td>
          <td>{{ Str::limit($r->product_title, 28) ?: '—' }}</td>
          <td class="hint">
            {{ $r->fabrics->pluck('consignment.consignment_no')->filter()->implode('، ') ?: '—' }}
            @if($r->fabrics->count() > 1)
              <div><span class="badge bg-light text-dark">{{ $r->fabrics->count() }} خامات</span></div>
            @endif
          </td>
          <td>{{ $r->factory?->name ?? '—' }}</td>
          <td class="num">{{ lv_num($r->fabrics->sum('planned_qty'), 1) }}</td>
          <td class="num fw-bold">
            {{ number_format($tgt) }}
            @if($r->fabrics->count() || $r->approved_qty)
              @include('partials.fx', ['t' => 'القطع المتوقعة', 'f' => $fxTarget,
                'why' => 'الرِقّات = الكمية ÷ وزن الرِقّة · القطع المتوقعة = الرِقّات × قطع الفرشة · الخامة اللي بتدي أقل قطع هي اللي بتحكم'])
            @endif
            @if($r->fabric_gap > 0)
              <div class="hint text-warning" title="فرق بين الخامات">فرق {{ number_format($r->fabric_gap) }}</div>
            @endif
          </td>
          <td class="num">{{ number_format($cut) }}</td>
          <td class="num">{{ number_format($rec) }}</td>
          <td class="num">
            {{ number_format($r->outstanding_pieces) }}
            @if($cut > 0 || $rec > 0)
              @include('partials.fx', ['t' => 'المتبقي على المصنع',
                'f' => 'مقصوص ' . number_format($cut) . ' − مستلم سليم ' . number_format($rec) . ' = ' . number_format($r->outstanding_pieces) . ' قطعة',
                'why' => 'قطع اتقصّت ولسه ما اتسلّمتش سليمة من المصنع — المرفوض من المصنع مش بيتحسب (لو المستلم أكبر من المقصوص بيتعرض صفر)'])
            @endif
          </td>
          <td class="num">
            @if($r->variance_pct !== null)
              <span class="badge bg-{{ ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$r->variance_flag] ?? 'secondary' }}">
                {{ $r->variance_pct }}%
              </span>
              @include('partials.fx', ['t' => 'الانحراف %',
                'f' => '(مقصوص ' . number_format($cut) . ' − متوقع ' . number_format($tgt) . ') ÷ ' . number_format($tgt) . ' × 100 = ' . $r->variance_pct . '%',
                'why' => '≤2% أخضر · ≤4% أصفر · فوقها أحمر — الفرق الطبيعي بين الحسبة والقص 2-4%'])
            @else — @endif
          </td>
          <td class="num {{ $r->is_late ? 'text-danger fw-bold' : '' }}">{{ $r->due_date?->format('Y-m-d') ?? '—' }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span>
            @if($r->is_ready_to_run)<div><span class="badge bg-success">جاهز للتشغيل</span></div>@endif</td>
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route('work-orders.show',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح أمر الشغل" aria-label="فتح أمر الشغل {{ $r->wo_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
            @if(Route::has('work-orders.print'))
              <a href="{{ route('work-orders.print',$r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary btn-icon"
                 title="طباعة" aria-label="طباعة أمر الشغل {{ $r->wo_no }}"><i class="bi bi-printer" aria-hidden="true"></i></a>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="14">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش أوامر شغل مطابقة للفلاتر.' : 'مفيش أوامر شغل لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو توسّع مدى التاريخ.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint mb-2">أمر الشغل بيتعمل من رسالة جاهزة للتشغيل أو من طلب تحضير اتوزن.</div>
              <a href="{{ route('work-orders.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> ابدأ أمر شغل</a>
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
