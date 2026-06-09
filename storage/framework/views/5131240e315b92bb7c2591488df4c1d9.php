<?php $__env->startSection('code', '500'); ?>
<?php $__env->startSection('title', __('Something Went Wrong')); ?>
<?php $__env->startSection('message', __('An unexpected error occurred. Our team has been notified and is investigating.')); ?>
<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>"><?php echo e(__('Return home')); ?></a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\aeos365\resources\views\errors\500.blade.php ENDPATH**/ ?>