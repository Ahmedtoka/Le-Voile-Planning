@php $ap = $row->approval ?? null; @endphp
@if($ap)
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-diagram-3"></i> دورة الاعتماد</span>
    <span class="badge bg-{{ $ap->status === 'approved' ? 'success' : ($ap->status === 'rejected' ? 'danger' : 'warning') }}">
      {{ $ap->status_name }}
    </span>
  </div>
  <ul class="list-group list-group-flush">
    @foreach($ap->steps as $s)
      <li class="list-group-item d-flex justify-content-between align-items-center py-2">
        <div>
          <span class="badge bg-light text-dark">{{ $s->step_no }}</span>
          {{ $s->title }}
          <span class="hint">
            — {{ $s->user?->name ?? $s->role?->name ?? 'غير محدد' }}
            @if($s->acted_by) · نفّذها {{ $s->actor?->name }} {{ $s->acted_at?->format('Y-m-d H:i') }} @endif
          </span>
          @if($s->comment)<div class="hint fst-italic">"{{ $s->comment }}"</div>@endif
        </div>
        <span class="badge bg-{{ ['waiting'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger','skipped'=>'light text-dark'][$s->status] ?? 'secondary' }}">
          {{ $s->status_name }}
        </span>
      </li>
    @endforeach
  </ul>

  @if($ap->status === 'pending' && \App\Services\ApprovalEngine::canAct($ap, auth()->user()))
    <div class="card-footer bg-white d-flex gap-2">
      <form method="post" action="{{ route('approvals.approve', $ap) }}" class="d-flex gap-2 flex-grow-1">@csrf
        <input name="comment" class="form-control form-control-sm" placeholder="تعليق (اختياري)">
        <button class="btn btn-success btn-sm text-nowrap"><i class="bi bi-check-lg"></i> اعتماد</button>
      </form>
      <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejModal">رفض</button>
    </div>

    <div class="modal fade" id="rejModal"><div class="modal-dialog"><div class="modal-content">
      <form method="post" action="{{ route('approvals.reject', $ap) }}">@csrf
        <div class="modal-header"><h6 class="modal-title">رفض المستند</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label req">سبب الرفض</label>
          <textarea name="comment" rows="3" class="form-control" required></textarea>
        </div>
        <div class="modal-footer"><button class="btn btn-danger btn-sm">تأكيد الرفض</button></div>
      </form>
    </div></div></div>
  @endif
</div>
@endif
