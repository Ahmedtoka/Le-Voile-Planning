@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>
    <b>ليه الشاشة دي موجودة؟</b>
    المبيعات مش متاحة باللون من المصدر — بنستنتج النسب من صرف المخزن الرئيسي.
    "مش أدق حاجة، بس دي اللي في إيدنا". تقدر تعدّل النسب يدوي هنا، وكل تعديل بيتسجّل باسمك.
    مجموع النسب لازم = 100%.
  </span>
</div>

<div class="card mb-3">
  <div class="card-header">
    <span><i class="bi bi-pie-chart" aria-hidden="true"></i> اختيار الموديل والسنة</span>
    <form class="filter-bar" method="get" role="search" aria-label="اختيار الموديل والسنة">
      <label class="visually-hidden" for="cr-model">الموديل</label>
      <select id="cr-model" name="product_model_id" class="form-select w-wide" onchange="this.form.submit()">
        <option value="">— اختر الموديل —</option>
        @foreach($models as $k=>$v)<option value="{{ $k }}" @selected($modelId==$k)>{{ $v }}</option>@endforeach
      </select>
      <label class="visually-hidden" for="cr-year">السنة</label>
      <input id="cr-year" type="number" name="year" class="form-control w-num" value="{{ $year }}" placeholder="السنة">
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel" aria-hidden="true"></i> عرض</button>
    </form>
  </div>
</div>

@if($modelId)
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="{{ route('planning.color-ratios.save') }}">@csrf
      <input type="hidden" name="product_model_id" value="{{ $modelId }}">
      <input type="hidden" name="year" value="{{ $year }}">
      <div class="card">
        <div class="card-header">
          <span>النسب المعتمدة</span>
          @php $sumOk = abs($total-100) < 0.5; @endphp
          <span class="badge bg-{{ $sumOk ? 'success' : 'danger' }}" title="{{ $sumOk ? 'المجموع مظبوط' : 'المجموع لازم يبقى 100%' }}">
            <i class="bi {{ $sumOk ? 'bi-check-circle' : 'bi-exclamation-triangle-fill' }}" aria-hidden="true"></i>
            المجموع {{ lv_num($total, 2) }}%
          </span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm line-table mb-0">
            <thead><tr><th style="width:35px">م</th><th class="req">اللون</th><th class="req" style="width:130px">النسبة %</th><th>المصدر</th><th>آخر تعديل</th><th style="width:40px"><span class="visually-hidden">حذف</span></th></tr></thead>
            <tbody id="lines">
              @foreach($rows as $i => $r)
                <tr>
                  <td class="text-center row-no">{{ $i+1 }}</td>
                  <td><select name="ratios[{{ $i }}][color_id]" aria-label="اللون">
                      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected($r->color_id==$k)>{{ $v }}</option>@endforeach
                    </select></td>
                  <td class="num"><input type="number" step="0.001" min="0" max="100" name="ratios[{{ $i }}][ratio_pct]" value="{{ $r->ratio_pct }}" aria-label="النسبة %"></td>
                  <td class="hint">{{ $r->source_name }}</td>
                  <td class="hint">{{ $r->updatedBy?->name }} <span class="num">{{ $r->updated_at?->format('Y-m-d') }}</span></td>
                  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer form-actions">
          <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ النسب</button>
          <button type="button" class="btn btn-outline-plum" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة لون</button>
          <span class="hint">المجموع لازم = 100%</span>
        </div>
      </div>
    </form>

    <template id="lineTpl">
      <td class="text-center row-no">1</td>
      <td><select name="ratios[__IDX__][color_id]" aria-label="اللون">
          @foreach($colors as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select></td>
      <td class="num"><input type="number" step="0.001" min="0" max="100" name="ratios[__IDX__][ratio_pct]" value="0" aria-label="النسبة %"></td>
      <td class="hint">يدوي</td><td></td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
    </template>
    @include('partials.lines_js',['startIndex'=>max($rows->count(),1)])
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><span>النسب المستنتجة من صرف المخزن <span class="hint">({{ $year }})</span></span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 no-min">
          <thead><tr><th>اللون</th><th class="num">النسبة %</th></tr></thead>
          <tbody>
          @forelse($derived as $colorId => $pct)
            <tr>
              <td>{{ \App\Models\Color::find($colorId)?->label ?? $colorId }}</td>
              <td class="num">{{ lv_num($pct, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="2">
              <div class="empty-state">
                <i class="bi bi-inbox ico" aria-hidden="true"></i>
                <div class="t">مفيش حركة صرف مسجلة للموديل ده في {{ $year }}.</div>
                <div class="hint">دخّل النسب يدوي في الجدول اللي جنبك.</div>
              </div>
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer hint">نسبة اللون = كمية صرف اللون ÷ إجمالي صرف الموديل × 100 — للاسترشاد، والمعتمد هو اللي بتحفظه.</div>
    </div>
  </div>
</div>
@else
  <div class="card"><div class="card-body">
    <div class="empty-state">
      <i class="bi bi-pie-chart ico" aria-hidden="true"></i>
      <div class="t">اختار موديل من فوق.</div>
      <div class="hint">هتشوف نسبه المعتمدة على الألوان والنسب المستنتجة من صرف المخزن.</div>
    </div>
  </div></div>
@endif
@endsection
