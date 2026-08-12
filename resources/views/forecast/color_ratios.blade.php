@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <b>ليه الشاشة دي موجودة؟</b>
  المبيعات مش متاحة باللون من المصدر — بنستنتج النسب من صرف المخزن الرئيسي.
  "مش أدق حاجة، بس دي اللي في إيدنا". تقدر تعدّل النسب يدوي هنا، وكل تعديل بيتسجّل باسمك.
  مجموع النسب لازم = 100%.
</div>

<div class="card mb-3">
  <div class="card-header">اختيار الموديل والسنة</div>
  <form method="get" class="card-body d-flex gap-2 flex-wrap align-items-end">
    <div style="width:320px"><label class="form-label">الموديل</label>
      <select name="product_model_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— اختر —</option>
        @foreach($models as $k=>$v)<option value="{{ $k }}" @selected($modelId==$k)>{{ $v }}</option>@endforeach
      </select></div>
    <div style="width:120px"><label class="form-label">السنة</label>
      <input type="number" name="year" class="form-control form-control-sm" value="{{ $year }}"></div>
    <button class="btn btn-plum btn-sm">عرض</button>
  </form>
</div>

@if($modelId)
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="{{ route('planning.color-ratios.save') }}">@csrf
      <input type="hidden" name="product_model_id" value="{{ $modelId }}">
      <input type="hidden" name="year" value="{{ $year }}">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>النسب المعتمدة</span>
          <span class="badge bg-{{ abs($total-100) < 0.5 ? 'success' : 'danger' }}">المجموع {{ round($total,2) }}%</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm line-table mb-0">
            <thead><tr><th style="width:35px">م</th><th>اللون</th><th style="width:130px">النسبة %</th><th>المصدر</th><th>آخر تعديل</th><th style="width:40px"></th></tr></thead>
            <tbody id="lines">
              @foreach($rows as $i => $r)
                <tr>
                  <td class="text-center row-no">{{ $i+1 }}</td>
                  <td><select name="ratios[{{ $i }}][color_id]">
                      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected($r->color_id==$k)>{{ $v }}</option>@endforeach
                    </select></td>
                  <td><input type="number" step="0.001" name="ratios[{{ $i }}][ratio_pct]" value="{{ $r->ratio_pct }}"></td>
                  <td class="hint">{{ $r->source_name }}</td>
                  <td class="hint">{{ $r->updatedBy?->name }} {{ $r->updated_at?->format('Y-m-d') }}</td>
                  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="LV.remove(this,'lines')"><i class="bi bi-x"></i></button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ النسب</button>
          <button type="button" class="btn btn-outline-plum btn-sm" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> لون</button>
        </div>
      </div>
    </form>

    <template id="lineTpl">
      <td class="text-center row-no">1</td>
      <td><select name="ratios[__IDX__][color_id]">
          @foreach($colors as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select></td>
      <td><input type="number" step="0.001" name="ratios[__IDX__][ratio_pct]" value="0"></td>
      <td class="hint">يدوي</td><td></td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="LV.remove(this,'lines')"><i class="bi bi-x"></i></button></td>
    </template>
    @include('partials.lines_js',['startIndex'=>max($rows->count(),1)])
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">النسب المستنتجة من صرف المخزن ({{ $year }})</div>
      <table class="table table-sm mb-0">
        <thead><tr><th>اللون</th><th>النسبة %</th></tr></thead>
        <tbody>
        @forelse($derived as $colorId => $pct)
          <tr>
            <td>{{ \App\Models\Color::find($colorId)?->label ?? $colorId }}</td>
            <td class="num">{{ $pct }}</td>
          </tr>
        @empty
          <tr><td colspan="2" class="text-center text-muted py-3">
            مفيش حركة صرف مسجلة للموديل ده في {{ $year }} — دخّل النسب يدوي.
          </td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
@endsection
