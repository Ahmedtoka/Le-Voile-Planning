@extends('layouts.app')
@section('content')

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'marker'])

@php $hasFilters = collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty(); @endphp

<div class="card">
  <div class="card-header">
    <span>{{ $title }} <span class="pill pill-muted num">{{ number_format($rows->total()) }}</span></span>

    <a href="{{ route('markers.create') }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> ماركر</a>

    <form class="filter-bar" method="get" role="search" aria-label="فلترة الماركرات">
      <div class="fb-search">
        <i class="bi bi-rulers" aria-hidden="true"></i>
        <input type="number" step="0.01" min="0" name="width" value="{{ request('width') }}" class="form-control w-mid"
               aria-label="ينفع على عرض قماش (سم)" placeholder="ينفع على عرض… (سم)">
      </div>
      <select name="status" class="form-select" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach(['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي'] as $k=>$v)
          <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
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
      <thead><tr><th>الكود</th><th>الاسم</th><th>المصنع</th><th>عرض القماش</th><th>طول الفرشة</th>
        <th>قطع الفرشة</th><th>الموديلات</th><th>الباترونست</th><th>الحالة</th>
        <th style="width:60px"><span class="visually-hidden">إجراءات</span></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold"><a href="{{ route('markers.edit',$r) }}">{{ $r->code }}</a></td>
          <td>{{ $r->name ?: '—' }}</td>
          <td>{{ $r->factory?->name ?? '—' }}</td>
          <td class="num">{{ $r->fabric_width_cm }} <span class="hint">سم</span></td>
          <td class="num">{{ $r->spread_length_m }} <span class="hint">م</span></td>
          <td class="num fw-bold">{{ $r->pieces_per_spread }}</td>
          <td class="hint">
            @foreach($r->lines as $l)
              {{ $l->productModel?->name }}@if($l->size)/{{ $l->size->name }}@endif ×{{ $l->qty_per_spread }}@if(!$loop->last) · @endif
            @endforeach
          </td>
          <td>{{ $r->patternist?->name ?? '—' }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_label }}</span></td>
          <td class="text-nowrap text-end"><div class="row-actions">
            <a href="{{ route('markers.edit',$r) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="فتح الماركر" aria-label="فتح الماركر {{ $r->code }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
          </div></td>
        </tr>
      @empty
        <tr><td colspan="10">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">{{ $hasFilters ? 'مفيش ماركرات مطابقة للفلاتر.' : 'مفيش ماركرات لسه.' }}</div>
            @if($hasFilters)
              <div class="hint mb-2">جرّب تشيل الفلاتر أو جرّب عرض أكبر.</div>
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-1">امسح الفلاتر واعرض الكل</a>
            @else
              <div class="hint mb-2">الماركر بيتعمل ردًا على طلب ماركر، أو مباشرة من هنا.</div>
              <a href="{{ route('markers.create') }}" class="btn btn-sm btn-plum mt-1"><i class="bi bi-plus-lg" aria-hidden="true"></i> ارفع أول ماركر</a>
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
@endsection
