@extends('layouts.app')
@section('purpose', 'أوامر شغل مصنعك: اللي مستني قص، واللي عليه قطع تتسلّم — من الموبايل أو اللاب')
@section('content')

@if(!$factory)
  <div class="card">
    <div class="card-body">
      <div class="note-box mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        المستخدم ده مش مربوط بمصنع. اربطه من <b>الإعدادات ← المستخدمين</b> (حقل «المصنع»)،
        أو اختار مصنع من هنا للتجربة.
      </div>
      @if($factories->count())
        <form method="get" class="d-flex gap-2" style="max-width:420px">
          <select name="factory_id" class="form-select form-select-sm">
            @foreach($factories as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select>
          <button class="btn btn-sm btn-plum">افتح</button>
        </form>
      @endif
    </div>
  </div>
@else

<style>
  .fp-card{border:1px solid var(--lv-line);border-radius:10px;padding:12px;margin-bottom:10px;background:#fff}
  .fp-card .t{font-weight:700;font-size:1rem}
  .fp-card .btn{min-height:40px}
  @media (max-width:576px){ .fp-card .btn{width:100%} }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="badge bg-light text-dark" style="font-size:.9rem"><i class="bi bi-building" aria-hidden="true"></i> {{ $factory->name }}</span>
  <span class="hint">{{ $open->count() }} أمر مفتوح</span>
  @if(auth()->user()->isAdmin() || !auth()->user()->factory_id)
    <form method="get" class="ms-auto d-flex gap-1">
      <select name="factory_id" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected($k == $factory->id)>{{ $v }}</option>@endforeach
      </select>
    </form>
  @endif
</div>

@forelse($open as $w)
  @php
    $needsCut = in_array($w->status, ['sent_to_factory', 'cutting'], true)
      && !$w->cutDeclarations()->where('status', 'approved')->exists();
    $hasOutstanding = $w->outstanding_pieces > 0;
    $breaks = $w->fabrics->filter(fn ($f) => $f->consignment?->has_color_break);
  @endphp
  <div class="fp-card {{ $w->is_late ? 'border-danger' : '' }}">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <div class="t"><a href="{{ route('work-orders.show', $w) }}">{{ $w->wo_no }}</a> — {{ $w->product_title }}</div>
        <div class="hint">
          {{ $w->fabrics->map(fn ($f) => ($f->fabricType?->name ?? '') . ' ' . ($f->color?->code ?? '') . ' · ' . ($f->consignment?->consignment_no ?? ''))->implode(' | ') }}
        </div>
        <div class="small mt-1">
          <b>المستهدف:</b> <span class="num">{{ number_format($w->target_qty) }}</span> قطعة ·
          <b>مقصوص:</b> <span class="num">{{ number_format((int) $w->cut_pieces) }}</span> ·
          <b>مستلم:</b> <span class="num">{{ number_format((int) $w->received_pieces) }}</span> ·
          <b>متبقي عليكم:</b> <span class="num {{ $hasOutstanding ? 'text-danger fw-bold' : '' }}">{{ number_format($w->outstanding_pieces) }}</span>
          @if($w->due_date) · <b>التسليم:</b> <span class="num {{ $w->is_late ? 'text-danger fw-bold' : '' }}">{{ $w->due_date->format('Y-m-d') }}</span> @endif
        </div>
        @if($breaks->count())
          <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            فواصل / اختلاف لون في: {{ $breaks->map(fn ($f) => $f->consignment->consignment_no . ' (' . $f->consignment->color_break_note . ')')->implode('، ') }}</div>
        @endif
      </div>
      <span class="badge bg-{{ $w->status_color }} align-self-start">{{ $w->status_name }}</span>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <a href="{{ route('work-orders.print', $w) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer" aria-hidden="true"></i> ورقة الأمر</a>
      @if(auth()->user()->can2('cut.manage'))
        <a href="{{ route('cut-declarations.create', ['work_order_id' => $w->id]) }}" class="btn btn-sm {{ $needsCut ? 'btn-plum' : 'btn-outline-plum' }}">
          <i class="bi bi-scissors" aria-hidden="true"></i> سجّل القص</a>
      @endif
      @if(auth()->user()->can2('prod.manage') && $w->cut_pieces > 0)
        <a href="{{ route('production-receipts.create', ['work_order_id' => $w->id]) }}" class="btn btn-sm {{ $hasOutstanding ? 'btn-success' : 'btn-outline-success' }}">
          <i class="bi bi-inboxes" aria-hidden="true"></i> سلّم إنتاج</a>
      @endif
    </div>
  </div>
@empty
  <div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i><div class="t">مفيش أوامر شغل مفتوحة على المصنع ده.</div></div>
@endforelse

@if($done->count())
  <details class="mt-3">
    <summary class="hint">آخر الأوامر المقفولة ({{ $done->count() }})</summary>
    <ul class="small mt-2">
      @foreach($done as $w)
        <li><a href="{{ route('work-orders.show', $w) }}">{{ $w->wo_no }}</a> — {{ $w->product_title }} · {{ number_format((int) $w->received_pieces) }} قطعة</li>
      @endforeach
    </ul>
  </details>
@endif
@endif
@endsection
