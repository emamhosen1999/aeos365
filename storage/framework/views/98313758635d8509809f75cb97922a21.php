<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 3px;
            text-align: center;
        }

        th {
            background: #428bca;
            color: #fff;
            font-weight: bold;
            font-size: 7px;
        }

        td {
            font-size: 7px;
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

        .summary {
            text-align: center;
            font-size: 9px;
            margin-top: 8px;
            font-weight: bold;
        }

        .employee-name {
            text-align: left !important;
            width: 120px;
            max-width: 120px;
        }

        .department {
            text-align: left !important;
            width: 80px;
            max-width: 80px;
        }

        .month-col {
            width: 25px;
            max-width: 25px;
        }

        .total-col {
            width: 35px;
            max-width: 35px;
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="title"><?php echo e($title); ?></div>
    <div class="meta">Generated on: <?php echo e($generatedOn); ?></div>
    
    <?php if(isset($summaryData['stats'])): ?>
    <div class="summary">
        Total Employees: <?php echo e($summaryData['stats']['total_employees'] ?? 0); ?> | 
        Approved Leaves: <?php echo e($summaryData['stats']['total_approved_leaves'] ?? 0); ?> | 
        Pending Leaves: <?php echo e($summaryData['stats']['total_pending_leaves'] ?? 0); ?> | 
        Departments: <?php echo e($summaryData['stats']['departments_count'] ?? 0); ?>

    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th class="employee-name">Employee</th>
                <th class="department">Department</th>
                <th class="month-col">Jan</th>
                <th class="month-col">Feb</th>
                <th class="month-col">Mar</th>
                <th class="month-col">Apr</th>
                <th class="month-col">May</th>
                <th class="month-col">Jun</th>
                <th class="month-col">Jul</th>
                <th class="month-col">Aug</th>
                <th class="month-col">Sep</th>
                <th class="month-col">Oct</th>
                <th class="month-col">Nov</th>
                <th class="month-col">Dec</th>
                <th class="total-col">Approved</th>
                <th class="total-col">Pending</th>
                <?php if(isset($summaryData['leave_types'])): ?>
                    <?php $__currentLoopData = $summaryData['leave_types']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leaveType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th class="total-col"><?php echo e($leaveType->type); ?> Used</th>
                        <th class="total-col"><?php echo e($leaveType->type); ?> Rem.</th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
                <th class="total-col">Balance</th>
                <th class="total-col">Usage %</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $summaryData['data'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="employee-name"><?php echo e($employee['employee_name'] ?? 'N/A'); ?></td>
                <td class="department"><?php echo e($employee['department'] ?? 'N/A'); ?></td>
                <td class="month-col"><?php echo e($employee['JAN'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['FEB'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['MAR'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['APR'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['MAY'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['JUN'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['JUL'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['AUG'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['SEP'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['OCT'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['NOV'] ?? ''); ?></td>
                <td class="month-col"><?php echo e($employee['DEC'] ?? ''); ?></td>
                <td class="total-col"><?php echo e($employee['total_approved'] ?? 0); ?></td>
                <td class="total-col"><?php echo e($employee['total_pending'] ?? 0); ?></td>
                <?php if(isset($summaryData['leave_types'])): ?>
                    <?php $__currentLoopData = $summaryData['leave_types']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leaveType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td class="total-col"><?php echo e($employee[$leaveType->type.'_used'] ?? 0); ?></td>
                        <td class="total-col"><?php echo e($employee[$leaveType->type.'_remaining'] ?? 0); ?></td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
                <td class="total-col"><?php echo e($employee['total_balance'] ?? 0); ?></td>
                <td class="total-col"><?php echo e(($employee['usage_percentage'] ?? 0)); ?>%</td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <?php if(isset($summaryData['department_summary']) && count($summaryData['department_summary']) > 0): ?>
    <div style="margin-top: 30px;">
        <div class="title" style="font-size: 14px;">Department Summary</div>
        <table style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="text-align: left; width: 150px;">Department</th>
                    <th style="width: 80px;">Employees</th>
                    <th style="width: 80px;">Total Leaves</th>
                    <th style="width: 80px;">Approved</th>
                    <th style="width: 80px;">Pending</th>
                    <th style="width: 100px;">Avg per Employee</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $summaryData['department_summary']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td style="text-align: left;"><?php echo e($dept['department'] ?? 'N/A'); ?></td>
                    <td><?php echo e($dept['employee_count'] ?? 0); ?></td>
                    <td><?php echo e($dept['total_leaves'] ?? 0); ?></td>
                    <td><?php echo e($dept['total_approved'] ?? 0); ?></td>
                    <td><?php echo e($dept['total_pending'] ?? 0); ?></td>
                    <td><?php echo e($dept['avg_leaves_per_employee'] ?? 0); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</body>

</html>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\leave_summary_pdf.blade.php ENDPATH**/ ?>