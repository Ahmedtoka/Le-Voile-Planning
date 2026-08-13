<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span><?php echo e($title); ?> <span class="hint">(<?php echo e($rows->total()); ?>)</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input name="q" value="<?php echo e(request('q')); ?>" class="form-control form-control-sm" style="width:140px" placeholder="رقم الأمر…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status')===$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <select name="factory_id" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">كل المصانع</option>
        <?php $__currentLoopData = $factories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('factory_id')==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="late" value="1" id="lt" <?php if(request('late')): echo 'checked'; endif; ?> onchange="this.form.submit()">
        <label class="form-check-label small" for="lt">المتأخر بس</label>
      </div>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="<?php echo e(route('io.export.work-orders')); ?>" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i></a>
    <a href="<?php echo e(route('work-orders.create')); ?>" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> أمر شغل</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>رقم الأمر</th><th>التاريخ</th><th>الحوض</th><th>اللون</th><th>المصنع</th><th>كجم</th>
        <th>متوقع</th><th>مقصوص</th><th>مستلم</th><th>متبقي</th><th>الانحراف</th><th>التسليم</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="<?php echo e($r->is_late ? 'table-warning' : ''); ?>">
          <td class="num fw-bold"><a href="<?php echo e(route('work-orders.show',$r)); ?>"><?php echo e($r->wo_no); ?></a></td>
          <td class="num"><?php echo e($r->wo_date?->format('Y-m-d')); ?></td>
          <td class="num"><?php echo e($r->consignment?->consignment_no ?? '—'); ?></td>
          <td><?php echo e($r->consignment?->color?->code ?? '—'); ?></td>
          <td><?php echo e($r->factory?->name ?? '—'); ?></td>
          <td class="num"><?php echo e(number_format((float)$r->allocated_kg,1)); ?></td>
          <td class="num"><?php echo e(number_format((int)$r->expected_pieces)); ?></td>
          <td class="num"><?php echo e(number_format((int)$r->cut_pieces)); ?></td>
          <td class="num"><?php echo e(number_format((int)$r->received_pieces)); ?></td>
          <td class="num"><?php echo e(number_format($r->outstanding_pieces)); ?></td>
          <td class="num">
            <?php if($r->variance_pct !== null): ?>
              <span class="badge bg-<?php echo e(['ok'=>'success','warn'=>'warning','danger'=>'danger'][$r->variance_flag] ?? 'secondary'); ?>">
                <?php echo e($r->variance_pct); ?>%
              </span>
            <?php else: ?> — <?php endif; ?>
          </td>
          <td class="num <?php echo e($r->is_late ? 'text-danger fw-bold' : ''); ?>"><?php echo e($r->due_date?->format('Y-m-d') ?? '—'); ?></td>
          <td><span class="badge bg-<?php echo e($r->status_color); ?>"><?php echo e($r->status_name); ?></span></td>
          <td><a href="<?php echo e(route('work-orders.show',$r)); ?>" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="14" class="text-center text-muted py-4">مفيش أوامر شغل.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/workorders/index.blade.php ENDPATH**/ ?>