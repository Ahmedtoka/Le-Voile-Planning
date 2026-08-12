@extends('layouts.app')
@section('content')
<div class="accordion" id="rolesAcc">
@foreach($roles as $r)
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#r{{ $r->id }}">
        <b>{{ $r->name }}</b>
        <span class="hint ms-2">{{ $r->key }} · {{ $r->permissions->count() }} صلاحية · {{ $r->users()->count() }} مستخدم</span>
      </button>
    </h2>
    <div id="r{{ $r->id }}" class="accordion-collapse collapse" data-bs-parent="#rolesAcc">
      <div class="accordion-body">
        @if($r->key === 'admin')
          <div class="alert alert-info py-2 small">مدير النظام عنده كل الصلاحيات تلقائيًا.</div>
        @endif
        <form method="post" action="{{ route('settings.roles.permissions',$r) }}">@csrf
          <div class="row g-3">
            @foreach($permissions as $group => $perms)
              <div class="col-md-4">
                <div class="card h-100">
                  <div class="card-header py-1 small">{{ $group ?: 'عام' }}</div>
                  <div class="card-body py-2">
                    @foreach($perms as $p)
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}"
                               id="r{{ $r->id }}p{{ $p->id }}" @checked($r->permissions->contains($p->id))>
                        <label class="form-check-label small" for="r{{ $r->id }}p{{ $p->id }}">{{ $p->name }}</label>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          <button class="btn btn-plum btn-sm mt-3">حفظ صلاحيات {{ $r->name }}</button>
        </form>
      </div>
    </div>
  </div>
@endforeach
</div>
@endsection
