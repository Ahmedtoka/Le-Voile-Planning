@php
  $steps = [
    'planning'   => ['التخطيط',        'bi-pencil-square'],
    'purchasing' => ['التسعير',        'bi-cart3'],
    'approved'   => ['مستني الاستلام', 'bi-hourglass-split'],
    'receiving'  => ['الاستلام',       'bi-truck'],
  ];
  // مراحل قديمة (finance/approval) بتتعامل كأنها اتسعّرت
  $stage = in_array($row->stage, ['approval', 'finance'], true) ? 'approved' : $row->stage;
  $order = array_keys($steps);
  $now   = array_search($stage, $order, true);
  $now   = $now === false ? count($order) - 1 : $now;
@endphp
<div class="card mb-3"><div class="card-body py-3">
  <ol class="stepper list-unstyled mb-0" aria-label="مراحل الطلب">
    @foreach($steps as $key => [$label, $icon])
      @php $i = $loop->index; $state = $i < $now ? 'done' : ($i === $now ? 'now' : 'todo'); @endphp
      <li class="st {{ $state }}" @if($state === 'now') aria-current="step" @endif>
        <div class="c"><i class="bi {{ $state==='done' ? 'bi-check-lg' : $icon }}" aria-hidden="true"></i></div>
        <span class="lbl">{{ $label }}</span>
      </li>
    @endforeach
  </ol>
</div></div>
