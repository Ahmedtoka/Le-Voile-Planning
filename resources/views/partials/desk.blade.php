{{-- قالب مكتب الإدارة: اللي مستني منك ← زراير الإنشاء ← جداول المتابعة ← مكانك في الدورة.
     الـAPI: $queue, $create, $flow, $flowStep, $intro, و @section('tracking') — والعنوان الفرعي
     للتوب بار من @section('purpose') في كل مكتب. --}}
@extends('layouts.app')
@section('content')

{{-- ① اللي مستني منك --}}
<section class="mb-4" aria-labelledby="h-desk-queue">
  <div class="section-head mt-0">
    <h2 id="h-desk-queue">اللي مستني منك دلوقتي</h2>
    <span class="hint">كل رقم شغل حقيقي — دوس على الكارت تدخل عليه</span>
  </div>
  @php $dots = ['brand'=>'dot-brand','warn'=>'dot-warn','danger'=>'dot-danger','ok'=>'dot-ok']; @endphp
  <div class="q-grid">
    @foreach($queue as $q)
      @if($q['count'] > 0)
        <a href="{{ $q['link'] }}" class="q-tile">
          <div class="q-top">
            <span class="q-n">{{ number_format($q['count']) }}</span>
            <span class="q-l"><span class="dot {{ $dots[$q['tone']] ?? 'dot-ink' }}" aria-hidden="true"></span>{{ $q['label'] }}</span>
          </div>
          <span class="q-go">{{ $q['action'] }} <i class="bi bi-arrow-left" aria-hidden="true"></i></span>
        </a>
      @else
        <div class="q-tile is-zero">
          <div class="q-top">
            <span class="q-n">0</span>
            <span class="q-l">{{ $q['label'] }}</span>
          </div>
          <span class="q-go"><i class="bi bi-check2" aria-hidden="true"></i> مفيش حاجة مستنية</span>
        </div>
      @endif
    @endforeach
  </div>
</section>

{{-- ② الإنشاء — شاشات منفصلة. أول زرار هو الأساسي --}}
@if(!empty($create))
  <div class="d-flex gap-2 flex-wrap align-items-center mb-4 pb-1">
    <span class="hint me-1">إنشاء جديد:</span>
    @foreach($create as $i => [$label, $link, $icon])
      <a href="{{ $link }}" class="btn btn-sm {{ $i === 0 ? 'btn-plum' : 'btn-outline-plum' }}">
        <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
      </a>
    @endforeach
  </div>
@endif

{{-- ③ جداول المتابعة --}}
<style>.desk-tracking td.num{white-space:nowrap}</style>
<section class="desk-tracking" aria-label="المتابعة">
  @yield('tracking')
</section>

{{-- ④ مكانك في الدورة — للسياق، مش للشغل اليومي --}}
@if(!empty($flow) || !empty($intro))
  <section class="mt-4" aria-labelledby="h-desk-flow">
    <div class="section-head">
      <h2 id="h-desk-flow">مكانك في الدورة</h2>
    </div>
    @if(!empty($flow))
      @include('partials.flow_bar', ['flow' => $flow, 'step' => $flowStep ?? ''])
    @endif
    @if(!empty($intro))
      <div class="note-box"><i class="bi bi-info-circle" aria-hidden="true"></i> {!! $intro !!}</div>
    @endif
  </section>
@endif

@endsection
