@extends('partials.desk')
@php
  $flow='fabric'; $flowStep='inspection';
  $intro = 'بتجرد الأتواب وتقيس العرض والطول، وتسجّل قراءات المعمل. <b>أقل عرض</b> هو المخرج اللي الماركر كله بيتبني عليه.';
@endphp

@section('tracking')

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>رسايل تحت الفحص والمعمل</span>
    <a href="{{ route('inspections.index') }}" class="btn btn-sm btn-outline-plum py-0">تقارير الفحص</a>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>الرسالة</th><th>الصنف</th><th>اللون</th><th>الكمية</th><th>الأتواب</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
    @forelse($recent as $c)
      <tr>
        <td class="num fw-bold"><a href="{{ route('consignments.show',$c) }}">{{ $c->consignment_no }}</a></td>
        <td>{{ $c->fabricType?->name ?? '—' }}</td>
        <td>{{ $c->color?->code ?? '—' }}</td>
        <td class="num">{{ rtrim(rtrim(number_format((float)$c->total_kg,2),'0'),'.') }} كجم</td>
        <td class="num">{{ (int) $c->rolls_count }}</td>
        <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_name }}</span></td>
        <td>
          @if($c->status === 'under_inspection')
            <a href="{{ route('inspections.create',['consignment_id'=>$c->id]) }}" class="btn btn-sm btn-plum py-0">افحص</a>
          @elseif($c->status === 'inspected')
            <a href="{{ route('lab-reports.create',['consignment_id'=>$c->id]) }}" class="btn btn-sm btn-plum py-0">المعمل</a>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check2-circle ico" aria-hidden="true"></i>
        <div class="t">مفيش رسايل مستنية فحص.</div></div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
