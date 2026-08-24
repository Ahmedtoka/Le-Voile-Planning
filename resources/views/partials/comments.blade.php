@php
  use App\Http\Controllers\CommentController;
  $ckey     = CommentController::keyFor($row);
  $comments = $ckey ? $row->comments()->with(['user','replyTo.user'])->get() : collect();
  $people   = \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name','id');
@endphp

@if($ckey)
<div class="card mt-3" id="discussion">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-chat-dots" aria-hidden="true"></i> نقاش المستند <span class="hint">({{ $comments->count() }})</span></span>
    <span class="hint">إثباتات ومراسلات — زي التيكيت</span>
  </div>

  <div class="card-body pb-2">
    <div class="hint mb-3">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      المكان ده لكل الأخذ والرد على المستند: استفسار من قسم لقسم، صورة إثبات (قماش، عيب، ورقة أصلية)،
      أو قرار متسجّل. التعليقات <b>ما بتتحذفش بعد ربع ساعة</b> — دي سجل المستند.
    </div>

    @forelse($comments as $c)
      <div class="d-flex gap-2 mb-3 {{ $c->kind === 'system' ? 'opacity-75' : '' }}">
        <div style="width:34px;height:34px;border-radius:50%;flex:0 0 34px;line-height:34px;text-align:center;
             background:var(--lv-tint);color:var(--lv-brand);font-weight:700;font-size:.8rem">
          {{ mb_substr($c->user?->name ?? '؟', 0, 1) }}
        </div>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <b style="font-size:.85rem">{{ $c->user?->name ?? 'النظام' }}</b>
            <span class="hint">{{ $c->user?->roleNames() }}</span>
            <span class="badge bg-{{ $c->kind_color }}">{{ $c->kind_name }}</span>
            <span class="hint ms-auto" title="{{ $c->created_at->format('Y-m-d H:i') }}">
              {{ $c->created_at->diffForHumans() }}
            </span>
            @if($c->user_id === auth()->id() || auth()->user()->isAdmin())
              <form method="post" action="{{ route('comments.destroy', $c) }}" onsubmit="return confirm('حذف التعليق؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-link text-danger p-0" style="font-size:.75rem">حذف</button>
              </form>
            @endif
          </div>

          @if($c->replyTo)
            <div class="hint border-start border-3 ps-2 my-1" style="border-color:var(--lv-soft)!important">
              ردًا على {{ $c->replyTo->user?->name }}: {{ Str::limit($c->replyTo->body, 70) }}
            </div>
          @endif

          @if($c->body)
            <div style="white-space:pre-wrap;font-size:.86rem">{{ $c->body }}</div>
          @endif

          @if($c->attachment_path)
            <div class="mt-1">
              @if($c->is_image)
                <a href="{{ asset('storage/'.$c->attachment_path) }}" target="_blank">
                  <img src="{{ asset('storage/'.$c->attachment_path) }}" alt="{{ $c->attachment_name }}"
                       style="max-height:170px;border-radius:8px;border:1px solid var(--lv-line)">
                </a>
              @else
                <a href="{{ asset('storage/'.$c->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-plum py-0">
                  <i class="bi bi-paperclip" aria-hidden="true"></i> {{ $c->attachment_name }}
                </a>
              @endif
            </div>
          @endif

          @if(!empty($c->mentions))
            <div class="hint mt-1">
              نادى:
              @foreach($c->mentions as $uid){{ $people[$uid] ?? '' }}@if(!$loop->last)، @endif @endforeach
            </div>
          @endif
        </div>
      </div>
    @empty
      <div class="text-center text-muted py-3 small">مفيش نقاش على المستند ده لسه.</div>
    @endforelse
  </div>

  <div class="card-footer bg-white">
    <form method="post" action="{{ route('comments.store', [$ckey, $row->id]) }}" enctype="multipart/form-data">
      @csrf
      <div class="row g-2">
        <div class="col-12">
          <textarea name="body" rows="2" class="form-control form-control-sm"
                    placeholder="اكتب استفسار، رد، أو قرار… وارفق صورة لو فيه إثبات"></textarea>
        </div>
        <div class="col-md-3">
          <select name="kind" class="form-select form-select-sm">
            <option value="note">ملاحظة</option>
            <option value="question">استفسار</option>
            <option value="answer">رد</option>
            <option value="decision">قرار</option>
          </select>
        </div>
        <div class="col-md-4">
          <select name="mentions[]" class="form-select form-select-sm" multiple size="1" title="نادي حد">
            @foreach($people as $uid => $nm)<option value="{{ $uid }}">{{ $nm }}</option>@endforeach
          </select>
          <div class="hint">Ctrl للاختيار المتعدد</div>
        </div>
        <div class="col-md-3">
          <input type="file" name="attachment" accept="image/*,.pdf" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <button class="btn btn-plum btn-sm w-100"><i class="bi bi-send" aria-hidden="true"></i> إرسال</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endif
