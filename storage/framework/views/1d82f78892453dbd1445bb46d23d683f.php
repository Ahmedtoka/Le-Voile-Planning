<?php $tpl = $tpl ?? false; ?>
<?php if(!$tpl): ?><tr><?php endif; ?>
  <td class="text-center row-no"><?php echo e(is_numeric($i) ? $i+1 : 1); ?></td>
  <td><select name="lines[<?php echo e($i); ?>][color_id]">
      <option value="">—</option>
      <?php $__currentLoopData = $colors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(($l['color_id'] ?? null)==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select></td>
  <td><select name="lines[<?php echo e($i); ?>][fabric_type_id]">
      <option value="">—</option>
      <?php $__currentLoopData = $fabricTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(($l['fabric_type_id'] ?? null)==$k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select></td>
  <td><input type="number" step="0.001" name="lines[<?php echo e($i); ?>][qty]" value="<?php echo e($l['qty'] ?? ''); ?>"></td>
  <td><select name="lines[<?php echo e($i); ?>][unit]">
      <?php $__currentLoopData = ['طن','كجم','متر']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($u); ?>" <?php if(($l['unit'] ?? 'طن')===$u): echo 'selected'; endif; ?>><?php echo e($u); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select></td>
  <td><input type="number" step="0.01" name="lines[<?php echo e($i); ?>][unit_price]" value="<?php echo e($l['unit_price'] ?? ''); ?>"></td>
  <td><input type="number" step="0.01" name="lines[<?php echo e($i); ?>][tolerance_pct]" value="<?php echo e($l['tolerance_pct'] ?? $defaultTolerance); ?>"></td>
  <td><input name="lines[<?php echo e($i); ?>][notes]" value="<?php echo e($l['notes'] ?? ''); ?>"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="LV.remove(this,'lines')"><i class="bi bi-x"></i></button></td>
<?php if(!$tpl): ?></tr><?php endif; ?>
<?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/po/line.blade.php ENDPATH**/ ?>