@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  الشاشة دي للأدمن بس. <b>المسح</b> بيشيل كل بيانات الشغل (طلبات، أحواض، أوامر، تقارير، تعليقات)
  ويسيب <b>المستخدمين والأدوار والصلاحيات ودورات الاعتماد</b> زي ما هي.
  <b>التوليد</b> بيملا السيستم بداتا ديمو مترابطة: طلبات في كل مرحلة، أحواض في كل حالة،
  أوامر شغل شغالة ومقفولة، ومبيعات 18 شهر — عشان كل شاشة تبان وهي شغّالة.
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header text-success"><i class="bi bi-database-add"></i> توليد داتا ديمو كاملة</div>
      <form method="post" action="{{ route('data.generate') }}" class="card-body">@csrf
        <ul class="small mb-3" style="line-height:1.9">
          <li>12 مورد · 8 مصانع · 6 مخازن · 8 خامات</li>
          <li>~250 كود لون (بنفس مشكلة التكرار والدمج الحقيقية)</li>
          <li>~30 موديل بمقاساتهم وقائمة إكسسواراتهم</li>
          <li>45 طلب شراء موزّعين على كل المراحل</li>
          <li>38 حوض في كل الحالات: تحت الفحص، متفحص، مفرج عنه</li>
          <li>أوامر شغل من المسودة للمقفول، ببيانات قص واستلامات</li>
          <li>18 شهر مبيعات + أرصدة + فوركاست + مخزون أمان</li>
          <li>نقاشات على المستندات زي التيكيتات</li>
        </ul>

        <div class="alert alert-warning py-2 small mb-3">
          <b>التوليد بيمسح الموجود الأول.</b> أرقام المستندات متسلسلة، فمينفعش نولّد فوق داتا موجودة.
          المستخدمون والأدوار ودورات الاعتماد بيفضلوا زي ما هم.
        </div>

        <label class="form-label req">اكتب كلمة «توليد» للتأكيد</label>
        <input name="confirm" class="form-control form-control-sm mb-2" placeholder="توليد" required>
        <button class="btn btn-success btn-sm w-100"
                onclick="return confirm('هيتولّد داتا ديمو. متأكد؟')">
          <i class="bi bi-magic"></i> ولّد الداتا
        </button>
        <div class="hint mt-2">ممكن تاخد من 20 لـ 60 ثانية. متقفلش الصفحة.</div>
      </form>
    </div>

    <div class="card">
      <div class="card-header text-danger"><i class="bi bi-trash3"></i> مسح كل بيانات الشغل</div>
      <form method="post" action="{{ route('data.reset') }}" class="card-body">@csrf
        <div class="alert alert-danger py-2 small mb-3">
          <b>مفيش رجوع.</b> كل الطلبات والأحواض والأوامر والتقارير والتعليقات هتتمسح.
          المستخدمون والأدوار ودورات الاعتماد هيفضلوا.
        </div>
        <label class="form-label req">اكتب كلمة «مسح» للتأكيد</label>
        <input name="confirm" class="form-control form-control-sm mb-2" placeholder="مسح" required>
        <button class="btn btn-danger btn-sm w-100"
                onclick="return confirm('هيتمسح كل بيانات الشغل نهائيًا. متأكد؟')">
          <i class="bi bi-exclamation-octagon"></i> امسح كل شيء
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">الموجود دلوقتي</div>
      <div class="card-body">
        <div class="row g-2">
          @foreach($counts as $label => $n)
            <div class="col-6 col-md-4 col-lg-3">
              <div class="stat py-2">
                <div class="v num" style="font-size:1.25rem">{{ number_format($n) }}</div>
                <div class="l">{{ $label }}</div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      <div class="card-footer bg-white hint">
        نفس الحاجة من التيرمنال:
        <code style="direction:ltr;display:inline-block">php artisan lv:demo</code> ·
        <code style="direction:ltr;display:inline-block">php artisan lv:reset</code>
      </div>
    </div>
  </div>
</div>
@endsection
