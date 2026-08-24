@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span>{{ $title }}</span>
    <form method="post" action="{{ route('notifications.read-all') }}">@csrf
      <button class="btn btn-sm btn-outline-plum">تعليم الكل كمقروء</button>
    </form>
  </div>
  <ul class="list-group list-group-flush">
  @forelse($rows as $n)
    <li class="list-group-item d-flex justify-content-between align-items-start {{ $n->read_at ? '' : 'bg-light' }}">
      <div>
        <div class="fw-bold">
          <i class="bi bi-{{ ['info'=>'info-circle','warning'=>'exclamation-triangle','danger'=>'exclamation-octagon'][$n->severity] ?? 'bell' }} text-{{ $n->severity === 'info' ? 'primary' : $n->severity }}"></i>
          {{ $n->title }}
        </div>
        <div class="hint">{{ $n->body }}</div>
        @if($n->link)<a href="{{ $n->link }}" class="small">فتح</a>@endif
      </div>
      <div class="text-nowrap">
        <span class="hint">{{ $n->created_at->diffForHumans() }}</span>
        @if(!$n->read_at)
          <form method="post" action="{{ route('notifications.read',$n->id) }}" class="d-inline">@csrf
            <button class="btn btn-sm btn-light py-0"><i class="bi bi-check" aria-hidden="true"></i></button>
          </form>
        @endif
      </div>
    </li>
  @empty
    <li class="list-group-item">
      <div class="empty-state">
        <i class="bi bi-bell-slash ico" aria-hidden="true"></i>
        <div class="t">مفيش إشعارات.</div>
        <div>أي حاجة محتاجة منك أكشن هتوصلك هنا.</div>
      </div>
    </li>
  @endforelse
  </ul>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
