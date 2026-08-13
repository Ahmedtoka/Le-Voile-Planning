<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-header d-flex gap-2 flex-wrap align-items-center">
    <span><?php echo e($title); ?> <span class="hint">(<?php echo e($rows->total()); ?>)</span></span>
    <form class="d-flex gap-2 ms-auto" method="get">
      <input name="q" value="<?php echo e(request('q')); ?>" class="form-control form-control-sm" style="width:150px" placeholder="رقم الطلب…">
      <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <option value="">كل الحالات</option>
        <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status')===$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <select name="supplier_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
        <option value="">كل الموردين</option>
        <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('supplier_id')==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="<?php echo e(route('purchase-orders.create')); ?>" class="btn btn-sm btn-plum"><i class="bi bi-plus-lg"></i> طلب شراء</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr>
        <th>رقم الطلب</th><th>التاريخ</th><th>المورد</th><th>الكمية</th><th>الإجمالي</th><th>الحالة</th><th></th>
      </tr></thead>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="num fw-bold"><?php echo e($r->po_no); ?></td>
          <td class="num"><?php echo e($r->po_date?->format('Y-m-d')); ?></td>
          <td><?php echo e($r->supplier?->name ?? '—'); ?></td>
          <td class="num"><?php echo e(rtrim(rtrim(number_format((float)$r->total_qty,3),'0'),'.')); ?></td>
          <td class="num"><?php echo e(number_format((float)$r->total, 2)); ?></td>
          <td><span class="badge bg-<?php echo e($r->status_color); ?>"><?php echo e($r->status_label); ?></span></td>
          <td class="text-nowrap">
            <a href="<?php echo e(route('purchase-orders.edit',$r)); ?>" class="btn btn-sm btn-outline-plum py-0"><i class="bi bi-pencil"></i></a>
            <a href="<?php echo e(route('purchase-orders.print',$r)); ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i></a>
          </td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">مفيش طلبات شراء.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/po/index.blade.php ENDPATH**/ ?>