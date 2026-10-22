{{-- شرايح المدى الزمني السريع — بتشتغل مع أي شاشة فيها from/to --}}
@php
  $qs    = fn (array $extra) => url()->current() . '?' . http_build_query(
             array_merge(request()->except(['page', 'from', 'to']), $extra));
  $today = now()->toDateString();
  $chips = [
    'النهارده'       => ['from' => $today, 'to' => $today],
    'آخر ٧ أيام'     => ['from' => now()->subDays(6)->toDateString(),  'to' => $today],
    'آخر ٣٠ يوم'     => ['from' => now()->subDays(29)->toDateString(), 'to' => $today],
    'الشهر ده'       => ['from' => now()->startOfMonth()->toDateString(), 'to' => $today],
    'الشهر اللي فات' => ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                          'to'   => now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
  ];
@endphp
<div class="chip-bar" role="navigation" aria-label="مدى زمني سريع">
  <span class="hint me-1">مدى سريع:</span>
  @foreach($chips as $lbl => $range)
    @php $on = request('from') === $range['from'] && request('to') === $range['to']; @endphp
    <a href="{{ $qs($range) }}" class="chip {{ $on ? 'on' : '' }}" @if($on) aria-current="true" @endif>{{ $lbl }}</a>
  @endforeach
  @if(request('from') || request('to'))
    <a href="{{ $qs([]) }}" class="chip" title="شيل فلتر التاريخ"><i class="bi bi-x-lg" aria-hidden="true"></i> كل التواريخ</a>
  @endif
</div>
