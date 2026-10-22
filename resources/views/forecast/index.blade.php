@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    <b>الفوركاست دلوقتي مش مبني على Data Set نضيف.</b>
    الأساس = مبيعات نفس الشهر السنة اللي فاتت × نسبة النمو، موزّعة على الألوان بالنسب اليدوية.
    لما الداتا تبقى نضيفة (مبيعات باللون، وأرصدة مجرودة) المحرك ده يتبدّل بواحد إحصائي حقيقي.
  </span>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> توليد فوركاست</span></div>
      <form method="post" action="{{ route('planning.forecast.generate') }}" class="card-body">@csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label req" for="g-model">الموديل</label>
            <select id="g-model" name="product_model_id" class="form-select" required>
              <option value="">— اختر الموديل —</option>
              @foreach($models as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label req" for="g-year">السنة</label>
            <input id="g-year" type="number" name="year" class="form-control w-num" value="{{ now()->year + 1 }}" placeholder="مثال: {{ now()->year + 1 }}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="g-growth">نسبة النمو</label>
            <div class="input-group">
              <input id="g-growth" type="number" step="0.01" name="growth_pct" class="form-control" value="10" placeholder="مثال: 10">
              <span class="input-group-text">%</span>
            </div>
          </div>
        </div>
        <div class="form-actions mt-3">
          <button class="btn btn-plum"><i class="bi bi-magic" aria-hidden="true"></i> توليد الفوركاست</button>
          <span class="hint">مبيعات نفس الشهر السنة اللي فاتت × (1 + النمو) موزّعة على الألوان</span>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><span><i class="bi bi-arrow-repeat" aria-hidden="true"></i> تحديث الفعلي</span></div>
      <div class="card-body">
        <p class="hint mb-3">بيقرا المبيعات المقفولة لسنة {{ $year }} ويحطها في عمود «الفعلي» عشان تقارنها بالمتوقع.</p>
        <form method="post" action="{{ route('planning.forecast.sync') }}">@csrf
          <input type="hidden" name="year" value="{{ $year }}">
          <button class="btn btn-outline-plum w-100"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> تحديث الفعلي من المبيعات المقفولة</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>{{ $title }}
      <span class="hint">إجمالي المتوقع <span class="num">{{ number_format($totals['forecast']) }}</span> · الفعلي <span class="num">{{ number_format($totals['actual']) }}</span></span>
    </span>
    <form class="filter-bar" method="get" role="search" aria-label="فلترة الفوركاست">
      <label class="visually-hidden" for="f-year">السنة</label>
      <input id="f-year" type="number" name="year" class="form-control w-num" value="{{ $year }}" placeholder="السنة">
      <label class="visually-hidden" for="f-model">الموديل</label>
      <select id="f-model" name="product_model_id" class="form-select w-wide" onchange="this.form.submit()">
        <option value="">كل الموديلات</option>
        @foreach($models as $k=>$v)<option value="{{ $k }}" @selected($modelId==$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($modelId || request('year'))
        <a class="btn btn-sm btn-ghost" href="{{ url()->current() }}" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>الموديل</th><th>اللون</th><th>الشهر</th><th class="num">الأساس</th><th class="num">النمو %</th>
        <th class="num">المتوقع</th><th class="num">الفعلي</th><th class="num">التحقق %</th><th>المصدر</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->productModel?->name }}</td>
          <td>{{ $r->color?->code ?? 'الكل' }}</td>
          <td>{{ $r->month_name }}</td>
          <td class="num">{{ number_format((float)$r->base_qty) }}</td>
          <td class="num">{{ lv_num($r->growth_pct, 2) }}</td>
          <td class="num fw-bold">
            {{ number_format((float)$r->forecast_qty) }}
            @include('partials.fx', ['t' => 'المتوقع',
              'f' => 'الأساس ' . number_format((float)$r->base_qty) . ' × (1 + ' . lv_num($r->growth_pct, 2) . '%) = ' . number_format((float)$r->forecast_qty),
              'why' => 'الأساس = مبيعات نفس الشهر السنة اللي فاتت'])
          </td>
          <td class="num">{{ number_format((float)$r->actual_qty) }}</td>
          <td class="num">
            @if($r->achievement_pct !== null)
              <span class="badge bg-{{ $r->achievement_pct >= 85 ? 'success' : ($r->achievement_pct >= 60 ? 'warning' : 'danger') }}">
                {{ lv_num($r->achievement_pct, 1) }}%
              </span>
              @include('partials.fx', ['t' => 'نسبة التحقق',
                'f' => 'الفعلي ' . number_format((float)$r->actual_qty) . ' ÷ المتوقع ' . number_format((float)$r->forecast_qty) . ' × 100 = ' . lv_num($r->achievement_pct, 1) . '%',
                'why' => '85% فأكتر أخضر · 60% فأكتر أصفر · أقل أحمر'])
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td><span class="badge {{ $r->source === 'manual' ? 'bg-primary' : 'bg-secondary' }}">{{ $r->source === 'manual' ? 'يدوي' : 'مولّد' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="9">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش فوركاست لسنة {{ $year }}.</div>
              <div class="hint">ولّده من كارت «توليد فوركاست» فوق — لازم يكون فيه مبيعات للسنة اللي قبلها.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->withQueryString()->links() }}</div>
</div>
@endsection
