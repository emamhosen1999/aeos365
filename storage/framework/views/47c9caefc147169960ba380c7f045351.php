<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title><?php echo e(config('app.name')); ?> Digest</title></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2><?php echo e(config('app.name')); ?> <?php echo e(ucfirst($digest['frequency'])); ?> Digest</h2>
<p>Period: <?php echo e($digest['period_start']); ?> - <?php echo e($digest['period_end']); ?></p>
<p>Total: <?php echo e($digest['total_items']); ?> notifications</p>
<?php if($digest['total_items'] === 0): ?>
<p>No new notifications.</p>
<?php else: ?>
<?php $__currentLoopData = $digest['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<h3><?php echo e(ucfirst($category)); ?></h3>
<ul>
<?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<li><?php echo e($item['title'] ?? 'Notification'); ?> - <?php echo e($item['created_at'] ?? ''); ?></li>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
<p><a href="<?php echo e(url('/')); ?>">View Dashboard</a></p>
</body>
</html>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\emails\digest.blade.php ENDPATH**/ ?>