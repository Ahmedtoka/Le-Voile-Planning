@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  أيام التغطية = (الرصيد − مخزون الأمان) ÷ متوسط البيع اليومي لآخر
  {{ config('lvplanning.avg_sales_window_days') }} يوم.
  الشاشة دي بديل شغل "النواقص" — بدل ما تكتشف إن اللون خلص، بتشوف قبلها بأسابيع.
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span>{{ $title }}</span>
    <a href="{{ route('io.export.coverage') }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i> تصدير</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <th>الكود</th><th>الموديل</th><th>مبيعات {{ config('lvplanning.avg_sales_window_days') }} يوم</th>
        <th>متوسط يومي</th><th>الرصيد</th><th>مخزون الأمان</th><th>المتاح</th><th>أيام التغطية</th><th>الحالة</th><th>آخر سحب</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num">{{ $r['model']->code }}</td>
          <td>{{ $r['model']->name }}</td>
          <td class="num">{{ number_format($r['sold_window']) }}</td>
          <td class="num">{{ $r['avg_daily'] }}</td>
          <td class="num">{{ number_format($r['stock']) }}</td>
          <td class="num">{{ number_format($r['safety']) }}</td>
          <td class="num">{{ number_format($r['usable']) }}</td>
          <td class="num fw-bold">{{ $r['cover_days'] ?? '—' }}</td>
          <td><span class="badge bg-{{ \App\Services\CoverageService::flagColor($r['flag']) }}">{{ $r['flag_label'] }}</span></td>
          <td class="num hint">{{ $r['last_pull'] ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="10" class="text-center text-muted py-4">
          مفيش موديلات نشطة، أو لسه مفيش داتا مبيعات/أرصدة. ارفعها من شاشة الاستيراد.
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
