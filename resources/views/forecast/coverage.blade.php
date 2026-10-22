@extends('layouts.app')
@section('content')

@php
  $windowDays = (int) config('lvplanning.avg_sales_window_days', 30);
  $flagIcon = fn (string $flag) => in_array($flag, ['out', 'danger'], true)
      ? 'bi-exclamation-triangle-fill'
      : ($flag === 'watch' ? 'bi-exclamation-circle' : ($flag === 'unknown' ? 'bi-question-circle' : 'bi-check-circle'));
@endphp

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    أيام التغطية = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي لآخر {{ $windowDays }} يوم.
    الشاشة دي بديل شغل "النواقص" — بدل ما تكتشف إن اللون خلص، بتشوف قبلها بأسابيع.
    خطر = {{ config('lvplanning.coverage.danger_days') }} يوم أو أقل · مراقبة = لحد {{ config('lvplanning.coverage.watch_days') }} يوم.
  </span>
</div>

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="hint">({{ count($rows) }} موديل)</span></span>
    <a href="{{ route('io.export.coverage') }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-download" aria-hidden="true"></i> تصدير</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <th>الكود</th><th>الموديل</th><th class="num">مبيعات {{ $windowDays }} يوم</th>
        <th class="num">متوسط يومي</th><th class="num">الرصيد</th><th class="num">مخزون الأمان</th><th class="num">المتاح</th>
        <th class="num">أيام التغطية</th><th>الحالة</th><th class="num">آخر سحب</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          /* الأيام اللي اتقسمت عليها المبيعات فعلًا: متوسط يومي = مبيعات ÷ أيام الفترة.
             لو مفيش متوسط (مفيش مبيعات) بنعرض نافذة الإعداد. */
          $days = $r['avg_daily'] > 0 ? (int) round($r['sold_window'] / $r['avg_daily']) : $windowDays;
          $rowCls = in_array($r['flag'], ['out', 'danger'], true) ? 'table-danger' : ($r['flag'] === 'watch' ? 'table-warning' : '');
        @endphp
        <tr class="{{ $rowCls }}">
          <td class="num">{{ $r['model']->code }}</td>
          <td>{{ $r['model']->name }}</td>
          <td class="num">{{ number_format($r['sold_window']) }}</td>
          <td class="num">
            {{ lv_num($r['avg_daily'], 2) }}
            @include('partials.fx', [
              't'   => 'متوسط البيع اليومي',
              'f'   => 'مبيعات ' . number_format($r['sold_window']) . ' ÷ ' . $days . ' يوم = ' . lv_num($r['avg_daily'], 2) . ' قطعة/يوم',
              'why' => 'مبيعات أحدث فترة كاملة مقسومة على عدد أيامها',
            ])
          </td>
          <td class="num">{{ number_format($r['stock']) }}</td>
          <td class="num">{{ number_format($r['safety']) }}</td>
          <td class="num">{{ number_format($r['usable']) }}</td>
          <td class="num fw-bold">
            {{ $r['cover_days'] !== null ? lv_num($r['cover_days'], 1) : '—' }}
            @if($r['cover_days'] !== null)
              @include('partials.fx', [
                't'   => 'أيام التغطية',
                'f'   => '(الرصيد ' . number_format($r['stock']) . ' − مخزون الأمان ' . number_format($r['safety']) . ') ÷ متوسط يومي ' . lv_num($r['avg_daily'], 2) . ' = ' . lv_num($r['cover_days'], 1) . ' يوم',
                'why' => 'الرصيد المتاح بعد خصم مخزون الأمان يكفي كام يوم بيع',
              ])
            @else
              @include('partials.fx', [
                't'   => 'أيام التغطية',
                'f'   => 'مفيش مبيعات مسجلة — المتوسط اليومي = 0، فالتغطية مش محسوبة',
                'why' => 'ارفع مبيعات الموديل من شاشة الاستيراد',
              ])
            @endif
          </td>
          <td>
            <span class="badge bg-{{ \App\Services\CoverageService::flagColor($r['flag']) }}">
              <i class="bi {{ $flagIcon($r['flag']) }}" aria-hidden="true"></i> {{ $r['flag_label'] }}
            </span>
          </td>
          <td class="num hint">{{ $r['last_pull'] ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="10">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش موديلات نشطة، أو لسه مفيش داتا مبيعات/أرصدة.</div>
              <div class="hint">ارفعها من <a href="{{ route('io.index') }}">شاشة الاستيراد</a>.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer hint">
    المتاح = الرصيد − مخزون الأمان · أيام التغطية = المتاح ÷ متوسط يومي · دوس على ⓘ جنب أي رقم تشوف حسبته بالأرقام.
  </div>
</div>
@endsection
