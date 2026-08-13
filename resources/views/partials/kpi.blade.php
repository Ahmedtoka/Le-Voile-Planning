{{-- بوكس رقم + شرح تحته + لينك اختياري.
     اللون مش لوحده اللي بيحمل المعنى — معاه أيقونة ونص. --}}
@php
  $tone = $tone ?? 'ink';
  $link = $link ?? null;
  $note = $note ?? null;
  $sub  = $sub  ?? null;

  $tones = [
    'ink'    => ['#3B092F', null],
    'brand'  => ['#9D197E', null],
    'muted'  => ['#6B606A', null],
    'ok'     => ['#1B7A50', 'bi-check-circle'],
    'warn'   => ['#9A6410', 'bi-exclamation-circle'],
    'danger' => ['#B5342B', 'bi-exclamation-triangle-fill'],
  ];
  [$color, $icon] = $tones[$tone] ?? $tones['ink'];
@endphp
<div class="stat h-100 d-flex flex-column">
  <div class="d-flex align-items-baseline gap-2">
    @if($icon)<i class="bi {{ $icon }}" style="color:{{ $color }};font-size:.95rem" aria-hidden="true"></i>@endif
    <div class="v num" style="color:{{ $color }}">{{ $value }}</div>
    @if($sub)<div class="hint">{{ $sub }}</div>@endif
  </div>
  <div class="l">{{ $label }}</div>
  @if($note)<div class="hint mt-1">{{ $note }}</div>@endif
  @if($link)
    <a href="{{ $link[0] }}" class="hint mt-auto pt-2 d-inline-block" style="color:var(--lv-brand)">
      {{ $link[1] }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
    </a>
  @endif
</div>
