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
  <span class="hint">كل صندوق فيه رقم شغل حقيقي — دوس تدخل عليه على طول</span>
</div>
<div class="row g-3 mb-4">
  @foreach($queue as $q)
    @php
      $tones = ['brand'=>'var(--lv-brand)','warn'=>'var(--lv-warn)','danger'=>'var(--lv-danger)','ok'=>'var(--lv-ok)'];
      $color = $tones[$q['tone']] ?? 'var(--lv-brand-ink)';
    @endphp
    <div class="col-6 col-lg-3">
      <div class="stat h-100 d-flex flex-column" style="border-inline-start:3px solid {{ $color }}">
        <div class="v num" style="color:{{ $q['count'] ? $color : 'var(--lv-muted)' }}">{{ number_format($q['count']) }}</div>
        <div class="l">{{ $q['label'] }}</div>
        @if($q['count'] > 0)
          <a href="{{ $q['link'] }}" class="btn btn-sm btn-plum mt-auto pt-1 mt-2">
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
  <div class="card mb-4">
    <div class="card-header">إنشاء مستند جديد</div>
    <div class="card-body d-flex gap-2 flex-wrap">
      @foreach($create as [$label, $link, $icon])
        <a href="{{ $link }}" class="btn btn-outline-plum btn-sm">
          <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
        </a>
      @endforeach
      <span class="hint align-self-center ms-2">الإنشاء في شاشة لوحده — الشاشة دي للمتابعة</span>
    </div>
  </div>
@endif

{{-- ③ جداول المتابعة --}}
@yield('tracking')

@endsection
