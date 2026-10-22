@extends('layouts.app')
@section('content')

<style>
.roles-acc .accordion-item{border-color:var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-1);margin-bottom:var(--sp-3);overflow:hidden}
.roles-acc .accordion-button{font-weight:700;color:var(--lv-brand-ink);gap:10px;flex-wrap:wrap;padding:.85rem 1.1rem}
.roles-acc .accordion-button .hint{font-weight:400}
.roles-acc .accordion-button .badge{margin-inline-start:auto}
.roles-acc .perm-group{height:100%;margin-bottom:0}
.roles-acc .perm-group .card-header{min-height:40px;padding:.5rem .9rem;font-size:var(--fs-sm)}
.roles-acc .perm-group .card-body{padding:.6rem .9rem}
.roles-acc .form-check{margin-bottom:4px}
</style>

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>كل دور بيحدد مين يشوف إيه ومين يعمل إيه. افتح الدور، علّم الصلاحيات، واحفظ — التغيير بيشتغل من أول صفحة بعدها.</span>
</div>

<div class="accordion roles-acc" id="rolesAcc">
@forelse($roles as $r)
  @php $usersCount = $r->users()->count(); @endphp
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#r{{ $r->id }}"
              aria-expanded="false" aria-controls="r{{ $r->id }}">
        <i class="bi bi-key" aria-hidden="true"></i>
        <span>{{ $r->name }}</span>
        <span class="hint"><span class="num">{{ $r->key }}</span> · <span class="num">{{ $r->permissions->count() }}</span> صلاحية</span>
        <span class="badge {{ $usersCount ? 'bg-primary' : 'bg-secondary' }}"><i class="bi bi-people" aria-hidden="true"></i> {{ $usersCount }} مستخدم</span>
      </button>
    </h2>
    <div id="r{{ $r->id }}" class="accordion-collapse collapse" data-bs-parent="#rolesAcc">
      <div class="accordion-body">
        @if($r->key === 'admin')
          <div class="alert alert-info py-2"><i class="bi bi-shield-check" aria-hidden="true"></i> مدير النظام عنده كل الصلاحيات تلقائيًا — اللي تعلّمه هنا للتوثيق بس.</div>
        @endif
        <form method="post" action="{{ route('settings.roles.permissions',$r) }}">@csrf
          <div class="row g-3">
            @foreach($permissions as $group => $perms)
              <div class="col-md-4">
                <div class="card perm-group">
                  <div class="card-header"><span>{{ $group ?: 'عام' }}</span></div>
                  <div class="card-body">
                    @foreach($perms as $p)
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}"
                               id="r{{ $r->id }}p{{ $p->id }}" @checked($r->permissions->contains($p->id))>
                        <label class="form-check-label" for="r{{ $r->id }}p{{ $p->id }}">{{ $p->name }}</label>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          <div class="form-actions">
            <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ صلاحيات {{ $r->name }}</button>
            <span class="hint">بيتطبق على {{ $usersCount }} مستخدم على طول</span>
          </div>
        </form>
      </div>
    </div>
  </div>
@empty
  <div class="card"><div class="card-body">
    <div class="empty-state">
      <i class="bi bi-key ico" aria-hidden="true"></i>
      <div class="t">مفيش أدوار معرّفة.</div>
    </div>
  </div></div>
@endforelse
</div>
@endsection
