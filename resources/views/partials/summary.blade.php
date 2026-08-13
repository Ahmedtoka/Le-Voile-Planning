{{-- شريط سامري فوق أي جدول --}}
@if(!empty($summary))
<div class="row g-2 mb-3">
  @foreach($summary as $s)
    <div class="col-6 col-md-4 col-lg">
      @include('partials.kpi', [
        'value' => $s['value'],
        'label' => $s['label'],
        'tone'  => $s['tone'] ?? 'ink',
        'sub'   => $s['sub']  ?? null,
        'note'  => $s['note'] ?? null,
        'link'  => $s['link'] ?? null,
      ])
    </div>
  @endforeach
</div>
@endif
