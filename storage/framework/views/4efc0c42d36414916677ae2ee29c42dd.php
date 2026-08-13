<?php if(session('success')): ?>
  <div class="alert alert-success alert-dismissible fade show py-2">
    <i class="bi bi-check-circle"></i> <?php echo e(session('success')); ?>

    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if($errors->any()): ?>
  <div class="alert alert-danger alert-dismissible fade show py-2">
    <i class="bi bi-exclamation-triangle"></i>
    <ul class="mb-0 ps-3">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/partials/alerts.blade.php ENDPATH**/ ?>