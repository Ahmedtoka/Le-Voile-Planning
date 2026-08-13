@extends('layouts.app')
@section('content')

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v text-primary">{{ $consignments['ready'] }}</div>
      <div class="l">أحواض جاهزة للتشغيل</div>
      <div class="hint mt-1">{{ number_format($consignments['ready_kg'], 0) }} كجم متاحة</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v text-warning">{{ number_format($consignments['hold_kg'], 0) }}</div>
      <div class="l">كجم محجوزة تحت الفحص</div>
      <div class="hint mt-1">
        {{ $consignments['awaiting_inspection'] }} مستني فحص ·
        {{ $consignments['awaiting_lab'] }} معمل ·
        {{ $consignments['awaiting_release'] }} إفراج
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v">{{ $workOrders['open'] }}</div>
      <div class="l">أوامر شغل مفتوحة</div>
      <div class="hint mt-1">{{ number_format($workOrders['outstanding'] ?? 0) }} قطعة لسه على المصانع</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v {{ $workOrders['late'] > 0 ? 'text-danger' : '' }}">{{ $workOrders['late'] }}</div>
      <div class="l">أوامر شغل متأخرة</div>
      <div class="hint mt-1">{{ $workOrders['danger'] }} انحرافهم خارج الحدود</div>
    </div>
  </div>
</div>

<div class="row g-3">

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> أخطر 10 موديلات في التغطية</span>
        <a href="{{ route('planning.coverage') }}" class="btn btn-sm btn-outline-plum">الكل</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr>
            <th>الموديل</th><th>متوسط يومي</th><th>الرصيد</th><th>أيام التغطية</th><th>الحالة</th>
          </tr></thead>
          <tbody>
          @forelse($coverage as $c)
            <tr>
              <td>{{ $c['model']->name }}</td>
              <td class="num">{{ $c['avg_daily'] }}</td>
              <td class="num">{{ number_format($c['stock']) }}</td>
              <td class="num">{{ $c['cover_days'] ?? '—' }}</td>
              <td><span class="badge bg-{{ \App\Services\CoverageService::flagColor($c['flag']) }}">{{ $c['flag_label'] }}</span></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-3">مفيش داتا مبيعات أو أرصدة لسه — ارفعها من شاشة الاستيراد.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-building"></i> تحميل المصانع</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>المصنع</th><th>أوامر مفتوحة</th><th>متأخرة</th><th>قطع لسه عليه</th></tr></thead>
          <tbody>
          @forelse($factoryLoad as $f)
            <tr>
              <td>{{ $f['factory']->name }}</td>
              <td class="num">{{ $f['open'] }}</td>
              <td class="num {{ $f['late'] ? 'text-danger fw-bold' : '' }}">{{ $f['late'] }}</td>
              <td class="num">{{ number_format($f['outstanding'] ?? 0) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-3">مفيش أوامر شغل مفتوحة.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-square"></i> مستني اعتمادك</span>
        <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-outline-plum">الكل</a>
      </div>
      <ul class="list-group list-group-flush">
        @forelse($myApprovals as $a)
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <div class="fw-bold">{{ $a->subject_no }}</div>
              <div class="hint">{{ $a->currentStepRow()?->title }}</div>
            </div>
            <span class="hint">{{ $a->created_at->diffForHumans() }}</span>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-3">مفيش حاجة مستنية اعتمادك.</li>
        @endforelse
      </ul>
    </div>

    <div class="card">
      <div class="card-header text-danger"><i class="bi bi-exclamation-triangle"></i> أوامر شغل متأخرة</div>
      <ul class="list-group list-group-flush">
        @forelse($lateOrders as $w)
          <li class="list-group-item py-2">
            <a href="{{ route('work-orders.show', $w) }}" class="fw-bold">{{ $w->wo_no }}</a>
            <div class="hint">
              {{ $w->factory?->name }} · الحوض {{ $w->consignment?->consignment_no }} ·
              متأخر {{ (int) $w->due_date->diffInDays(now()) }} يوم · متبقي {{ number_format($w->outstanding_pieces) }} قطعة
            </div>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-3">مفيش تأخير. تمام.</li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
