@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => ''])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  الاستلام مش «قبول كله أو رفض كله». الورق بيسجّل على نفس الإذن:
  <b>رفض أتواب بعينها</b> بوزنها وسببها (قرار الجودة)، و<b>تعليق ألوان</b> لحين رد
  التخطيط والمشتريات. المرفوض ما بيدخلش المخزون وما بيتحسبش على أمر الشراء —
  وبيفضل مطالبة على المورد لحد ما يتقفل.
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <select name="kind" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">الكل</option>
        @foreach(\App\Models\GoodsReceiptRejection::KINDS as $k=>$v)
          <option value="{{ $k }}" @selected(request('kind')===$k)>{{ $v }}</option>
        @endforeach
      </select>
      <select name="resolution" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل القرارات</option>
        @foreach(\App\Models\GoodsReceiptRejection::RESOLUTIONS as $k=>$v)
          <option value="{{ $k }}" @selected(request('resolution')===$k)>{{ $v }}</option>
        @endforeach
      </select>
      <select name="supplier_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <th>النوع</th><th>البند</th><th>الرسالة</th><th>المورد</th><th>أتواب</th><th>الكمية</th>
        <th>الجهة</th><th>السبب</th><th>القرار</th><th style="width:120px"></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr class="{{ $r->resolution === 'open' ? ($r->kind === 'on_hold' ? 'table-warning' : 'table-danger') : '' }}">
          <td>
            <span class="badge bg-{{ $r->kind === 'on_hold' ? 'warning' : 'danger' }}">
              <i class="bi bi-{{ $r->kind === 'on_hold' ? 'pause-circle' : 'x-octagon' }}" aria-hidden="true"></i>
              {{ $r->kind_name }}
            </span>
          </td>
          <td>{{ $r->lot_label ?: ('كود ' . ($r->color_code ?: $r->color?->code ?: '—')) }}</td>
          <td class="num">
            @if($r->consignment)
              <a href="{{ route('consignments.show', $r->consignment) }}">{{ $r->consignment->consignment_no }}</a>
            @else — @endif
          </td>
          <td>{{ $r->consignment?->supplier?->name ?? '—' }}</td>
          <td class="num">{{ $r->rolls_count }}</td>
          <td class="num fw-bold">{{ rtrim(rtrim(number_format((float)$r->qty,3),'0'),'.') }} {{ $r->unit }}</td>
          <td class="hint">{{ $r->party_name }}</td>
          <td class="hint">{{ Str::limit($r->reason, 60) }}</td>
          <td>
            <span class="badge bg-{{ $r->resolution === 'open' ? 'secondary' : 'success' }}">{{ $r->resolution_name }}</span>
            @if($r->resolved_at)<div class="hint">{{ $r->resolver?->name }} · {{ $r->resolved_at->format('Y-m-d') }}</div>@endif
          </td>
          <td class="text-nowrap">
            @if($r->resolution === 'open')
              <button class="btn btn-sm btn-plum py-0" data-bs-toggle="modal" data-bs-target="#rs{{ $r->id }}">قفل البند</button>
            @endif
          </td>
        </tr>

        @if($r->resolution === 'open')
        <div class="modal fade" id="rs{{ $r->id }}"><div class="modal-dialog"><div class="modal-content">
          <form method="post" action="{{ route('rejections.resolve', $r) }}">@csrf
            <div class="modal-header">
              <h6 class="modal-title">قفل: {{ $r->label }}</h6>
              <button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
              <div class="hint mb-2">السبب المسجّل: {{ $r->reason }}</div>
              <label class="form-label req">القرار</label>
              <select name="resolution" class="form-select form-select-sm mb-2" required>
                <option value="accepted">اتقبل بعد المراجعة — يرجع للكمية المتاحة</option>
                <option value="returned">رجع للمورد</option>
                <option value="rejected">اترفض نهائي</option>
              </select>
              <label class="form-label req">الملاحظة</label>
              <textarea name="resolution_note" rows="3" class="form-control form-control-sm" required
                        placeholder="مثال: المورد وافق على الاستبدال، والرسالة البديلة هتوصل الأسبوع الجاي"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-plum btn-sm">تأكيد</button></div>
          </form>
        </div></div></div>
        @endif
      @empty
        <tr><td colspan="10">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش مرفوضات ولا بنود معلّقة. تمام.</div>
            </div>
          </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
