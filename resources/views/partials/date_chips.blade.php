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
<div class="d-flex gap-2 flex-wrap align-items-center px-3 py-2"
     style="border-bottom:1px solid var(--lv-line-soft);background:var(--lv-offwhite)">
  <span class="hint">مدى سريع:</span>
  @foreach($chips as $lbl => $range)
    @php $on = request('from') === $range['from'] && request('to') === $range['to']; @endphp
    <a href="{{ $qs($range) }}" class="btn btn-sm py-0 {{ $on ? 'btn-plum' : 'btn-outline-secondary' }}"
       style="font-size:.75rem">{{ $lbl }}</a>
  @endforeach
  @if(request('from') || request('to'))
    <a href="{{ $qs([]) }}" class="btn btn-sm btn-link text-muted py-0 px-1" style="font-size:.75rem">كل التواريخ</a>
  @endif
</div>
