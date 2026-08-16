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
  <div class="d-flex align-items-center" style="gap:0">
    @foreach($steps as $key => [$label, $icon])
      @php $i = $loop->index; $state = $i < $now ? 'done' : ($i === $now ? 'now' : 'todo'); @endphp
      <div class="text-center" style="flex:1">
        <div style="width:34px;height:34px;line-height:34px;border-radius:50%;margin:0 auto;
          background:{{ $state==='done' ? 'var(--lv-brand)' : ($state==='now' ? 'var(--lv-tint)' : '#F1EDEF') }};
          color:{{ $state==='done' ? '#fff' : ($state==='now' ? 'var(--lv-brand)' : '#B7ABB2') }};
          border:{{ $state==='now' ? '2px solid var(--lv-brand)' : '0' }};font-size:.9rem">
          <i class="bi {{ $state==='done' ? 'bi-check-lg' : $icon }}"></i>
        </div>
        <div style="font-size:.72rem;margin-top:4px;
          color:{{ $state==='todo' ? '#B7ABB2' : 'var(--lv-brand-ink)' }};
          font-weight:{{ $state==='now' ? 700 : 400 }}">{{ $label }}</div>
      </div>
      @if(!$loop->last)
        <div style="height:2px;flex:.6;margin-bottom:18px;
          background:{{ $i < $now ? 'var(--lv-brand)' : '#EDE6EA' }}"></div>
      @endif
    @endforeach
  </div>
</div></div>
