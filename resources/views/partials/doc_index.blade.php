@extends('layouts.app')
@section('content')
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:170px" placeholder="بحث برقم المستند…">
      <select name="status" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        @foreach(['draft'=>'مسودة','pending'=>'تحت الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض'] as $k=>$v)
          <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    @if(!empty($createRoute))
      <a href="{{ route($createRoute) }}" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> {{ $createLabel }}</a>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>@foreach($cols as $c)<th>{{ $c }}</th>@endforeach<th></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          {!! $rowRenderer($r) !!}
          <td class="text-nowrap">
            <a href="{{ route($editRoute, $r) }}" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-pencil"></i></a>
            @if(!empty($printRoute))
              <a href="{{ route($printRoute, $r) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i></a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="20" class="text-center text-muted py-4">مفيش مستندات.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
