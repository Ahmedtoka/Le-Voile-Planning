@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:170px" placeholder="رقم الرسالة…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
      </select>
      <select name="color_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الألوان</option>
        @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(request('color_id')==$k)>{{ $v }}</option>@endforeach
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="ready" value="1" id="rdy" @checked(request('ready')) onchange="this.form.submit()">
        <label class="form-check-label small" for="rdy">الجاهز للتشغيل بس</label>
      </div>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('io.export.consignments') }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الرسالة</th><th>التاريخ</th><th>المورد</th><th>الخامة</th><th>اللون</th>
        <th>الوزن</th><th>الأتواب</th><th>أقل عرض</th><th>البنشر</th><th>محجوز</th><th>متاح</th><th>الحالة</th><th></th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="num fw-bold"><a href="{{ route('consignments.show',$r) }}">{{ $r->consignment_no }}</a></td>
          <td class="num">{{ $r->arrival_date?->format('Y-m-d') }}</td>
          <td>{{ $r->supplier?->name ?? '—' }}</td>
          <td>{{ $r->fabricType?->name ?? '—' }}</td>
          <td>{{ $r->color?->code ?? '—' }}</td>
          <td class="num">{{ number_format((float)$r->total_kg,1) }}</td>
          <td class="num">{{ $r->rolls_count }}</td>
          <td class="num {{ $r->min_width_cm ? 'fw-bold' : 'text-muted' }}">{{ $r->min_width_cm ?? '—' }}</td>
          <td class="num {{ $r->avg_gsm ? 'fw-bold' : 'text-muted' }}">{{ $r->avg_gsm ?? '—' }}</td>
          <td class="num {{ $r->hold_kg > 0 ? 'text-warning fw-bold' : 'text-muted' }}">{{ $r->hold_kg > 0 ? number_format((float)$r->hold_kg,1) : '—' }}</td>
          <td class="num {{ $r->remaining_kg > 0 ? 'fw-bold' : 'text-muted' }}">{{ number_format((float)$r->remaining_kg,1) }}</td>
          <td><span class="badge bg-{{ $r->status_color }}">{{ $r->status_name }}</span></td>
          <td class="text-nowrap">
            <a href="{{ route('consignments.show',$r) }}" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-eye"></i></a>
            @php $next = $r->nextStep(); @endphp
            @if($next === 'inspection')
              <a href="{{ route('inspections.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-warning py-0" title="محتاج فحص"><i class="bi bi-search"></i></a>
            @elseif($next === 'lab')
              <a href="{{ route('lab-reports.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-warning py-0" title="محتاج معمل"><i class="bi bi-thermometer-half"></i></a>
            @elseif($next === 'receipt')
              <a href="{{ route('goods-receipts.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-outline-success py-0" title="محتاج إذن استلام (إفراج)"><i class="bi bi-truck"></i></a>
            @elseif($r->is_ready)
              <a href="{{ route('work-orders.create', ['consignment_id'=>$r->id]) }}" class="btn btn-sm btn-success py-0" title="أمر شغل"><i class="bi bi-hammer"></i></a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="13" class="text-center text-muted py-4">مفيش أحواض. الحوض بيتولّد لوحده من إذن الإضافة.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
