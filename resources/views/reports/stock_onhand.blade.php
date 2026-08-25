@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'fabric', 'step' => 'consign'])

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  نفس شيت «رصيد القماش — ON Hand»: رصيد كل رسالة لوحدها. <b>المتاح</b> = مفرج عنه
  وغير مخصص لأمر شغل · <b>المحجوز</b> = وصل ولسه تحت الفحص. التقرير بيقرا من نفس
  الأحواض اللي الشاشات شغالة بيها — فمفيش رقمين مختلفين.
</div>

@include('partials.summary')

<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap align-items-center" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:170px"
             placeholder="رسالة أو صنف أو لون…">
      <select name="fabric_type_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الخامات</option>
        @foreach($fabrics as $k=>$v)<option value="{{ $k }}" @selected(request('fabric_type_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="status" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="all" value="1" id="all"
               @checked(request('all')) onchange="this.form.submit()">
        <label class="form-check-label small" for="all">اعرض الصفري كمان</label>
      </div>
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
    <a href="{{ route('io.export.onhand', request()->query()) }}" class="btn btn-sm btn-outline-plum"
       title="تصدير إكسيل" aria-label="تصدير إكسيل"><i class="bi bi-download" aria-hidden="true"></i> إكسيل</a>
  </div>

  @include('partials.date_chips')

  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <th>كود الصنف</th><th>الصنف</th><th>اللون</th>
        @include('partials.th_sort', ['label'=>'العرض','col'=>'min_width_cm'])
        @include('partials.th_sort', ['label'=>'رقم الرسالة','col'=>'consignment_no'])
        @include('partials.th_sort', ['label'=>'وصلت','col'=>'arrival_date'])
        @include('partials.th_sort', ['label'=>'الأتواب','col'=>'rolls_count'])
        @include('partials.th_sort', ['label'=>'إجمالي (كجم)','col'=>'total_kg'])
        @include('partials.th_sort', ['label'=>'محجوز','col'=>'hold_kg'])
        @include('partials.th_sort', ['label'=>'متاح','col'=>'remaining_kg'])
        @include('partials.th_sort', ['label'=>'الحالة','col'=>'status'])
        <th><span class="visually-hidden">فتح</span></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num hint">{{ $r->fabricType?->code ?? '—' }}</td>
          <td>{{ $r->fabricType?->name ?? '—' }}</td>
          <td>{{ $r->color?->label ?? $r->color?->code ?? '—' }}</td>
          <td class="num">{{ $r->min_width_cm ?: '—' }}</td>
          <td class="num fw-bold"><a href="{{ route('consignments.show',$r) }}">{{ $r->consignment_no }}</a></td>
          <td class="num">{{ $r->arrival_date?->format('Y-m-d') }}</td>
          <td class="num">{{ (int) $r->rolls_count }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float)$r->total_kg,2),'0'),'.') }}</td>
          <td class="num {{ (float)$r->hold_kg > 0 ? 'text-warning fw-bold' : 'hint' }}">
            {{ (float)$r->hold_kg > 0 ? rtrim(rtrim(number_format((float)$r->hold_kg,2),'0'),'.') : '—' }}</td>
          <td class="num fw-bold {{ (float)$r->remaining_kg > 0 ? 'text-success' : 'hint' }}">
            {{ rtrim(rtrim(number_format((float)$r->remaining_kg,2),'0'),'.') }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td><a href="{{ route('consignments.show',$r) }}" class="btn btn-sm btn-outline-plum py-0"
                 title="فتح" aria-label="فتح الرسالة"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
        </tr>
      @empty
        <tr><td colspan="12">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش رسايل مطابقة للفلاتر.</div>
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>{{ $rows->links() }}</div>
    <div class="hint">المتاح + المحجوز + المنصرف والمرفوض = إجمالي الرسالة.</div>
  </div>
</div>
@endsection
