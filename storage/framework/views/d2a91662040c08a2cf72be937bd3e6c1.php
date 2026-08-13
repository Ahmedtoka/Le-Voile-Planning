<?php $__env->startPush('scripts'); ?>
<script>
/* إدارة سطور المستندات — إضافة/حذف صف بنفس الطريقة في كل الشاشات */
window.LV = (function () {
  let idx = <?php echo e($startIndex ?? 0); ?>;

  function add(tplId, bodyId) {
    const tpl  = document.getElementById(tplId);
    const body = document.getElementById(bodyId);
    const html = tpl.innerHTML.replace(/__IDX__/g, idx++);
    const tr   = document.createElement('tr');
    tr.innerHTML = html;
    body.appendChild(tr);
    renumber(bodyId);
    return tr;
  }

  function remove(btn, bodyId) {
    const body = document.getElementById(bodyId);
    if (body.querySelectorAll('tr').length <= 1) {
      btn.closest('tr').querySelectorAll('input,select,textarea').forEach(el => {
        if (el.type !== 'hidden') el.value = '';
      });
      return;
    }
    btn.closest('tr').remove();
    renumber(bodyId);
  }

  function renumber(bodyId) {
    document.querySelectorAll('#' + bodyId + ' .row-no').forEach((el, i) => el.textContent = i + 1);
  }

  return { add, remove, renumber };
})();
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\LvPlanning\resources\views/partials/lines_js.blade.php ENDPATH**/ ?>