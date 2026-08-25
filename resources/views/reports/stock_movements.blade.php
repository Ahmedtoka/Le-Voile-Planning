@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  نفس شيت «IN &amp; OUT»: كل حركة داخلة وخارجة بمستندها — استلام مورد، إفراج،
  مرفوض للمرتجعات، صرف مصنع، استلام إنتاج. اضغط رقم الإذن تفتح المستند نفسه.
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap align-items-center" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:170px"
             placeholder="رقم الإذن أو الرسالة…">
      <select name="direction" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">
        <option value="">داخل وخارج</option>
        <option value="in" @selected(request('direction')==='in')>IN — داخل</option>
        <option value="out" @selected(request('direction')==='out')>OUT — خارج</option>
      </select>
      <select name="source_type" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل العمليات</option>
        @foreach($operations as $k=>$v)<option value="{{ $k }}" @selected(request('source_type')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="warehouse_id" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">كل المخازن</option>
        @foreach($warehouses as $k=>$v)<option value="{{ $k }}" @selected(request('warehouse_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="d-flex align-items-center gap-1">
        <span class="hint">من</span>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:135px">
        <span class="hint">إلى</span>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:135px">
      </div>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    <a href="{{ route('io.export.movements', request()->query()) }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        @include('partials.th_sort', ['label'=>'التاريخ','col'=>'moved_at'])
        <th>الصنف</th><th>اللون</th><th>رقم الرسالة</th>
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'direction'])
        <th>العملية</th><th>الجهة (المخزن)</th>
        @include('partials.th_sort', ['label'=>'الكمية','col'=>'qty'])
        <th>الوحدة</th><th>رقم الإذن</th><th>الجودة</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num">{{ \Illuminate\Support\Carbon::parse($r->moved_at)->format('Y-m-d') }}</td>
          <td>{{ $r->fabricType?->name ?? $r->accessory?->name ?? '—' }}</td>
          <td>{{ $r->color?->label ?? $r->color?->code ?? '—' }}</td>
          <td class="num hint">{{ $r->consignment?->consignment_no ?? '—' }}</td>
          <td>
            @if($r->direction === 'in')
              <span class="pill pill-ok">IN داخل</span>
            @else
              <span class="pill pill-warn">OUT خارج</span>
            @endif
          </td>
          <td>{{ $operations[$r->source_type] ?? '—' }}</td>
          <td>{{ $r->warehouse?->name ?? '—' }}</td>
          <td class="num fw-bold">{{ rtrim(rtrim(number_format((float)$r->qty,2),'0'),'.') }}</td>
          <td>{{ $r->unit }}</td>
          <td class="num">{{ $r->reference ?? '—' }}</td>
          <td>
            @if($r->quality_state === 'hold')<span class="pill pill-warn">محجوز</span>
            @elseif($r->quality_state === 'released')<span class="pill pill-ok">مفرج</span>
            @elseif($r->quality_state === 'rejected')<span class="pill pill-danger">مرفوض</span>
            @else <span class="hint">—</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="11">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش حركات مطابقة للفلاتر.</div>
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    <div class="hint">كل حركة بتتسجل تلقائيًا لحظة اعتماد مستندها — مفيش إدخال يدوي هنا.</div>
  </div>
</div>
@endsection
