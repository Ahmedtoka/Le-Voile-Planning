<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span><?php echo e($title); ?> <span class="hint">(<?php echo e($rows->total()); ?>)</span></span>
    <form class="d-flex gap-2 ms-auto flex-wrap" method="get">
      <input name="q" value="<?php echo e(request('q')); ?>" class="form-control form-control-sm" style="width:170px" placeholder="رقم الرسالة…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status')===$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <select name="color_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الألوان</option>
        <?php $__currentLoopData = $colors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('color_id')==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" name="ready" value="1" id="rdy" <?php if(request('ready')): echo 'checked'; endif; ?> onchange="this.form.submit()">
        <label class="form-check-label small" for="rdy">الجاهز للتشغيل بس</label>
      </div>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="<?php echo e(route('io.export.consignments')); ?>" class="btn btn-sm btn-outline-plum"><i class="bi bi-download"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الرسالة</th><th>التاريخ</th><th>المورد</th><th>الخامة</th><th>اللون</th>
        <th>الوزن</th><th>الأتواب</th><th>أقل عرض</th><th>البنشر</th><th>المتبقي</th><th>الحالة</th><th></th>
      </tr></thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="num fw-bold"><a href="<?php echo e(route('consignments.show',$r)); ?>"><?php echo e($r->consignment_no); ?></a></td>
          <td class="num"><?php echo e($r->arrival_date?->format('Y-m-d')); ?></td>
          <td><?php echo e($r->supplier?->name ?? '—'); ?></td>
          <td><?php echo e($r->fabricType?->name ?? '—'); ?></td>
          <td><?php echo e($r->color?->code ?? '—'); ?></td>
          <td class="num"><?php echo e(number_format((float)$r->total_kg,1)); ?></td>
          <td class="num"><?php echo e($r->rolls_count); ?></td>
          <td class="num <?php echo e($r->min_width_cm ? 'fw-bold' : 'text-muted'); ?>"><?php echo e($r->min_width_cm ?? '—'); ?></td>
          <td class="num <?php echo e($r->avg_gsm ? 'fw-bold' : 'text-muted'); ?>"><?php echo e($r->avg_gsm ?? '—'); ?></td>
          <td class="num"><?php echo e(number_format((float)$r->remaining_kg,1)); ?></td>
          <td><span class="badge bg-<?php echo e($r->is_ready ? 'success' : ($r->status==='rejected'?'danger':'secondary')); ?>"><?php echo e($r->status_name); ?></span></td>
          <td class="text-nowrap">
            <a href="<?php echo e(route('consignments.show',$r)); ?>" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-eye"></i></a>
            <?php if($r->is_ready): ?>
              <a href="<?php echo e(route('work-orders.create', ['consignment_id'=>$r->id])); ?>" class="btn btn-sm btn-success py-0" title="أمر شغل"><i class="bi bi-hammer"></i></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="12" class="text-center text-muted py-4">مفيش أحواض. ابدأ بإذن استلام خام.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/consignments/index.blade.php ENDPATH**/ ?>