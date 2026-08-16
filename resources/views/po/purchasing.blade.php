@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  دي طلبات الشراء اللي <b>نزلت من التخطيط ومستنياك</b>. افتح الطلب، حدد المورد
  والسعر والوحدة وتاريخ التوريد، واضغط «نزّل للحسابات» — الطلب يختفي من هنا
  ويظهر عند الحسابات. <b>الأقدم فوق</b> عشان محدش يستنى كتير.
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:150px" placeholder="رقم الطلب…">
      <select name="requested_by" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
        <option value="">كل الطالبين</option>
        @foreach($requesters as $k=>$v)<option value="{{ $k }}" @selected(request('requested_by')==$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الطلب</th><th>طلبه مين</th><th>إمتى</th><th>مستني بقاله</th>
        <th>الأصناف المطلوبة</th><th>الإجمالي</th><th style="width:130px"></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php $days = (int) ($r->requested_at?->diffInDays(now(), true) ?? 0); @endphp
        <tr class="{{ $days > 3 ? 'table-warning' : '' }}">
          <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
          <td>
            {{ $r->requester?->name ?? '—' }}
            @if($r->planning_note)<div class="hint">{{ Str::limit($r->planning_note, 45) }}</div>@endif
          </td>
          <td class="num">{{ $r->requested_at?->format('Y-m-d') }}</td>
          <td class="num {{ $days > 3 ? 'text-danger fw-bold' : '' }}">
            {{ $days === 0 ? 'النهارده' : $days . ' يوم' }}
          </td>
          <td class="hint">
            @foreach($r->lines->take(3) as $l)
              {{ $l->fabricType?->name }} {{ $l->color?->name }}
              ({{ rtrim(rtrim(number_format((float)$l->qty,2),'0'),'.') }} {{ $l->unit }})@if(!$loop->last) · @endif
            @endforeach
            @if($r->lines->count() > 3) … +{{ $r->lines->count() - 3 }} @endif
          </td>
          <td class="num">{{ rtrim(rtrim(number_format((float)$r->total_qty,2),'0'),'.') }}</td>
          <td>
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-plum py-1 w-100">
              <i class="bi bi-tag" aria-hidden="true"></i> سعّر الطلب
            </a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          مفيش طلبات مستنية تسعير — كله متسعّر.
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
