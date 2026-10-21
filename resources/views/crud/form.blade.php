@extends('layouts.app')
@section('content')

<form method="post" enctype="multipart/form-data"
      action="{{ $mode === 'create' ? route($routeName.'.store') : route($routeName.'.update', $row->id) }}">
  @csrf
  @if($mode === 'edit') @method('PUT') @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>{{ $mode === 'create' ? 'إضافة' : 'تعديل' }} {{ $singular }}</span>
      <a href="{{ route($routeName.'.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($fields as $f)
          @php
            $type = $f['type'] ?? 'text';
            $val  = old($f['name'], $row->{$f['name']} ?? null);
            $req  = in_array('required', $f['rules'] ?? []);
            $opts = $f['options'] ?? (isset($f['options_from']) ? (${$f['options_from']} ?? []) : []);
          @endphp

          <div class="col-md-{{ $type === 'textarea' ? 12 : 4 }}">
            @if($type === 'checkbox')
              <div class="form-check mt-4">
                <input type="hidden" name="{{ $f['name'] }}" value="0">
                <input class="form-check-input" type="checkbox" name="{{ $f['name'] }}" value="1"
                       id="f_{{ $f['name'] }}" @checked($val)>
                <label class="form-check-label" for="f_{{ $f['name'] }}">{{ $f['label'] }}</label>
              </div>
            @else
              <label class="form-label {{ $req ? 'req' : '' }}">{{ $f['label'] }}</label>

              @if($type === 'select')
                <select name="{{ $f['name'] }}" class="form-select form-select-sm">
                  <option value="">— اختر —</option>
                  @foreach($opts as $k => $v)
                    <option value="{{ $k }}" @selected((string)$val === (string)$k)>{{ $v }}</option>
                  @endforeach
                </select>
              @elseif($type === 'textarea')
                <textarea name="{{ $f['name'] }}" rows="2" class="form-control form-control-sm">{{ $val }}</textarea>
              @elseif($type === 'date')
                <input type="date" name="{{ $f['name'] }}" class="form-control form-control-sm"
                       value="{{ $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : $val }}">
              @elseif($type === 'number')
                <input type="number" step="{{ $f['step'] ?? '1' }}" name="{{ $f['name'] }}"
                       class="form-control form-control-sm" value="{{ $val }}">
              @elseif($type === 'file')
                <input type="file" name="{{ $f['name'] }}" accept="image/*" class="form-control form-control-sm">
                @if($val)<div class="mt-1"><img src="{{ $val }}" alt="" style="max-height:90px;border:1px solid var(--lv-line);border-radius:6px"></div>@endif
              @else
                <input type="text" name="{{ $f['name'] }}" class="form-control form-control-sm" value="{{ $val }}">
              @endif

              @if(!empty($f['hint']))<div class="hint mt-1">{{ $f['hint'] }}</div>@endif
            @endif
          </div>
        @endforeach
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>
      <a href="{{ route($routeName.'.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    </div>
  </div>
</form>
@endsection
