<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo e($employee['name']); ?> - <?php echo e($payroll['pay_period']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 10px;
            color: #666;
        }

        .payslip-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            text-align: center;
            background: #f8fafc;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }

        .employee-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }

        .info-title {
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            min-width: 120px;
        }

        .salary-breakdown {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .earnings-section,
        .deductions-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .section-title {
            background: #2563eb;
            color: white;
            font-weight: bold;
            padding: 10px;
            text-align: center;
        }

        .section-content {
            border: 1px solid #e2e8f0;
            border-top: none;
            min-height: 200px;
        }

        .salary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .salary-item:last-child {
            border-bottom: none;
        }

        .salary-item.total {
            background: #f8fafc;
            font-weight: bold;
            border-top: 2px solid #2563eb;
        }

        .summary {
            margin-top: 20px;
            background: #f8fafc;
            border: 2px solid #2563eb;
            padding: 15px;
        }

        .summary-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            color: #2563eb;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .summary-row.net-salary {
            font-size: 16px;
            font-weight: bold;
            color: #059669;
            border-top: 2px solid #2563eb;
            padding-top: 10px;
            margin-top: 10px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }

        .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name"><?php echo e($company['name']); ?></div>
            <div class="company-details">
                <?php echo e($company['address']); ?><br>
                <?php echo e($company['city']); ?><br>
                Phone: <?php echo e($company['phone']); ?> | Email: <?php echo e($company['email']); ?>

            </div>
        </div>

        <div class="payslip-title">
            PAYSLIP FOR <?php echo e(strtoupper($payroll['pay_period'])); ?>

        </div>

        <!-- Employee Information -->
        <div class="employee-info">
            <div class="info-section">
                <div class="info-title">Employee Information</div>
                <div class="info-row">
                    <span class="info-label">Employee Name:</span>
                    <span><?php echo e($employee['name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee ID:</span>
                    <span><?php echo e($employee['employee_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Designation:</span>
                    <span><?php echo e($employee['designation']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span><?php echo e($employee['department']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Joining:</span>
                    <span><?php echo e($employee['join_date']); ?></span>
                </div>
            </div>

            <div class="info-section">
                <div class="info-title">Pay Period Information</div>
                <div class="info-row">
                    <span class="info-label">Pay Period:</span>
                    <span><?php echo e($payroll['pay_period_start']); ?> to <?php echo e($payroll['pay_period_end']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Working Days:</span>
                    <span><?php echo e($payroll['working_days']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Present Days:</span>
                    <span><?php echo e($payroll['present_days']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Absent Days:</span>
                    <span><?php echo e($payroll['absent_days']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Leave Days:</span>
                    <span><?php echo e($payroll['leave_days']); ?></span>
                </div>
                <?php if($payroll['overtime_hours'] > 0): ?>
                <div class="info-row">
                    <span class="info-label">Overtime Hours:</span>
                    <span><?php echo e($payroll['overtime_hours']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="salary-breakdown">
            <!-- Earnings -->
            <div class="earnings-section">
                <div class="section-title">EARNINGS</div>
                <div class="section-content">
                    <?php
                    $totalEarnings = 0;
                    ?>
                    <?php $__currentLoopData = $earnings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $earning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                    $totalEarnings += $earning['amount'];
                    ?>
                    <div class="salary-item">
                        <span><?php echo e($earning['description']); ?></span>
                        <span class="amount"><?php echo e(number_format($earning['amount'], 2)); ?></span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="salary-item total">
                        <span>TOTAL EARNINGS</span>
                        <span class="amount"><?php echo e(number_format($totalEarnings, 2)); ?></span>
                    </div>
                </div>
            </div>

            <!-- Deductions -->
            <div class="deductions-section">
                <div class="section-title">DEDUCTIONS</div>
                <div class="section-content">
                    <?php
                    $totalDeductions = 0;
                    ?>
                    <?php $__currentLoopData = $deductions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $deduction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                    $totalDeductions += $deduction['amount'];
                    ?>
                    <div class="salary-item">
                        <span><?php echo e($deduction['description']); ?></span>
                        <span class="amount"><?php echo e(number_format($deduction['amount'], 2)); ?></span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="salary-item total">
                        <span>TOTAL DEDUCTIONS</span>
                        <span class="amount"><?php echo e(number_format($totalDeductions, 2)); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-title">SALARY SUMMARY</div>
            <div class="summary-row">
                <span>Gross Salary:</span>
                <span class="amount">₹ <?php echo e(number_format($summary['gross_salary'], 2)); ?></span>
            </div>
            <div class="summary-row">
                <span>Total Deductions:</span>
                <span class="amount">₹ <?php echo e(number_format($summary['total_deductions'], 2)); ?></span>
            </div>
            <div class="summary-row net-salary">
                <span>Net Salary:</span>
                <span class="amount">₹ <?php echo e(number_format($summary['net_salary'], 2)); ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Note:</strong> This is a computer-generated payslip and does not require a signature.</p>
            <p>Generated on: <?php echo e($generated_date); ?></p>
            <p>For any queries regarding this payslip, please contact HR Department.</p>
        </div>
    </div>
</body>

</html><?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\payslips\template.blade.php ENDPATH**/ ?>