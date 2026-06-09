<?php $__env->startComponent('mail::message'); ?>
# Password Successfully Changed

Hello <?php echo e($user->name); ?>,

Your password has been successfully changed for your <?php echo e(config('app.name')); ?> account.

## Change Details
- **Change Time:** <?php echo e($timestamp); ?>

- **IP Address:** <?php echo e($ipAddress); ?>

- **Location:** <?php echo e($location ?? 'Unknown'); ?>

- **Browser:** <?php echo e($userAgent); ?>


<?php $__env->startComponent('mail::panel'); ?>
**Security Confirmation:**
✅ Password change completed successfully<br>
✅ All active sessions have been terminated<br>
✅ Security audit log updated<br>
<?php echo $__env->renderComponent(); ?>

## Security Actions Taken

For your security, we have automatically:
- Logged you out of all devices
- Invalidated all remember tokens
- Updated your security audit log
- Sent this confirmation email

## Did You Make This Change?

If **you** changed your password, no further action is needed.

If you **did not** change your password:

<?php $__env->startComponent('mail::button', ['url' => $supportUrl ?? '#']); ?>
Report Security Issue
<?php echo $__env->renderComponent(); ?>

<?php $__env->startComponent('mail::panel'); ?>
**⚠️ Immediate Actions Required:**
1. Contact our security team immediately
2. Check for unauthorized account access
3. Review recent account activity
4. Consider enabling 2FA if not already active
<?php echo $__env->renderComponent(); ?>

## Account Security Tips

- Use a unique, strong password
- Enable two-factor authentication
- Regularly review account activity
- Never share your login credentials

For immediate security assistance, contact us at: <?php echo e(config('mail.security_email', 'security@company.com')); ?>


Best regards,<br>
<?php echo e(config('app.name')); ?> Security Team

---
<small>This is an automated security notification. If you have concerns about this change, please contact our support team immediately.</small>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\laragon\www\Aero-Enterprise-Suite-Saas\packages\aero-ui\resources\views\emails\auth\password-changed-notification.blade.php ENDPATH**/ ?>