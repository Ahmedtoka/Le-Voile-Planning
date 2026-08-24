@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <b>الفوركاست دلوقتي مش مبني على Data Set نضيف.</b>
  الأساس = مبيعات نفس الشهر السنة اللي فاتت × نسبة النمو، موزّعة على الألوان بالنسب اليدوية.
  لما الداتا تبقى نضيفة (مبيعات باللون، وأرصدة مجرودة) المحرك ده يتبدّل بواحد إحصائي حقيقي.
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">عرض</div>
      <form method="get" class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div style="width:130px"><label class="form-label">السنة</label>
          <input type="number" name="year" class="form-control form-control-sm" value="{{ $year }}"></div>
        <div style="width:300px"><label class="form-label">الموديل</label>
          <select name="product_model_id" class="form-select form-select-sm">
            <option value="">كل الموديلات</option>
            @foreach($models as $k=>$v)<option value="{{ $k }}" @selected($modelId==$k)>{{ $v }}</option>@endforeach
          </select></div>
        <button class="btn btn-plum btn-sm">عرض</button>
      </form>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">توليد فوركاست</div>
      <form method="post" action="{{ route('planning.forecast.generate') }}" class="card-body">@csrf
        <div class="row g-2">
          <div class="col-12"><label class="form-label req">الموديل</label>
            <select name="product_model_id" class="form-select form-select-sm" required>
              <option value="">—</option>
              @foreach($models as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-6"><label class="form-label req">السنة</label>
            <input type="number" name="year" class="form-control form-control-sm" value="{{ now()->year + 1 }}" required></div>
          <div class="col-6"><label class="form-label">نسبة النمو %</label>
            <input type="number" step="0.01" name="growth_pct" class="form-control form-control-sm" value="10"></div>
        </div>
        <button class="btn btn-plum btn-sm mt-2 w-100">توليد</button>
      </form>
      <div class="card-footer bg-white">
        <form method="post" action="{{ route('planning.forecast.sync') }}">@csrf
          <input type="hidden" name="year" value="{{ $year }}">
          <button class="btn btn-outline-plum btn-sm w-100">تحديث الفعلي من المبيعات المقفولة</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span>{{ $title }}</span>
    <span class="hint">
      إجمالي المتوقع {{ number_format($totals['forecast']) }} · الفعلي {{ number_format($totals['actual']) }}
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>الموديل</th><th>اللون</th><th>الشهر</th><th>الأساس</th><th>النمو %</th>
        <th>المتوقع</th><th>الفعلي</th><th>التحقق %</th><th>الحالة</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->productModel?->name }}</td>
          <td>{{ $r->color?->code ?? 'الكل' }}</td>
          <td>{{ $r->month_name }}</td>
          <td class="num">{{ number_format((float)$r->base_qty) }}</td>
          <td class="num">{{ $r->growth_pct }}</td>
          <td class="num fw-bold">{{ number_format((float)$r->forecast_qty) }}</td>
          <td class="num">{{ number_format((float)$r->actual_qty) }}</td>
          <td class="num">
            @if($r->achievement_pct !== null)
              <span class="badge bg-{{ $r->achievement_pct >= 85 ? 'success' : ($r->achievement_pct >= 60 ? 'warning' : 'danger') }}">
                {{ $r->achievement_pct }}%
              </span>
            @else — @endif
          </td>
          <td class="hint">{{ $r->source === 'manual' ? 'يدوي' : 'مولّد' }}</td>
        </tr>
      @empty
        <tr><td colspan="9">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش فوركاست للسنة دي.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
