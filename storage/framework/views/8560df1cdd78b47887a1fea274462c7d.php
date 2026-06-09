<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: left;
        }

        th {
            background: #428bca;
            color: #fff;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        .meta {
            text-align: center;
            font-size: 10px;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="title"><?php echo e($title); ?></div>
    <div class="meta">Generated on: <?php echo e($generatedOn); ?></div>
    <table>
        <thead>
            <tr>
                <?php $__currentLoopData = array_keys($rows->first()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <th><?php echo e($col); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr style="background: <?php echo e(stripos($row['Status'],'Absent')!==false || stripos($row['Status'],'Leave')!==false ? '#FFEBEE':'transparent'); ?>; color: <?php echo e(stripos($row['Status'],'Absent')!==false||stripos($row['Status'],'Leave')!==false? '#D32F2F':'#000'); ?>;"
                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <td><?php echo e($cell); ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>

</html><?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\attendance_pdf.blade.php ENDPATH**/ ?>