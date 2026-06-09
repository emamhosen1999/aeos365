<?php $__env->startSection('code', '403'); ?>
<?php $__env->startSection('title', __('Access Denied')); ?>
<?php $__env->startSection('message', __('You don\'t have permission to access this resource. If you believe this is a mistake, contact your administrator.')); ?>
<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>"><?php echo e(__('Return home')); ?></a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\aeos365\resources\views\errors\403.blade.php ENDPATH**/ ?>