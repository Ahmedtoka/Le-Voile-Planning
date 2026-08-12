@extends('layouts.app')
@section('content')

@include('partials.approval_box')

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((float)$row->allocated_kg,1) }}</div><div class="l">كجم مخصصة</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((int)$row->expected_pieces) }}</div><div class="l">قطع متوقعة</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((int)$row->cut_pieces) }}</div><div class="l">مقصوص فعلي</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format((int)$row->received_pieces) }}</div><div class="l">مستلم تام</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num">{{ number_format($row->outstanding_pieces) }}</div><div class="l">لسه على المصنع</div></div></div>
  <div class="col-6 col-lg-2">
    <div class="stat">
      <div class="v num text-{{ ['ok'=>'success','warn'=>'warning','danger'=>'danger'][$row->variance_flag] ?? 'muted' }}">
        {{ $row->variance_pct !== null ? $row->variance_pct.'%' : '—' }}
      </div>
      <div class="l">الانحراف</div>
    </div>
  </div>
</div>

@if($row->variance_flag === 'danger')
  <div class="alert alert-danger py-2">
    <b>الانحراف خارج الحدود المقبولة ({{ config('lvplanning.variance.warn_pct') }}%).</b>
    الأسباب المعتادة: المصنع ما التزمش بطول الفرشة، أو بيانات الفحص غلط، أو القماش نفسه فيه مشكلة.
    @if($row->variance_reason)<div class="mt-1">السبب المسجّل: <i>{{ $row->variance_reason }}</i></div>@endif
  </div>
@endif

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات أمر الشغل</span>
        <span class="badge bg-{{ $row->status_color }}">{{ $row->status_name }}</span>
      </div>
      <table class="table table-sm mb-0">
        <tr><th style="width:150px">رقم الأمر</th><td class="num fw-bold">{{ $row->wo_no }}</td></tr>
        <tr><th>التاريخ</th><td class="num">{{ $row->wo_date?->format('Y-m-d') }}</td></tr>
        <tr><th>الحوض</th><td class="num"><a href="{{ route('consignments.show',$row->consignment) }}">{{ $row->consignment?->consignment_no }}</a></td></tr>
        <tr><th>اللون / الخامة</th><td>{{ $row->consignment?->color?->code }} · {{ $row->consignment?->fabricType?->name }}</td></tr>
        <tr><th>المصنع</th><td>{{ $row->factory?->name }}</td></tr>
        <tr><th>الماركر</th><td class="num">{{ $row->marker?->code }}</td></tr>
        <tr><th>تاريخ التسليم</th><td class="num {{ $row->is_late ? 'text-danger fw-bold' : '' }}">{{ $row->due_date?->format('Y-m-d') ?? '—' }}</td></tr>
      </table>
    </div>

    <div class="card mb-3">
      <div class="card-header">مدخلات ومخرجات الحسبة</div>
      <table class="table table-sm mb-0">
        <tr><th style="width:180px">أقل عرض (المستخدم)</th><td class="num">{{ $row->input_min_width_cm }} سم</td></tr>
        <tr><th>متوسط البنشر</th><td class="num">{{ $row->input_avg_gsm }} جم/م²</td></tr>
        <tr><th>طول الفرشة المخطط</th><td class="num">{{ $row->input_spread_length_m }} م</td></tr>
        <tr><th>قطع الفرشة</th><td class="num">{{ $row->input_pieces_per_spread }}</td></tr>
        <tr class="table-light"><th>وزن الرِقّة</th><td class="num fw-bold">{{ $row->ply_weight_kg }} كجم</td></tr>
        <tr class="table-light"><th>استهلاك القطعة</th><td class="num fw-bold">{{ $row->kg_per_piece ? number_format((float)$row->kg_per_piece*1000, 1) . ' جم' : '—' }}</td></tr>
        <tr><th>طول الفرشة الفعلي</th><td class="num">{{ $row->actual_spread_length_m ?? '— لسه' }}</td></tr>
      </table>
    </div>

    @if($spreadImpact && $spreadImpact['ok'] && $spreadImpact['lost_plies'] != 0)
      <div class="alert alert-warning py-2">
        <b>تأثير فرق طول الفرشة:</b>
        المصنع فرش على فرق {{ $spreadImpact['deviation_cm'] }} سم عن المخطط.
        ده كلّفنا {{ $spreadImpact['lost_plies'] }} رِقّة من كل توب
        ≈ {{ number_format($spreadImpact['lost_pieces']) }} قطعة ({{ $spreadImpact['loss_pct'] }}%).
      </div>
    @endif

    <div class="card">
      <div class="card-header">احتياج الإكسسوارات</div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الإكسسوار</th><th>المطلوب</th><th>المتاح</th><th>الناقص</th></tr></thead>
        <tbody>
        @forelse($accessories as $a)
          <tr class="{{ $a['shortage'] > 0 ? 'table-danger' : '' }}">
            <td>{{ $a['accessory']->name }}</td>
            <td class="num">{{ number_format($a['required'], 0) }}</td>
            <td class="num">{{ number_format($a['available'], 0) }}</td>
            <td class="num fw-bold">{{ $a['shortage'] > 0 ? number_format($a['shortage'], 0) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-3">مفيش BOM مسجّل للموديلات دي.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">الموديلات والمقاسات</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>الموديل</th><th>المقاس</th><th>في الفرشة</th><th>مخطط</th><th>مقصوص</th><th>مستلم</th><th>متبقي</th></tr></thead>
          <tbody>
          @foreach($row->lines as $l)
            <tr>
              <td>{{ $l->productModel?->name }}</td>
              <td>{{ $l->size?->name ?? 'كل المقاسات' }}</td>
              <td class="num">{{ $l->qty_per_spread }}</td>
              <td class="num">{{ number_format($l->planned_qty) }}</td>
              <td class="num">{{ number_format($l->cut_qty) }}</td>
              <td class="num">{{ number_format($l->received_qty) }}</td>
              <td class="num fw-bold">{{ number_format($l->remaining_qty) }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات القص</span>
        @if(in_array($row->status, ['approved','sent_to_factory','cutting','cut_declared','in_production','partially_received']))
          <a href="{{ route('cut-declarations.create', ['work_order_id'=>$row->id]) }}" class="btn btn-sm btn-outline-plum py-0">
            <i class="bi bi-plus-lg"></i> بيان قص
          </a>
        @endif
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>طول الفرشة الفعلي</th><th>القطع</th><th>الانحراف</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($row->cutDeclarations as $c)
          <tr>
            <td class="num"><a href="{{ route('cut-declarations.edit',$c) }}">{{ $c->doc_no }}</a></td>
            <td class="num">{{ $c->doc_date?->format('Y-m-d') }}</td>
            <td class="num">{{ $c->actual_spread_length_m }}
              @if($c->spread_deviation_cm !== null)<span class="hint">({{ $c->spread_deviation_cm > 0 ? '+' : '' }}{{ $c->spread_deviation_cm }} سم)</span>@endif
            </td>
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

    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>استلامات الإنتاج</span>
        @if($row->cut_pieces > 0 && $row->outstanding_pieces > 0)
          <a href="{{ route('production-receipts.create', ['work_order_id'=>$row->id]) }}" class="btn btn-sm btn-outline-plum py-0">
            <i class="bi bi-plus-lg"></i> استلام
          </a>
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

    <div class="card">
      <div class="card-header">إجراءات</div>
      <div class="card-body d-flex gap-2 flex-wrap">
        @if($row->isEditable())
          <a href="{{ route('work-orders.edit',$row) }}" class="btn btn-sm btn-outline-plum"><i class="bi bi-pencil"></i> تعديل</a>
          <form method="post" action="{{ route('work-orders.submit',$row) }}" onsubmit="return confirm('إرسال للاعتماد؟')">@csrf
            <button class="btn btn-sm btn-success"><i class="bi bi-send"></i> إرسال للاعتماد</button>
          </form>
        @endif
        @if($row->status === 'approved')
          <form method="post" action="{{ route('work-orders.send',$row) }}">@csrf
            <button class="btn btn-sm btn-plum"><i class="bi bi-truck"></i> إرسال للمصنع</button>
          </form>
        @endif
        <a href="{{ route('work-orders.print',$row) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</a>
        @if(!in_array($row->status, ['closed','cancelled','draft']))
          <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#closeModal">
            <i class="bi bi-lock"></i> قفل أمر الشغل
          </button>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="closeModal"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="{{ route('work-orders.close',$row) }}">@csrf
    <div class="modal-header"><h6 class="modal-title">قفل أمر الشغل {{ $row->wo_no }}</h6>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="small">متبقي على المصنع <b>{{ number_format($row->outstanding_pieces) }}</b> قطعة.</p>
      <input type="hidden" name="force" value="1">
      <label class="form-label req">سبب الفرق / القفل</label>
      <textarea name="variance_reason" rows="3" class="form-control" required>{{ $row->variance_reason }}</textarea>
    </div>
    <div class="modal-footer"><button class="btn btn-dark btn-sm">تأكيد القفل</button></div>
  </form>
</div></div></div>
@endsection
