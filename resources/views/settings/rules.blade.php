@extends('layouts.app')
@section('purpose', 'القواعد اللي السيستم بيحسب بيها — عدّلها من هنا بدل الكود')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  <span>الأرقام دي بتدخل في الحسبة على طول: عيّنة الفحص، تأمين العروض، هامش الفرشة، ونسبة الزيادة الافتراضية. غيّرها واحفظ — بتشتغل من أول شاشة بعدها.</span>
</div>

<form method="post" action="{{ route('settings.rules.save') }}">@csrf
  <div class="card">
    <div class="card-header">قواعد الحساب</div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($rules as $key => $r)
          <div class="col-md-6 col-xl-3">
            <label class="form-label" for="rule_{{ $key }}">{{ $r['label'] }}</label>
            <div class="input-group">
              <input type="number" step="{{ $r['step'] }}" min="0" name="rules[{{ $key }}]" id="rule_{{ $key }}" class="form-control"
                     value="{{ old('rules.' . $key, $r['value']) }}" required>
              <span class="input-group-text">{{ $r['unit'] }}</span>
            </div>
            <div class="hint">{{ $r['hint'] }}</div>
          </div>
        @endforeach
      </div>
    </div>
    <div class="card-footer form-actions m-0">
      <button class="btn btn-plum"><i class="bi bi-save" aria-hidden="true"></i> احفظ القواعد</button>
      <span class="hint">بتتسجل في سجل الحركة مين غيّر إيه.</span>
    </div>
  </div>
</form>
@endsection
