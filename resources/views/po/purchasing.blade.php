@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <b>مستنية تسعير</b>: افتح الطلب، حدد المورد والسعر والوحدة وتاريخ التوريد واضغط «احفظ» —
  الطلب يتعلّم <b>«اتسعّر — مستني الاستلام»</b> والحسابات توصلها الترانزاكشن.
  <b>اتسعّرت</b>: تتابع منها اللي مستني القماش يوصل.
</div>

@include('partials.summary')

{{-- فلتر الحالة --}}
<div class="d-flex gap-2 flex-wrap mb-3">
  <a href="{{ route('purchasing.queue', ['state' => 'pending']) }}"
     class="btn btn-sm {{ $state === 'pending' ? 'btn-plum' : 'btn-outline-plum' }}">
    مستنية تسعير <span class="badge bg-light text-dark">{{ $counts['pending'] }}</span>
  </a>
  <a href="{{ route('purchasing.queue', ['state' => 'priced']) }}"
     class="btn btn-sm {{ $state === 'priced' ? 'btn-plum' : 'btn-outline-plum' }}">
    اتسعّرت <span class="badge bg-light text-dark">{{ $counts['priced'] }}</span>
  </a>
  <a href="{{ route('purchasing.queue', ['state' => 'all']) }}"
     class="btn btn-sm {{ $state === 'all' ? 'btn-plum' : 'btn-outline-plum' }}">
    الكل <span class="badge bg-light text-dark">{{ $counts['pending'] + $counts['priced'] }}</span>
  </a>
</div>

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input type="hidden" name="state" value="{{ $state }}">
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
        <th>رقم الطلب</th><th>الحالة</th><th>طلبه مين</th><th>إمتى</th>
        <th>الأصناف</th><th>المورد</th><th>تاريخ التوريد</th><th>الإجمالي</th><th style="width:110px"></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $pending = $r->stage === 'purchasing';
          $days    = (int) ($r->requested_at?->diffInDays(now(), true) ?? 0);
        @endphp
        <tr class="{{ $pending && $days > 3 ? 'table-warning' : '' }}">
          <td class="num fw-bold">
            <a href="{{ $pending ? route('purchasing.source',$r) : route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a>
          </td>
          <td>
            <span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span>
            @if($pending && $days > 0)
              <div class="hint {{ $days > 3 ? 'text-danger fw-bold' : '' }}">مستني {{ $days }} يوم</div>
            @endif
          </td>
          <td>
            {{ $r->requester?->name ?? '—' }}
            @if($r->planning_note)<div class="hint">{{ Str::limit($r->planning_note, 40) }}</div>@endif
          </td>
          <td class="num">{{ $r->requested_at?->format('Y-m-d') }}</td>
          <td class="hint">
            @foreach($r->lines->take(2) as $l)
              {{ $l->fabricType?->name }} {{ $l->color?->name }}
              ({{ rtrim(rtrim(number_format((float)$l->qty,2),'0'),'.') }} {{ $l->unit }})@if(!$loop->last) · @endif
            @endforeach
            @if($r->lines->count() > 2) … +{{ $r->lines->count() - 2 }} @endif
          </td>
          <td>{{ $r->supplier?->name ?? '—' }}</td>
          <td class="num">
            @if($r->delivery_date)
              {{ $r->delivery_date->format('Y-m-d') }}
              @if($r->stage === 'approved')
                <div class="hint">{{ $r->delivery_date->isPast() ? 'متأخر' : 'باقي' }}
                  {{ (int) $r->delivery_date->diffInDays(now(), true) }} يوم</div>
              @endif
            @else — @endif
          </td>
          <td class="num">{{ $r->total > 0 ? number_format((float)$r->total, 0) : '—' }}</td>
          <td>
            @if($pending)
              <a href="{{ route('purchasing.source',$r) }}" class="btn btn-sm btn-plum py-1 w-100">
                <i class="bi bi-tag" aria-hidden="true"></i> سعّر
              </a>
            @else
              <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum py-1 w-100">
                <i class="bi bi-eye" aria-hidden="true"></i> عرض
              </a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="text-center text-muted py-4">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          {{ $state === 'pending' ? 'مفيش طلبات مستنية تسعير — كله متسعّر.' : 'مفيش طلبات هنا.' }}
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
