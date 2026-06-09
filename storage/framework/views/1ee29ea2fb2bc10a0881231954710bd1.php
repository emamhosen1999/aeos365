<?php $__env->startSection('code', '429'); ?>
<?php $__env->startSection('title', __('Too Many Requests')); ?>
<?php $__env->startSection('message', __('You\'ve made too many requests in a short window. Please wait a moment and try again.')); ?>
<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>"><?php echo e(__('Return home')); ?></a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\aeos365\resources\views\errors\429.blade.php ENDPATH**/ ?>