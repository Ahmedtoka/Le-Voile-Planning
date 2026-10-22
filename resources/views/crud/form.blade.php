@extends('layouts.app')
@section('content')
@php
  /* الوحدة جوه الخانة لما اسم الحقل بيقولها: «العرض المعياري (سم)» ⇒ label «العرض المعياري» + سم */
  $unitOf = function (array $f): array {
      $label = $f['label'];
      if (!empty($f['unit'])) return [$label, $f['unit']];
      if (($f['type'] ?? 'text') !== 'number') return [$label, null];
      $map = ['جم/م²' => 'جم/م²', 'كجم/قطعة' => 'كجم/قطعة', 'كجم' => 'كجم', 'سم' => 'سم', 'يوم' => 'يوم', 'قطعة' => 'قطعة', 'متر' => 'م', '%' => '%'];
      foreach ($map as $needle => $unit) {
          if (preg_match('/\s*\(\s*' . preg_quote($needle, '/') . '\s*\)\s*$/u', $label)) {
              return [preg_replace('/\s*\(\s*' . preg_quote($needle, '/') . '\s*\)\s*$/u', '', $label), $unit];
          }
          if ($needle === '%' && preg_match('/\s%\s*$/u', $label)) {
              return [preg_replace('/\s%\s*$/u', '', $label), '%'];
          }
      }
      return [$label, null];
  };
@endphp

<form method="post" enctype="multipart/form-data"
      action="{{ $mode === 'create' ? route($routeName.'.store') : route($routeName.'.update', $row->id) }}">
  @csrf
  @if($mode === 'edit') @method('PUT') @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>{{ $mode === 'create' ? 'إضافة' : 'تعديل' }} {{ $singular }}</span>
      <a href="{{ route($routeName.'.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($fields as $f)
          @php
            $type = $f['type'] ?? 'text';
            $val  = old($f['name'], $row->{$f['name']} ?? null);
            $req  = in_array('required', $f['rules'] ?? []);
            $opts = $f['options'] ?? (isset($f['options_from']) ? (${$f['options_from']} ?? []) : []);
            [$label, $unit] = $unitOf($f);
            $fid  = 'f_' . $f['name'];
          @endphp

          <div class="col-md-{{ $type === 'textarea' ? 12 : ($type === 'file' ? 6 : 4) }}">
            @if($type === 'checkbox')
              <div class="form-check mt-4">
                <input type="hidden" name="{{ $f['name'] }}" value="0">
                <input class="form-check-input" type="checkbox" name="{{ $f['name'] }}" value="1"
                       id="{{ $fid }}" @checked($val)>
                <label class="form-check-label" for="{{ $fid }}">{{ $f['label'] }}</label>
              </div>
              @if(!empty($f['hint']))<div class="hint mt-1">{{ $f['hint'] }}</div>@endif
            @else
              <label class="form-label {{ $req ? 'req' : '' }}" for="{{ $fid }}">{{ $label }}</label>

              @if($type === 'select')
                <select name="{{ $f['name'] }}" id="{{ $fid }}" class="form-select" @required($req)>
                  <option value="">— اختر —</option>
                  @foreach($opts as $k => $v)
                    <option value="{{ $k }}" @selected((string)$val === (string)$k)>{{ $v }}</option>
                  @endforeach
                </select>
              @elseif($type === 'textarea')
                <textarea name="{{ $f['name'] }}" id="{{ $fid }}" rows="2" class="form-control" placeholder="اختياري">{{ $val }}</textarea>
              @elseif($type === 'date')
                <input type="date" name="{{ $f['name'] }}" id="{{ $fid }}" class="form-control" @required($req)
                       value="{{ $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : $val }}">
              @elseif($type === 'number')
                @if($unit)
                  <div class="input-group">
                    <input type="number" step="{{ $f['step'] ?? '1' }}" name="{{ $f['name'] }}" id="{{ $fid }}"
                           class="form-control" value="{{ $val }}" @required($req) placeholder="{{ $f['placeholder'] ?? '' }}">
                    <span class="input-group-text">{{ $unit }}</span>
                  </div>
                @else
                  <input type="number" step="{{ $f['step'] ?? '1' }}" name="{{ $f['name'] }}" id="{{ $fid }}"
                         class="form-control" value="{{ $val }}" @required($req) placeholder="{{ $f['placeholder'] ?? '' }}">
                @endif
              @elseif($type === 'file')
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  @if($val)
                    <img src="{{ $val }}" alt="الصورة الحالية" class="crud-photo">
                  @else
                    <span class="crud-photo crud-photo-empty" aria-hidden="true"><i class="bi bi-image"></i></span>
                  @endif
                  <div class="flex-grow-1">
                    <input type="file" name="{{ $f['name'] }}" id="{{ $fid }}" accept="image/*" class="form-control">
                    <div class="hint mt-1">{{ $val ? 'سيبها فاضية = الصورة الحالية تفضل زي ما هي' : 'صورة JPG أو PNG لحد 4 ميجا' }}</div>
                  </div>
                </div>
              @else
                <input type="text" name="{{ $f['name'] }}" id="{{ $fid }}" class="form-control" value="{{ $val }}" @required($req)
                       placeholder="{{ $f['placeholder'] ?? ($f['name'] === 'code' ? 'مثال: ' . strtoupper(substr($routeName, 0, 3)) . '-001' : '') }}">
              @endif

              @if(!empty($f['hint']))<div class="hint mt-1">{{ $f['hint'] }}</div>@endif
            @endif
          </div>
        @endforeach
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> حفظ</button>
      <div class="fa-end">
        <a href="{{ route($routeName.'.index') }}" class="btn btn-outline-secondary">إلغاء</a>
      </div>
    </div>
  </div>
</form>

<style>
  .crud-photo{width:88px;height:88px;object-fit:cover;border:1px solid var(--lv-line);border-radius:var(--r-md);background:var(--lv-surface-2);flex:0 0 88px}
  .crud-photo-empty{display:inline-flex;align-items:center;justify-content:center;color:var(--lv-faint);font-size:1.6rem}
</style>
@endsection
