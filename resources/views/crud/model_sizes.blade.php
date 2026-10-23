@extends('layouts.app')
@section('purpose', 'المقاسات المتاحة للموديل وقائمة الإكسسوارات (BOM) — منها أمر الشغل بيطلّع احتياجات الكياس والاستيكرات')
@section('content')

<div class="doc-actions" role="toolbar" aria-label="الموديل">
  <span class="da-label">الموديل</span>
  <b>{{ $model->name }}</b>
  @if($model->code)<span class="badge bg-secondary num">{{ $model->code }}</span>@endif
  <div class="da-end">
    <a href="{{ route('product-models.edit', $model->id) }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-pencil" aria-hidden="true"></i> بيانات الموديل</a>
    <a href="{{ route('product-models.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">مقاسات الموديل <span class="hint">— علّم المقاسات اللي بتتشغل</span></div>
      <form method="post" action="{{ route('product-models.sizes.save', $model->id) }}">@csrf
        <div class="card-body">
          @forelse($allSizes as $s)
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="sizes[]" value="{{ $s->id }}"
                     id="s{{ $s->id }}" @checked($model->sizes->contains($s->id))>
              <label class="form-check-label" for="s{{ $s->id }}">{{ $s->name }}</label>
            </div>
          @empty
            <div class="empty-state">
              <i class="bi bi-rulers ico" aria-hidden="true"></i>
              <div class="t">مفيش مقاسات نشطة — ضيفها من شاشة المقاسات.</div>
            </div>
          @endforelse
        </div>
        <div class="card-footer form-actions m-0">
          <button class="btn btn-outline-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ المقاسات</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">خامات الموديل <span class="hint">— الموديل بيتعمل من أنهي خامات؟ طلب الشراء بيطلّعها لوحده لما تختار الموديل</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>الخامة</th><th>اللون</th><th>الاستهلاك للقطعة</th><th>ملاحظات</th><th><span class="visually-hidden">حذف</span></th></tr></thead>
          <tbody>
          @forelse($model->fabrics as $f)
            <tr>
              <td>{{ $f->fabricType?->name }}</td>
              <td>{{ $f->color?->label ?? 'أي لون' }}</td>
              <td class="num">{{ $f->consumption_per_piece ? lv_num($f->consumption_per_piece, 4) . ' ' . $f->unit : '—' }}</td>
              <td class="hint">{{ $f->notes }}</td>
              <td class="text-end"><div class="row-actions">
                <form method="post" action="{{ route('product-models.fabrics.delete', [$model->id, $f->id]) }}"
                      onsubmit="return confirm('حذف الخامة دي من الموديل؟')">@csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف" title="حذف"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>
              </div></td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-layers ico" aria-hidden="true"></i>
              <div class="t">مفيش خامات مسجلة للموديل ده.</div>
              <div class="hint">{{ $model->fabricType ? 'هيتاخد بالخامة الأساسية من كارت الموديل: ' . $model->fabricType->name : 'سجّل خاماته عشان طلب الشراء يطلّعها لوحده.' }}</div></div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="{{ route('product-models.fabrics.add', $model->id) }}" class="card-footer bg-white">@csrf
        <div class="row g-2 align-items-end">
          <div class="col-md-4"><label class="form-label req">الخامة</label>
            <select name="fabric_type_id" class="form-select" required><option value="">— اختر —</option>
              @foreach($fabricTypes as $ft)<option value="{{ $ft->id }}">{{ $ft->name }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label">اللون</label>
            <select name="color_id" class="form-select"><option value="">أي لون</option>
              @foreach($colors as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach
            </select></div>
          <div class="col-md-3"><label class="form-label">الاستهلاك للقطعة</label>
            <div class="input-group">
              <input type="number" step="0.0001" min="0" name="consumption_per_piece" class="form-control" placeholder="مثال: 0.18" value="{{ $model->std_consumption_kg }}">
              <select name="unit" class="form-select" style="max-width:90px" aria-label="الوحدة"><option value="كجم">كجم</option><option value="متر">متر</option></select>
            </div></div>
          <div class="col-md-2"><button class="btn btn-plum w-100"><i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف</button></div>
        </div>
      </form>
    </div>

    <div class="card mb-3">
      <div class="card-header">قائمة الإكسسوارات (BOM) <span class="hint">— الكمية لكل قطعة واحدة من الموديل</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>الإكسسوار</th><th>المقاس</th><th>الكمية لكل قطعة</th><th>ملاحظات</th><th><span class="visually-hidden">حذف</span></th></tr></thead>
          <tbody>
          @forelse($model->boms as $b)
            <tr>
              <td>{{ $b->accessory?->name }}
                @if($b->accessory?->code)<div class="hint num">{{ $b->accessory->code }}</div>@endif</td>
              <td>{{ $b->size?->name ?? 'كل المقاسات' }}</td>
              <td class="num">{{ lv_num($b->qty_per_piece, 4) }} {{ $b->accessory?->unit }}</td>
              <td class="hint">{{ $b->notes }}</td>
              <td class="text-end"><div class="row-actions">
                <form method="post" action="{{ route('product-models.bom.delete', [$model->id, $b->id]) }}"
                      onsubmit="return confirm('حذف الإكسسوار ده من قائمة الموديل؟')">@csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف" title="حذف"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>
              </div></td>
            </tr>
          @empty
            <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش إكسسوارات مسجلة.</div>
              <div class="hint">من غيرها أوامر الشغل مش هتطلع احتياجات الكياس والاستيكرات.</div>
            </div>
          </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">إضافة إكسسوار</div>
      <form method="post" action="{{ route('product-models.bom.add', $model->id) }}">@csrf
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label req">الإكسسوار</label>
              <select name="accessory_id" class="form-select" required>
                <option value="">— اختر —</option>
                @foreach($accessories as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">المقاس</label>
              <select name="size_id" class="form-select">
                <option value="">كل المقاسات</option>
                @foreach($model->sizes as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label req">الكمية لكل قطعة</label>
              <input type="number" step="0.0001" min="0" name="qty_per_piece" value="1" class="form-control" required placeholder="مثال: 1">
              <div class="hint">بوحدة الإكسسوار</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">ملاحظات</label>
              <input name="notes" class="form-control" placeholder="اختياري">
            </div>
          </div>
        </div>
        <div class="card-footer form-actions m-0">
          <button class="btn btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة للقائمة</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
