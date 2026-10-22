@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    مخزون الأمان بيتخصم من الرصيد قبل حساب أيام التغطية.
    فايدته إنك لما ييجي طلب كبير مفاجئ تسحب منه وتغطي، وبعدين تعوّضه —
    زي حنفية بتفتحها وتقفلها.
  </span>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><span><i class="bi bi-shield-check" aria-hidden="true"></i> تحديد مخزون أمان</span></div>
      <form method="post" action="{{ route('planning.safety-stock.save') }}" class="card-body">@csrf
        <div class="mb-3">
          <label class="form-label req" for="ss-model">الموديل</label>
          <select id="ss-model" name="product_model_id" class="form-select" required>
            <option value="">— اختر الموديل —</option>
            @foreach($models as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ss-color">اللون</label>
          <select id="ss-color" name="color_id" class="form-select">
            <option value="">كل الألوان</option>
            @foreach($colors as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label req" for="ss-qty">الكمية</label>
          <div class="input-group">
            <input id="ss-qty" type="number" step="0.01" min="0" name="qty_pcs" class="form-control" placeholder="مثال: 500" required>
            <span class="input-group-text">قطعة</span>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ss-days">أو بأيام تغطية <span class="hint">(اختياري)</span></label>
          <div class="input-group">
            <input id="ss-days" type="number" min="0" name="cover_days" class="form-control" placeholder="مثال: 15">
            <span class="input-group-text">يوم</span>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ss-notes">ملاحظات</label>
          <input id="ss-notes" name="notes" class="form-control" placeholder="مثال: موسم الصيف">
        </div>
        <div class="form-actions">
          <button class="btn btn-plum w-100"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><span>{{ $title }} <span class="hint">({{ number_format($rows->total()) }})</span></span></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الموديل</th><th>اللون</th><th class="num">الكمية (قطعة)</th><th class="num">أيام التغطية</th><th>آخر تعديل</th><th>ملاحظات</th></tr></thead>
          <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->productModel?->name }}</td>
              <td>@if($r->color?->code){{ $r->color->code }}@else<span class="badge bg-secondary">كل الألوان</span>@endif</td>
              <td class="num fw-bold">{{ number_format((float)$r->qty_pcs) }}</td>
              <td class="num">{{ $r->cover_days ?? '—' }}</td>
              <td class="hint">{{ $r->updatedBy?->name }} <span class="num">{{ $r->updated_at?->format('Y-m-d') }}</span></td>
              <td class="hint">{{ $r->notes }}</td>
            </tr>
          @empty
            <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-shield ico" aria-hidden="true"></i>
              <div class="t">مفيش مخزون أمان محدد.</div>
              <div class="hint">حدّده من الفورم اللي جنبك — هيتخصم من الرصيد في شاشة أيام التغطية.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">{{ $rows->links() }}</div>
    </div>
  </div>
</div>
@endsection
