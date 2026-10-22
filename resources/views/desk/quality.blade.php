@extends('partials.desk')
@section('purpose', 'فحص الأتواب وقراءات المعمل وقرارات المرفوضات')
@php
  $flow='fabric'; $flowStep='inspection';
  $intro = 'بتجرد الأتواب وتقيس العرض والطول، وتسجّل قراءات المعمل. <b>أقل عرض</b> هو المخرج اللي الماركر كله بيتبني عليه.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header">
    <span>رسايل تحت الفحص والمعمل</span>
    <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-plum">تقارير الفحص <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr>
      <th>الرسالة</th><th>الصنف</th><th>اللون</th><th class="num">الكمية (كجم)</th><th class="num">الأتواب</th><th>الحالة</th>
      <th class="text-end"><span class="visually-hidden">إجراءات</span></th>
    </tr></thead>
    <tbody>
    @forelse($recent as $c)
      <tr>
        <td class="num fw-bold"><a href="{{ route('consignments.show',$c) }}">{{ $c->consignment_no }}</a></td>
        <td>{{ $c->fabricType?->name ?? '—' }}</td>
        <td>{{ $c->color?->code ?? '—' }}</td>
        <td class="num">{{ lv_num($c->total_kg, 2) }}</td>
        <td class="num">{{ (int) $c->rolls_count }}</td>
        <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_name }}</span></td>
        <td class="text-end">
          <div class="row-actions">
            @if($c->status === 'under_inspection')
              <a href="{{ route('inspections.create',['consignment_id'=>$c->id]) }}" class="btn btn-sm btn-outline-plum btn-icon"
                 title="افحص الرسالة" aria-label="افحص الرسالة {{ $c->consignment_no }}"><i class="bi bi-search" aria-hidden="true"></i></a>
            @elseif($c->status === 'inspected')
              <a href="{{ route('lab-reports.create',['consignment_id'=>$c->id]) }}" class="btn btn-sm btn-outline-plum btn-icon"
                 title="سجّل تقرير المعمل" aria-label="سجّل تقرير المعمل للرسالة {{ $c->consignment_no }}"><i class="bi bi-thermometer-half" aria-hidden="true"></i></a>
            @endif
            <a href="{{ route('consignments.show',$c) }}" class="btn btn-sm btn-outline-plum btn-icon"
               title="افتح الرسالة" aria-label="افتح الرسالة {{ $c->consignment_no }}"><i class="bi bi-box-arrow-up-left" aria-hidden="true"></i></a>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check2-circle ico" aria-hidden="true"></i>
        <div class="t">مفيش رسايل مستنية فحص.</div>
        <div class="hint">أول ما المخزن يستلم رسالة بإذن إضافة هتظهر هنا للفحص.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
