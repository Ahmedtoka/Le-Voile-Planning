@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => ''])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الاستلام مش «قبول كله أو رفض كله». الورق بيسجّل على نفس الإذن:
  <b>رفض أتواب بعينها</b> بوزنها وسببها (قرار الجودة)، و<b>تعليق ألوان</b> لحين رد
  التخطيط والمشتريات. المرفوض ما بيدخلش المخزون وما بيتحسبش على أمر الشراء —
  وبيفضل مطالبة على المورد لحد ما يتقفل.</span>
</div>

@include('partials.summary')

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة المرفوضات">
      <div class="fb-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input name="q" value="{{ request('q') }}" class="form-control" aria-label="بحث" placeholder="رقم الرسالة أو الإذن أو كود اللون…">
      </div>
      <select name="kind" class="form-select" aria-label="النوع" onchange="this.form.submit()">
        <option value="">كل الأنواع</option>
        @foreach(\App\Models\GoodsReceiptRejection::KINDS as $k=>$v)
          <option value="{{ $k }}" @selected(request('kind')===$k)>{{ $v }}</option>
        @endforeach
      </select>
      <select name="resolution" class="form-select" aria-label="القرار" onchange="this.form.submit()">
        <option value="">كل القرارات</option>
        @foreach(\App\Models\GoodsReceiptRejection::RESOLUTIONS as $k=>$v)
          <option value="{{ $k }}" @selected(request('resolution')===$k)>{{ $v }}</option>
        @endforeach
      </select>
      <select name="supplier_id" class="form-select" aria-label="المورد" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        @foreach($suppliers as $k=>$v)<option value="{{ $k }}" @selected(request('supplier_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" title="طبّق الفلاتر"><i class="bi bi-funnel" aria-hidden="true"></i> فلتر</button>
      @if($hasFilters)
        <a href="{{ url()->current() }}" class="btn btn-sm btn-ghost" title="شيل كل الفلاتر"><i class="bi bi-x-lg" aria-hidden="true"></i> مسح</a>
      @endif
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm doc-table">
      <thead><tr>
        <th>النوع</th><th>البند</th><th>الرسالة</th><th>المورد</th><th>أتواب</th><th>الكمية</th>
        <th>الجهة</th><th>السبب</th><th>القرار</th><th style="width:120px"><span class="visually-hidden">إجراءات</span></th>
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
          <td class="num fw-bold">{{ lv_num($r->qty) }} {{ $r->unit }}</td>
          <td class="hint">{{ $r->party_name }}</td>
          <td class="hint">
            <span class="badge bg-{{ $r->reason_category === 'color' ? 'danger' : ($r->is_negotiable ? 'info' : 'secondary') }}">{{ $r->category_name }}</span>
            {{ Str::limit($r->reason, 60) }}
            @if($r->discount_pct !== null)<div class="text-warning fw-bold num">خصم {{ lv_num($r->discount_pct, 2) }}%</div>@endif
          </td>
          <td>
            <span class="badge bg-{{ $r->resolution === 'open' ? 'secondary' : 'success' }}">{{ $r->resolution_name }}</span>
            @if($r->resolved_at)<div class="hint">{{ $r->resolver?->name }} · <span class="num">{{ $r->resolved_at->format('Y-m-d') }}</span></div>@endif
          </td>
          <td class="text-nowrap text-end"><div class="row-actions">
            @if($r->resolution === 'open')
              <button type="button" class="btn btn-sm btn-plum" data-bs-toggle="modal" data-bs-target="#rs{{ $r->id }}"
                      title="سجّل قرار البند ده" aria-label="قفل البند {{ $r->label }}">
                <i class="bi bi-check2-square" aria-hidden="true"></i> قفل البند</button>
            @endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="10">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش بنود مطابقة للفلاتر.' : 'مفيش مرفوضات ولا بنود معلّقة. تمام.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint">البنود بتتسجل من شاشة إذن الاستلام وقت ما الجودة ترفض أتواب أو تعلّق لون.</div>
            @endif
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    @if($rows->total() > 0)
      <div class="hint num">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} من {{ number_format($rows->total()) }}</div>
    @endif
  </div>
</div>

{{-- بوب أبات القفل برّه الجدول — جوه <tbody> المتصفح بيطلّع المحتوى --}}
@foreach($rows as $r)
  @if($r->resolution === 'open')
  <div class="modal fade" id="rs{{ $r->id }}" tabindex="-1" aria-labelledby="rs{{ $r->id }}-t"><div class="modal-dialog"><div class="modal-content">
    <form method="post" action="{{ route('rejections.resolve', $r) }}">@csrf
      <div class="modal-header">
        <h6 class="modal-title" id="rs{{ $r->id }}-t">قفل: {{ $r->label }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <div class="ro-box mb-3">
          <div class="ro-title">من إذن الاستلام — للقراءة</div>
          <div class="hint">السبب المسجّل: {{ $r->reason }}</div>
          <div class="hint">نوع السبب: <b>{{ $r->category_name }}</b> —
            {{ $r->reason_category === 'color' ? 'مش قابل للتفاوض: يرجع للمورد أو يترفض نهائي.' : ($r->is_negotiable ? 'قابل للتفاوض: ممكن يتقبل بخصم من المورد.' : '') }}</div>
        </div>
        <label class="form-label req" for="rs{{ $r->id }}-res">القرار</label>
        <select name="resolution" id="rs{{ $r->id }}-res" class="form-select mb-2" required
                onchange="this.closest('form').querySelector('.disc-wrap').hidden = this.value !== 'accepted_with_discount'">
          @if($r->reason_category !== 'color')
            <option value="accepted">اتقبل بعد المراجعة — يرجع للكمية المتاحة</option>
            <option value="accepted_with_discount">اتقبل بخصم من المورد (عرض/بنشر/انكماش)</option>
          @endif
          <option value="returned">رجع للمورد</option>
          <option value="rejected">اترفض نهائي</option>
        </select>
        <div class="disc-wrap mb-2" hidden>
          <label class="form-label req" for="rs{{ $r->id }}-disc">نسبة الخصم من المورد</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="discount_pct" id="rs{{ $r->id }}-disc" class="form-control" placeholder="مثال: 10">
            <span class="input-group-text">%</span>
          </div>
          <div class="hint">بيوصل إشعار للمشتريات والحسابات بالخصم</div>
        </div>
        <label class="form-label req" for="rs{{ $r->id }}-note">الملاحظة</label>
        <textarea name="resolution_note" id="rs{{ $r->id }}-note" rows="3" class="form-control" required
                  placeholder="مثال: المورد وافق على الاستبدال، والرسالة البديلة هتوصل الأسبوع الجاي"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
        <button class="btn btn-plum btn-sm"><i class="bi bi-check2" aria-hidden="true"></i> تأكيد القرار</button>
      </div>
    </form>
  </div></div></div>
  @endif
@endforeach
@endsection
