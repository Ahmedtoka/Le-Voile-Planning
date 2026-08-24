{{-- بوكس رقم + شرح تحته + لينك اختياري.
     اللون مش لوحده اللي بيحمل المعنى — معاه أيقونة ونص. --}}
@php
  $tone = $tone ?? 'ink';
  $link = $link ?? null;
  $note = $note ?? null;
  $sub  = $sub  ?? null;

  // اللون + أيقونة + شريط جانبي — الحالة مش بلون لوحده
  $tones = [
    'ink'    => ['var(--lv-brand-ink)', null,                          'var(--lv-line)'],
    'brand'  => ['var(--lv-brand)',     null,                          'var(--lv-brand)'],
    'muted'  => ['var(--lv-muted)',     null,                          'var(--lv-line)'],
    'ok'     => ['var(--lv-ok)',        'bi-check-circle',             'var(--lv-ok)'],
    'warn'   => ['var(--lv-warn)',      'bi-exclamation-circle',       'var(--lv-warn)'],
    'danger' => ['var(--lv-danger)',    'bi-exclamation-triangle-fill','var(--lv-danger)'],
  ];
  [$color, $icon, $bar] = $tones[$tone] ?? $tones['ink'];
@endphp
<div class="stat h-100 d-flex flex-column"
     style="border-inline-start:3px solid {{ $bar }}">
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
