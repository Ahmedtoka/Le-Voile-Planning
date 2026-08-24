@extends('layouts.app')
@section('content')

<div class="card">
  <div class="card-header d-flex align-items-center gap-2 flex-wrap">
    <span>{{ $title }} <span class="hint">({{ $rows->total() }})</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:200px" placeholder="بحث…">
      @foreach($fields as $f)
        @if(!empty($f['filter']))
          <select name="{{ $f['name'] }}" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
            <option value="">كل {{ $f['label'] }}</option>
            @foreach(($f['options'] ?? []) as $k => $v)
              <option value="{{ $k }}" @selected(request($f['name']) == $k)>{{ $v }}</option>
            @endforeach
          </select>
        @endif
      @endforeach
      @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <button class="btn btn-sm btn-outline-secondary" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
    <a href="{{ route($routeName.'.create') }}" class="btn btn-sm btn-plum">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ $singular }} جديد
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          @foreach($fields as $f)
            @if(!empty($f['list']))
              @if(empty($f['relation']) && ($f['type'] ?? '') !== 'checkbox')
                @include('partials.th_sort', ['label' => $f['label'], 'col' => $f['name']])
              @else
                <th>{{ $f['label'] }}</th>
              @endif
            @endif
          @endforeach
          <th style="width:110px"><span class="visually-hidden">إجراءات</span></th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $i => $row)
        <tr>
          <td class="text-muted">{{ $rows->firstItem() + $i }}</td>
          @foreach($fields as $f)
            @if(!empty($f['list']))
              <td>
                @php $val = $row->{$f['name']}; @endphp
                @if(($f['type'] ?? '') === 'checkbox')
                  <span class="badge bg-{{ $val ? 'success' : 'secondary' }}">{{ $val ? 'نعم' : 'لا' }}</span>
                @elseif(!empty($f['relation']))
                  {{ $row->{$f['relation']}->name ?? '—' }}
                @elseif(($f['type'] ?? '') === 'select')
                  {{ ($f['options'][$val] ?? $val) ?: '—' }}
                @elseif(($f['type'] ?? '') === 'date')
                  <span class="num">{{ $val?->format('Y-m-d') ?? '—' }}</span>
                @elseif(($f['type'] ?? '') === 'number')
                  <span class="num">{{ $val !== null ? rtrim(rtrim(number_format((float)$val, 3), '0'), '.') : '—' }}</span>
                @else
                  {{ $val ?: '—' }}
                @endif
              </td>
            @endif
          @endforeach
          <td class="text-nowrap">
            <a href="{{ route($routeName.'.edit', $row->id) }}" class="btn btn-sm btn-outline-plum py-0" aria-label="تعديل" title="تعديل"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            @if($routeName === 'product-models')
              <a href="{{ route('product-models.sizes', $row->id) }}" class="btn btn-sm btn-outline-secondary py-0" title="المقاسات والإكسسوارات"><i class="bi bi-list-check" aria-hidden="true"></i></a>
            @endif
            @if($canDelete)
              <form method="post" action="{{ route($routeName.'.destroy', $row->id) }}" class="d-inline"
                    onsubmit="return confirm('متأكد من الحذف؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger py-0" aria-label="حذف" title="حذف"><i class="bi bi-trash" aria-hidden="true"></i></button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ collect($fields)->filter(fn ($f) => !empty($f['list']))->count() + 2 }}">
          <div class="empty-state">
            <i class="bi bi-inbox ico" aria-hidden="true"></i>
            <div class="t">مفيش بيانات لسه.</div>
            @if(collect(request()->except(['page','sort','dir']))->filter()->isNotEmpty())
              <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-plum mt-2">امسح البحث واعرض الكل</a>
            @else
              <a href="{{ route($routeName.'.create') }}" class="btn btn-sm btn-plum mt-2">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> ضيف {{ $singular }}</a>
            @endif
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer bg-white">{{ $rows->links() }}</div>
</div>
@endsection
