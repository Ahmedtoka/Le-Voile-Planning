@php
  use App\Http\Controllers\CommentController;
  $ckey     = CommentController::keyFor($row);
  $comments = $ckey ? $row->comments()->with(['user','replyTo.user'])->get() : collect();
  $people   = \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name','id');
@endphp

@if($ckey)
<style>
  /* نقاش المستند: تريد زي التيكيت — أفاتار + اسم + دور + نوع + وقت، والنص تحتهم */
  .cmt-list{display:flex;flex-direction:column;gap:14px}
  .cmt{display:flex;gap:10px;align-items:flex-start}
  .cmt.is-system{opacity:.75}
  .cmt .cmt-av{width:34px;height:34px;border-radius:50%;flex:0 0 34px;display:inline-flex;align-items:center;justify-content:center;
    background:var(--lv-tint);color:var(--lv-brand);font-weight:700;font-size:.85rem}
  .cmt .cmt-body{flex:1 1 auto;min-width:0;background:var(--lv-surface-2);border:1px solid var(--lv-line);border-radius:10px;padding:8px 12px}
  .cmt .cmt-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
  .cmt .cmt-head b{font-size:var(--fs-sm)}
  .cmt .cmt-time{margin-inline-start:auto;font-size:var(--fs-xs);color:var(--lv-muted);font-variant-numeric:tabular-nums}
  .cmt .cmt-text{white-space:pre-wrap;font-size:var(--fs-base);line-height:1.7;overflow-wrap:anywhere}
  .cmt .cmt-quote{border-inline-start:3px solid var(--lv-soft);padding-inline-start:8px;margin:4px 0;font-size:var(--fs-xs);color:var(--lv-muted)}
  .cmt .cmt-img{max-height:170px;max-width:100%;border-radius:8px;border:1px solid var(--lv-line)}
  .cmt .cmt-del{padding:0 4px;min-height:0;font-size:var(--fs-xs)}
  .cmt-compose select[multiple]{min-height:var(--ctl-h)}
</style>
<div class="card mt-3" id="discussion">
  <div class="card-header">
    <span><i class="bi bi-chat-dots" aria-hidden="true"></i> نقاش المستند <span class="badge bg-secondary">{{ $comments->count() }}</span></span>
    <span class="hint ms-auto">إثباتات ومراسلات — زي التيكيت</span>
  </div>

  <div class="card-body">
    <div class="note-box mb-3">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span>المكان ده لكل الأخذ والرد على المستند: استفسار من قسم لقسم، صورة إثبات (قماش، عيب، ورقة أصلية)،
      أو قرار متسجّل. التعليقات <b>ما بتتحذفش بعد ربع ساعة</b> — دي سجل المستند.</span>
    </div>

    <div class="cmt-list">
    @forelse($comments as $c)
      <div class="cmt {{ $c->kind === 'system' ? 'is-system' : '' }}">
        <div class="cmt-av" aria-hidden="true">{{ mb_substr($c->user?->name ?? '؟', 0, 1) }}</div>
        <div class="cmt-body">
          <div class="cmt-head">
            <b>{{ $c->user?->name ?? 'النظام' }}</b>
            @if($c->user)<span class="hint">{{ $c->user->roleNames() }}</span>@endif
            <span class="badge bg-{{ $c->kind_color }}">{{ $c->kind_name }}</span>
            <time class="cmt-time" datetime="{{ $c->created_at->toIso8601String() }}" title="{{ $c->created_at->format('Y-m-d H:i') }}">
              {{ $c->created_at->diffForHumans() }}
            </time>
            @if($c->user_id === auth()->id() || auth()->user()->isAdmin())
              <form method="post" action="{{ route('comments.destroy', $c) }}" onsubmit="return confirm('حذف التعليق؟')">
                @csrf @method('DELETE')
                <button class="btn btn-ghost btn-sm cmt-del text-danger" aria-label="حذف التعليق" title="حذف التعليق"><i class="bi bi-trash" aria-hidden="true"></i> حذف</button>
              </form>
            @endif
          </div>

          @if($c->replyTo)
            <div class="cmt-quote">
              ردًا على {{ $c->replyTo->user?->name }}: {{ Str::limit($c->replyTo->body, 70) }}
            </div>
          @endif

          @if($c->body)
            <div class="cmt-text">{{ $c->body }}</div>
          @endif

          @if($c->attachment_path)
            <div class="mt-2">
              @if($c->is_image)
                <a href="{{ asset('storage/'.$c->attachment_path) }}" target="_blank">
                  <img src="{{ asset('storage/'.$c->attachment_path) }}" alt="{{ $c->attachment_name }}" class="cmt-img">
                </a>
              @else
                <a href="{{ asset('storage/'.$c->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-plum">
                  <i class="bi bi-paperclip" aria-hidden="true"></i> {{ $c->attachment_name }}
                </a>
              @endif
            </div>
          @endif

          @if(!empty($c->mentions))
            <div class="hint mt-1">
              <i class="bi bi-at" aria-hidden="true"></i> نادى:
              @foreach($c->mentions as $uid){{ $people[$uid] ?? '' }}@if(!$loop->last)، @endif @endforeach
            </div>
          @endif
        </div>
      </div>
    @empty
      <div class="empty-state py-3">
        <i class="bi bi-chat-square-text ico" aria-hidden="true"></i>
        <div class="t">مفيش نقاش على المستند ده لسه.</div>
        <div class="hint">اكتب أول استفسار أو ارفق صورة إثبات تحت.</div>
      </div>
    @endforelse
    </div>
  </div>

  <div class="card-footer bg-white">
    <form method="post" action="{{ route('comments.store', [$ckey, $row->id]) }}" enctype="multipart/form-data" class="cmt-compose">
      @csrf
      <div class="row g-2">
        <div class="col-12">
          <label class="form-label req" for="cmt_body">تعليق جديد</label>
          <textarea name="body" id="cmt_body" rows="2" class="form-control"
                    placeholder="اكتب استفسار، رد، أو قرار… وارفق صورة لو فيه إثبات"></textarea>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="cmt_kind">النوع</label>
          <select name="kind" id="cmt_kind" class="form-select">
            <option value="note">ملاحظة</option>
            <option value="question">استفسار</option>
            <option value="answer">رد</option>
            <option value="decision">قرار</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="cmt_mentions">نادي حد <span class="hint">(اختياري)</span></label>
          <select name="mentions[]" id="cmt_mentions" class="form-select" multiple size="1" title="نادي حد">
            @foreach($people as $uid => $nm)<option value="{{ $uid }}">{{ $nm }}</option>@endforeach
          </select>
          <div class="hint">Ctrl للاختيار المتعدد</div>
        </div>
        <div class="col-md-5">
          <label class="form-label" for="cmt_attachment">مرفق <span class="hint">(صورة أو PDF)</span></label>
          <input type="file" name="attachment" id="cmt_attachment" accept="image/*,.pdf" class="form-control">
        </div>
        <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
          <button class="btn btn-plum"><i class="bi bi-send" aria-hidden="true"></i> إرسال</button>
          <span class="hint">بيتسجل باسمك ووقته — وبيوصل إشعار لأي حد ناديته.</span>
        </div>
      </div>
    </form>
  </div>
</div>
@endif
