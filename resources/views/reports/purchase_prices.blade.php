@extends('layouts.app')
@section('purpose', 'كل خامة اتشرت من مين وبكام — آخر سعر والمتوسط وأقل وأعلى سعر، والأرخص متعلّم')
@section('content')
@php
  $cur = config('lvplanning.currency');
  $hasFilters = collect(request()->except(['page']))->filter()->isNotEmpty();
  $n = fn ($v, $d = 2) => number_format((float) $v, $d);
@endphp

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>التقرير بيقرا من <b>أوامر الشراء</b> (التسكينات): كل مرة خامة اتشرت من مورد بسعر. لكل مورد:
  <b>آخر سعر</b>، و<b>المتوسط</b> = إجمالي اللي اتدفع ÷ الكمية (مرجّح بالكمية)، وأقل وأعلى سعر.
  الموردين مترتبين من الأرخص للأغلى بالمتوسط، والأرخص عليه علامة.</span>
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ $groups->count() }}</span></span>
    <form class="filter-bar" method="get" role="search" aria-label="فلترة أسعار الشراء">
      <select name="fabric_type_id" class="form-select" aria-label="الخامة" onchange="this.form.submit()">
        <option value="">كل الخامات</option>
        @foreach($fabrics as $k=>$v)<option value="{{ $k }}" @selected(request('fabric_type_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="supplier_id" class="form-select" aria-label="المورد" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="fb-range">
        <label class="hint" for="pp-from">من</label>
        <input type="date" id="pp-from" name="from" value="{{ request('from') }}" class="form-control">
        <label class="hint" for="pp-to">إلى</label>
        <input type="date" id="pp-to" name="to" value="{{ request('to') }}" class="form-control">
      </div>
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm doc-table mb-0">
      <thead><tr>
        <th>الخامة</th><th>المورد</th><th>آخر سعر ({{ $cur }})</th><th>المتوسط ({{ $cur }})</th>
        <th>أقل – أعلى</th><th>مرات الشراء</th><th>الكمية</th><th>آخر شراء</th>
      </tr></thead>
      <tbody>
      @forelse($groups as $g)
        @php
          $first  = $g->first();
          $cheap  = $g->count() > 1 ? $first->supplier_id : null;
          $spread = $g->count() > 1 && $first->avg_price > 0 ? ($g->last()->avg_price - $first->avg_price) / $first->avg_price * 100 : null;
        @endphp
        @foreach($g as $i => $r)
          <tr>
            @if($i === 0)
              <td rowspan="{{ $g->count() }}" class="fw-bold align-top">{{ $fabrics[$r->fabric_type_id] ?? '—' }}
                <div class="hint">بال{{ $r->unit === 'متر' ? 'متر' : 'كيلو' }}</div>
                @if($spread !== null)<div class="hint">فرق الأسعار {{ lv_num($spread, 1) }}% بين الأرخص والأغلى</div>@endif
              </td>
            @endif
            <td>{{ $suppliers[$r->supplier_id] ?? '—' }}
              @if($cheap && $r->supplier_id === $cheap)<span class="pill pill-ok">الأرخص</span>@endif</td>
            <td class="num fw-bold">{{ $r->last_price !== null ? $n($r->last_price) : '—' }}</td>
            <td class="num">{{ $n($r->avg_price) }}
              @include('partials.fx', ['t' => 'متوسط سعر الشراء', 'f' => 'إجمالي ' . $n($r->total) . ' ÷ كمية ' . lv_num($r->qty, 3) . ' ' . $r->unit . ' = ' . $n($r->avg_price) . ' ' . $cur,
                'why' => 'مرجّح بالكمية — الشروة الكبيرة بتأثر أكتر من الصغيرة'])</td>
            <td class="num">{{ $r->min_price == $r->max_price ? $n($r->min_price) : $n($r->min_price) . ' – ' . $n($r->max_price) }}</td>
            <td class="num">{{ $r->times }}</td>
            <td class="num">{{ lv_num($r->qty, 3) }} <span class="hint">{{ $r->unit }}</span></td>
            <td class="num">{{ $r->last_at ? \Illuminate\Support\Carbon::parse($r->last_at)->format('Y-m-d') : '—' }}</td>
          </tr>
        @endforeach
      @empty
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="bi bi-tags ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش أسعار مطابقة للفلاتر.' : 'لسه مفيش أوامر شراء بأسعار.' }}</div>
            <div class="hint">أول ما المشتريات تسكّن طلب مع مورد بسعر، هيظهر هنا.</div>
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
