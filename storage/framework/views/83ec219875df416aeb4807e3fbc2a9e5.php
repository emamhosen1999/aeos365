<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo e($receipt['receipt_number'] ?? $receipt['invoice_number'] ?? ''); ?></title>
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
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Header */
        .header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            margin-bottom: 30px;
        }

        .logo {
            max-height: 50px;
            max-width: 180px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Receipt Title */
        .receipt-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .receipt-title h1 {
            font-size: 24px;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }

        .receipt-number {
            font-size: 12px;
            color: #6b7280;
        }

        /* Success Badge */
        .success-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 15px 0;
        }

        /* Info Grid */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }

        .info-box {
            display: table-cell;
            width: 50%;
            padding: 15px;
            background: #f9fafb;
            vertical-align: top;
        }

        .info-box:first-child {
            border-right: 1px solid #e5e7eb;
        }

        .info-label {
            font-size: 10px;
            font-weight: bold;
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 11px;
            color: #374151;
            line-height: 1.6;
        }

        .info-value strong {
            display: block;
            font-size: 13px;
            margin-bottom: 3px;
        }

        /* Payment Details */
        .payment-details {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .payment-header {
            background: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
            color: white;
            padding: 12px 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .payment-row {
            display: table;
            width: 100%;
            border-bottom: 1px solid #e5e7eb;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-row-label,
        .payment-row-value {
            display: table-cell;
            padding: 12px 20px;
            font-size: 11px;
        }

        .payment-row-label {
            color: #6b7280;
            width: 40%;
        }

        .payment-row-value {
            font-weight: 500;
            color: #111827;
            text-align: right;
        }

        .payment-row.total {
            background: #f9fafb;
        }

        .payment-row.total .payment-row-label,
        .payment-row.total .payment-row-value {
            font-weight: bold;
            font-size: 14px;
            padding: 15px 20px;
        }

        .payment-row.total .payment-row-value {
            color: <?php echo e($branding['primary_color'] ?? '#2563eb'); ?>;
        }

        /* Items Summary */
        .items-summary {
            margin-bottom: 25px;
        }

        .items-title {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .item-row {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-desc,
        .item-amount {
            display: table-cell;
            font-size: 11px;
        }

        .item-desc {
            color: #4b5563;
        }

        .item-amount {
            text-align: right;
            font-weight: 500;
            color: #111827;
        }

        /* Transaction Info */
        .transaction-info {
            background: #f9fafb;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 25px;
            font-size: 10px;
            color: #6b7280;
        }

        .transaction-info strong {
            color: #374151;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-message {
            font-size: 12px;
            color: #111827;
            margin-bottom: 8px;
        }

        .footer-contact {
            font-size: 10px;
            color: #9ca3af;
        }

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
            <?php if(!empty($branding['logo_url'])): ?>
                <img src="<?php echo e($branding['logo_url']); ?>" alt="<?php echo e($branding['company_name'] ?? 'Company'); ?>" class="logo"><br>
            <?php else: ?>
                <div class="company-name"><?php echo e($branding['company_name'] ?? config('app.name')); ?></div>
            <?php endif; ?>
            <div class="company-details">
                <?php if(!empty($branding['address'])): ?>
                    <?php echo e($branding['address']); ?><br>
                <?php endif; ?>
                <?php if(!empty($branding['phone'])): ?>
                    <?php echo e($branding['phone']); ?>

                <?php endif; ?>
                <?php if(!empty($branding['email'])): ?>
                    <?php if(!empty($branding['phone'])): ?> | <?php endif; ?>
                    <?php echo e($branding['email']); ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- Receipt Title -->
        <div class="receipt-title">
            <h1>Payment Receipt</h1>
            <div class="receipt-number">
                Receipt #<?php echo e($receipt['receipt_number'] ?? $receipt['invoice_number'] ?? ''); ?>

            </div>
            <span class="success-badge">✓ Payment Successful</span>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Date & Time</div>
                <div class="info-value">
                    <strong><?php echo e(\Carbon\Carbon::parse($receipt['payment_date'] ?? $receipt['created_at'] ?? now())->format('F d, Y')); ?></strong>
                    <?php echo e(\Carbon\Carbon::parse($receipt['payment_date'] ?? $receipt['created_at'] ?? now())->format('h:i A')); ?>

                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Received From</div>
                <div class="info-value">
                    <strong><?php echo e($receipt['customer']['name'] ?? 'Customer'); ?></strong>
                    <?php echo e($receipt['customer']['email'] ?? ''); ?>

                </div>
            </div>
        </div>

        <!-- Items Summary (if present) -->
        <?php if(!empty($receipt['items']) && count($receipt['items']) > 0): ?>
        <div class="items-summary">
            <div class="items-title">Items</div>
            <?php $__currentLoopData = $receipt['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="item-row">
                <span class="item-desc">
                    <?php echo e($item['name'] ?? $item['description'] ?? 'Item'); ?>

                    <?php if(($item['quantity'] ?? 1) > 1): ?>
                        × <?php echo e($item['quantity']); ?>

                    <?php endif; ?>
                </span>
                <span class="item-amount">
                    <?php echo e($receipt['currency_symbol'] ?? '$'); ?><?php echo e(number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? $item['amount'] ?? 0), 2)); ?>

                </span>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>

        <!-- Payment Details -->
        <div class="payment-details">
            <div class="payment-header">Payment Details</div>
            
            <?php if(!empty($receipt['invoice_number'])): ?>
            <div class="payment-row">
                <span class="payment-row-label">Invoice Number</span>
                <span class="payment-row-value">#<?php echo e($receipt['invoice_number']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="payment-row">
                <span class="payment-row-label">Payment Method</span>
                <span class="payment-row-value"><?php echo e(ucfirst($receipt['payment_method'] ?? 'Card')); ?></span>
            </div>
            
            <?php if(!empty($receipt['card_last_four'])): ?>
            <div class="payment-row">
                <span class="payment-row-label">Card</span>
                <span class="payment-row-value">•••• <?php echo e($receipt['card_last_four']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($receipt['subtotal'])): ?>
            <div class="payment-row">
                <span class="payment-row-label">Subtotal</span>
                <span class="payment-row-value"><?php echo e($receipt['currency_symbol'] ?? '$'); ?><?php echo e(number_format($receipt['subtotal'], 2)); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($receipt['tax']) && $receipt['tax'] > 0): ?>
            <div class="payment-row">
                <span class="payment-row-label">Tax</span>
                <span class="payment-row-value"><?php echo e($receipt['currency_symbol'] ?? '$'); ?><?php echo e(number_format($receipt['tax'], 2)); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($receipt['discount']) && $receipt['discount'] > 0): ?>
            <div class="payment-row">
                <span class="payment-row-label">Discount</span>
                <span class="payment-row-value">-<?php echo e($receipt['currency_symbol'] ?? '$'); ?><?php echo e(number_format($receipt['discount'], 2)); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="payment-row total">
                <span class="payment-row-label">Amount Paid</span>
                <span class="payment-row-value"><?php echo e($receipt['currency_symbol'] ?? '$'); ?><?php echo e(number_format($receipt['amount'] ?? $receipt['total'] ?? 0, 2)); ?></span>
            </div>
        </div>

        <!-- Transaction Info -->
        <?php if(!empty($receipt['transaction_id'])): ?>
        <div class="transaction-info">
            <strong>Transaction ID:</strong> <?php echo e($receipt['transaction_id']); ?><br>
            <?php if(!empty($receipt['reference_id'])): ?>
            <strong>Reference:</strong> <?php echo e($receipt['reference_id']); ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-message">
                <?php echo e($branding['thank_you_message'] ?? 'Thank you for your payment!'); ?>

            </div>
            <div class="footer-contact">
                <?php if(!empty($branding['support_email'])): ?>
                    Questions? Contact <?php echo e($branding['support_email']); ?>

                <?php elseif(!empty($branding['email'])): ?>
                    Questions? Contact <?php echo e($branding['email']); ?>

                <?php endif; ?>
                <br>
                <?php if(!empty($branding['website'])): ?>
                    <?php echo e($branding['website']); ?>

                <?php endif; ?>
            </div>
            <?php if(!empty($branding['footer_text'])): ?>
            <div style="margin-top: 15px; font-size: 9px; color: #9ca3af;">
                <?php echo e($branding['footer_text']); ?>

            </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\invoices\receipt.blade.php ENDPATH**/ ?>