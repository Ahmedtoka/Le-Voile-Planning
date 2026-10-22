@extends('layouts.app')
@section('content')

<style>
.io-card{height:100%;display:flex;flex-direction:column;margin-bottom:0}
.io-card .card-body{flex:1 1 auto}
.io-card .card-footer{display:flex;gap:var(--sp-2);flex-wrap:wrap}
.io-card .card-footer .btn{flex:1 1 auto}
.io-cols{background:var(--lv-surface-3);border:1px solid var(--lv-line);border-radius:var(--r-sm);padding:8px 10px;margin-bottom:12px}
.io-cols code{color:var(--lv-brand-dark);direction:ltr;unicode-bidi:embed;font-size:var(--fs-xs)}
.io-cols .ro-title{margin-bottom:4px}
</style>

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>ارفع ملف إكسيل أو CSV بنفس أعمدة القالب. نزّل «القالب» الأول لو مش متأكد من الأعمدة — الاستيراد بيحدّث ويضيف، ومفيش حذف.</span>
</div>

<div class="row g-3">

  <div class="col-lg-4">
    <div class="card io-card">
      <div class="card-header"><span><i class="bi bi-palette" aria-hidden="true"></i> استيراد الألوان</span></div>
      <form method="post" action="{{ route('io.import.colors') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="io-cols">
          <div class="ro-title">الأعمدة المطلوبة</div>
          <code>code, name, family, hex, is_basic, legacy_code, merged_into</code>
          <div class="hint mt-1">عمود <code>merged_into</code> بيعمل الدمج تلقائيًا. مفيش حذف — بس تحديث وإضافة ودمج.</div>
        </div>
        <div class="mb-3">
          <label class="form-label req" for="imp-colors-file">الملف</label>
          <input id="imp-colors-file" type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <button class="btn btn-plum w-100"><i class="bi bi-upload" aria-hidden="true"></i> استيراد الألوان</button>
      </form>
      <div class="card-footer bg-white">
        <a href="{{ route('io.template','colors') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> القالب</a>
        <a href="{{ route('io.export.colors') }}" class="btn btn-outline-plum"><i class="bi bi-download" aria-hidden="true"></i> تصدير الحالي</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card io-card">
      <div class="card-header"><span><i class="bi bi-cash-coin" aria-hidden="true"></i> استيراد المبيعات</span></div>
      <form method="post" action="{{ route('io.import.sales') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="io-cols">
          <div class="ro-title">الأعمدة المطلوبة</div>
          <code>model_code, qty, unit</code>
          <div class="hint mt-1">الدستة بتتحوّل لقطعة تلقائيًا، وأي رقم شاذ بيتعلّم للمراجعة.</div>
        </div>
        <div class="alert alert-warning py-2">
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          مبيعات الشهر ما بتتقفلش غير يوم {{ config('lvplanning.sales_lock_day_next_month') }}
          من الشهر التالي — لأن الأرقام بتتعدّل طول الشهر.
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label req" for="imp-sales-from">الفترة من</label>
            <input id="imp-sales-from" type="date" name="period_from" class="form-control w-full" value="{{ now()->startOfMonth()->toDateString() }}" required>
          </div>
          <div class="col-6">
            <label class="form-label req" for="imp-sales-to">إلى</label>
            <input id="imp-sales-to" type="date" name="period_to" class="form-control w-full" value="{{ now()->toDateString() }}" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label req" for="imp-sales-file">الملف</label>
          <input id="imp-sales-file" type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <button class="btn btn-plum w-100"><i class="bi bi-upload" aria-hidden="true"></i> استيراد المبيعات</button>
      </form>
      <div class="card-footer bg-white">
        <a href="{{ route('io.template','sales') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> القالب</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card io-card">
      <div class="card-header"><span><i class="bi bi-boxes" aria-hidden="true"></i> استيراد الأرصدة</span></div>
      <form method="post" action="{{ route('io.import.stock') }}" enctype="multipart/form-data" class="card-body">@csrf
        <div class="io-cols">
          <div class="ro-title">الأعمدة المطلوبة</div>
          <code>model_code, color_code, warehouse_code, qty</code>
          <div class="hint mt-1">لو اللون مدموج، الرصيد بيتسجّل على الكود الفعّال.</div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label req" for="imp-stock-date">تاريخ السحب</label>
            <input id="imp-stock-date" type="date" name="pulled_at" class="form-control w-full" value="{{ now()->toDateString() }}" required>
          </div>
          <div class="col-6">
            <label class="form-label" for="imp-stock-rel">مصدر الرصيد</label>
            <select id="imp-stock-rel" name="reliability" class="form-select">
              <option value="book">دفتري</option>
              <option value="counted">مجرود</option>
              <option value="estimated">تقديري</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label req" for="imp-stock-file">الملف</label>
          <input id="imp-stock-file" type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <button class="btn btn-plum w-100"><i class="bi bi-upload" aria-hidden="true"></i> استيراد الأرصدة</button>
      </form>
      <div class="card-footer bg-white">
        <a href="{{ route('io.template','stock') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> القالب</a>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><span><i class="bi bi-download" aria-hidden="true"></i> تصدير</span><span class="hint">إكسيل بنفس أعمدة الشاشة</span></div>
      <div class="card-body actions-bar">
        <a href="{{ route('io.export.colors') }}" class="btn btn-outline-plum"><i class="bi bi-palette" aria-hidden="true"></i> الألوان</a>
        <a href="{{ route('io.export.consignments') }}" class="btn btn-outline-plum"><i class="bi bi-box-seam" aria-hidden="true"></i> الأحواض</a>
        <a href="{{ route('io.export.work-orders') }}" class="btn btn-outline-plum"><i class="bi bi-hammer" aria-hidden="true"></i> أوامر الشغل</a>
        <a href="{{ route('io.export.coverage') }}" class="btn btn-outline-plum"><i class="bi bi-clock-history" aria-hidden="true"></i> أيام التغطية</a>
      </div>
    </div>
  </div>
</div>
@endsection
