@extends('layouts.app')
@section('content')

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">مقاسات الموديل</div>
      <form method="post" action="{{ route('product-models.sizes.save', $model->id) }}" class="card-body">@csrf
        @foreach($allSizes as $s)
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="sizes[]" value="{{ $s->id }}"
                   id="s{{ $s->id }}" @checked($model->sizes->contains($s->id))>
            <label class="form-check-label" for="s{{ $s->id }}">{{ $s->name }}</label>
          </div>
        @endforeach
        <button class="btn btn-plum btn-sm w-100 mt-3">حفظ المقاسات</button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">قائمة الإكسسوارات (BOM) — {{ $model->name }}</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الإكسسوار</th><th>المقاس</th><th>الكمية لكل قطعة</th><th>ملاحظات</th><th></th></tr></thead>
          <tbody>
          @forelse($model->boms as $b)
            <tr>
              <td>{{ $b->accessory?->name }}</td>
              <td>{{ $b->size?->name ?? 'كل المقاسات' }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$b->qty_per_piece, 4), '0'), '.') }}</td>
              <td class="hint">{{ $b->notes }}</td>
              <td>
                <form method="post" action="{{ route('product-models.bom.delete', [$model->id, $b->id]) }}"
                      onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger py-0" aria-label="حذف" title="حذف"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-3">
              مفيش إكسسوارات مسجلة. من غيرها أوامر الشغل مش هتطلع احتياجات الكياس والاستيكرات.
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">إضافة إكسسوار</div>
      <form method="post" action="{{ route('product-models.bom.add', $model->id) }}" class="card-body">@csrf
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label req">الإكسسوار</label>
            <select name="accessory_id" class="form-select form-select-sm" required>
              <option value="">— اختر —</option>
              @foreach($accessories as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">المقاس</label>
            <select name="size_id" class="form-select form-select-sm">
              <option value="">كل المقاسات</option>
              @foreach($model->sizes as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label req">الكمية/قطعة</label>
            <input type="number" step="0.0001" name="qty_per_piece" value="1" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">ملاحظات</label>
            <input name="notes" class="form-control form-control-sm">
          </div>
        </div>
        <button class="btn btn-plum btn-sm mt-3">إضافة</button>
      </form>
    </div>
  </div>
</div>
@endsection
