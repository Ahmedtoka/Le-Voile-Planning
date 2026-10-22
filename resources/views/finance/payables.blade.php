@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'buy', 'step' => 'finance'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الشاشة دي <b>متابعة — مش بوابة</b>. أول ما المشتريات تسعّر طلب، الترانزاكشن بتنزل هنا
  تلقائيًا: المستحق، المورد، طريقة الدفع، وتاريخ التوريد — والمخزن بيستلم عادي من غير ما يستناك.
  زرار «سجّل علمك» بيشيل الطلب من قايمة الجديد عندك بس، وما بيوقّفش حاجة.</span>
</div>

@php $cur = config('lvplanning.currency'); @endphp
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    @include('partials.kpi', ['value' => number_format((int) $summary['priced_today']), 'label' => 'اتسعّرت النهارده', 'tone' => 'ok',
      'note' => 'طلبات خلّصت تسعير النهارده ونزلت هنا للمتابعة.'])
  </div>
  <div class="col-6 col-md-4">
    @include('partials.kpi', ['value' => number_format($summary['total'], 0), 'label' => 'إجمالي المستحق المتوقع', 'tone' => 'brand',
      'sub' => $cur,
      'note' => 'مجموع إجمالي الطلبات اللي اتسعّرت ولسه ما اتقفلتش (عند الحسابات، معتمدة، أو بتتستلم).',
      'fx'  => 'مجموع «الإجمالي» لكل طلب ليه مورد ومرحلته: الحسابات / معتمد / بيتستلم = ' . number_format($summary['total'], 2) . ' ' . $cur,
      'why' => 'إجمالي كل طلب = مجموع البنود − الخصم + الضريبة'])
  </div>
  <div class="col-6 col-md-4">
    @include('partials.kpi', ['value' => number_format($summary['due_30'], 0), 'label' => 'توريد خلال 30 يوم', 'tone' => 'warn',
      'sub' => $cur,
      'note' => 'منها اللي تاريخ توريدها من النهارده لحد ' . now()->addDays(30)->format('Y-m-d') . '.',
      'fx'  => 'مجموع «الإجمالي» للطلبات اللي تاريخ توريدها ≤ ' . now()->addDays(30)->format('Y-m-d') . ' = ' . number_format($summary['due_30'], 2) . ' ' . $cur])
  </div>
</div>

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

        <form class="filter-bar" method="get" role="search" aria-label="فلترة المستحقات">
          <select name="supplier_id" class="form-select" aria-label="المورد" onchange="this.form.submit()">
            <option value="">كل الموردين</option>
            @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
          </select>
          <select name="stage" class="form-select" aria-label="المرحلة" onchange="this.form.submit()">
            <option value="">كل المراحل</option>
            @foreach($stages as $k=>$v)<option value="{{ $k }}" @selected(request('stage')===$k)>{{ $v }}</option>@endforeach
          </select>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="unseen" value="1" id="unseen" @checked(request('unseen')) onchange="this.form.submit()">
            <label class="form-check-label" for="unseen">اللي ما اتشافش بس</label>
          </div>
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
          <thead><tr><th>رقم الطلب</th><th>المورد</th><th>تاريخ التوريد</th><th>الإجمالي ({{ $cur }})</th>
            <th>طريقة الدفع</th><th>المرحلة</th><th>علم الحسابات</th>
            <th style="width:60px"><span class="visually-hidden">إجراءات</span></th></tr></thead>
          <tbody>
          @forelse($rows as $r)
            @php
              /* ⓘ المستحق المتوقع = إجمالي الطلب = مجموع البنود − الخصم + الضريبة */
              $fxTotal = ['مجموع البنود = ' . number_format((float) $r->subtotal, 2)];
              if ((float) $r->discount_value > 0) $fxTotal[] = '− خصم ' . lv_num($r->discount_pct, 2) . '% = ' . number_format((float) $r->discount_value, 2);
              if ((float) $r->tax_value > 0)      $fxTotal[] = '+ ضريبة ' . lv_num($r->tax_pct, 2) . '% = ' . number_format((float) $r->tax_value, 2);
              $fxTotal[] = 'المستحق المتوقع = ' . number_format((float) $r->total, 2) . ' ' . $cur;
              $dueSoon = $r->delivery_date && !$r->delivery_date->isPast() && $r->delivery_date->lte(now()->addDays(30));
            @endphp
            <tr class="{{ !$r->finance_at ? 'table-warning' : '' }}">
              <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
              <td>{{ $r->supplier?->name }}</td>
              <td class="num">{{ $r->delivery_date?->format('Y-m-d') ?? '—' }}
                @if($dueSoon)<div class="hint">خلال {{ (int) now()->diffInDays($r->delivery_date, true) }} يوم</div>@endif</td>
              <td class="num fw-bold">
                {{ number_format((float)$r->total, 2) }}
                @include('partials.fx', ['t' => 'المستحق المتوقع', 'f' => $fxTotal, 'why' => 'بيتحسب تلقائي من أسعار المشتريات — ده المتوقع مش الفاتورة النهائية'])
              </td>
              <td class="hint">{{ $r->payment_method ?: '—' }}</td>
              <td><span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span></td>
              <td>
                @if($r->finance_at)
                  <span class="badge bg-success">اتشاف <span class="num">{{ $r->finance_at->format('Y-m-d') }}</span></span>
                @else
                  <a href="{{ route('finance.ack',$r) }}" class="btn btn-sm btn-plum"
                     title="يشيل الطلب من قايمة الجديد عندك — ما بيوقّفش حاجة" aria-label="سجّل علمك بالطلب {{ $r->po_no }}">
                    <i class="bi bi-check2" aria-hidden="true"></i> سجّل علمك</a>
                @endif
              </td>
              <td class="text-nowrap text-end"><div class="row-actions">
                <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
                   title="فتح الطلب" aria-label="فتح الطلب {{ $r->po_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
              </div></td>
            </tr>
          @empty
            <tr><td colspan="8">
              <div class="empty-state">
                <i class="bi bi-inbox ico" aria-hidden="true"></i>
                <div class="t">{{ $hasFilters ? 'مفيش مستحقات مطابقة للفلاتر.' : 'مفيش مستحقات متوقعة.' }}</div>
                @if($hasFilters)
                  <div class="hint mb-2">جرّب تشيل الفلاتر.</div>
                  <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
                @else
                  <div class="hint">أول ما المشتريات تسعّر طلب هينزل هنا تلقائي.</div>
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
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><span>حسب المورد <span class="pill pill-muted num">{{ $bySupplier->count() }}</span></span></div>
      <div class="table-responsive">
        <table class="table table-sm no-min mb-0">
          <thead><tr><th>المورد</th><th>طلبات</th><th>الإجمالي ({{ $cur }})</th></tr></thead>
          <tbody>
          @forelse($bySupplier as $s)
            <tr>
              <td>
                @if($s->supplier)
                  <a href="{{ route('finance.payables', ['supplier_id' => $s->supplier_id]) }}" title="اعرض طلبات المورد ده">{{ $s->supplier->name }}</a>
                @else — @endif
              </td>
              <td class="num">{{ $s->orders }}</td>
              <td class="num fw-bold">{{ number_format((float)$s->total, 0) }}</td>
            </tr>
          @empty
            <tr><td colspan="3">
              <div class="empty-state">
                <i class="bi bi-inbox ico" aria-hidden="true"></i>
                <div class="t">مفيش مستحقات على أي مورد.</div>
              </div>
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
