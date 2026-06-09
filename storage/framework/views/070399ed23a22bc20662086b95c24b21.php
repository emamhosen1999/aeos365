<?php $__env->startComponent('mail::message'); ?>
# Secure Password Reset Request

Hello <?php echo e($user->name); ?>,

We received a request to reset your password for your <?php echo e(config('app.name')); ?> account.

## Security Details
- **Request Time:** <?php echo e($timestamp); ?>

- **IP Address:** <?php echo e($ipAddress); ?>

- **Location:** <?php echo e($location ?? 'Unknown'); ?>

- **Browser:** <?php echo e($userAgent); ?>


## One-Time Password (OTP)

Your secure OTP is: **<?php echo e($otp); ?>**

This OTP will expire in **15 minutes** and can only be used once.

<?php $__env->startComponent('mail::button', ['url' => $resetUrl]); ?>
Reset Password
<?php echo $__env->renderComponent(); ?>

## Security Notice

If you did not request this password reset:
1. **Do not use this OTP**
2. **Change your password immediately**
3. **Contact our security team**

<?php $__env->startComponent('mail::panel'); ?>
**Important Security Information:**
- This OTP is single-use only
- The link expires in 15 minutes
- Never share your OTP with anyone
- We will never ask for your password via email
<?php echo $__env->renderComponent(); ?>

## Need Help?

If you're having trouble with the button above, copy and paste the URL below into your web browser:
<?php echo e($resetUrl); ?>


For security concerns, contact us at: <?php echo e(config('mail.security_email', 'security@company.com')); ?>


Best regards,<br>
<?php echo e(config('app.name')); ?> Security Team

---
<small>This is an automated security message. If you believe this email was sent in error, please contact our support team immediately.</small>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\emails\auth\secure-password-reset.blade.php ENDPATH**/ ?>