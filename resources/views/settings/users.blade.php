@extends('layouts.app')
@section('content')
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">إضافة مستخدم</div>
      <form method="post" action="{{ route('settings.users.store') }}" class="card-body">@csrf
        <div class="mb-2"><label class="form-label req">الاسم</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label req">اسم الدخول</label><input name="username" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label">البريد</label><input type="email" name="email" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label">التليفون</label><input name="phone" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label">المسمى الوظيفي</label><input name="job_title" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label req">كلمة المرور</label><input type="password" name="password" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label">الأدوار</label>
          @foreach($roles as $r)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}" id="nr{{ $r->id }}">
              <label class="form-check-label small" for="nr{{ $r->id }}">{{ $r->name }}</label></div>
          @endforeach
        </div>
        <button class="btn btn-plum btn-sm w-100">إضافة</button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">{{ $title }}</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الأدوار</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          @foreach($rows as $u)
            <tr>
              <td>{{ $u->name }}<div class="hint">{{ $u->job_title }}</div></td>
              <td class="num">{{ $u->username }}</td>
              <td class="hint">{{ $u->roleNames() ?: '—' }}</td>
              <td><span class="badge bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'نشط' : 'موقوف' }}</span></td>
              <td><button class="btn btn-sm btn-outline-plum py-0" data-bs-toggle="modal" data-bs-target="#u{{ $u->id }}"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
            </tr>
            <div class="modal fade" id="u{{ $u->id }}"><div class="modal-dialog"><div class="modal-content">
              <form method="post" action="{{ route('settings.users.update',$u) }}">@csrf @method('PUT')
                <div class="modal-header"><h6 class="modal-title">تعديل {{ $u->name }}</h6>
                  <button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-2"><label class="form-label req">الاسم</label><input name="name" class="form-control form-control-sm" value="{{ $u->name }}" required></div>
                  <div class="mb-2"><label class="form-label">البريد</label><input type="email" name="email" class="form-control form-control-sm" value="{{ $u->email }}"></div>
                  <div class="mb-2"><label class="form-label">التليفون</label><input name="phone" class="form-control form-control-sm" value="{{ $u->phone }}"></div>
                  <div class="mb-2"><label class="form-label">المسمى الوظيفي</label><input name="job_title" class="form-control form-control-sm" value="{{ $u->job_title }}"></div>
                  <div class="mb-2"><label class="form-label">كلمة مرور جديدة</label><input type="password" name="password" class="form-control form-control-sm" placeholder="سيبها فاضية لو مش هتتغير"></div>
                  <div class="mb-2"><label class="form-label">الأدوار</label>
                    @foreach($roles as $r)
                      <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}"
                        id="u{{ $u->id }}r{{ $r->id }}" @checked($u->roles->contains($r->id))>
                        <label class="form-check-label small" for="u{{ $u->id }}r{{ $r->id }}">{{ $r->name }}</label></div>
                    @endforeach
                  </div>
                  <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($u->is_active)>
                    <label class="form-check-label">نشط</label>
                  </div>
                </div>
                <div class="modal-footer"><button class="btn btn-plum btn-sm">حفظ</button></div>
              </form>
            </div></div></div>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">{{ $rows->links() }}</div>
    </div>
  </div>
</div>
@endsection
