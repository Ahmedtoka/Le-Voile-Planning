@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  الدورة: <b>التخطيط</b> يكتب الأصناف والكميات ← <b>المشتريات</b> تحدد المورد والسعر والوحدة
  وتاريخ التوريد ← <b>الحسابات</b> تعلم بالمستحق — <b>من غير أي اعتماد</b> — وبعدها الطلب
  جاهز يتستقبل عليه أذون إضافة.
</div>

@include('partials.summary')

{{-- تابات المراحل --}}
<div class="d-flex gap-2 flex-wrap mb-3">
  <a href="{{ route('purchase-orders.index') }}"
     class="btn btn-sm {{ !request('stage') ? 'btn-plum' : 'btn-outline-plum' }}">
    الكل <span class="badge bg-light text-dark">{{ $counts->sum() }}</span>
  </a>
  @foreach($stages as $k => $v)
    @continue($k === 'approval')
    @continue(($counts[$k] ?? 0) === 0 && !in_array($k, ['planning','purchasing','finance'], true))
    <a href="{{ route('purchase-orders.index', ['stage' => $k]) }}"
       class="btn btn-sm {{ request('stage') === $k ? 'btn-plum' : 'btn-outline-plum' }}">
      {{ $v }} <span class="badge bg-light text-dark">{{ $counts[$k] ?? 0 }}</span>
    </a>
  @endforeach
</div>

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input type="hidden" name="stage" value="{{ request('stage') }}">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:150px" placeholder="رقم الطلب…">
      <select name="supplier_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="d-flex align-items-center gap-1">
        <span class="hint">من</span>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:132px">
        <span class="hint">إلى</span>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:132px">
      </div>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine')) onchange="this.form.submit()">
        <label class="form-check-label small" for="mine">طلباتي</label>
      </div>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    @if(auth()->user()->can2('po.request'))
      <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> طلب شراء</a>
    @endif
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الطلب</th><th>حالة الطلب</th><th>المورد</th><th>الأصناف</th><th>الإجمالي</th>
        <th>متوقع وصوله</th><th>آخر أبديت</th><th>مين عمله</th><th style="width:90px"></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $eta     = $r->delivery_date;
          $etaLate = $eta && $eta->isPast() && in_array($r->stage, ['approved','receiving'], true);
          $lastActor = $r->financer?->name ?? $r->sourcer?->name ?? $r->requester?->name;
        @endphp
        <tr class="{{ $etaLate ? 'table-warning' : '' }}">
          <td class="num fw-bold"><a href="{{ route('purchase-orders.edit',$r) }}">{{ $r->po_no }}</a></td>
          <td>
            <span class="badge bg-{{ $r->stage_color }}">{{ $r->stage_name }}</span>
            @if($r->stage === 'receiving')
              @php
                $rec = (float) $r->lines->sum('received_qty');
                $tot = (float) $r->lines->sum('qty');
              @endphp
              <div class="hint">استلم {{ $tot > 0 ? round($rec / $tot * 100) : 0 }}%</div>
            @endif
          </td>
          <td>{{ $r->supplier?->name ?? '— لسه' }}</td>
          <td class="num">{{ $r->lines->count() }}
            <span class="hint">({{ rtrim(rtrim(number_format((float)$r->total_qty,2),'0'),'.') }})</span></td>
          <td class="num fw-bold">{{ $r->total > 0 ? number_format((float)$r->total, 0) : '—' }}</td>
          <td class="num">
            @if($eta)
              {{ $eta->format('Y-m-d') }}
              <div class="hint {{ $etaLate ? 'text-danger fw-bold' : '' }}">
                @if($etaLate)
                  <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                  متأخر {{ (int) $eta->diffInDays(now(), true) }} يوم
                @elseif(in_array($r->stage, ['approved','receiving'], true))
                  باقي {{ (int) $eta->diffInDays(now(), true) }} يوم
                @endif
              </div>
            @else — @endif
          </td>
          <td class="num hint" title="{{ $r->updated_at->format('Y-m-d H:i') }}">
            {{ $r->updated_at->diffForHumans() }}
          </td>
          <td class="hint">
            {{ $r->requester?->name ?? '—' }}
            @if($lastActor && $lastActor !== $r->requester?->name)
              <div>آخر تعديل: {{ $lastActor }}</div>
            @endif
          </td>
          <td class="text-nowrap">
            <a href="{{ route('purchase-orders.edit',$r) }}" class="btn btn-sm btn-outline-plum py-0" aria-label="فتح" title="فتح"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            <a href="{{ route('purchase-orders.print',$r) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" aria-label="طباعة" title="طباعة"><i class="bi bi-printer" aria-hidden="true"></i></a>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="text-center text-muted py-4">مفيش طلبات في المرحلة دي.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
