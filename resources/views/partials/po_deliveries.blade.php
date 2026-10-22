{{--
  تاريخ الاستلام على طلب الشراء: لكل صنف — المطلوب، وكل دفعة وصلت بتاريخها ورقم إذنها، والمستلم والباقي.
  الاستخدام: @include('partials.po_deliveries', ['po' => $po])
--}}
@php
  $pdPo = $po;
  $pdDeliveries = $pdPo->deliveryHistory();
  $pdOpen = $pdPo->lines->contains(fn ($l) => !$l->is_closed && (float) $l->min_allowed_qty - (float) $l->received_qty > 0.0001);
  $pdCanReceive = auth()->user()?->can2('receipt.manage');
  $n = fn ($v, $d = 3) => rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
@endphp
<div class="card mt-3">
  <div class="card-header">
    <span><i class="bi bi-clock-history" aria-hidden="true"></i> تاريخ الاستلام على الطلب <span class="num">{{ $pdPo->po_no }}</span>
      <span class="hint">— كل صنف وصل على كام مرة وبأي تاريخ</span></span>
    @if($pdOpen && $pdCanReceive)
      <a href="{{ route('stock-additions.create', ['purchase_order_id' => $pdPo->id]) }}" class="btn btn-sm btn-outline-plum ms-auto">
        <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> استلم الباقي</a>
    @elseif(!$pdOpen)
      <span class="badge bg-success ms-auto">الطلب اكتمل</span>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr>
        <th>الصنف</th><th>اللون</th><th>المطلوب</th><th>الدفعات اللي وصلت</th><th>المستلم</th><th>الباقي</th><th>الحالة</th>
      </tr></thead>
      <tbody>
      @foreach($pdPo->lines as $l)
        @php
          $d = $pdDeliveries[$l->id] ?? [];
          $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty);
        @endphp
        <tr class="{{ $l->is_closed ? '' : ($left > 0 ? 'table-warning' : '') }}">
          <td>{{ $l->fabricType?->name ?? $l->item_name }}</td>
          <td>{{ $l->color?->label ?? $l->color?->code ?? '—' }}</td>
          <td class="num fw-bold">{{ $n($l->qty) }} {{ $l->unit }}
            <div class="hint">زيادة {{ $n($l->tolerance_pct, 2) }}%
              @include('partials.fx', ['t' => 'أقصى كمية مسموح استلامها', 'f' => $n($l->qty) . ' × (1 + ' . $n($l->tolerance_pct, 2) . '%) = ' . $n($l->max_allowed_qty) . ' ' . $l->unit, 'why' => 'الزيادة بتسمح بالأكتر بس — الأقل بيفضل باقي مفتوح'])</div></td>
          <td>
            @forelse($d as $k => $dl)
              <div class="small">
                <span class="badge bg-light text-dark">{{ $k + 1 }}</span>
                <b class="num">{{ $n($dl['qty']) }} {{ $dl['unit'] }}</b>
                <span class="hint">· {{ $dl['date'] }} ·</span>
                <a href="{{ route('stock-additions.edit', $dl['sa_id']) }}" class="num">{{ $dl['doc_no'] }}</a>
                @if($dl['supplier_doc_no'])<span class="hint">· إذن المورد {{ $dl['supplier_doc_no'] }}</span>@endif
                @if($dl['rolls'])<span class="hint">· {{ $dl['rolls'] }} توب</span>@endif
              </div>
            @empty
              <span class="hint">لسه ما وصلش</span>
            @endforelse
          </td>
          <td class="num">{{ $n($l->received_qty) }} {{ $l->unit }}
            @if(count($d) > 1)
              @include('partials.fx', ['t' => 'المستلم', 'f' => collect($d)->map(fn ($x) => $n($x['qty']))->implode(' + ') . ' = ' . $n($l->received_qty) . ' ' . $l->unit, 'why' => 'مجموع الدفعات اللي وصلت'])
            @endif</td>
          <td class="num {{ $left > 0 ? 'text-danger fw-bold' : 'text-success' }}">{{ $left > 0 ? $n($left) . ' ' . $l->unit : '—' }}
            @include('partials.fx', ['t' => 'الباقي على الطلب', 'f' => 'المطلوب ' . $n($l->min_allowed_qty) . ' − الواصل ' . $n($l->received_qty) . ' = ' . $n($left) . ' ' . $l->unit])</td>
          <td>
            @if($l->is_closed)
              <span class="badge bg-success"><i class="bi bi-lock-fill" aria-hidden="true"></i> اكتمل</span>
            @elseif(count($d))
              <span class="badge bg-info">جزئي — باقي {{ $n($left) }}</span>
              @php $eta = collect($d)->pluck('eta')->filter()->last(); @endphp
              @if($eta)<div class="hint">متوقع {{ $eta }}</div>@endif
            @else
              <span class="badge bg-secondary">مستني</span>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
