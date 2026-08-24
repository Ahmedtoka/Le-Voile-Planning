@extends('layouts.app')
@section('content')
<div class="row g-3">

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-palette" aria-hidden="true"></i> استيراد الألوان</div>
      <form method="post" action="{{ route('io.import.colors') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="hint mb-2">
          الأعمدة: <code>code, name, family, hex, is_basic, legacy_code, merged_into</code><br>
          عمود <code>merged_into</code> بيعمل الدمج تلقائيًا. مفيش حذف — بس تحديث وإضافة ودمج.
        </div>
        <input type="file" name="file" class="form-control form-control-sm mb-2" accept=".xlsx,.xls,.csv" required>
        <button class="btn btn-plum btn-sm w-100">استيراد</button>
      </form>
      <div class="card-footer bg-white d-flex gap-2">
        <a href="{{ route('io.template','colors') }}" class="btn btn-outline-secondary btn-sm flex-fill">قالب</a>
        <a href="{{ route('io.export.colors') }}" class="btn btn-outline-plum btn-sm flex-fill">تصدير</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-cash-coin" aria-hidden="true"></i> استيراد المبيعات</div>
      <form method="post" action="{{ route('io.import.sales') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="hint mb-2">
          الأعمدة: <code>model_code, qty, unit</code>.
          الدستة بتتحوّل لقطعة تلقائيًا، وأي رقم شاذ بيتعلّم للمراجعة.
        </div>
        <div class="alert alert-warning py-2 small">
          مبيعات الشهر ما بتتقفلش غير يوم {{ config('lvplanning.sales_lock_day_next_month') }}
          من الشهر التالي — لأن الأرقام بتتعدّل طول الشهر.
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label">من</label>
            <input type="date" name="period_from" class="form-control form-control-sm" value="{{ now()->startOfMonth()->toDateString() }}" required></div>
          <div class="col-6"><label class="form-label">إلى</label>
            <input type="date" name="period_to" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
        </div>
        <input type="file" name="file" class="form-control form-control-sm mb-2" accept=".xlsx,.xls,.csv" required>
        <button class="btn btn-plum btn-sm w-100">استيراد</button>
      </form>
      <div class="card-footer bg-white">
        <a href="{{ route('io.template','sales') }}" class="btn btn-outline-secondary btn-sm w-100">قالب</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-boxes" aria-hidden="true"></i> استيراد الأرصدة</div>
      <form method="post" action="{{ route('io.import.stock') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="hint mb-2">
          الأعمدة: <code>model_code, color_code, warehouse_code, qty</code>.
          لو اللون مدموج، الرصيد بيتسجّل على الكود الفعّال.
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label">تاريخ السحب</label>
            <input type="date" name="pulled_at" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
          <div class="col-6"><label class="form-label">مصدر الرصيد</label>
            <select name="reliability" class="form-select form-select-sm">
              <option value="book">دفتري</option>
              <option value="counted">مجرود</option>
              <option value="estimated">تقديري</option>
            </select></div>
        </div>
        <input type="file" name="file" class="form-control form-control-sm mb-2" accept=".xlsx,.xls,.csv" required>
        <button class="btn btn-plum btn-sm w-100">استيراد</button>
      </form>
      <div class="card-footer bg-white">
        <a href="{{ route('io.template','stock') }}" class="btn btn-outline-secondary btn-sm w-100">قالب</a>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-download" aria-hidden="true"></i> تصدير</div>
      <div class="card-body d-flex gap-2 flex-wrap">
        <a href="{{ route('io.export.colors') }}" class="btn btn-outline-plum btn-sm">الألوان</a>
        <a href="{{ route('io.export.consignments') }}" class="btn btn-outline-plum btn-sm">الأحواض</a>
        <a href="{{ route('io.export.work-orders') }}" class="btn btn-outline-plum btn-sm">أوامر الشغل</a>
        <a href="{{ route('io.export.coverage') }}" class="btn btn-outline-plum btn-sm">أيام التغطية</a>
      </div>
    </div>
  </div>
</div>
@endsection
