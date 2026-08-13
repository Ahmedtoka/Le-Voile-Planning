<?php $__env->startSection('content'); ?>
<?php $lines = old('lines', $row->lines?->toArray() ?? []); $editable = $row->isEditable() || $mode==='create'; ?>

<?php echo $__env->make('partials.approval_box', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<form method="post" action="<?php echo e($mode==='create' ? route('purchase-orders.store') : route('purchase-orders.update',$row)); ?>">
  <?php echo csrf_field(); ?> <?php if($mode==='edit'): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><?php echo e($mode==='create' ? 'طلب شراء جديد' : 'طلب شراء ' . $row->po_no); ?></span>
      <div class="d-flex gap-2">
        <?php if($mode==='edit'): ?>
          <span class="badge bg-<?php echo e($row->status_color); ?> align-self-center"><?php echo e($row->status_label); ?></span>
          <a href="<?php echo e(route('purchase-orders.print',$row)); ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i> طباعة</a>
        <?php endif; ?>
        <a href="<?php echo e(route('purchase-orders.index')); ?>" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
      </div>
    </div>
    <div class="card-body">
      <fieldset <?php if(!$editable): echo 'disabled'; endif; ?>>
      <div class="row g-3">
        <div class="col-md-2"><label class="form-label req">التاريخ</label>
          <input type="date" name="po_date" class="form-control form-control-sm"
                 value="<?php echo e(old('po_date', $row->po_date?->format('Y-m-d') ?? $row->po_date)); ?>" required></div>
        <div class="col-md-3"><label class="form-label">المورد</label>
          <select name="supplier_id" class="form-select form-select-sm">
            <option value="">— اختر —</option>
            <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('supplier_id',$row->supplier_id)==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select></div>
        <div class="col-md-3"><label class="form-label">اسم الموظف</label>
          <select name="employee_id" class="form-select form-select-sm">
            <option value="">— اختر —</option>
            <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('employee_id',$row->employee_id ?? auth()->id())==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select></div>
        <div class="col-md-2"><label class="form-label">تاريخ التوريد</label>
          <input type="date" name="delivery_date" class="form-control form-control-sm"
                 value="<?php echo e(old('delivery_date', $row->delivery_date?->format('Y-m-d'))); ?>"></div>
        <div class="col-md-2"><label class="form-label">مكان التسليم</label>
          <input name="delivery_place" class="form-control form-control-sm" value="<?php echo e(old('delivery_place',$row->delivery_place)); ?>"></div>
        <div class="col-md-2"><label class="form-label">طريقة الدفع</label>
          <input name="payment_method" class="form-control form-control-sm" value="<?php echo e(old('payment_method',$row->payment_method)); ?>"></div>
        <div class="col-md-2"><label class="form-label">الخصم %</label>
          <input type="number" step="0.01" name="discount_pct" class="form-control form-control-sm" value="<?php echo e(old('discount_pct',$row->discount_pct ?? 0)); ?>"></div>
        <div class="col-md-2"><label class="form-label">الضريبة %</label>
          <input type="number" step="0.01" name="tax_pct" class="form-control form-control-sm" value="<?php echo e(old('tax_pct',$row->tax_pct ?? 0)); ?>"></div>
        <div class="col-md-6"><label class="form-label">ملاحظات</label>
          <input name="notes" class="form-control form-control-sm" value="<?php echo e(old('notes',$row->notes)); ?>"></div>
      </div>
      </fieldset>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>الأصناف</span>
      <?php if($editable): ?><button type="button" class="btn btn-sm btn-outline-plum py-0" onclick="LV.add('lineTpl','lines')"><i class="bi bi-plus-lg"></i> سطر</button><?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm line-table mb-0">
        <thead><tr>
          <th style="width:35px">م</th><th style="width:160px">كود اللون</th><th>اسم الصنف</th>
          <th style="width:100px">الكمية</th><th style="width:80px">الوحدة</th><th style="width:100px">سعر الوحدة</th>
          <th style="width:95px">نسبة الزيادة %</th><th>ملاحظات</th><th style="width:40px"></th>
        </tr></thead>
        <tbody id="lines">
          <?php $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('po.line', ['i'=>$i, 'l'=>$l], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php if(!count($lines)): ?>
            <?php echo $__env->make('po.line', ['i'=>0, 'l'=>[]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white hint">
      <i class="bi bi-info-circle"></i>
      نسبة الزيادة المسموح بها بتتقارن أوتوماتيك عند الاستلام — لو المورد ورّد فوقها، السيستم هيمنع الإذن.
    </div>
  </div>

  <?php if($editable): ?>
    <button class="btn btn-plum btn-sm"><i class="bi bi-save"></i> حفظ</button>
  <?php endif; ?>
  <?php if($mode==='edit' && $row->isEditable()): ?>
    <button type="button" class="btn btn-success btn-sm"
            onclick="if(confirm('إرسال للاعتماد؟ المستند هيتقفل عن التعديل.')) document.getElementById('submitForm').submit()">
      <i class="bi bi-send"></i> إرسال للاعتماد
    </button>
  <?php endif; ?>
</form>

<?php if($mode==='edit' && $row->isEditable()): ?>
  <form id="submitForm" method="post" action="<?php echo e(route('purchase-orders.submit',$row)); ?>" class="d-none"><?php echo csrf_field(); ?></form>
<?php endif; ?>

<template id="lineTpl">
  <?php echo $__env->make('po.line', ['i'=>'__IDX__', 'l'=>[], 'tpl'=>true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</template>

<?php echo $__env->make('partials.lines_js', ['startIndex' => max(count($lines), 1)], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/po/form.blade.php ENDPATH**/ ?>