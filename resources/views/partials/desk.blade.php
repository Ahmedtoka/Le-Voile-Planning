{{-- قالب مكتب الإدارة: اللي مستني منك ← زراير الإنشاء ← جداول المتابعة --}}
@extends('layouts.app')
@section('content')

@if(!empty($flow))
  @include('partials.flow_bar', ['flow' => $flow, 'step' => $flowStep ?? ''])
@endif

@if(!empty($intro))
  <div class="note-box mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> {!! $intro !!}</div>
@endif

{{-- ① اللي مستني منك --}}
<div class="section-head">
  <h2>اللي مستني منك دلوقتي</h2>
  <span class="hint">كل رقم شغل حقيقي — دوس على الكارت تدخل عليه</span>
</div>
<div class="row g-3 mb-4">
  @foreach($queue as $q)
    @php
      $dots = ['brand'=>'dot-brand','warn'=>'dot-warn','danger'=>'dot-danger','ok'=>'dot-ok'];
      $dot  = $q['count'] ? ($dots[$q['tone']] ?? 'dot-ink') : 'dot-muted';
    @endphp
    <div class="col-6 col-lg-3">
      <div class="stat h-100 d-flex flex-column {{ $q['count'] > 0 ? 'is-waiting is-link' : 'is-zero' }}">
        <div class="stat-head">
          <span class="dot {{ $dot }}" aria-hidden="true"></span>
          <div class="l">{{ $q['label'] }}</div>
        </div>
        <div class="v num">{{ number_format($q['count']) }}</div>
        @if($q['count'] > 0)
          <a href="{{ $q['link'] }}" class="stat-link stretched-link">
            {{ $q['action'] }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
          </a>
        @else
          <div class="hint mt-auto pt-2"><i class="bi bi-check2" aria-hidden="true"></i> مفيش حاجة مستنية</div>
        @endif
      </div>
    </div>
  @endforeach
</div>

{{-- ② الإنشاء — شاشات منفصلة --}}
@if(!empty($create))
  <div class="d-flex gap-2 flex-wrap align-items-center mb-4">
    <span class="hint me-1">إنشاء جديد:</span>
    @foreach($create as [$label, $link, $icon])
      <a href="{{ $link }}" class="btn btn-outline-plum btn-sm">
        <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
      </a>
    @endforeach
  </div>
@endif

{{-- ③ جداول المتابعة --}}
@yield('tracking')

@endsection
