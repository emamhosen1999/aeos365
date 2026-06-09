<?php $__env->startSection('code', '419'); ?>
<?php $__env->startSection('title', __('Page Expired')); ?>
<?php $__env->startSection('message', __('Your session has expired due to inactivity. Please refresh the page and try again.')); ?>
<?php $__env->startSection('actions'); ?>
    <a href="javascript:window.location.reload()"><?php echo e(__('Refresh page')); ?></a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\aeos365\resources\views\errors\419.blade.php ENDPATH**/ ?>