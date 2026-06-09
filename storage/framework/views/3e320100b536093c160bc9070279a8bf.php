<!DOCTYPE html>
<html>

<head>
    <title>Attendance PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
        }

        th {
            background-color: #eee;
        }
    </style>
</head>

<body>
    <h3 style="text-align:center">DBEDC Site Office Attendance - <?php echo e($monthName); ?></h3>
    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Name</th>
                <?php for($d = $from->day; $d <= $to->day; $d++): ?>
                    <th><?php echo e($d); ?></th>
                    <?php endfor; ?>
                    <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th><?php echo e($type->type); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($index + 1); ?></td>
                <td><?php echo e($user->name); ?></td>
                <?php for($d = $from->day; $d <= $to->day; $d++): ?>
                    <?php
                    $date = \Carbon\Carbon::create($from->year, $from->month, $d)->toDateString();
                    $status = $attendanceData[$index][$date]['status'] ?? '#';
                    ?>
                    <td><?php echo e($status); ?></td>
                    <?php endfor; ?>
                    <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                    $count = collect($attendanceData[$index])
                    ->where('remarks', 'On Leave')
                    ->where('status', $type->symbol)
                    ->count();
                    ?>
                    <td><?php echo e($count > 0 ? $count : '-'); ?></td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <td></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>

</html><?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\attendance_admin_pdf.blade.php ENDPATH**/ ?>