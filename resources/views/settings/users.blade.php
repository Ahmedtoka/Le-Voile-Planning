@extends('layouts.app')
@section('content')
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><span><i class="bi bi-person-plus" aria-hidden="true"></i> إضافة مستخدم</span></div>
      <form method="post" action="{{ route('settings.users.store') }}" class="card-body">@csrf
        <div class="mb-3">
          <label class="form-label req" for="nu-name">الاسم</label>
          <input id="nu-name" name="name" class="form-control" value="{{ old('name') }}" placeholder="مثال: أحمد محمود" required>
        </div>
        <div class="mb-3">
          <label class="form-label req" for="nu-username">اسم الدخول</label>
          <input id="nu-username" name="username" class="form-control" value="{{ old('username') }}" placeholder="مثال: ahmed" autocomplete="off" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="nu-email">البريد</label>
          <input id="nu-email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="مثال: ahmed@levoile.com">
        </div>
        <div class="mb-3">
          <label class="form-label" for="nu-phone">التليفون</label>
          <input id="nu-phone" name="phone" class="form-control w-mid" value="{{ old('phone') }}" placeholder="مثال: 01000000000" inputmode="tel">
        </div>
        <div class="mb-3">
          <label class="form-label" for="nu-job">المسمى الوظيفي</label>
          <input id="nu-job" name="job_title" class="form-control" value="{{ old('job_title') }}" placeholder="مثال: مخطط إنتاج">
        </div>
        <div class="mb-3">
          <label class="form-label req" for="nu-password">كلمة المرور</label>
          <input id="nu-password" type="password" name="password" class="form-control" autocomplete="new-password" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="nu-factory">المصنع <span class="hint">(لمستخدم بوابة المصنع)</span></label>
          <select id="nu-factory" name="factory_id" class="form-select"><option value="">— مش مصنع —</option>
            @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected(old('factory_id') == $k)>{{ $v }}</option>@endforeach
          </select>
        </div>
        <fieldset class="mb-3">
          <legend class="form-label">الأدوار</legend>
          @foreach($roles as $r)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}" id="nr{{ $r->id }}" @checked(in_array($r->id, old('roles', [])))>
              <label class="form-check-label" for="nr{{ $r->id }}">{{ $r->name }}</label></div>
          @endforeach
        </fieldset>
        <div class="form-actions">
          <button class="btn btn-plum w-100"><i class="bi bi-person-plus" aria-hidden="true"></i> إضافة المستخدم</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><span>{{ $title }} <span class="hint">({{ number_format($rows->total()) }})</span></span></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الأدوار</th><th>المصنع</th><th>الحالة</th><th class="text-end"><span class="visually-hidden">إجراءات</span></th></tr></thead>
          <tbody>
          @forelse($rows as $u)
            <tr>
              <td>{{ $u->name }}@if($u->job_title)<div class="hint">{{ $u->job_title }}</div>@endif</td>
              <td class="num">{{ $u->username }}</td>
              <td class="hint">{{ $u->roleNames() ?: '—' }}</td>
              <td class="hint">{{ $u->factory_id ? ($factories[$u->factory_id] ?? '—') : '—' }}</td>
              <td><span class="badge bg-{{ $u->is_active ? 'success' : 'secondary' }}">
                <i class="bi {{ $u->is_active ? 'bi-check-circle' : 'bi-pause-circle' }}" aria-hidden="true"></i> {{ $u->is_active ? 'نشط' : 'موقوف' }}</span></td>
              <td class="text-end">
                <div class="row-actions">
                  <button type="button" class="btn btn-sm btn-outline-plum btn-icon" data-bs-toggle="modal" data-bs-target="#u{{ $u->id }}"
                          title="تعديل {{ $u->name }}" aria-label="تعديل {{ $u->name }}"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6">
              <div class="empty-state">
                <i class="bi bi-people ico" aria-hidden="true"></i>
                <div class="t">مفيش مستخدمين لسه.</div>
                <div class="hint">ضيف أول مستخدم من الفورم اللي جنبك.</div>
              </div>
            </td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">{{ $rows->links() }}</div>
    </div>
  </div>
</div>

{{-- مودالات التعديل — برّه الجدول عشان الـHTML يفضل صحيح (نفس الـids: #u{id}) --}}
@foreach($rows as $u)
  <div class="modal fade" id="u{{ $u->id }}" tabindex="-1" aria-labelledby="u{{ $u->id }}-title" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
      <form method="post" action="{{ route('settings.users.update',$u) }}">@csrf @method('PUT')
        <div class="modal-header">
          <h6 class="modal-title" id="u{{ $u->id }}-title">تعديل {{ $u->name }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label req" for="u{{ $u->id }}-name">الاسم</label>
            <input id="u{{ $u->id }}-name" name="name" class="form-control" value="{{ $u->name }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="u{{ $u->id }}-email">البريد</label>
            <input id="u{{ $u->id }}-email" type="email" name="email" class="form-control" value="{{ $u->email }}">
          </div>
          <div class="mb-3">
            <label class="form-label" for="u{{ $u->id }}-phone">التليفون</label>
            <input id="u{{ $u->id }}-phone" name="phone" class="form-control w-mid" value="{{ $u->phone }}" inputmode="tel">
          </div>
          <div class="mb-3">
            <label class="form-label" for="u{{ $u->id }}-job">المسمى الوظيفي</label>
            <input id="u{{ $u->id }}-job" name="job_title" class="form-control" value="{{ $u->job_title }}">
          </div>
          <div class="mb-3">
            <label class="form-label" for="u{{ $u->id }}-password">كلمة مرور جديدة</label>
            <input id="u{{ $u->id }}-password" type="password" name="password" class="form-control" placeholder="سيبها فاضية لو مش هتتغير" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label" for="u{{ $u->id }}-factory">المصنع <span class="hint">(لمستخدم بوابة المصنع)</span></label>
            <select id="u{{ $u->id }}-factory" name="factory_id" class="form-select"><option value="">— مش مصنع —</option>
              @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected($u->factory_id == $k)>{{ $v }}</option>@endforeach
            </select>
          </div>
          <fieldset class="mb-3">
            <legend class="form-label">الأدوار</legend>
            @foreach($roles as $r)
              <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}"
                id="u{{ $u->id }}r{{ $r->id }}" @checked($u->roles->contains($r->id))>
                <label class="form-check-label" for="u{{ $u->id }}r{{ $r->id }}">{{ $r->name }}</label></div>
            @endforeach
          </fieldset>
          <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="u{{ $u->id }}-active" @checked($u->is_active)>
            <label class="form-check-label" for="u{{ $u->id }}-active">نشط <span class="hint">— لو اتشال، المستخدم مش هيقدر يدخل</span></label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>
        </div>
      </form>
    </div></div>
  </div>
@endforeach
@endsection
