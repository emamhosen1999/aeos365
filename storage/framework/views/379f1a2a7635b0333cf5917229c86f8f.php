<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice <?php echo e($invoice->reference ?? $invoice->invoice_number); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .header { background: #1e40af; color: #fff; padding: 24px 32px; }
        .header h1 { font-size: 24px; font-weight: 700; }
        .header p { font-size: 11px; opacity: 0.8; margin-top: 4px; }
        .container { padding: 24px 32px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .col { width: 48%; }
        .label { font-size: 10px; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        .value { font-size: 13px; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-issued { background: #dbeafe; color: #1e40af; }
        .badge-void { background: #f3f4f6; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        thead tr { background: #f9fafb; }
        thead th { padding: 8px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
        .text-right { text-align: right; }
        .totals { margin-left: auto; width: 280px; }
        .totals tr td { padding: 6px 12px; }
        .totals tr.total td { font-size: 14px; font-weight: 700; border-top: 2px solid #1e40af; color: #1e40af; }
        .footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1>INVOICE</h1>
    <p><?php echo e(config('app.name')); ?></p>
</div>

<div class="container">

    <div class="row" style="margin-top: 20px;">
        <div class="col">
            <div class="label">Invoice Number</div>
            <div class="value"><?php echo e($invoice->reference ?? $invoice->invoice_number); ?></div>

            <div class="label" style="margin-top:10px;">Status</div>
            <div>
                <?php
                    $badgeClass = match($invoice->status) {
                        'paid'   => 'badge-paid',
                        'issued' => 'badge-issued',
                        default  => 'badge-void',
                    };
                ?>
                <span class="badge <?php echo e($badgeClass); ?>"><?php echo e(strtoupper($invoice->status)); ?></span>
            </div>
        </div>
        <div class="col" style="text-align:right;">
            <div class="label">Issue Date</div>
            <div class="value"><?php echo e($invoice->created_at?->format('d M Y')); ?></div>

            <?php if($invoice->due_date): ?>
            <div class="label" style="margin-top:10px;">Due Date</div>
            <div class="value"><?php echo e(\Carbon\Carbon::parse($invoice->due_date)->format('d M Y')); ?></div>
            <?php endif; ?>

            <?php if($invoice->paid_at): ?>
            <div class="label" style="margin-top:10px;">Paid On</div>
            <div class="value"><?php echo e($invoice->paid_at->format('d M Y')); ?></div>
            <?php endif; ?>
        </div>
    </div>

    
    <?php if($invoice->billable): ?>
    <div style="margin-bottom:20px;">
        <div class="label">Billed To</div>
        <div class="value"><?php echo e($invoice->billable->name ?? $invoice->billable->id); ?></div>
        <?php if(isset($invoice->billable->email)): ?>
        <div style="color:#6b7280; font-size:11px;"><?php echo e($invoice->billable->email); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <?php if($invoice->relationLoaded('lineItems') && $invoice->lineItems->isNotEmpty()): ?>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $invoice->lineItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($item->description); ?></td>
                <td class="text-right"><?php echo e($item->quantity ?? 1); ?></td>
                <td class="text-right"><?php echo e(number_format((float)($item->unit_price ?? 0), 2)); ?></td>
                <td class="text-right"><?php echo e(number_format((float)($item->total ?? $item->amount ?? 0), 2)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>

    
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right"><?php echo e($invoice->currency ?? 'USD'); ?> <?php echo e(number_format((float)($invoice->subtotal ?? $invoice->amount ?? 0), 2)); ?></td>
        </tr>
        <?php if((float)($invoice->discount_amount ?? 0) > 0): ?>
        <tr>
            <td>Discount</td>
            <td class="text-right">- <?php echo e($invoice->currency ?? 'USD'); ?> <?php echo e(number_format((float)$invoice->discount_amount, 2)); ?></td>
        </tr>
        <?php endif; ?>
        <?php if((float)($invoice->tax_amount ?? 0) > 0): ?>
        <tr>
            <td>Tax</td>
            <td class="text-right"><?php echo e($invoice->currency ?? 'USD'); ?> <?php echo e(number_format((float)$invoice->tax_amount, 2)); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total">
            <td>Total Due</td>
            <td class="text-right"><?php echo e($invoice->currency ?? 'USD'); ?> <?php echo e(number_format((float)($invoice->total ?? $invoice->amount ?? 0), 2)); ?></td>
        </tr>
    </table>

    <?php if($invoice->notes): ?>
    <div style="margin-top:20px;">
        <div class="label">Notes</div>
        <div style="color:#4b5563; font-size:11px;"><?php echo e($invoice->notes); ?></div>
    </div>
    <?php endif; ?>

</div>

<div class="footer">
    Generated by <?php echo e(config('app.name')); ?> &bull; <?php echo e(now()->format('d M Y H:i')); ?> UTC
</div>

</body>
</html>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\invoices\pdf.blade.php ENDPATH**/ ?>