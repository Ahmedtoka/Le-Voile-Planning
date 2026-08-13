<?php $ap = $row->approval ?? null; ?>
<?php if($ap): ?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-diagram-3"></i> دورة الاعتماد</span>
    <span class="badge bg-<?php echo e($ap->status === 'approved' ? 'success' : ($ap->status === 'rejected' ? 'danger' : 'warning')); ?>">
      <?php echo e($ap->status_name); ?>

    </span>
  </div>
  <ul class="list-group list-group-flush">
    <?php $__currentLoopData = $ap->steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li class="list-group-item d-flex justify-content-between align-items-center py-2">
        <div>
          <span class="badge bg-light text-dark"><?php echo e($s->step_no); ?></span>
          <?php echo e($s->title); ?>

          <span class="hint">
            — <?php echo e($s->user?->name ?? $s->role?->name ?? 'غير محدد'); ?>

            <?php if($s->acted_by): ?> · نفّذها <?php echo e($s->actor?->name); ?> <?php echo e($s->acted_at?->format('Y-m-d H:i')); ?> <?php endif; ?>
          </span>
          <?php if($s->comment): ?><div class="hint fst-italic">"<?php echo e($s->comment); ?>"</div><?php endif; ?>
        </div>
        <span class="badge bg-<?php echo e(['waiting'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger','skipped'=>'light text-dark'][$s->status] ?? 'secondary'); ?>">
          <?php echo e($s->status_name); ?>

        </span>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </ul>

  <?php if($ap->status === 'pending' && \App\Services\ApprovalEngine::canAct($ap, auth()->user())): ?>
    <div class="card-footer bg-white d-flex gap-2">
      <form method="post" action="<?php echo e(route('approvals.approve', $ap)); ?>" class="d-flex gap-2 flex-grow-1"><?php echo csrf_field(); ?>
        <input name="comment" class="form-control form-control-sm" placeholder="تعليق (اختياري)">
        <button class="btn btn-success btn-sm text-nowrap"><i class="bi bi-check-lg"></i> اعتماد</button>
      </form>
      <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejModal">رفض</button>
    </div>

    <div class="modal fade" id="rejModal"><div class="modal-dialog"><div class="modal-content">
      <form method="post" action="<?php echo e(route('approvals.reject', $ap)); ?>"><?php echo csrf_field(); ?>
        <div class="modal-header"><h6 class="modal-title">رفض المستند</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label req">سبب الرفض</label>
          <textarea name="comment" rows="3" class="form-control" required></textarea>
        </div>
        <div class="modal-footer"><button class="btn btn-danger btn-sm">تأكيد الرفض</button></div>
      </form>
    </div></div></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/partials/approval_box.blade.php ENDPATH**/ ?>