@extends('layouts.app')
@section('content')

<style>
/* ══ الإشعارات — غير المقروء بارز، المقروء هادي ══ */
.ntf-list .list-group-item{padding:.8rem 1.1rem;display:flex;gap:12px;align-items:flex-start;border-color:var(--lv-line-soft)}
.ntf-list .ntf-ico{width:34px;height:34px;flex:0 0 34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
  font-size:1rem;background:var(--lv-surface-2);color:var(--lv-muted)}
.ntf-list .ntf-ico.is-info{background:var(--lv-info-bg);color:var(--lv-info)}
.ntf-list .ntf-ico.is-warning{background:var(--lv-warn-bg);color:var(--lv-warn)}
.ntf-list .ntf-ico.is-danger{background:var(--lv-danger-bg);color:var(--lv-danger)}
.ntf-list .ntf-main{flex:1 1 auto;min-width:0}
.ntf-list .ntf-title{font-weight:600;color:var(--lv-ink-2);line-height:1.4;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ntf-list .ntf-body{font-size:var(--fs-sm);color:var(--lv-muted);line-height:1.6;margin-top:2px;overflow-wrap:anywhere}
.ntf-list .ntf-meta{font-size:var(--fs-xs);color:var(--lv-muted);margin-top:4px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.ntf-list .ntf-side{display:flex;gap:6px;align-items:center;flex:0 0 auto}
/* غير المقروء: خلفية وردي خفيف + خط جانبي + عنوان أغمق */
.ntf-list .is-unread{background:linear-gradient(180deg,#fff 0%,#FDF8FB 100%);box-shadow:inset 3px 0 0 var(--lv-brand)}
.ntf-list .is-unread .ntf-title{color:var(--lv-ink);font-weight:700}
.ntf-list .is-unread .ntf-body{color:var(--lv-ink-2)}
.ntf-list .is-read .ntf-ico{opacity:.7}
@media (max-width:600px){
  .ntf-list .list-group-item{flex-wrap:wrap}
  .ntf-list .ntf-side{width:100%;justify-content:flex-end;padding-inline-start:46px}
}
</style>

@php
  $unread = $rows->getCollection()->whereNull('read_at')->count();
  $icons  = ['info'=>'bi-info-circle','warning'=>'bi-exclamation-triangle','danger'=>'bi-exclamation-octagon'];
@endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }}
      @if($unread > 0)<span class="badge badge-alert rounded-pill">{{ $unread }} جديد</span>@endif
      <span class="hint">({{ number_format($rows->total()) }})</span>
    </span>
    <form method="post" action="{{ route('notifications.read-all') }}" class="ms-auto">@csrf
      <button class="btn btn-sm btn-outline-plum" @disabled($unread === 0)><i class="bi bi-check2-all" aria-hidden="true"></i> تعليم الكل كمقروء</button>
    </form>
  </div>
  <ul class="list-group list-group-flush ntf-list">
  @forelse($rows as $n)
    @php $sev = in_array($n->severity, ['info','warning','danger'], true) ? $n->severity : 'info'; @endphp
    <li class="list-group-item {{ $n->read_at ? 'is-read' : 'is-unread' }}">
      <span class="ntf-ico is-{{ $sev }}" aria-hidden="true"><i class="bi {{ $icons[$sev] ?? 'bi-bell' }}"></i></span>
      <div class="ntf-main">
        <div class="ntf-title">
          {{ $n->title }}
          @if(!$n->read_at)<span class="badge bg-primary">جديد</span>@endif
        </div>
        @if($n->body)<div class="ntf-body">{{ $n->body }}</div>@endif
        <div class="ntf-meta">
          <span title="{{ $n->created_at->format('Y-m-d H:i') }}"><i class="bi bi-clock" aria-hidden="true"></i> {{ $n->created_at->diffForHumans() }}</span>
        </div>
      </div>
      <div class="ntf-side">
        @if($n->link)
          <a href="{{ $n->link }}" class="btn btn-sm {{ $n->read_at ? 'btn-outline-secondary' : 'btn-outline-plum' }}">افتح <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
        @endif
        @if(!$n->read_at)
          <form method="post" action="{{ route('notifications.read',$n->id) }}" class="d-inline">@csrf
            <button class="btn btn-sm btn-outline-plum btn-icon" title="تعليم كمقروء" aria-label="تعليم «{{ $n->title }}» كمقروء"><i class="bi bi-check" aria-hidden="true"></i></button>
          </form>
        @endif
      </div>
    </li>
  @empty
    <li class="list-group-item d-block">
      <div class="empty-state">
        <i class="bi bi-bell-slash ico" aria-hidden="true"></i>
        <div class="t">مفيش إشعارات.</div>
        <div class="hint">أي حاجة محتاجة منك أكشن هتوصلك هنا.</div>
      </div>
    </li>
  @endforelse
  </ul>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
