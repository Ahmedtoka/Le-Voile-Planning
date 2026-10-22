{{-- شريط سامري فوق أي جدول — بياخد $summary من الكونترولر:
     كل كارت: value + label (+ tone, sub, note = جملة تفسّر الرقم, link = [url, label], fx = المعادلة بالأرقام, why) --}}
@if(!empty($summary))
<div class="row g-3 mb-4">
  @foreach($summary as $s)
    <div class="col-6 col-md-4 col-lg">
      @include('partials.kpi', [
        'value' => $s['value'],
        'label' => $s['label'],
        'tone'  => $s['tone'] ?? 'ink',
        'sub'   => $s['sub']  ?? null,
        'note'  => $s['note'] ?? null,
        'link'  => $s['link'] ?? null,
        'fx'    => $s['fx']   ?? null,
        'why'   => $s['why']  ?? null,
      ])
    </div>
  @endforeach
</div>
@endif
