<?php $__env->startSection('content'); ?>

<?php $__currentLoopData = $warnings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="alert alert-<?php echo e($w['level']==='danger'?'danger':'warning'); ?> py-2">
    <i class="bi bi-exclamation-triangle"></i> <?php echo e($w['text']); ?>

  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num"><?php echo e(number_format((float)$row->total_kg,1)); ?></div><div class="l">إجمالي الوزن (كجم)</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num"><?php echo e($row->rolls_count); ?></div><div class="l">عدد الأتواب</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num text-danger"><?php echo e($row->min_width_cm ?? '—'); ?></div><div class="l">أقل عرض (سم) ★</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num text-danger"><?php echo e($row->avg_gsm ?? '—'); ?></div><div class="l">متوسط البنشر ★</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num"><?php echo e(number_format((float)$row->remaining_kg,1)); ?></div><div class="l">المتبقي (كجم)</div></div></div>
  <div class="col-6 col-lg-2"><div class="stat"><div class="v num"><?php echo e($row->defect_pct ?? '—'); ?></div><div class="l">نسبة العيوب %</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <span>بيانات الحوض</span>
        <span class="badge bg-<?php echo e($row->is_ready ? 'success' : 'secondary'); ?>"><?php echo e($row->status_name); ?></span>
      </div>
      <table class="table table-sm mb-0">
        <tr><th style="width:140px">رقم الرسالة</th><td class="num fw-bold"><?php echo e($row->consignment_no); ?></td></tr>
        <tr><th>تاريخ الوصول</th><td class="num"><?php echo e($row->arrival_date?->format('Y-m-d')); ?></td></tr>
        <tr><th>المورد</th><td><?php echo e($row->supplier?->name ?? '—'); ?></td></tr>
        <tr><th>أمر الشراء</th><td class="num"><?php echo e($row->purchaseOrder?->po_no ?? '—'); ?></td></tr>
        <tr><th>الخامة</th><td><?php echo e($row->fabricType?->name ?? '—'); ?></td></tr>
        <tr><th>اللون</th><td><?php echo e($row->color?->label ?? '—'); ?></td></tr>
        <tr><th>المخزن</th><td><?php echo e($row->warehouse?->name ?? '—'); ?></td></tr>
        <tr><th>الانكماش</th><td class="num">طول <?php echo e($row->shrink_len_pct ?? '—'); ?>% · عرض <?php echo e($row->shrink_width_pct ?? '—'); ?>%</td></tr>
        <tr><th>مطابقة اللون</th><td>
          <?php if($row->color_match_ok === null): ?> —
          <?php elseif($row->color_match_ok): ?> <span class="badge bg-success">مطابق</span>
          <?php else: ?> <span class="badge bg-danger">غير مطابق</span> <?php endif; ?>
        </td></tr>
      </table>
      <div class="card-footer bg-white d-flex gap-2 flex-wrap">
        <?php if(!$row->inspections->count()): ?>
          <a href="<?php echo e(route('inspections.create', ['consignment_id'=>$row->id])); ?>" class="btn btn-sm btn-outline-plum">تقرير فحص</a>
        <?php endif; ?>
        <?php if(!$row->labReports->count()): ?>
          <a href="<?php echo e(route('lab-reports.create', ['consignment_id'=>$row->id])); ?>" class="btn btn-sm btn-outline-plum">تقرير معمل</a>
        <?php endif; ?>
        <?php if($row->min_width_cm): ?>
          <a href="<?php echo e(route('markers.requests.create')); ?>" class="btn btn-sm btn-outline-plum">طلب ماركر</a>
        <?php endif; ?>
        <?php if($row->is_ready): ?>
          <a href="<?php echo e(route('work-orders.create', ['consignment_id'=>$row->id])); ?>" class="btn btn-sm btn-success">أمر شغل</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">تعديل يدوي</div>
      <form method="post" action="<?php echo e(route('consignments.update',$row)); ?>" class="card-body"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">إجمالي الوزن (كجم)</label>
            <input type="number" step="0.001" name="total_kg" class="form-control form-control-sm" value="<?php echo e($row->total_kg); ?>"></div>
          <div class="col-6"><label class="form-label">عدد الأتواب</label>
            <input type="number" name="rolls_count" class="form-control form-control-sm" value="<?php echo e($row->rolls_count); ?>"></div>
          <div class="col-6"><label class="form-label">إجمالي الطول (م)</label>
            <input type="number" step="0.01" name="total_length_m" class="form-control form-control-sm" value="<?php echo e($row->total_length_m); ?>">
            <div class="hint">مهم لحساب عدد الرِقّات</div></div>
          <div class="col-6"><label class="form-label">الحالة</label>
            <select name="status" class="form-select form-select-sm">
              <?php $__currentLoopData = \App\Models\Consignment::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($k); ?>" <?php if($row->status===$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select></div>
          <div class="col-12"><label class="form-label">ملاحظات</label>
            <input name="notes" class="form-control form-control-sm" value="<?php echo e($row->notes); ?>"></div>
        </div>
        <button class="btn btn-plum btn-sm mt-3">حفظ</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">أوامر الشغل على الحوض ده</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>أمر الشغل</th><th>المصنع</th><th>الماركر</th><th>كجم</th><th>متوقع</th><th>مقصوص</th><th>مستلم</th><th>الحالة</th></tr></thead>
          <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $row->workOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td class="num"><a href="<?php echo e(route('work-orders.show',$w)); ?>"><?php echo e($w->wo_no); ?></a></td>
              <td><?php echo e($w->factory?->name); ?></td>
              <td class="num"><?php echo e($w->marker?->code); ?></td>
              <td class="num"><?php echo e(number_format((float)$w->allocated_kg,1)); ?></td>
              <td class="num"><?php echo e(number_format((int)$w->expected_pieces)); ?></td>
              <td class="num"><?php echo e(number_format((int)$w->cut_pieces)); ?></td>
              <td class="num"><?php echo e(number_format((int)$w->received_pieces)); ?></td>
              <td><span class="badge bg-<?php echo e($w->status_color); ?>"><?php echo e($w->status_name); ?></span></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">مفيش أوامر شغل لسه.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">الأتواب (<?php echo e($row->rolls->count()); ?>)</div>
      <div class="table-responsive" style="max-height:380px;overflow:auto">
        <table class="table table-sm mb-0">
          <thead><tr><th>رقم التوب</th><th>الطول</th><th>العرض</th><th>البنشر</th><th>الوزن</th><th>مفحوص</th><th>الحالة</th></tr></thead>
          <tbody>
          <?php $__currentLoopData = $row->rolls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="num"><?php echo e($r->roll_no); ?></td>
              <td class="num"><?php echo e($r->length_m ?? '—'); ?></td>
              <td class="num"><?php echo e($r->width_cm ?? '—'); ?></td>
              <td class="num"><?php echo e($r->gsm ?? '—'); ?></td>
              <td class="num"><?php echo e($r->net_kg ?? '—'); ?></td>
              <td><?php if($r->is_inspected): ?><i class="bi bi-check-circle text-success"></i><?php endif; ?></td>
              <td class="hint"><?php echo e($r->status_name); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/consignments/show.blade.php ENDPATH**/ ?>