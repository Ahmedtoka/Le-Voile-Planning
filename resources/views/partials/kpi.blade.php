{{-- بوكس رقم: نقطة حالة + عنوان قصير ← الرقم كبير ← ملاحظة صغيرة باهتة (سطرين بالكتير) ← لينك.
     الحالة مش بلون لوحده: النقطة معاها أيقونة للتحذير/الخطر، والنص كامل في الـtitle.
     الاستخدام: @include('partials.kpi', ['value'=>..,'label'=>..,'tone'=>'ok','sub'=>..,'note'=>'جملة تفسّر الرقم','link'=>[url,label],'fx'=>'المعادلة بالأرقام'])
     $fx (اختياري): نص أو مصفوفة سطور — بيظهر زرار ⓘ في ركن الكارت يفتح الحسبة. --}}
@php
  $tone = $tone ?? 'ink';
  $link = $link ?? null;
  $note = $note ?? null;
  $sub  = $sub  ?? null;
  $fx   = $fx   ?? null;
  $why  = $why  ?? null;

  // [لون الرقم, أيقونة جنب الرقم, كلاس النقطة]
  $tones = [
    'ink'    => ['var(--lv-brand-ink)', null,                           'dot-muted'],
    'brand'  => ['var(--lv-brand)',     null,                           'dot-brand'],
    'muted'  => ['var(--lv-muted)',     null,                           'dot-muted'],
    'ok'     => ['var(--lv-ok)',        null,                           'dot-ok'],
    'warn'   => ['var(--lv-warn)',      null,                           'dot-warn'],
    'danger' => ['var(--lv-danger)',    'bi-exclamation-triangle-fill', 'dot-danger'],
  ];
  // صفر في كارت تحذير/خطر مش إنذار — بيتعرض هادي من غير مثلث أحمر
  $raw = is_string($value) ? str_replace([',', ' '], '', strip_tags($value)) : $value;
  $isZero = is_numeric($raw) && (float) $raw == 0;
  if ($isZero && in_array($tone, ['danger', 'warn'], true)) $tone = 'muted';
  [$color, $icon, $dot] = $tones[$tone] ?? $tones['ink'];
@endphp
<div class="stat h-100 d-flex flex-column {{ $link ? 'is-link' : '' }} {{ $isZero ? 'is-zero' : '' }}">
  {{-- ⓘ المعادلة في ركن الكارت (الـlayout بيثبّته في الزاوية) --}}
  @if($fx)
    @include('partials.fx', ['t' => $label, 'f' => $fx, 'why' => $why])
  @endif
  <div class="stat-head">
    <span class="dot {{ $dot }}" aria-hidden="true"></span>
    <div class="l">{{ $label }}</div>
  </div>
  <div class="stat-value">
    <div class="v num" style="color:{{ $color }}">{{ $value }}</div>
    @if($icon)<i class="bi {{ $icon }}" style="color:{{ $color }};font-size:.9rem" aria-hidden="true"></i>@endif
    @if($sub)<div class="stat-sub">{{ $sub }}</div>@endif
  </div>
  {{-- الجملة اللي بتفسّر الرقم — دايمًا تحت الرقم مباشرة --}}
  @if($note)<div class="stat-note" title="{{ $note }}">{{ $note }}</div>@endif
  @if($link)
    <a href="{{ $link[0] }}" class="stat-link stretched-link">
      {{ $link[1] }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
    </a>
  @endif
</div>
