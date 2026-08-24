@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  مخزون الأمان بيتخصم من الرصيد قبل حساب أيام التغطية.
  فايدته إنك لما ييجي طلب كبير مفاجئ تسحب منه وتغطي، وبعدين تعوّضه —
  زي حنفية بتفتحها وتقفلها.
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">تحديد مخزون أمان</div>
      <form method="post" action="{{ route('planning.safety-stock.save') }}" class="card-body">@csrf
        <div class="mb-2"><label class="form-label req">الموديل</label>
          <select name="product_model_id" class="form-select form-select-sm" required>
            <option value="">—</option>
            @foreach($models as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select></div>
        <div class="mb-2"><label class="form-label">اللون</label>
          <select name="color_id" class="form-select form-select-sm">
            <option value="">كل الألوان</option>
            @foreach($colors as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select></div>
        <div class="mb-2"><label class="form-label req">الكمية (قطعة)</label>
          <input type="number" step="0.01" name="qty_pcs" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label">أو بأيام تغطية</label>
          <input type="number" name="cover_days" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm"></div>
        <button class="btn btn-plum btn-sm w-100">حفظ</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">{{ $title }}</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الموديل</th><th>اللون</th><th>الكمية</th><th>أيام التغطية</th><th>آخر تعديل</th><th>ملاحظات</th></tr></thead>
          <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->productModel?->name }}</td>
              <td>{{ $r->color?->code ?? 'الكل' }}</td>
              <td class="num">{{ number_format((float)$r->qty_pcs) }}</td>
              <td class="num">{{ $r->cover_days ?? '—' }}</td>
              <td class="hint">{{ $r->updatedBy?->name }} {{ $r->updated_at?->format('Y-m-d') }}</td>
              <td class="hint">{{ $r->notes }}</td>
            </tr>
          @empty
            <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش مخزون أمان محدد.</div>
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
