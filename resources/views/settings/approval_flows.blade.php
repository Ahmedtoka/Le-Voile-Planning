@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  الدورات دي بتتحكم في "مين يعتمد إيه". تغييرها من هنا — من غير أي كود.
  لو مستند مالوش دورة معرّفة، بيتعمد مباشرة من اللي أرسله وبيتسجّل كده.
</div>

<div class="row g-3">
@foreach($flows as $f)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $f->name }} <span class="hint">({{ $f->doc_type }})</span></span>
        <form method="post" action="{{ route('settings.flows.toggle',$f) }}">@csrf
          <button class="btn btn-sm btn-{{ $f->is_active ? 'success' : 'secondary' }} py-0">
            {{ $f->is_active ? 'مفعّلة' : 'موقوفة' }}
          </button>
        </form>
      </div>
      <ul class="list-group list-group-flush">
        @forelse($f->steps as $s)
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <span class="badge bg-light text-dark">{{ $s->step_no }}</span> {{ $s->title }}
              <div class="hint">
                {{ $s->user?->name ?? $s->role?->name ?? 'غير محدد' }}
                @if($s->min_amount) · فوق {{ number_format((float)$s->min_amount) }} {{ config('lvplanning.currency') }} @endif
              </div>
            </div>
            <form method="post" action="{{ route('settings.flows.step.delete',[$f,$s]) }}" onsubmit="return confirm('حذف الخطوة؟')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger py-0" aria-label="حذف" title="حذف"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </form>
          </li>
        @empty
          <li class="list-group-item text-muted small py-2">مفيش خطوات — المستند هيتعمد مباشرة.</li>
        @endforelse
      </ul>
      <div class="card-footer bg-white">
        <form method="post" action="{{ route('settings.flows.step.add',$f) }}" class="row g-2">@csrf
          <div class="col-12"><input name="title" class="form-control form-control-sm" placeholder="اسم الخطوة (مثال: اعتماد مدير المشتريات)" required></div>
          <div class="col-5"><select name="role_id" class="form-select form-select-sm">
              <option value="">— دور —</option>
              @foreach($roles as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-5"><select name="user_id" class="form-select form-select-sm">
              <option value="">— أو مستخدم بعينه —</option>
              @foreach($users as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select></div>
          <div class="col-2"><button class="btn btn-plum btn-sm w-100"><i class="bi bi-plus-lg"></i></button></div>
        </form>
      </div>
    </div>
  </div>
@endforeach
</div>
@endsection
