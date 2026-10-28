@extends('layouts.app')
@section('purpose', 'ورقة المصنع وحسبة الخامات، ومتابعة الصرف والقص والاستلام لحد ما الأمر يتقفل')
@section('content')
@php
  $gov    = $row->governingFabric();
  $target = $row->target_qty;
  $n      = fn ($v, $d = 3) => lv_num($v, $d);

  /* أرقام الحسبة لكل خامة — نفس معادلات PlanningEngine عشان ⓘ يطلع بأرقام الصف */
  $expVals = $row->fabrics->map(fn ($f) => $f->expected_pieces)->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->values();
  $minExp  = $expVals->count() ? $expVals->min() : 0;
  $maxExp  = $expVals->count() ? $expVals->max() : 0;
  $fabList = $row->fabrics->map(fn ($f) => ($f->fabricType?->name ?? 'خامة') . ' ' . number_format((int) $f->expected_pieces))->implode(' · ');
@endphp

@include('partials.flow_bar', ['flow' => 'prod', 'step' => 'wo'])

{{-- النسخ المعدلة --}}
@if($row->status === 'superseded' && $row->revisions->isNotEmpty())
  <div class="alert alert-warning py-2">
    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
    الأمر ده اتعمل منه نسخة معدلة:
    @foreach($row->revisions as $rv)
      <a href="{{ route('work-orders.show', $rv) }}" class="fw-bold">{{ $rv->wo_no }}</a>{{ !$loop->last ? '، ' : '' }}
    @endforeach
    — النسخة دي محفوظة للرجوع بس.
  </div>
@elseif($row->revisedFrom)
  <div class="alert alert-info py-2">
    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
    نسخة معدلة رقم {{ $row->revision_no }} من
    <a href="{{ route('work-orders.show', $row->revisedFrom) }}" class="fw-bold">{{ $row->revisedFrom->wo_no }}</a>
    @if($row->revision_reason) — السبب: «{{ $row->revision_reason }}» @endif
  </div>
@endif

@foreach(($calc['warnings'] ?? []) as $w)
  <div class="alert alert-{{ $w['level']==='danger' ? 'danger' : 'warning' }} py-2">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $w['text'] }}
  </div>
@endforeach

@if($row->status === 'draft')
  @php $gaps = $row->readinessGaps(); @endphp
  @if($row->is_ready_to_run)
    <div class="alert alert-success py-2"><i class="bi bi-check2-circle" aria-hidden="true"></i>
      <b>جاهز للتشغيل</b> — المخزن وزن الخامة والباترونست سلّم الماركر. اعتمده عشان يتحجز الخام ويتصرف.</div>
  @elseif($gaps)
    <div class="alert alert-warning py-2"><i class="bi bi-hourglass-split" aria-hidden="true"></i>
      <b>لسه مش جاهز للتشغيل:</b> {{ implode(' · ', $gaps) }}</div>
  @endif
@endif
@php $breaks = $row->fabrics->filter(fn ($f) => $f->consignment?->has_color_break); @endphp
@if($breaks->count())
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    <b>فواصل / اختلاف لون:</b> {{ $breaks->map(fn ($f) => $f->consignment->consignment_no . ' — ' . ($f->consignment->color_break_note ?: 'راجع تقرير المعمل'))->implode(' · ') }}
    — خد بالك في الماركر والقص.</div>
@endif

<div class="doc-actions" role="toolbar" aria-label="إجراءات أمر الشغل">
  <span class="da-label">إجراءات</span>
  <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
  @if($row->is_ready_to_run)<span class="badge bg-success">جاهز للتشغيل</span>@endif

  @if($row->isEditable())
    <a href="{{ route('work-orders.edit',$row) }}" class="btn btn-sm btn-outline-plum">
      <i class="bi bi-pencil" aria-hidden="true"></i> تعديل</a>
    <form method="post" action="{{ route('work-orders.submit',$row) }}"
          onsubmit="return confirm('أمر الشغل هيتقفل ويتحجز عليه الخام ويبقى جاهز للصرف للمصنع. متأكد؟')">@csrf
      <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle" aria-hidden="true"></i> اعتمده للتشغيل</button>
    </form>
  @endif
  @if($row->status === 'approved')
    <form method="post" action="{{ route('work-orders.send',$row) }}">@csrf
      <button class="btn btn-sm btn-plum"><i class="bi bi-truck" aria-hidden="true"></i> إرسال للمصنع</button>
    </form>
  @endif
  @if(in_array($row->status, ['approved','sent_to_factory']))
    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reviseModal">
      <i class="bi bi-arrow-repeat" aria-hidden="true"></i> نسخة معدلة</button>
  @endif
  <div class="da-end">
    <a href="{{ route('work-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer" aria-hidden="true"></i> طباعة ورقة المصنع</a>
    @if(in_array($row->status, \App\Models\WorkOrder::CLOSABLE_STATUSES))
      <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#closeModal">
        <i class="bi bi-lock" aria-hidden="true"></i> قفل</button>
    @endif
  </div>
</div>

<style>
/* ⓘ فوق كارت KPI (الكارت نفسه من partials.kpi) */

</style>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value'=>number_format($target), 'label'=>'الكمية المستهدفة', 'tone'=>'brand',
        'note'=>$row->approved_qty ? 'اعتماد المخطط' : 'الحاكمة من الخامات'])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'الكمية المستهدفة',
        'f' => $row->approved_qty
          ? ['المستهدف = الكمية المعتمدة من المخطط = ' . number_format($row->approved_qty) . ' قطعة', 'الحاكمة من الخامات كانت ' . number_format($row->computed_governing_qty)]
          : ['المستهدف = الحاكمة (أقل قطع بين الخامات) = ' . number_format($target) . ' قطعة'],
        'why' => $row->approved_qty && $row->approved_qty_reason ? 'السبب: ' . $row->approved_qty_reason : null])</span>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value'=>number_format($row->computed_governing_qty), 'label'=>'الحاكمة من الخامات',
        'note'=>$gov ? 'الخامة: '.($gov->fabricType?->name ?? '—') : null])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'الحاكمة',
        'f' => ['الحاكمة = أقل قطع متوقعة بين الخامات', ($fabList ?: '—') . ' ← أقلهم ' . number_format($minExp)],
        'why' => 'أول خامة تخلص بتوقف الإنتاج حتى لو الباقي موجود'])</span>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value'=>number_format($row->fabric_gap), 'label'=>'فرق بين الخامات',
        'tone'=>$row->fabric_gap > 0 ? 'warn' : 'ok',
        'note'=>$row->fabric_gap > 0 ? 'خامة هتخلص قبل التانية وتوقف الإنتاج' : 'الخامات متوازنة'])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'فرق الخامات',
        'f' => ['فرق الخامات = أعلى − الحاكمة', number_format($maxExp) . ' − ' . number_format($minExp) . ' = ' . number_format($maxExp - $minExp) . ' قطعة',
                $maxExp > 0 ? 'نسبة الفرق = ' . number_format($maxExp - $minExp) . ' ÷ ' . number_format($maxExp) . ' × 100 = ' . $n(($maxExp - $minExp) / $maxExp * 100, 1) . '%' : ''],
        'why' => 'فوق 10% = الخامات مش متوازنة: زوّد الحاكمة أو الباقي هيركد'])</span>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format((int)$row->cut_pieces), 'label'=>'مقصوص فعلي', 'note'=>'مجموع بيانات القص المعتمدة'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format((int)$row->received_pieces), 'label'=>'مستلم تام (سليم)', 'tone'=>'ok', 'note'=>'السليم في استلامات الإنتاج المعتمدة' . ((int)$row->rejected_pieces > 0 ? ' · مرفوض ' . number_format((int)$row->rejected_pieces) : '')])
  </div>
  <div class="col-6 col-lg-2">
    <div class="position-relative h-100">
      @include('partials.kpi', ['value'=>number_format($row->outstanding_pieces), 'label'=>'لسه على المصنع',
        'tone'=>$row->outstanding_pieces ? 'warn' : 'ok'])
      <span class="kpi-fx">@include('partials.fx', ['t' => 'لسه على المصنع',
        'f' => ['المتبقي = المقصوص − المستلم السليم', number_format((int)$row->cut_pieces) . ' − ' . number_format((int)$row->received_pieces) . ' = ' . number_format($row->outstanding_pieces) . ' قطعة'],
        'why' => 'المرفوض من المصنع' . ((int)$row->rejected_pieces > 0 ? ' (' . number_format((int)$row->rejected_pieces) . ' قطعة)' : '') . ' مش بيتحسب ضمن المستلم — المصنع بيعوّضه أو الأمر يتقفل يدوي بسبب'])</span>
    </div>
  </div>
</div>

    {{-- الخامات --}}
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>الخامات وحسبة كل واحدة <span class="hint">— اضغط ⓘ جنب أي رقم تشوف حسبته بأرقام الصف</span></span>
        <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 wo-show-fab">
          <thead><tr>
            <th>الخامة</th><th>الرسالة</th><th>اللون</th><th>الكمية</th>
            <th>طول الفرشة (م)</th><th>العرض (م)</th><th>وزن الرِقّة (كجم)</th><th>قطع الفرشة</th>
            <th>استهلاك القطعة</th><th>الرقات</th><th>القص المتوقع</th><th>المنصرف</th>
          </tr></thead>
          <tbody>
          @foreach($row->fabrics as $f)
            @php
              $isW   = $f->calc_mode !== 'length';
              $eff   = $f->effective_spread;
              $unit  = $f->unit ?: ($isW ? 'كجم' : 'متر');
              $ply   = ($isW && $eff && $f->fabric_width_m && $f->gsm_kg_m2) ? $eff * (float) $f->fabric_width_m * (float) $f->gsm_kg_m2 : null;
              $per   = $ply ?? $eff;              // اللي بيتقسم عليه عدد الرِقّات
              $pps   = (int) $f->pieces_per_spread;
              $plies = $f->plies ?? $f->calc_plies;
              $spTxt = $n($eff) . ' م' . ($f->spread_length_safe_m ? ' (بالأمان)' : '');
            @endphp
            <tr class="{{ $f->is_governing ? 'table-warning' : '' }}">
              <td>
                {{ $f->fabricType?->name ?? '—' }}
                @if($f->is_governing)
                  <div><span class="badge bg-warning"><i class="bi bi-flag" aria-hidden="true"></i> حاكمة</span></div>
                @endif
                <div class="hint">{{ $f->mode_name }}</div>
              </td>
              <td class="num">{{ $f->consignment?->consignment_no ?? '—' }}
                @if($f->consignment)<div>@include('partials.packaging', ['c' => $f->consignment, 'inline' => true])</div>@endif</td>
              <td>{{ $f->color?->code ?? '—' }}</td>
              <td class="num">{{ $n($f->planned_qty) }} {{ $f->unit }}
                @if($f->preparationRequest)<div class="hint text-success" title="وزن فعلي من المخزن">{{ $f->preparationRequest->doc_no }} · {{ $f->preparationRequest->status_name }}</div>@endif</td>
              <td class="num">
                {{ $n($f->spread_length_m) }}
                @if($f->spread_length_safe_m)<div class="hint">بالأمان {{ $n($f->spread_length_safe_m) }}</div>@endif
              </td>
              <td class="num">{{ $f->fabric_width_m ? $n($f->fabric_width_m) : '—' }}
                @if($isW && $f->gsm_kg_m2)<div class="hint">بنشر {{ $n($f->gsm_kg_m2, 4) }}</div>@endif</td>
              <td class="num">
                @if($ply !== null)
                  {{ $n($ply, 4) }}
                  @include('partials.fx', ['t' => 'وزن الرِقّة',
                    'f' => 'طول الفرشة ' . $spTxt . ' × العرض ' . $n($f->fabric_width_m) . ' م × البنشر ' . $n($f->gsm_kg_m2, 4) . ' كجم/م² = ' . $n($ply, 4) . ' كجم',
                    'why' => 'المساحة × وزن المتر المربع'])
                @else — @endif
              </td>
              <td class="num">{{ $pps }}</td>
              <td class="num">{{ $n($f->consumption_per_piece, 5) }}
                @if($f->consumption_per_piece && $pps)
                  @include('partials.fx', ['t' => 'استهلاك القطعة',
                    'f' => $isW
                      ? 'وزن الرِقّة ' . $n($ply, 4) . ' كجم ÷ قطع الفرشة ' . $pps . ' = ' . $n($f->consumption_per_piece, 5) . ' كجم'
                      : 'طول الفرشة ' . $spTxt . ' ÷ قطع الفرشة ' . $pps . ' = ' . $n($f->consumption_per_piece, 5) . ' م',
                    'why' => 'القطعة الواحدة بتاخد كام من الخامة'])
                @endif
              </td>
              <td class="num fw-bold">
                {{ $f->plies ?? '—' }}
                @if($per && $f->calc_plies !== null)
                  @include('partials.fx', ['t' => 'الرِقّات',
                    'f' => 'الكمية ' . $n($f->planned_qty) . ' ' . $unit . ' ÷ ' . ($isW ? 'وزن الرِقّة ' . $n($ply, 4) : 'طول الفرشة ' . $n($eff)) . ' = ' . $n((float) $f->planned_qty / $per, 2) . ' ← ' . (int) $f->calc_plies . ' رِقّة',
                    'why' => 'بيتقطع لأقل رقم صحيح — المصنع ما بيقصّش نص رِقّة'])
                @endif
                @if($f->calc_plies !== null && (int)$f->calc_plies !== (int)$f->plies)
                  <div class="hint">الحسبة {{ $f->calc_plies }}</div>
                @endif
              </td>
              <td class="num fw-bold">
                {{ number_format((int)$f->expected_pieces) }}
                @if($plies !== null && $pps)
                  @include('partials.fx', ['t' => 'القص المتوقع',
                    'f' => 'الرِقّات ' . (int) $plies . ' × قطع الفرشة ' . $pps . ' = ' . number_format((int) $plies * $pps) . ' قطعة',
                    'why' => 'رقم متوقع — الفرق الطبيعي عن الفعلي في المصنع 2% لـ 4%'])
                @endif
                @if($f->calc_pieces !== null && (int)$f->calc_pieces !== (int)$f->expected_pieces)
                  <div class="hint">الحسبة {{ number_format((int)$f->calc_pieces) }}</div>
                @endif
              </td>
              <td class="num">
                {{ $n($f->issued_actual, 2) }}
                @if($f->shortage > 0)
                  <div class="hint text-danger">ناقص {{ $n($f->shortage, 2) }}
                    @include('partials.fx', ['t' => 'الناقص', 'f' => 'المخطط ' . $n($f->planned_qty) . ' − المنصرف ' . $n($f->issued_actual, 2) . ' = ' . $n($f->shortage, 2) . ' ' . $unit])
                  </div>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white hint">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        كل رقم محسوب جنبه ⓘ بحسبته بأرقام الصف نفسه. الأرقام متوقعة لأن الفحص عيّنة — الفعلي بيبان في بيان القص.
        @if($row->approved_qty && $row->approved_qty_reason)
          <div class="mt-1"><b>سبب اعتماد كمية مختلفة:</b> {{ $row->approved_qty_reason }}</div>
        @endif
      </div>
    </div>

<div class="row g-3">
  <div class="col-lg-7">
    {{-- الموديلات وتوزيع الاستهلاك --}}
    @if($row->lines->isNotEmpty())
    @php
      $mainFab  = $row->fabrics->firstWhere('role','main') ?: $row->fabrics->first();
      $sumPps   = (int) $row->lines->sum('qty_per_spread');
      $fabPps   = (int) ($mainFab?->pieces_per_spread ?? 0);
      $actualPp = (float) ($mainFab?->consumption_per_piece ?? 0);
      $mainPlies= (int) ($mainFab?->plies ?? $mainFab?->calc_plies ?? 0);
      /* المتوسط المرجّح = Σ(قطع الموديل × متوسطه) ÷ إجمالي القطع — نفس PlanningEngine::splitConsumption */
      $withAvg  = $row->lines->filter(fn ($l) => (float) $l->avg_consumption_kg > 0 && (int) $l->qty_per_spread > 0);
      $wAvg     = $withAvg->count() === $row->lines->count() && $sumPps > 0
                  ? $row->lines->sum(fn ($l) => (int) $l->qty_per_spread * (float) $l->avg_consumption_kg) / $sumPps : null;
      $wAvgTxt  = $wAvg ? $row->lines->map(fn ($l) => (int) $l->qty_per_spread . ' × ' . $n($l->avg_consumption_kg, 4))->implode(' + ') : '';
    @endphp
    <div class="card mb-3">
      <div class="card-header">الموديلات — توزيع الاستهلاك بالمتوسطات</div>
      @if(count($row->lines) > 1 && $fabPps > 0 && $sumPps !== $fabPps)
        <div class="alert alert-warning rounded-0 mb-0 py-2 px-3">
          <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
          مجموع قطع الموديلات في الفرشة ({{ $sumPps }}) مش مساوي لقطع فرشة الخامة الرئيسية ({{ $fabPps }}) —
          راجع التوزيع قبل ما تبعت للمصنع.
        </div>
      @endif
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr>
          <th>الموديل</th><th>المقاس</th><th>قطعه في الفرشة</th>
          <th>متوسطه التاريخي</th><th>نصيبه من الاستهلاك</th>
          <th>الكمية</th><th>بالدستة</th><th>كجم مخططة</th>
        </tr></thead>
        <tbody>
        @foreach($row->lines as $l)
          <tr>
            <td>{{ $l->productModel?->name ?? '—' }}
              @if($l->productModel?->code)<div class="hint">{{ $l->productModel->code }}</div>@endif
            </td>
            <td>{{ $l->size?->name ?? 'كل المقاسات' }}</td>
            <td class="num">{{ $l->qty_per_spread }}</td>
            <td class="num">
              @if($l->avg_consumption_kg)
                {{ $n((float)$l->avg_consumption_kg * 1000, 1) }} جم
              @else
                <span class="text-danger">مش مسجّل</span>
              @endif
            </td>
            <td class="num fw-bold">
              @if($l->consumption_per_piece)
                {{ $n((float)$l->consumption_per_piece * 1000, 1) }} جم
                @if($wAvg && $l->avg_consumption_kg && $actualPp)
                  @include('partials.fx', ['t' => 'نصيب الموديل من الاستهلاك',
                    'f' => ['المتوسط المرجّح = (' . $wAvgTxt . ') ÷ ' . $sumPps . ' = ' . $n($wAvg, 4) . ' كجم',
                            'نسبة الموديل = متوسطه ' . $n($l->avg_consumption_kg, 4) . ' ÷ المرجّح ' . $n($wAvg, 4) . ' = ' . $n((float) $l->avg_consumption_kg / $wAvg, 3),
                            'نصيبه = الاستهلاك الفعلي ' . $n($actualPp, 5) . ' × ' . $n((float) $l->avg_consumption_kg / $wAvg, 3) . ' = ' . $n($l->consumption_per_piece, 5) . ' كجم'],
                    'why' => 'البادي بياخد أكتر من المعصم — التوزيع بنسبة المتوسطات ومجموعه = وزن الرِقّة بالظبط'])
                @elseif($actualPp)
                  @include('partials.fx', ['t' => 'نصيب الموديل من الاستهلاك',
                    'f' => 'اتعمّم بالتساوي = الاستهلاك الفعلي للقطعة ' . $n($actualPp, 5) . ' كجم',
                    'why' => 'فيه موديل من غير متوسط مسجّل — سجّله من شاشة الموديلات عشان التوزيع يبقى بالنسب'])
                @endif
              @else — @endif
            </td>
            <td class="num fw-bold">{{ number_format((int)$l->planned_qty) }}
              @if($mainPlies && (int) $l->qty_per_spread)
                @include('partials.fx', ['t' => 'كمية الموديل', 'f' => 'الرِقّات ' . $mainPlies . ' × قطعه في الفرشة ' . (int) $l->qty_per_spread . ' = ' . number_format($mainPlies * (int) $l->qty_per_spread) . ' قطعة'])
              @endif
            </td>
            <td class="num">{{ number_format((int)$l->planned_qty / 12, 2) }}
              @include('partials.fx', ['t' => 'بالدستة', 'f' => number_format((int) $l->planned_qty) . ' ÷ 12 = ' . number_format((int) $l->planned_qty / 12, 2) . ' دستة'])
            </td>
            <td class="num">{{ $l->planned_kg ? $n($l->planned_kg) : '—' }}
              @if($l->planned_kg && $l->consumption_per_piece)
                @include('partials.fx', ['t' => 'كجم مخططة', 'f' => 'نصيبه ' . $n($l->consumption_per_piece, 5) . ' كجم × الكمية ' . number_format((int) $l->planned_qty) . ' = ' . $n($l->planned_kg) . ' كجم'])
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
      <div class="card-footer bg-white hint">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        التوزيع بنسبة المتوسطات التاريخية — اضغط ⓘ تشوف نصيب كل موديل بالأرقام.
        @if($row->lines->count() > 1 && $row->lines->contains(fn ($l) => !$l->avg_consumption_kg))
          <span class="text-danger">فيه موديل من غير متوسط مسجّل — الاستهلاك اتعمّم. سجّل المتوسط من شاشة الموديلات.</span>
        @endif
      </div>
    </div>
    @endif

    {{-- بيان القص --}}
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات القص</span>
        @if(in_array($row->status, ['approved','sent_to_factory','cutting','cut_declared','in_production','partially_received']))
          <a href="{{ route('cut-declarations.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> بيان قص</a>
        @endif
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>طول الفرشة الفعلي (م)</th><th>القطع</th><th>الانحراف</th><th>الحالة</th></tr></thead>
        <tbody>
        @php $cumCut = 0; $plannedSp = $gov?->effective_spread; @endphp
        @forelse($row->cutDeclarations->sortBy('id') as $c)
          @php
            /* الانحراف تراكمي: اللي اتقص في البيانات المعتمدة قبل ده + البيان ده، مقابل المستهدف */
            $cumHere = $cumCut + (int) $c->total_pieces;
            if ($c->status === 'approved') $cumCut += (int) $c->total_pieces;
            $vTone = ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$c->variance_flag] ?? 'muted';
          @endphp
          <tr>
            <td class="num"><a href="{{ route('cut-declarations.edit',$c) }}">{{ $c->doc_no }}</a></td>
            <td class="num">{{ $c->doc_date?->format('Y-m-d') }}</td>
            <td class="num">{{ $n($c->actual_spread_length_m) }}
              @if($plannedSp && $c->actual_spread_length_m)
                @php $devCm = ((float) $c->actual_spread_length_m - $plannedSp) * 100; @endphp
                <div class="hint {{ abs($devCm) >= 5 ? 'text-danger' : '' }}">{{ $devCm > 0 ? '+' : '' }}{{ $n($devCm, 1) }} سم
                  @include('partials.fx', ['t' => 'فرق طول الفرشة', 'f' => '(الفعلي ' . $n($c->actual_spread_length_m) . ' − المخطط ' . $n($plannedSp) . ') × 100 = ' . $n($devCm, 1) . ' سم',
                    'why' => 'كل سنتيمتر زيادة بياكل من كل رِقّة وبيقلل القطع'])
                </div>
              @endif
            </td>
            <td class="num">{{ number_format($c->total_pieces) }}</td>
            <td class="num text-{{ $vTone }}">{{ $c->variance_pct !== null ? $c->variance_pct.'%' : '—' }}
              @if($c->variance_pct !== null && $target > 0)
                @include('partials.fx', ['t' => 'الانحراف',
                  'f' => ['الانحراف % = (الفعلي − المتوقع) ÷ المتوقع × 100',
                          '(' . number_format($cumHere) . ' − ' . number_format($target) . ') ÷ ' . number_format($target) . ' × 100 = ' . $c->variance_pct . '%'],
                  'why' => 'الفعلي تراكمي (البيانات المعتمدة قبله + البيان ده) · ≤2% أخضر · ≤4% أصفر · فوقها أحمر ولازم سبب'])
              @endif
            </td>
            <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_label }}</span></td>
          </tr>
        @empty
          <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش بيان قص لسه.</div>
            </div>
          </td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>

    {{-- الاستلامات --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>استلامات الإنتاج</span>
        @if($row->cut_pieces > 0 && $row->outstanding_pieces > 0)
          <a href="{{ route('production-receipts.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> استلام</a>
        @endif
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>المخزن</th><th>القطع</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($row->receipts as $p)
          <tr>
            <td class="num"><a href="{{ route('production-receipts.edit',$p) }}">{{ $p->doc_no }}</a></td>
            <td class="num">{{ $p->doc_date?->format('Y-m-d') }}</td>
            <td>{{ $p->warehouse?->name }}</td>
            <td class="num">{{ number_format($p->total_pieces) }}</td>
            <td><span class="badge bg-{{ $p->status_color }}">{{ $p->status_label }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش استلامات لسه.</div>
            </div>
          </td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">ورقة المصنع</div>
      <table class="table table-sm kv mb-0">
        <tr><th>رقم أمر الشغل</th><td class="num fw-bold">{{ $row->wo_no }}</td></tr>
        <tr><th>المنتج</th><td>{{ $row->product_title ?: '—' }}</td></tr>
        <tr><th>الكود / Q.B</th><td class="num">{{ $row->product_code ?: '—' }} / {{ $row->qb_code ?: '—' }}</td></tr>
        <tr><th>تشغيل مصنع</th><td>{{ $row->factory?->name }}</td></tr>
        <tr><th>التاريخ</th><td class="num">{{ $row->wo_date?->format('Y-m-d') }}</td></tr>
        <tr><th>تاريخ الاستلام</th><td class="num">{{ $row->receive_date?->format('Y-m-d') ?? '—' }}</td></tr>
        <tr><th>تاريخ التسليم</th><td class="num {{ $row->is_late ? 'text-danger fw-bold' : '' }}">{{ $row->due_date?->format('Y-m-d') ?? '—' }}{{ $row->is_late ? ' — متأخر' : '' }}</td></tr>
        <tr><th>نسخ الماركر</th><td class="num">{{ $row->marker_copies }}</td></tr>
        <tr><th>إدارة التخطيط</th><td>{{ $row->planner?->name ?? '—' }}</td></tr>
        <tr><th>باركود التكويد</th><td class="num">{{ $row->barcode ?: '—' }}</td></tr>
      </table>
      @if($row->cutting_notes)
        <div class="card-footer bg-white">
          <b class="small">ملاحظات خاصة بقسم القص</b>
          <div class="hint">{{ $row->cutting_notes }}</div>
        </div>
      @endif
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>إذون صرف الخام</span>
        @if(in_array($row->status, \App\Models\WorkOrder::MATERIAL_STATUSES))
          <a href="{{ route('material-issues.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum"><i class="bi bi-plus-lg" aria-hidden="true"></i> صرف خام</a>
        @endif
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>الإذن</th><th>التاريخ</th><th>الخامة</th><th>الكمية</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($row->materialIssueLines as $l)
          <tr>
            <td class="num"><a href="{{ route('material-issues.edit', $l->material_issue_id) }}">{{ $l->materialIssue?->doc_no }}</a></td>
            <td class="num">{{ $l->materialIssue?->doc_date?->format('Y-m-d') }}</td>
            <td class="hint">{{ $l->consignment_no }}</td>
            <td class="num">{{ $n($l->qty, 2) }} {{ $l->unit }}</td>
            <td><span class="badge bg-{{ $l->materialIssue?->status_color }}">{{ $l->materialIssue?->status_label }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">لسه ما اتصرفش خامة للمصنع.</div>
            </div>
          </td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>

    {{-- سجل الحالات والتغييرات --}}
    <div class="card mt-3">
      <div class="card-header">سجل الحالات والتغييرات</div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th style="width:140px">إمتى</th><th style="width:130px">مين</th><th>إيه اللي حصل</th></tr></thead>
        <tbody>
        @forelse($history as $h)
          <tr>
            <td class="num hint">{{ $h->created_at?->format('Y-m-d H:i') }}</td>
            <td>{{ $h->user?->name ?? 'السيستم' }}</td>
            <td>{{ $h->title ?? $h->action }}</td>
          </tr>
        @empty
          <tr><td colspan="3">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش أحداث مسجلة لسه.</div>
            </div>
          </td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<div class="mt-3">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>بيانات تخص المنتج (الإكسسوارات)</span>
        @php $hasShortage = collect($accessories)->contains(fn ($a) => $a['shortage'] > 0); @endphp
        @if($hasShortage)
          <form method="post" action="{{ route('work-orders.shortage-po', $row) }}"
                onsubmit="return confirm('يتعمل طلب شراء بكل أصناف العجز وينزل للمشتريات؟')">@csrf
            <button class="btn btn-sm btn-outline-danger">
              <i class="bi bi-cart-plus" aria-hidden="true"></i> طلب شراء بالعجز
            </button>
          </form>
        @endif
      </div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>البند</th><th>كود الإكسسوار</th><th>المطلوب</th><th>الوحدة</th><th>المتاح</th><th>الناقص</th></tr></thead>
        <tbody>
        @forelse($accessories as $a)
          <tr class="{{ $a['shortage'] > 0 ? 'table-danger' : '' }}">
            <td>{{ $a['accessory']->name }}
              @if(count($a['by_model'] ?? []) > 1)
                <div class="hint">
                  @foreach($a['by_model'] as $ml => $mq)
                    {{ $ml }}: {{ $n($mq) }}{{ !$loop->last ? ' · ' : '' }}
                  @endforeach
                </div>
              @endif
            </td>
            <td class="num hint">{{ $a['accessory']->code }}</td>
            <td class="num">{{ $n($a['required']) }}</td>
            <td>{{ $a['accessory']->unit }}</td>
            <td class="num">{{ $n($a['available']) }}</td>
            <td class="num fw-bold">
              @if($a['shortage'] > 0)
                {{ $n($a['shortage']) }}
                @include('partials.fx', ['t' => 'الناقص', 'f' => 'المطلوب ' . $n($a['required']) . ' − المتاح ' . $n($a['available']) . ' = ' . $n($a['shortage']) . ' ' . $a['accessory']->unit])
              @else — @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-inbox ico" aria-hidden="true"></i>
              <div class="t">مفيش BOM مسجّل للموديلات دي — سجّله من شاشة الموديلات.</div>
            </div>
          </td></tr>
        @endforelse
        </tbody>
      </table></div>
    </div>
</div>

<div class="modal fade" id="reviseModal"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="{{ route('work-orders.revise',$row) }}">@csrf
    <div class="modal-header"><h6 class="modal-title">نسخة معدلة من {{ $row->wo_no }}</h6>
      <button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
    <div class="modal-body">
      <p class="small">
        الأمر المعتمد مش بيتعدل مباشرة — هتتعمل نسخة جديدة (مسودة) بكل بياناته،
        تعدّل فيها وتعتمدها من جديد. النسخة دي هتتعلم «استُبدل بنسخة أحدث» وتفضل محفوظة.
      </p>
      <label class="form-label req">سبب التعديل</label>
      <textarea name="revision_reason" rows="3" class="form-control" required
                placeholder="مثال: العرض الفعلي اختلف بعد الفحص / تغيير توزيع الموديلات في الفرشة"></textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
      <button class="btn btn-warning btn-sm"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> اعمل النسخة</button>
    </div>
  </form>
</div></div></div>

<div class="modal fade" id="closeModal"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="{{ route('work-orders.close',$row) }}">@csrf
    <div class="modal-header"><h6 class="modal-title">قفل أمر الشغل {{ $row->wo_no }}</h6>
      <button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
    <div class="modal-body">
      <p class="small">متبقي على المصنع <b class="num">{{ number_format($row->outstanding_pieces) }}</b> قطعة
        <span class="hint">(مقصوص {{ number_format((int)$row->cut_pieces) }} − مستلم سليم {{ number_format((int)$row->received_pieces) }}@if((int)$row->rejected_pieces > 0) · مرفوض من المصنع {{ number_format((int)$row->rejected_pieces) }}@endif)</span>.</p>
      <label class="form-label req">سبب القفل</label>
      <textarea name="variance_reason" rows="3" class="form-control" required placeholder="مثال: الباقي اتصرف كعيوب / الكمية اكتفت بالمستلم">{{ $row->variance_reason }}</textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
      <button class="btn btn-dark btn-sm"><i class="bi bi-lock" aria-hidden="true"></i> تأكيد القفل</button>
    </div>
  </form>
</div></div></div>

@endsection
