@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span><b>مفيش حذف في شاشة الألوان.</b>
  عندنا آلاف الأكواد اتراكموا لأن كل صبغة رجعت بكود جديد. حذف أي كود بيكسر الداتا التاريخية —
  فبدل الحذف: <b>دمج</b> (الكود القديم يفضل ويشاور على الجديد) أو <b>إيقاف</b> (يتقفل عن الاستخدام الجديد بس يفضل مقروء).</span>
</div>

@php
  $countCards = [
    'all'     => ['كل الأكواد', 'ink',   'كل كود لون اتسجل في السيستم — نشط أو مدموج أو موقوف.'],
    'active'  => ['نشط',        'ok',    'ينفع يتستخدم في أذون وطلبات جديدة.'],
    'merged'  => ['مدموج',      'muted', 'كود قديم بيشاور على كود تاني — بيفضل مقروء في الداتا التاريخية.'],
    'retired' => ['موقوف',      'warn',  'اتقفل عن الاستخدام الجديد بس بيفضل مقروء.'],
  ];
  $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty();
@endphp
<div class="row g-3 mb-4">
  @foreach($countCards as $k => [$lbl, $tone, $note])
    <div class="col-6 col-lg-3">
      @include('partials.kpi', ['value' => number_format($counts[$k]), 'label' => $lbl, 'tone' => $tone, 'note' => $note,
        'link' => $k === 'all' ? [route('colors.index'), 'اعرض الكل'] : [route('colors.index', ['status' => $k]), 'اعرض ' . $lbl]])
    </div>
  @endforeach
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <span>{{ $title ?? 'الألوان' }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

        <a href="{{ route('io.export.colors') }}" class="btn btn-sm btn-outline-plum"
           title="تصدير إكسيل" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>

        <form class="filter-bar" method="get" role="search" aria-label="فلترة الألوان">
          <div class="fb-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="كود أو اسم أو كود قديم…">
          </div>
          <select name="status" class="form-select w-short" aria-label="الحالة" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach(\App\Models\Color::STATUSES as $k => $v)
              <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
          </select>
          <select name="family" class="form-select w-short" aria-label="العائلة" onchange="this.form.submit()">
            <option value="">كل العائلات</option>
            @foreach($families as $f)<option value="{{ $f }}" @selected(request('family') === $f)>{{ $f }}</option>@endforeach
          </select>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="basic" value="1" id="basic" @checked(request('basic')) onchange="this.form.submit()">
            <label class="form-check-label" for="basic">الأساسي بس</label>
          </div>
          @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
          @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
          <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
          @if($hasFilters)
            <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
          @endif
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-sm doc-table">
          <thead><tr>
            <th>الكود</th><th>الاسم</th><th>العائلة</th><th>أساسي</th><th>الحالة</th><th>مدموج في</th>
            <th style="width:84px"><span class="visually-hidden">إجراءات</span></th>
          </tr></thead>
          <tbody>
          @forelse($rows as $c)
            <tr class="{{ $c->status !== 'active' ? 'table-light text-muted' : '' }}">
              <td class="num fw-bold">{{ $c->code }}
                @if($c->legacy_code)<div class="hint">قديم: {{ $c->legacy_code }}</div>@endif</td>
              <td>
                @if($c->hex)<span class="color-dot" style="background:{{ $c->hex }}" aria-hidden="true"></span>@endif
                {{ $c->name }}
              </td>
              <td>{{ $c->family ?: '—' }}</td>
              <td>@if($c->is_basic)<span class="badge bg-info">أساسي</span>@else <span class="hint">—</span> @endif</td>
              <td>
                <span class="badge bg-{{ $c->status === 'active' ? 'success' : ($c->status === 'merged' ? 'secondary' : 'dark') }}">
                  {{ $c->status_name }}
                </span>
              </td>
              <td class="num">{{ $c->mergedInto?->code ?: '—' }}</td>
              <td class="text-nowrap text-end"><div class="row-actions">
                <button type="button" class="btn btn-sm btn-outline-plum btn-icon" data-bs-toggle="modal" data-bs-target="#ed{{ $c->id }}"
                        title="تعديل اللون" aria-label="تعديل اللون {{ $c->code }}">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                </button>
                @if($c->status !== 'merged')
                  <form method="post" action="{{ route('colors.toggle', $c->id) }}">@csrf
                    <button class="btn btn-sm btn-outline-secondary btn-icon"
                            title="{{ $c->status === 'active' ? 'إيقاف' : 'تفعيل' }}"
                            aria-label="{{ $c->status === 'active' ? 'إيقاف' : 'تفعيل' }} اللون {{ $c->code }}">
                      <i class="bi bi-{{ $c->status === 'active' ? 'pause' : 'play' }}" aria-hidden="true"></i>
                    </button>
                  </form>
                @endif
              </div></td>
            </tr>
          @empty
            <tr><td colspan="7">
              <div class="empty-state">
                <i class="bi bi-inbox ico" aria-hidden="true"></i>
                <div class="t">{{ $hasFilters ? 'مفيش ألوان مطابقة للبحث.' : 'مفيش ألوان لسه.' }}</div>
                @if($hasFilters)
                  <div class="hint mb-2">جرّب كلمة تانية أو شيل الفلاتر.</div>
                  <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح البحث واعرض الكل</a>
                @else
                  <div class="hint">ضيف لون من الفورم اللي جنب الجدول، أو ابدأ بالاستيراد من إكسيل.</div>
                @endif
              </div>
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>{{ $rows->links() }}</div>
        @if($rows->total() > 0)
          <div class="hint num">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} من {{ number_format($rows->total()) }}</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><span><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة لون</span></div>
      <form method="post" action="{{ route('colors.store') }}" class="card-body">@csrf
        <div class="mb-2"><label class="form-label req" for="nc-code">الكود</label><input name="code" id="nc-code" class="form-control" required placeholder="مثال: 1042"></div>
        <div class="mb-2"><label class="form-label req" for="nc-name">الاسم</label><input name="name" id="nc-name" class="form-control" required placeholder="مثال: أوف وايت"></div>
        <div class="mb-2"><label class="form-label" for="nc-family">العائلة</label><input name="family" id="nc-family" class="form-control" placeholder="مثال: بيج"></div>
        <div class="mb-2"><label class="form-label" for="nc-hex">لون العرض</label><input type="color" name="hex" id="nc-hex" class="form-control form-control-color" value="#cccccc"></div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_basic" value="1" id="nc-basic">
          <label class="form-check-label" for="nc-basic">لون أساسي</label>
        </div>
        <button class="btn btn-plum btn-sm w-100"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header"><span><i class="bi bi-arrow-left-right text-warning" aria-hidden="true"></i> دمج لونين</span></div>
      <form method="post" action="{{ route('colors.merge') }}" class="card-body">@csrf
        <div class="hint mb-2">الكود المدموج هيفضل موجود ويشاور على الهدف — مش هيتحذف.</div>
        @php $activeColors = \App\Models\Color::where('status','active')->orderBy('code')->get(); @endphp
        <div class="mb-2">
          <label class="form-label req" for="mg-from">اللون اللي هيندمج (القديم)</label>
          <select name="from_color_id" id="mg-from" class="form-select" required>
            <option value="">— اختر —</option>
            @foreach($activeColors as $c)
              <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label req" for="mg-to">اللون الهدف (اللي هيفضل)</label>
          <select name="to_color_id" id="mg-to" class="form-select" required>
            <option value="">— اختر —</option>
            @foreach($activeColors as $c)
              <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3"><label class="form-label" for="mg-reason">السبب</label>
          <textarea name="reason" id="mg-reason" rows="2" class="form-control" placeholder="مثال: نفس الصبغة رجعت بكود جديد"></textarea></div>
        <button class="btn btn-warning btn-sm w-100" onclick="return confirm('متأكد من الدمج؟')"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> دمج</button>
      </form>
    </div>
  </div>
</div>

{{-- بوب أبات التعديل برّه الجدول — جوه <tbody> المتصفح بيطلّع المحتوى --}}
@foreach($rows as $c)
  <div class="modal fade" id="ed{{ $c->id }}" tabindex="-1" aria-labelledby="ed{{ $c->id }}-t">
    <div class="modal-dialog"><div class="modal-content">
      <form method="post" action="{{ route('colors.update', $c->id) }}">@csrf @method('PUT')
        <div class="modal-header"><h6 class="modal-title" id="ed{{ $c->id }}-t">تعديل اللون {{ $c->code }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label req" for="ed{{ $c->id }}-name">الاسم</label>
            <input name="name" id="ed{{ $c->id }}-name" class="form-control" value="{{ $c->name }}" required></div>
          <div class="mb-2"><label class="form-label" for="ed{{ $c->id }}-family">العائلة</label>
            <input name="family" id="ed{{ $c->id }}-family" class="form-control" value="{{ $c->family }}"></div>
          <div class="mb-2"><label class="form-label" for="ed{{ $c->id }}-hex">لون العرض</label>
            <input type="color" name="hex" id="ed{{ $c->id }}-hex" class="form-control form-control-color" value="{{ $c->hex ?: '#cccccc' }}"></div>
          <div class="mb-2"><label class="form-label" for="ed{{ $c->id }}-legacy">الكود القديم</label>
            <input name="legacy_code" id="ed{{ $c->id }}-legacy" class="form-control" value="{{ $c->legacy_code }}"></div>
          <div class="form-check">
            <input type="hidden" name="is_basic" value="0">
            <input class="form-check-input" type="checkbox" name="is_basic" value="1" id="ed{{ $c->id }}-basic" @checked($c->is_basic)>
            <label class="form-check-label" for="ed{{ $c->id }}-basic">لون أساسي (أبيض/أسود/أوف وايت)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
          <button class="btn btn-plum btn-sm">حفظ</button>
        </div>
      </form>
    </div></div>
  </div>
@endforeach

<style>
  /* نقطة لون العرض جنب اسم اللون */

</style>
@endsection
