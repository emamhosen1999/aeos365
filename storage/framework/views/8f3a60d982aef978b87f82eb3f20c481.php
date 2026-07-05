<?php $__env->startSection('code', '404'); ?>
<?php $__env->startSection('title', __('Page Not Found')); ?>
<?php $__env->startSection('message', __('The page you\'re looking for doesn\'t exist or has been moved.')); ?>
<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>"><?php echo e(__('Return home')); ?></a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\aeos365\resources\views/errors/404.blade.php ENDPATH**/ ?>