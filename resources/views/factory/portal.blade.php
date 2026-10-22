@extends('layouts.app')
@section('purpose', 'أوامر شغل مصنعك: اللي مستني قص، واللي عليه قطع تتسلّم — من الموبايل أو اللاب')
@section('content')

<style>
/* ══ بوابة المصنع — موبايل أولًا (375px هو الجهاز الأساسي) ══ */
.fp-top{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:center;margin-bottom:var(--sp-4);
  padding:10px 14px;background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-1)}
.fp-top .fp-name{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:var(--fs-md);color:var(--lv-brand-ink)}
.fp-top .fp-name .bi{color:var(--lv-brand)}
.fp-top .fp-pick{margin-inline-start:auto}
.fp-top .fp-pick .form-select{width:220px;max-width:100%}
.fp-list{display:grid;gap:var(--sp-3)}
.fp-card{background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-md);box-shadow:var(--e-1);
  padding:14px;position:relative}
.fp-card.is-late{border-color:var(--lv-danger-line);box-shadow:inset 3px 0 0 var(--lv-danger-dot),var(--e-1)}
.fp-head{display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap}
.fp-head .fp-no{font-size:var(--fs-lg);font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums}
.fp-head .fp-no a{color:var(--lv-brand-ink)}
.fp-head .fp-title{font-size:var(--fs-base);font-weight:600;color:var(--lv-ink);line-height:1.4;overflow-wrap:anywhere}
.fp-head .badge{margin-inline-start:auto;flex:0 0 auto}
.fp-fab{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.6;margin-top:4px;overflow-wrap:anywhere}
/* الأرقام الكبيرة: شبكة 2×2 — عنوان صغير فوق رقم كبير */
.fp-nums{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.fp-num{background:var(--lv-surface-2);border:1px solid var(--lv-line);border-radius:var(--r-sm);padding:8px 10px;text-align:center;min-width:0}
.fp-num .k{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:600;line-height:1.3}
.fp-num .v{font-size:var(--fs-xl);font-weight:700;color:var(--lv-brand-ink);line-height:1.2;font-variant-numeric:tabular-nums;margin-top:2px}
.fp-num.is-due{background:var(--lv-danger-bg);border-color:var(--lv-danger-line)}
.fp-num.is-due .v,.fp-num.is-due .k{color:var(--lv-danger)}
.fp-num.is-zero .v{color:var(--lv-faint)}
.fp-due{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:var(--fs-sm);color:var(--lv-ink-2)}
.fp-due .v{font-weight:700;font-variant-numeric:tabular-nums;direction:ltr}
.fp-due.is-late{color:var(--lv-danger);font-weight:700}
.fp-warn{margin-top:10px;padding:8px 10px;border-radius:var(--r-sm);background:var(--lv-danger-bg);border:1px solid var(--lv-danger-line);
  color:var(--lv-danger);font-size:var(--fs-xs);line-height:1.6;display:flex;gap:6px;align-items:flex-start}
.fp-warn .bi{flex:0 0 auto;margin-top:3px}
/* الأزرار: فوق بعض بعرض الشاشة على الموبايل، وجنب بعض على الشاشة الواسعة */
.fp-actions{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.fp-actions .btn{width:100%;min-height:48px;font-size:var(--fs-md)}
.fp-done summary{font-size:var(--fs-sm);font-weight:600;color:var(--lv-ink-2)}
.fp-done ul{margin:8px 0 0;padding-inline-start:18px;font-size:var(--fs-sm);line-height:1.9}
@media (min-width:600px){
  .fp-list{grid-template-columns:repeat(auto-fill,minmax(360px,1fr))}
  .fp-nums{grid-template-columns:repeat(4,1fr)}
  .fp-actions{flex-direction:row;flex-wrap:wrap}
  .fp-actions .btn{width:auto;flex:1 1 auto;min-height:var(--ctl-h);font-size:var(--fs-sm)}
}
</style>

@if(!$factory)
  <div class="card">
    <div class="card-body">
      <div class="note-box mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>المستخدم ده مش مربوط بمصنع. اربطه من <b>الإعدادات ← المستخدمين</b> (حقل «المصنع»)،
        أو اختار مصنع من هنا للتجربة.</span>
      </div>
      @if($factories->count())
        <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
          <div>
            <label class="form-label" for="fp-factory">المصنع</label>
            <select id="fp-factory" name="factory_id" class="form-select w-wide">
              @foreach($factories as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
          </div>
          <button class="btn btn-plum">افتح البوابة</button>
        </form>
      @endif
    </div>
  </div>
@else

<div class="fp-top">
  <span class="fp-name"><i class="bi bi-building" aria-hidden="true"></i> {{ $factory->name }}</span>
  <span class="hint"><span class="num">{{ $open->count() }}</span> أمر مفتوح</span>
  @if(auth()->user()->isAdmin() || !auth()->user()->factory_id)
    <form method="get" class="fp-pick">
      <label class="visually-hidden" for="fp-switch">غيّر المصنع</label>
      <select id="fp-switch" name="factory_id" class="form-select" onchange="this.form.submit()" aria-label="غيّر المصنع">
        @foreach($factories as $k=>$v)<option value="{{ $k }}" @selected($k == $factory->id)>{{ $v }}</option>@endforeach
      </select>
    </form>
  @endif
</div>

<div class="fp-list">
@forelse($open as $w)
  @php
    $needsCut = in_array($w->status, ['sent_to_factory', 'cutting'], true)
      && !$w->cutDeclarations()->where('status', 'approved')->exists();
    $hasOutstanding = $w->outstanding_pieces > 0;
    $breaks = $w->fabrics->filter(fn ($f) => $f->consignment?->has_color_break);
    $lateDays = ($w->is_late && $w->due_date) ? (int) $w->due_date->diffInDays(today(), true) : 0;
  @endphp
  <article class="fp-card {{ $w->is_late ? 'is-late' : '' }}" aria-labelledby="fp-{{ $w->id }}">
    <div class="fp-head">
      <div style="min-width:0;flex:1 1 auto">
        <div class="fp-no" id="fp-{{ $w->id }}"><a href="{{ route('work-orders.show', $w) }}">{{ $w->wo_no }}</a></div>
        <div class="fp-title">{{ $w->product_title }}</div>
      </div>
      <span class="badge bg-{{ $w->status_color }}">{{ $w->status_name }}</span>
    </div>
    <div class="fp-fab">
      {{ $w->fabrics->map(fn ($f) => ($f->fabricType?->name ?? '') . ' ' . ($f->color?->code ?? '') . ' · ' . ($f->consignment?->consignment_no ?? ''))->implode(' | ') }}
    </div>

    <div class="fp-nums">
      <div class="fp-num">
        <div class="k">المستهدف</div>
        <div class="v num">{{ number_format($w->target_qty) }}</div>
      </div>
      <div class="fp-num {{ (int) $w->cut_pieces === 0 ? 'is-zero' : '' }}">
        <div class="k">مقصوص</div>
        <div class="v num">{{ number_format((int) $w->cut_pieces) }}</div>
      </div>
      <div class="fp-num {{ (int) $w->received_pieces === 0 ? 'is-zero' : '' }}">
        <div class="k">مستلم</div>
        <div class="v num">{{ number_format((int) $w->received_pieces) }}</div>
      </div>
      <div class="fp-num {{ $hasOutstanding ? 'is-due' : 'is-zero' }}">
        <div class="k">متبقي عليكم</div>
        <div class="v num">{{ number_format($w->outstanding_pieces) }}</div>
      </div>
    </div>

    @if($w->due_date)
      <div class="fp-due {{ $w->is_late ? 'is-late' : '' }}">
        <i class="bi {{ $w->is_late ? 'bi-exclamation-triangle-fill' : 'bi-calendar-event' }}" aria-hidden="true"></i>
        <span>التسليم:</span>
        <span class="v">{{ $w->due_date->format('Y-m-d') }}</span>
        @if($w->is_late)<span>— متأخر {{ $lateDays }} يوم</span>@endif
      </div>
    @endif

    @if($breaks->count())
      <div class="fp-warn"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span>فواصل / اختلاف لون في: {{ $breaks->map(fn ($f) => $f->consignment->consignment_no . ' (' . $f->consignment->color_break_note . ')')->implode('، ') }}</span></div>
    @endif

    <div class="fp-actions">
      @if(auth()->user()->can2('cut.manage'))
        <a href="{{ route('cut-declarations.create', ['work_order_id' => $w->id]) }}" class="btn btn-lg {{ $needsCut ? 'btn-plum' : 'btn-outline-plum' }}">
          <i class="bi bi-scissors" aria-hidden="true"></i> سجّل القص</a>
      @endif
      @if(auth()->user()->can2('prod.manage') && $w->cut_pieces > 0)
        <a href="{{ route('production-receipts.create', ['work_order_id' => $w->id]) }}" class="btn btn-lg {{ $hasOutstanding ? 'btn-success' : 'btn-outline-success' }}">
          <i class="bi bi-inboxes" aria-hidden="true"></i> سلّم إنتاج</a>
      @endif
      <a href="{{ route('work-orders.print', $w) }}" target="_blank" class="btn btn-lg btn-outline-secondary">
        <i class="bi bi-printer" aria-hidden="true"></i> ورقة الأمر</a>
    </div>
  </article>
@empty
  <div class="card"><div class="card-body">
    <div class="empty-state"><i class="bi bi-inbox ico" aria-hidden="true"></i>
      <div class="t">مفيش أوامر شغل مفتوحة على المصنع ده.</div>
      <div class="hint">أول ما التخطيط يبعت أمر شغل للمصنع هيظهر هنا.</div></div>
  </div></div>
@endforelse
</div>

@if($done->count())
  <details class="fp-done mt-4">
    <summary>آخر الأوامر المقفولة (<span class="num">{{ $done->count() }}</span>)</summary>
    <ul>
      @foreach($done as $w)
        <li><a href="{{ route('work-orders.show', $w) }}" class="num">{{ $w->wo_no }}</a> — {{ $w->product_title }} · <span class="num">{{ number_format((int) $w->received_pieces) }}</span> قطعة</li>
      @endforeach
    </ul>
  </details>
@endif
@endif
@endsection
