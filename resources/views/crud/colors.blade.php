@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <b>مفيش حذف في شاشة الألوان.</b>
  عندنا آلاف الأكواد اتراكموا لأن كل صبغة رجعت بكود جديد. حذف أي كود بيكسر الداتا التاريخية —
  فبدل الحذف: <b>دمج</b> (الكود القديم يفضل ويشاور على الجديد) أو <b>إيقاف</b> (يتقفل عن الاستخدام الجديد بس يفضل مقروء).
</div>

<div class="row g-3 mb-3">
  @foreach(['all'=>'كل الأكواد','active'=>'نشط','merged'=>'مدموج','retired'=>'موقوف'] as $k => $lbl)
    <div class="col-6 col-lg-3">
      <div class="stat"><div class="v">{{ number_format($counts[$k]) }}</div><div class="l">{{ $lbl }}</div></div>
    </div>
  @endforeach
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex gap-2 flex-wrap align-items-center">
        <span>الألوان</span>
        <form class="d-flex gap-2 ms-auto" method="get">
          <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:180px" placeholder="كود أو اسم…">
          <select name="status" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach(\App\Models\Color::STATUSES as $k => $v)
              <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
          </select>
          <select name="family" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
            <option value="">كل العائلات</option>
            @foreach($families as $f)<option value="{{ $f }}" @selected(request('family') === $f)>{{ $f }}</option>@endforeach
          </select>
          <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('io.export.colors') }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i></a>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr>
            <th>الكود</th><th>الاسم</th><th>العائلة</th><th>أساسي</th><th>الحالة</th><th>مدموج في</th><th></th>
          </tr></thead>
          <tbody>
          @forelse($rows as $c)
            <tr class="{{ $c->status !== 'active' ? 'table-light text-muted' : '' }}">
              <td class="num fw-bold">{{ $c->code }}</td>
              <td>
                @if($c->hex)<span style="display:inline-block;width:12px;height:12px;border-radius:3px;border:1px solid #ccc;background:{{ $c->hex }}"></span>@endif
                {{ $c->name }}
              </td>
              <td>{{ $c->family ?: '—' }}</td>
              <td>@if($c->is_basic)<span class="badge bg-info">أساسي</span>@endif</td>
              <td>
                <span class="badge bg-{{ $c->status === 'active' ? 'success' : ($c->status === 'merged' ? 'secondary' : 'dark') }}">
                  {{ $c->status_name }}
                </span>
              </td>
              <td class="num">{{ $c->mergedInto?->code ?: '—' }}</td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-outline-plum py-0" data-bs-toggle="modal" data-bs-target="#ed{{ $c->id }}">
                  <i class="bi bi-pencil"></i>
                </button>
                @if($c->status !== 'merged')
                  <form method="post" action="{{ route('colors.toggle', $c->id) }}" class="d-inline">@csrf
                    <button class="btn btn-sm btn-outline-secondary py-0" title="{{ $c->status === 'active' ? 'إيقاف' : 'تفعيل' }}">
                      <i class="bi bi-{{ $c->status === 'active' ? 'pause' : 'play' }}"></i>
                    </button>
                  </form>
                @endif
              </td>
            </tr>

            <div class="modal fade" id="ed{{ $c->id }}">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post" action="{{ route('colors.update', $c->id) }}">@csrf @method('PUT')
                  <div class="modal-header"><h6 class="modal-title">تعديل اللون {{ $c->code }}</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <div class="mb-2"><label class="form-label req">الاسم</label>
                      <input name="name" class="form-control form-control-sm" value="{{ $c->name }}" required></div>
                    <div class="mb-2"><label class="form-label">العائلة</label>
                      <input name="family" class="form-control form-control-sm" value="{{ $c->family }}"></div>
                    <div class="mb-2"><label class="form-label">لون العرض</label>
                      <input type="color" name="hex" class="form-control form-control-sm form-control-color" value="{{ $c->hex ?: '#cccccc' }}"></div>
                    <div class="mb-2"><label class="form-label">الكود القديم</label>
                      <input name="legacy_code" class="form-control form-control-sm" value="{{ $c->legacy_code }}"></div>
                    <div class="form-check">
                      <input type="hidden" name="is_basic" value="0">
                      <input class="form-check-input" type="checkbox" name="is_basic" value="1" @checked($c->is_basic)>
                      <label class="form-check-label">لون أساسي (أبيض/أسود/أوف وايت)</label>
                    </div>
                  </div>
                  <div class="modal-footer"><button class="btn btn-plum btn-sm">حفظ</button></div>
                </form>
              </div></div>
            </div>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">مفيش ألوان — ابدأ بالاستيراد من إكسيل.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">{{ $rows->links() }}</div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">إضافة لون</div>
      <form method="post" action="{{ route('colors.store') }}" class="card-body">@csrf
        <div class="mb-2"><label class="form-label req">الكود</label><input name="code" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label req">الاسم</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label">العائلة</label><input name="family" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label">لون العرض</label><input type="color" name="hex" class="form-control form-control-sm form-control-color" value="#cccccc"></div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_basic" value="1">
          <label class="form-check-label">لون أساسي</label>
        </div>
        <button class="btn btn-plum btn-sm w-100">إضافة</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header text-warning"><i class="bi bi-arrow-left-right"></i> دمج لونين</div>
      <form method="post" action="{{ route('colors.merge') }}" class="card-body">@csrf
        <div class="hint mb-2">الكود المدموج هيفضل موجود ويشاور على الهدف — مش هيتحذف.</div>
        <div class="mb-2">
          <label class="form-label req">اللون اللي هيندمج (القديم)</label>
          <select name="from_color_id" class="form-select form-select-sm" required>
            <option value="">— اختر —</option>
            @foreach(\App\Models\Color::where('status','active')->orderBy('code')->get() as $c)
              <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label req">اللون الهدف (اللي هيفضل)</label>
          <select name="to_color_id" class="form-select form-select-sm" required>
            <option value="">— اختر —</option>
            @foreach(\App\Models\Color::where('status','active')->orderBy('code')->get() as $c)
              <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-2"><label class="form-label">السبب</label>
          <textarea name="reason" rows="2" class="form-control form-control-sm"></textarea></div>
        <button class="btn btn-warning btn-sm w-100" onclick="return confirm('متأكد من الدمج؟')">دمج</button>
      </form>
    </div>
  </div>
</div>
@endsection
