{{-- رأس عمود قابل للترتيب — بيحافظ على كل الفلاتر الشغالة
     الاستخدام: @include('partials.th_sort', ['label' => 'رقم الأمر', 'col' => 'wo_no']) --}}
@php
  $curSort = request('sort');
  $curDir  = request('dir') === 'asc' ? 'asc' : 'desc';
  $on      = $curSort === $col;
  $next    = $on && $curDir === 'asc' ? 'desc' : 'asc';
  $url     = url()->current() . '?' . http_build_query(array_merge(
               request()->except(['page', 'sort', 'dir']), ['sort' => $col, 'dir' => $next]));
@endphp
<th @if($on) aria-sort="{{ $curDir === 'asc' ? 'ascending' : 'descending' }}" @endif
    @if(!empty($width)) style="width:{{ $width }}" @endif>
  <a href="{{ $url }}" class="th-sort {{ $on ? 'on' : '' }}"
     title="ترتيب بـ{{ $label }} {{ $next === 'asc' ? 'تصاعدي' : 'تنازلي' }}">
    {{ $label }}
    <i class="bi {{ $on ? ($curDir === 'asc' ? 'bi-sort-up-alt' : 'bi-sort-down') : 'bi-arrow-down-up' }}"
       aria-hidden="true"></i>
  </a>
</th>
