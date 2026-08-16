@extends('layouts.app')
@section('content')
@php
  $gov = $row->governingFabric();
  $target = $row->target_qty;
@endphp

@include('partials.approval_box')

@foreach(($calc['warnings'] ?? []) as $w)
  <div class="alert alert-{{ $w['level']==='danger' ? 'danger' : 'warning' }} py-2">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $w['text'] }}
  </div>
@endforeach

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format($target), 'label'=>'الكمية المستهدفة', 'tone'=>'brand',
      'note'=>$row->approved_qty ? 'اعتماد المخطط' : 'الحاكمة من الخامات'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format($row->computed_governing_qty), 'label'=>'الحاكمة من الخامات',
      'note'=>$gov ? 'الخامة: '.($gov->fabricType?->name ?? '—') : null])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format($row->fabric_gap), 'label'=>'فرق بين الخامات',
      'tone'=>$row->fabric_gap > 0 ? 'warn' : 'ok',
      'note'=>$row->fabric_gap > 0 ? 'خامة هتخلص قبل التانية وتوقف الإنتاج' : 'الخامات متوازنة'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format((int)$row->cut_pieces), 'label'=>'مقصوص فعلي'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format((int)$row->received_pieces), 'label'=>'مستلم تام', 'tone'=>'ok'])
  </div>
  <div class="col-6 col-lg-2">
    @include('partials.kpi', ['value'=>number_format($row->outstanding_pieces), 'label'=>'لسه على المصنع',
      'tone'=>$row->outstanding_pieces ? 'warn' : 'ok'])
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    {{-- الخامات --}}
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>الخامات وحسبة كل واحدة</span>
        <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr>
            <th>الخامة</th><th>الرسالة</th><th>اللون</th><th>الكمية</th>
            <th>طول الفرشة</th><th>العرض</th><th>الرقات</th><th>قطع الفرشة</th>
            <th>استهلاك القطعة</th><th>القص المتوقع</th><th>المنصرف</th>
          </tr></thead>
          <tbody>
          @foreach($row->fabrics as $f)
            <tr class="{{ $f->is_governing ? 'table-warning' : '' }}">
              <td>
                {{ $f->fabricType?->name ?? '—' }}
                @if($f->is_governing)
                  <div><span class="badge bg-warning"><i class="bi bi-flag" aria-hidden="true"></i> حاكمة</span></div>
                @endif
                <div class="hint">{{ $f->mode_name }}</div>
              </td>
              <td class="num">{{ $f->consignment?->consignment_no ?? '—' }}</td>
              <td>{{ $f->color?->code ?? '—' }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$f->planned_qty,3),'0'),'.') }} {{ $f->unit }}</td>
              <td class="num">
                {{ $f->spread_length_m }}
                @if($f->spread_length_safe_m)<div class="hint">بالأمان {{ $f->spread_length_safe_m }}</div>@endif
              </td>
              <td class="num">{{ $f->fabric_width_m ?? '—' }}</td>
              <td class="num fw-bold">
                {{ $f->plies ?? '—' }}
                @if($f->calc_plies !== null && (int)$f->calc_plies !== (int)$f->plies)
                  <div class="hint">الحسبة {{ $f->calc_plies }}</div>
                @endif
              </td>
              <td class="num">{{ $f->pieces_per_spread }}</td>
              <td class="num">{{ rtrim(rtrim(number_format((float)$f->consumption_per_piece,5),'0'),'.') }}</td>
              <td class="num fw-bold">
                {{ number_format((int)$f->expected_pieces) }}
                @if($f->calc_pieces !== null && (int)$f->calc_pieces !== (int)$f->expected_pieces)
                  <div class="hint">الحسبة {{ number_format((int)$f->calc_pieces) }}</div>
                @endif
              </td>
              <td class="num">
                {{ rtrim(rtrim(number_format($f->issued_actual,2),'0'),'.') }}
                @if($f->shortage > 0)
                  <div class="hint text-danger">ناقص {{ rtrim(rtrim(number_format($f->shortage,2),'0'),'.') }}</div>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white hint">
        وزن الراق = طول الفرشة (بالأمان) × العرض بالمتر × البنشر ·
        استهلاك القطعة = وزن الراق ÷ قطع الفرشة ·
        القص = الرقات × قطع الفرشة.
        @if($row->approved_qty && $row->approved_qty_reason)
          <div class="mt-1"><b>سبب اعتماد كمية مختلفة:</b> {{ $row->approved_qty_reason }}</div>
        @endif
      </div>
    </div>

    {{-- بيان القص --}}
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات القص</span>
        @if(in_array($row->status, ['approved','sent_to_factory','cutting','cut_declared','in_production','partially_received']))
          <a href="{{ route('cut-declarations.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-plus-lg" aria-hidden="true"></i> بيان قص</a>
        @endif
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>طول الفرشة الفعلي</th><th>القطع</th><th>الانحراف</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($row->cutDeclarations as $c)
          <tr>
            <td class="num"><a href="{{ route('cut-declarations.edit',$c) }}">{{ $c->doc_no }}</a></td>
            <td class="num">{{ $c->doc_date?->format('Y-m-d') }}</td>
            <td class="num">{{ $c->actual_spread_length_m }}</td>
            <td class="num">{{ number_format($c->total_pieces) }}</td>
            <td class="num">{{ $c->variance_pct !== null ? $c->variance_pct.'%' : '—' }}</td>
            <td><span class="badge bg-{{ $c->status_color }}">{{ $c->status_label }}</span></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">مفيش بيان قص لسه.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{-- الاستلامات --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>استلامات الإنتاج</span>
        @if($row->cut_pieces > 0 && $row->outstanding_pieces > 0)
          <a href="{{ route('production-receipts.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-plus-lg" aria-hidden="true"></i> استلام</a>
        @endif
      </div>
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
          <tr><td colspan="5" class="text-center text-muted py-3">مفيش استلامات لسه.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">ورقة المصنع</div>
      <table class="table table-sm mb-0">
        <tr><th style="width:140px">رقم أمر الشغل</th><td class="num fw-bold">{{ $row->wo_no }}</td></tr>
        <tr><th>المنتج</th><td>{{ $row->product_title ?: '—' }}</td></tr>
        <tr><th>الكود / Q.B</th><td class="num">{{ $row->product_code ?: '—' }} / {{ $row->qb_code ?: '—' }}</td></tr>
        <tr><th>تشغيل مصنع</th><td>{{ $row->factory?->name }}</td></tr>
        <tr><th>التاريخ</th><td class="num">{{ $row->wo_date?->format('Y-m-d') }}</td></tr>
        <tr><th>تاريخ الاستلام</th><td class="num">{{ $row->receive_date?->format('Y-m-d') ?? '—' }}</td></tr>
        <tr><th>تاريخ التسليم</th><td class="num {{ $row->is_late ? 'text-danger fw-bold' : '' }}">{{ $row->due_date?->format('Y-m-d') ?? '—' }}</td></tr>
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
        @if(in_array($row->status, ['approved','sent_to_factory','cutting']))
          <a href="{{ route('material-issues.create', ['work_order_id'=>$row->id]) }}"
             class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-plus-lg" aria-hidden="true"></i> صرف خام</a>
        @endif
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الإذن</th><th>التاريخ</th><th>الخامة</th><th>الكمية</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($row->materialIssueLines as $l)
          <tr>
            <td class="num"><a href="{{ route('material-issues.edit', $l->material_issue_id) }}">{{ $l->materialIssue?->doc_no }}</a></td>
            <td class="num">{{ $l->materialIssue?->doc_date?->format('Y-m-d') }}</td>
            <td class="hint">{{ $l->consignment_no }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float)$l->qty,2),'0'),'.') }} {{ $l->unit }}</td>
            <td><span class="badge bg-{{ $l->materialIssue?->status_color }}">{{ $l->materialIssue?->status_label }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-3">لسه ما اتصرفش خامة للمصنع.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card mb-3">
      <div class="card-header">بيانات تخص المنتج (الإكسسوارات)</div>
      <table class="table table-sm mb-0">
        <thead><tr><th>البند</th><th>المطلوب</th><th>المتاح</th><th>الناقص</th></tr></thead>
        <tbody>
        @forelse($accessories as $a)
          <tr class="{{ $a['shortage'] > 0 ? 'table-danger' : '' }}">
            <td>{{ $a['accessory']->name }}</td>
            <td class="num">{{ number_format($a['required'], 0) }}</td>
            <td class="num">{{ number_format($a['available'], 0) }}</td>
            <td class="num fw-bold">{{ $a['shortage'] > 0 ? number_format($a['shortage'], 0) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-3">مفيش BOM مسجّل.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header">إجراءات</div>
      <div class="card-body d-flex gap-2 flex-wrap">
        @if($row->isEditable())
          <a href="{{ route('work-orders.edit',$row) }}" class="btn btn-sm btn-outline-plum">
            <i class="bi bi-pencil" aria-hidden="true"></i> تعديل</a>
          <form method="post" action="{{ route('work-orders.submit',$row) }}" onsubmit="return confirm('إرسال للاعتماد؟')">@csrf
            <button class="btn btn-sm btn-success"><i class="bi bi-send" aria-hidden="true"></i> إرسال للاعتماد</button>
          </form>
        @endif
        @if($row->status === 'approved')
          <form method="post" action="{{ route('work-orders.send',$row) }}">@csrf
            <button class="btn btn-sm btn-plum"><i class="bi bi-truck" aria-hidden="true"></i> إرسال للمصنع</button>
          </form>
        @endif
        <a href="{{ route('work-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-printer" aria-hidden="true"></i> طباعة ورقة المصنع</a>
        @if(!in_array($row->status, ['closed','cancelled','draft']))
          <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#closeModal">
            <i class="bi bi-lock" aria-hidden="true"></i> قفل</button>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="closeModal"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="{{ route('work-orders.close',$row) }}">@csrf
    <div class="modal-header"><h6 class="modal-title">قفل أمر الشغل {{ $row->wo_no }}</h6>
      <button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
    <div class="modal-body">
      <p class="small">متبقي على المصنع <b>{{ number_format($row->outstanding_pieces) }}</b> قطعة.</p>
      <label class="form-label req">سبب القفل</label>
      <textarea name="variance_reason" rows="3" class="form-control" required>{{ $row->variance_reason }}</textarea>
    </div>
    <div class="modal-footer"><button class="btn btn-dark btn-sm">تأكيد القفل</button></div>
  </form>
</div></div></div>

@include('partials.comments')
@endsection
