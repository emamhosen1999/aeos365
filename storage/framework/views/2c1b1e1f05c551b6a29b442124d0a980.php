<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo e($invoice['invoice_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333333;
            background: #ffffff;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Header Section */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }

        .logo-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .logo {
            max-height: 60px;
            max-width: 200px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 10px;
            color: #666666;
            line-height: 1.6;
        }

        .invoice-info-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .invoice-number {
            font-size: 14px;
            color: #666666;
            margin-bottom: 5px;
        }

        .invoice-date {
            font-size: 11px;
            color: #888888;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .status-paid {
            background: #10b981;
            color: white;
        }

        .status-pending {
            background: #f59e0b;
            color: white;
        }

        .status-overdue {
            background: #ef4444;
            color: white;
        }

        .status-draft {
            background: #6b7280;
            color: white;
        }

        /* Billing Section */
        .billing-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .bill-from,
        .bill-to {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .bill-from {
            border-right: none;
        }

        .billing-label {
            font-size: 10px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .billing-name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .billing-address {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table thead th {
            background: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table thead th:last-child {
            text-align: right;
        }

        .items-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }

        .items-table tbody td:last-child {
            text-align: right;
            font-weight: 500;
        }

        .item-name {
            font-weight: 600;
            color: #111827;
        }

        .item-description {
            font-size: 10px;
            color: #6b7280;
            margin-top: 3px;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        /* Totals Section */
        .totals-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .notes-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 30px;
        }

        .totals-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table tr td {
            padding: 8px 15px;
            font-size: 11px;
        }

        .totals-table tr td:first-child {
            text-align: right;
            color: #6b7280;
        }

        .totals-table tr td:last-child {
            text-align: right;
            font-weight: 500;
            width: 120px;
        }

        .totals-table tr.subtotal {
            border-top: 1px solid #e5e7eb;
        }

        .totals-table tr.tax {
            color: #6b7280;
        }

        .totals-table tr.total {
            background: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            color: white;
        }

        .totals-table tr.total td {
            padding: 12px 15px;
            font-size: 14px;
            font-weight: bold;
        }

        .totals-table tr.amount-due {
            background: #fef3c7;
        }

        .totals-table tr.amount-due td {
            font-weight: bold;
            color: #92400e;
        }

        /* Notes */
        .notes-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .notes-content {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Payment Section */
        .payment-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }

        .payment-title {
            font-size: 12px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            margin-bottom: 10px;
        }

        .payment-details {
            display: table;
            width: 100%;
        }

        .payment-item {
            display: table-row;
        }

        .payment-label,
        .payment-value {
            display: table-cell;
            padding: 5px 0;
            font-size: 11px;
        }

        .payment-label {
            color: #6b7280;
            width: 150px;
        }

        .payment-value {
            font-weight: 500;
            color: #111827;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-message {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .footer-contact {
            font-size: 10px;
            color: #9ca3af;
        }

        <?php if(!empty($branding['footer_text'])): ?>
        .custom-footer {
            font-size: 10px;
            color: #6b7280;
            margin-top: 15px;
            font-style: italic;
        }
        <?php endif; ?>

        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .container {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <?php if(!empty($branding['logo_url'])): ?>
                    <img src="<?php echo e($branding['logo_url']); ?>" alt="<?php echo e($branding['company_name'] ?? 'Company'); ?>" class="logo">
                <?php else: ?>
                    <div class="company-name"><?php echo e($branding['company_name'] ?? config('app.name')); ?></div>
                <?php endif; ?>
                <div class="company-details">
                    <?php if(!empty($branding['address'])): ?>
                        <?php echo e($branding['address']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($branding['phone'])): ?>
                        Tel: <?php echo e($branding['phone']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($branding['email'])): ?>
                        <?php echo e($branding['email']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($branding['website'])): ?>
                        <?php echo e($branding['website']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($branding['tax_id'])): ?>
                        Tax ID: <?php echo e($branding['tax_id']); ?>

                    <?php endif; ?>
                </div>
            </div>
            <div class="invoice-info-section">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-number">#<?php echo e($invoice['invoice_number']); ?></div>
                <div class="invoice-date">
                    Date: <?php echo e(\Carbon\Carbon::parse($invoice['issue_date'])->format('M d, Y')); ?><br>
                    Due: <?php echo e(\Carbon\Carbon::parse($invoice['due_date'])->format('M d, Y')); ?>

                </div>
                <span class="status-badge status-<?php echo e(strtolower($invoice['status'] ?? 'pending')); ?>">
                    <?php echo e(ucfirst($invoice['status'] ?? 'Pending')); ?>

                </span>
            </div>
        </div>

        <!-- Billing Information -->
        <div class="billing-section">
            <div class="bill-from">
                <div class="billing-label">From</div>
                <div class="billing-name"><?php echo e($branding['company_name'] ?? config('app.name')); ?></div>
                <div class="billing-address">
                    <?php if(!empty($branding['address'])): ?>
                        <?php echo nl2br(e($branding['address'])); ?>

                    <?php endif; ?>
                </div>
            </div>
            <div class="bill-to">
                <div class="billing-label">Bill To</div>
                <div class="billing-name"><?php echo e($invoice['customer']['name'] ?? 'Customer'); ?></div>
                <div class="billing-address">
                    <?php if(!empty($invoice['customer']['company'])): ?>
                        <?php echo e($invoice['customer']['company']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($invoice['customer']['address'])): ?>
                        <?php echo nl2br(e($invoice['customer']['address'])); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($invoice['customer']['email'])): ?>
                        <?php echo e($invoice['customer']['email']); ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%;">Description</th>
                    <th style="width: 15%; text-align: center;">Quantity</th>
                    <th style="width: 20%; text-align: right;">Unit Price</th>
                    <th style="width: 20%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $invoice['items'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <div class="item-name"><?php echo e($item['name'] ?? $item['description'] ?? ''); ?></div>
                        <?php if(!empty($item['description']) && !empty($item['name'])): ?>
                            <div class="item-description"><?php echo e($item['description']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?php echo e($item['quantity'] ?? 1); ?></td>
                    <td style="text-align: right;"><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($item['unit_price'] ?? $item['amount'] ?? 0, 2)); ?></td>
                    <td style="text-align: right;"><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? $item['amount'] ?? 0), 2)); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <!-- Totals and Notes -->
        <div class="totals-section">
            <div class="notes-section">
                <?php if(!empty($invoice['notes'])): ?>
                    <div class="notes-title">Notes</div>
                    <div class="notes-content"><?php echo nl2br(e($invoice['notes'])); ?></div>
                <?php endif; ?>

                <?php if(!empty($invoice['terms'])): ?>
                    <div class="notes-title" style="margin-top: 15px;">Terms & Conditions</div>
                    <div class="notes-content"><?php echo nl2br(e($invoice['terms'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="totals-box">
                <table class="totals-table">
                    <tr class="subtotal">
                        <td>Subtotal</td>
                        <td><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($invoice['subtotal'] ?? 0, 2)); ?></td>
                    </tr>
                    <?php if(!empty($invoice['discount']) && $invoice['discount'] > 0): ?>
                    <tr>
                        <td>Discount<?php echo e(!empty($invoice['discount_percentage']) ? ' ('.$invoice['discount_percentage'].'%)' : ''); ?></td>
                        <td>-<?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($invoice['discount'], 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if(!empty($invoice['tax']) && $invoice['tax'] > 0): ?>
                    <tr class="tax">
                        <td>Tax<?php echo e(!empty($invoice['tax_rate']) ? ' ('.$invoice['tax_rate'].'%)' : ''); ?></td>
                        <td><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($invoice['tax'], 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total">
                        <td>Total</td>
                        <td><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($invoice['total'] ?? 0, 2)); ?></td>
                    </tr>
                    <?php if(!empty($invoice['amount_paid']) && $invoice['amount_paid'] > 0): ?>
                    <tr>
                        <td>Amount Paid</td>
                        <td>-<?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format($invoice['amount_paid'], 2)); ?></td>
                    </tr>
                    <tr class="amount-due">
                        <td>Amount Due</td>
                        <td><?php echo e($invoice['currency_symbol'] ?? '$'); ?><?php echo e(number_format(($invoice['total'] ?? 0) - ($invoice['amount_paid'] ?? 0), 2)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Payment Information -->
        <?php if(!empty($branding['payment_instructions']) || !empty($invoice['payment_method'])): ?>
        <div class="payment-section">
            <div class="payment-title">Payment Information</div>
            <div class="payment-details">
                <?php if(!empty($invoice['payment_method'])): ?>
                <div class="payment-item">
                    <span class="payment-label">Payment Method:</span>
                    <span class="payment-value"><?php echo e($invoice['payment_method']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($branding['bank_name'])): ?>
                <div class="payment-item">
                    <span class="payment-label">Bank:</span>
                    <span class="payment-value"><?php echo e($branding['bank_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($branding['account_number'])): ?>
                <div class="payment-item">
                    <span class="payment-label">Account Number:</span>
                    <span class="payment-value"><?php echo e($branding['account_number']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($branding['routing_number'])): ?>
                <div class="payment-item">
                    <span class="payment-label">Routing Number:</span>
                    <span class="payment-value"><?php echo e($branding['routing_number']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($branding['swift_code'])): ?>
                <div class="payment-item">
                    <span class="payment-label">SWIFT Code:</span>
                    <span class="payment-value"><?php echo e($branding['swift_code']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php if(!empty($branding['payment_instructions'])): ?>
            <div style="margin-top: 10px; font-size: 10px; color: #6b7280;">
                <?php echo nl2br(e($branding['payment_instructions'])); ?>

            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-message">
                <?php echo e($branding['thank_you_message'] ?? 'Thank you for your business!'); ?>

            </div>
            <div class="footer-contact">
                <?php if(!empty($branding['support_email'])): ?>
                    Questions? Contact us at <?php echo e($branding['support_email']); ?>

                <?php elseif(!empty($branding['email'])): ?>
                    Questions? Contact us at <?php echo e($branding['email']); ?>

                <?php endif; ?>
            </div>
            <?php if(!empty($branding['footer_text'])): ?>
            <div class="custom-footer">
                <?php echo e($branding['footer_text']); ?>

            </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\invoices\branded.blade.php ENDPATH**/ ?>