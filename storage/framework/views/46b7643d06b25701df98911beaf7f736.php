<?php $__env->startSection('content'); ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v text-primary"><?php echo e($consignments['ready']); ?></div>
      <div class="l">أحواض جاهزة للتشغيل</div>
      <div class="hint mt-1"><?php echo e(number_format($consignments['ready_kg'], 0)); ?> كجم متاحة</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v text-warning"><?php echo e($consignments['awaiting_inspection'] + $consignments['awaiting_lab']); ?></div>
      <div class="l">أحواض مستنية فحص أو معمل</div>
      <div class="hint mt-1"><?php echo e($consignments['awaiting_inspection']); ?> فحص · <?php echo e($consignments['awaiting_lab']); ?> معمل</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v"><?php echo e($workOrders['open']); ?></div>
      <div class="l">أوامر شغل مفتوحة</div>
      <div class="hint mt-1"><?php echo e(number_format($workOrders['outstanding'] ?? 0)); ?> قطعة لسه على المصانع</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat">
      <div class="v <?php echo e($workOrders['late'] > 0 ? 'text-danger' : ''); ?>"><?php echo e($workOrders['late']); ?></div>
      <div class="l">أوامر شغل متأخرة</div>
      <div class="hint mt-1"><?php echo e($workOrders['danger']); ?> انحرافهم خارج الحدود</div>
    </div>
  </div>
</div>

<div class="row g-3">

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> أخطر 10 موديلات في التغطية</span>
        <a href="<?php echo e(route('planning.coverage')); ?>" class="btn btn-sm btn-outline-plum">الكل</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr>
            <th>الموديل</th><th>متوسط يومي</th><th>الرصيد</th><th>أيام التغطية</th><th>الحالة</th>
          </tr></thead>
          <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $coverage; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($c['model']->name); ?></td>
              <td class="num"><?php echo e($c['avg_daily']); ?></td>
              <td class="num"><?php echo e(number_format($c['stock'])); ?></td>
              <td class="num"><?php echo e($c['cover_days'] ?? '—'); ?></td>
              <td><span class="badge bg-<?php echo e(\App\Services\CoverageService::flagColor($c['flag'])); ?>"><?php echo e($c['flag_label']); ?></span></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">مفيش داتا مبيعات أو أرصدة لسه — ارفعها من شاشة الاستيراد.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-building"></i> تحميل المصانع</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>المصنع</th><th>أوامر مفتوحة</th><th>متأخرة</th><th>قطع لسه عليه</th></tr></thead>
          <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $factoryLoad; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($f['factory']->name); ?></td>
              <td class="num"><?php echo e($f['open']); ?></td>
              <td class="num <?php echo e($f['late'] ? 'text-danger fw-bold' : ''); ?>"><?php echo e($f['late']); ?></td>
              <td class="num"><?php echo e(number_format($f['outstanding'] ?? 0)); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">مفيش أوامر شغل مفتوحة.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-square"></i> مستني اعتمادك</span>
        <a href="<?php echo e(route('approvals.index')); ?>" class="btn btn-sm btn-outline-plum">الكل</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php $__empty_1 = true; $__currentLoopData = $myApprovals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <div class="fw-bold"><?php echo e($a->subject_no); ?></div>
              <div class="hint"><?php echo e($a->currentStepRow()?->title); ?></div>
            </div>
            <span class="hint"><?php echo e($a->created_at->diffForHumans()); ?></span>
          </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <li class="list-group-item text-center text-muted py-3">مفيش حاجة مستنية اعتمادك.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card-header text-danger"><i class="bi bi-exclamation-triangle"></i> أوامر شغل متأخرة</div>
      <ul class="list-group list-group-flush">
        <?php $__empty_1 = true; $__currentLoopData = $lateOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <li class="list-group-item py-2">
            <a href="<?php echo e(route('work-orders.show', $w)); ?>" class="fw-bold"><?php echo e($w->wo_no); ?></a>
            <div class="hint">
              <?php echo e($w->factory?->name); ?> · الحوض <?php echo e($w->consignment?->consignment_no); ?> ·
              متأخر <?php echo e((int) $w->due_date->diffInDays(now())); ?> يوم · متبقي <?php echo e(number_format($w->outstanding_pieces)); ?> قطعة
            </div>
          </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <li class="list-group-item text-center text-muted py-3">مفيش تأخير. تمام.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/dashboard.blade.php ENDPATH**/ ?>