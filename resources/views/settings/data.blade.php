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
    <div class="card mb-3" style="border:2px solid var(--lv-brand)">
      <div class="card-header" style="background:var(--lv-tint);color:var(--lv-brand-ink)">
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> ⓪ الورق الحقيقي — المستندات الفعلية
        <span class="badge bg-{{ 'primary' }} ms-1">الافتراضي</span>
      </div>
      <form method="post" action="{{ route('data.paper') }}" class="card-body">@csrf
        <p class="hint mb-2">
          بيفرّغ السيستم ويدخّل المستندات اللي في إيدك بأرقامها الفعلية، عشان تقارن كل شاشة بالورقة.
        </p>
        <ul class="small mb-3" style="line-height:1.9">
          <li><b>طلب شراء 107</b> — 22 سطر، مياي بالكيلو وتل بالمتر</li>
          <li><b>إذن استلام 1000885</b> (أنس تكس) + الخمس قرارات رفض وتعليق</li>
          <li><b>تقرير فحص 04379</b> — 3 أتواب · 61.7 كجم · رفض 2 توب 8.36 كجم</li>
          <li><b>أمر شغل KB106</b> (الخطيب) — خامتين بحسبة كل واحدة</li>
          <li><b>إذن صرف 1303774</b> — يغطي KB106 و KB107</li>
        </ul>
        <div class="note-box mb-3" style="font-size:.78rem">
          هتشوف على شاشة أمر الشغل إن التل بيدي <b>392</b> والمياي <b>459</b> —
          الفرق ده مخفي تمامًا على الورق، والسيستم بيوريهولك.
        </div>
        <label class="form-label req" for="c0">اكتب «الورق» للتأكيد</label>
        <input id="c0" name="confirm" class="form-control form-control-sm mb-2" placeholder="الورق" required autocomplete="off">
        <button class="btn btn-plum btn-sm w-100" onclick="return confirm('هيتفرّغ السيستم ويتدخّل الورق الحقيقي. متأكد؟')">
          <i class="bi bi-file-earmark-check" aria-hidden="true"></i> ادخّل الورق الحقيقي
        </button>
      </form>
    </div>

    <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header text-success">
        <i class="bi bi-database-add" aria-hidden="true"></i> ① ديمو كامل — بيانات مولّدة للتجربة
      </div>
      <form method="post" action="{{ route('data.generate') }}" class="card-body">@csrf
        <p class="hint mb-2">
          للفهم والتجربة. بيملا السيستم بمستندات في كل مرحلة، فكل دور يلاقي شغل مستني منه
          وكل شاشة تبان وهي شغّالة.
        </p>
        <ul class="small mb-3" style="line-height:1.9">
          <li>كل البيانات الأساسية</li>
          <li>45 طلب شراء موزّعين على كل المراحل</li>
          <li>38 حوض: تحت الفحص، متفحص، مفرج عنه</li>
          <li>ماركرات وأوامر شغل ببيانات قص واستلامات</li>
          <li>18 شهر مبيعات + أرصدة + فوركاست</li>
          <li>نقاشات على المستندات</li>
        </ul>
        <div class="alert alert-warning py-2 small mb-3">
          بيمسح الموجود الأول. المستخدمون والأدوار ودورات الاعتماد بيفضلوا.
        </div>
        <label class="form-label req" for="c1">اكتب «توليد» للتأكيد</label>
        <input id="c1" name="confirm" class="form-control form-control-sm mb-2" placeholder="توليد" required autocomplete="off">
        <button class="btn btn-success btn-sm w-100" onclick="return confirm('هيتمسح الموجود ويتولّد ديمو كامل. متأكد؟')">
          <i class="bi bi-magic" aria-hidden="true"></i> ولّد الديمو الكامل
        </button>
        <div class="hint mt-2">ممكن تاخد من 30 لـ 90 ثانية. متقفلش الصفحة.</div>
      </form>
    </div>

    <div class="card mb-3" style="border-color:var(--lv-soft)">
      <div class="card-header" style="color:var(--lv-brand)">
        <i class="bi bi-box-seam" aria-hidden="true"></i> ② بيانات أساسية بس — للشغل الحقيقي
      </div>
      <form method="post" action="{{ route('data.master') }}" class="card-body">@csrf
        <p class="hint mb-2">
          نقطة البداية لما تحب تمشي الدورة بنفسك ببيانات حقيقية. بيجهّز الكتالوج
          <b>من غير أي مستندات</b> — مفيش طلبات ولا مستحقات ولا أحواض ولا أوامر شغل.
        </p>
        <ul class="small mb-3" style="line-height:1.9">
          <li>12 مورد · 8 مصانع · 6 مخازن</li>
          <li>8 خامات بمواصفاتها المعتمدة</li>
          <li>~250 كود لون (بمشكلة التكرار والدمج الحقيقية)</li>
          <li>~30 موديل بمقاساتهم</li>
          <li>18 إكسسوار + قائمة BOM لكل موديل</li>
        </ul>
        <div class="alert alert-warning py-2 small mb-3">
          بيمسح أي مستندات موجودة عشان تبدأ نضيف. المستخدمون والأدوار بيفضلوا.
        </div>
        <label class="form-label req" for="c2">اكتب «أساسية» للتأكيد</label>
        <input id="c2" name="confirm" class="form-control form-control-sm mb-2" placeholder="أساسية" required autocomplete="off">
        <button class="btn btn-plum btn-sm w-100" onclick="return confirm('هيتمسح أي مستندات وتتجهز البيانات الأساسية. متأكد؟')">
          <i class="bi bi-box-seam" aria-hidden="true"></i> جهّز البيانات الأساسية
        </button>
      </form>
    </div>

    <div class="card">
      <div class="card-header text-danger"><i class="bi bi-trash3" aria-hidden="true"></i> مسح كل بيانات الشغل</div>
      <form method="post" action="{{ route('data.reset') }}" class="card-body">@csrf
        <div class="alert alert-danger py-2 small mb-3">
          <b>مفيش رجوع.</b> كل الطلبات والأحواض والأوامر والتقارير والتعليقات
          <b>والبيانات الأساسية</b> هتتمسح. المستخدمون والأدوار ودورات الاعتماد هيفضلوا.
        </div>
        <label class="form-label req" for="c3">اكتب «مسح» للتأكيد</label>
        <input id="c3" name="confirm" class="form-control form-control-sm mb-2" placeholder="مسح" required autocomplete="off">
        <button class="btn btn-danger btn-sm w-100" onclick="return confirm('هيتمسح كل شيء نهائيًا. متأكد؟')">
          <i class="bi bi-exclamation-octagon" aria-hidden="true"></i> امسح كل شيء
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
        من التيرمنال:
        <code style="direction:ltr;display:inline-block">php artisan lv:paper</code> الورق الحقيقي ·
        <code style="direction:ltr;display:inline-block">php artisan lv:demo</code> ديمو كامل ·
        <code style="direction:ltr;display:inline-block">php artisan lv:master</code> بيانات أساسية ·
        <code style="direction:ltr;display:inline-block">php artisan lv:reset</code> مسح ·
        <code style="direction:ltr;display:inline-block">php artisan lv:doctor</code> فحص
      </div>
    </div>
  </div>
</div>
@endsection
