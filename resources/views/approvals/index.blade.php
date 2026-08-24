@extends('layouts.app')
@section('content')

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-inbox" aria-hidden="true"></i> مستني اعتمادك ({{ $mine->total() }})</div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>نوع المستند</th><th>رقم المستند</th><th>الخطوة</th><th>مقدّم الطلب</th><th>منذ</th><th style="width:340px"></th></tr></thead>
      <tbody>
      @forelse($mine as $a)
        @php $step = $a->currentStepRow(); @endphp
        <tr>
          <td>{{ __('doc.'.$a->doc_type) }}</td>
          <td class="num fw-bold">{{ $a->subject_no }}</td>
          <td>{{ $step?->title }}</td>
          <td>{{ $a->requester?->name }}</td>
          <td class="hint">{{ $a->created_at->diffForHumans() }}</td>
          <td>
            <div class="d-flex gap-1">
              <form method="post" action="{{ route('approvals.approve',$a) }}" class="d-flex gap-1 flex-grow-1">@csrf
                <input name="comment" class="form-control form-control-sm" placeholder="تعليق">
                <button class="btn btn-success btn-sm text-nowrap"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
              </form>
              <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rej{{ $a->id }}">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
              </button>
            </div>
          </td>
        </tr>
        <div class="modal fade" id="rej{{ $a->id }}"><div class="modal-dialog"><div class="modal-content">
          <form method="post" action="{{ route('approvals.reject',$a) }}">@csrf
            <div class="modal-header"><h6 class="modal-title">رفض {{ $a->subject_no }}</h6>
              <button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <label class="form-label req">سبب الرفض</label>
              <textarea name="comment" rows="3" class="form-control" required></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-danger btn-sm">تأكيد الرفض</button></div>
          </form>
        </div></div></div>
      @empty
        <tr><td colspan="6">
          <div class="empty-state">
            <i class="bi bi-check2-circle ico" aria-hidden="true"></i>
            <div class="t">مفيش حاجة مستنية اعتمادك.</div>
            <div>كل اللي وصلك اتعمد — لما يجيلك جديد هيبان هنا وفي المنيو برقم أحمر.</div>
          </div>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white">{{ $mine->links() }}</div>
</div>

<div class="card">
  <div class="card-header">آخر 50 دورة اعتماد</div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>النوع</th><th>المستند</th><th>التقدّم</th><th>مقدّم الطلب</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
      @foreach($all as $a)
        <tr>
          <td>{{ __('doc.'.$a->doc_type) }}</td>
          <td class="num">{{ $a->subject_no }}</td>
          <td class="num">{{ $a->current_step }}/{{ $a->total_steps }}</td>
          <td>{{ $a->requester?->name }}</td>
          <td><span class="badge bg-{{ ['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'secondary'][$a->status] }}">{{ $a->status_name }}</span></td>
          <td class="num hint">{{ $a->created_at->format('Y-m-d H:i') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
