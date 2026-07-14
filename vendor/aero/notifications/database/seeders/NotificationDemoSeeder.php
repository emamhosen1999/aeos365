<?php

declare(strict_types=1);

namespace Aero\Notifications\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Realistic notification data for a demo tenant.
 *
 * Idempotent: seeds only when a table is empty, so re-running never duplicates.
 * Run inside tenant context (tenancy()->initialize($tenant)).
 */
class NotificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $recipients = $this->recipients();

        $this->seedDeliveryLog($recipients);
        $this->seedSuppression();
        $this->seedInbox();
    }

    /** @return array<int, array{id:int, name:string, email:string}> */
    private function recipients(): array
    {
        $users = DB::table('users')
            ->whereNotNull('email')
            ->select('id', 'name', 'email')
            ->limit(40)
            ->get();

        return $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all();
    }

    private function seedDeliveryLog(array $recipients): void
    {
        if (DB::table('notification_logs')->count() > 0 || empty($recipients)) {
            return;
        }

        $subjects = [
            ['Your payslip for June 2026', 'payroll', 'PayslipReadyNotification'],
            ['Leave request approved', 'leave', 'LeaveApprovedNotification'],
            ['Leave request awaiting your approval', 'leave', 'LeaveRequestedNotification'],
            ['Welcome to AEOS365', 'auth', 'WelcomeNotification'],
            ['Your password was changed', 'security', 'PasswordChangedNotification'],
            ['New sign-in from a new device', 'security', 'NewDeviceNotification'],
            ['Timesheet reminder — submit by Friday', 'hr', 'TimesheetReminderNotification'],
            ['Your invoice is ready', 'billing', 'InvoiceReadyNotification'],
        ];

        // Dead addresses that will bounce — makes the Bounces tab tell a real story.
        $deadAddresses = [
            'old.address@gmail.com', 'former.staff@yahoo.com', 'full.inbox@gmail.com',
            'no.longer.here@outlook.com', 'typo.adress@gmail.com', 'bounced@hotmail.com',
        ];

        $rows = [];

        for ($i = 0; $i < 140; $i++) {
            [$subject, $event, $type] = $subjects[array_rand($subjects)];
            $person = $recipients[array_rand($recipients)];

            // Weighted channel mix: mail dominates, then in-app, then sms/push.
            $channel = $this->weighted(['mail' => 68, 'database' => 18, 'sms' => 8, 'push' => 6]);

            // ~88% land, the rest fail/bounce/queue — mirrors a healthy-but-real system.
            $status = $this->weighted(['delivered' => 62, 'sent' => 24, 'failed' => 5, 'bounced' => 5, 'pending' => 4]);

            $isBounce = in_array($status, ['bounced', 'failed'], true);
            $recipient = match (true) {
                $channel === 'sms' => '+8801'.random_int(300000000, 999999999),
                $isBounce && $channel === 'mail' => $deadAddresses[array_rand($deadAddresses)],
                default => $person['email'],
            };

            $createdAt = now()->subMinutes(random_int(2, 60 * 24 * 14));
            $attempts = $isBounce ? random_int(2, 3) : 1;

            $rows[] = [
                'idempotency_key' => (string) Str::uuid(),
                'user_id' => $person['id'],
                'notifiable_type' => 'Aero\\Core\\Models\\User',
                'notifiable_id' => $person['id'],
                'channel' => $channel,
                'notification_type' => "Aero\\Notifications\\Notifications\\{$type}",
                'event_type' => $event,
                'recipient' => $recipient,
                'subject' => $subject,
                'content' => "Hello {$person['name']},\n\n{$subject}. Sign in to AEOS365 to view the details.",
                'status' => $status,
                'error_message' => $this->errorFor($status),
                'metadata' => json_encode(['event' => $event, 'source' => 'demo']),
                'attempts' => $attempts,
                'max_attempts' => 3,
                'last_attempt_at' => $createdAt,
                'sent_at' => in_array($status, ['sent', 'delivered'], true) ? $createdAt : null,
                'delivered_at' => $status === 'delivered' ? $createdAt->copy()->addSeconds(random_int(2, 90)) : null,
                'read_at' => null,
                'failed_at' => $isBounce ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('notification_logs')->insert($chunk);
        }
    }

    private function errorFor(string $status): ?string
    {
        return match ($status) {
            'bounced' => collect([
                '550 5.1.1 The email account that you tried to reach does not exist',
                '552 5.2.2 The recipient mailbox is full',
                '550 5.7.1 Message rejected as spam by the recipient server',
            ])->random(),
            'failed' => collect([
                'Connection timed out after 30s',
                'SMTP authentication failed',
                'Rate limit exceeded by upstream provider',
            ])->random(),
            default => null,
        };
    }

    private function seedSuppression(): void
    {
        if (DB::table('email_suppression_list')->count() > 0) {
            return;
        }

        $entries = [
            ['old.address@gmail.com', 'bounce', '550 mailbox does not exist'],
            ['former.staff@yahoo.com', 'bounce', 'Hard bounce — 3 consecutive failures'],
            ['no.longer.here@outlook.com', 'complaint', 'Marked the message as spam'],
            ['bounced@hotmail.com', 'bounce', '550 mailbox does not exist'],
            ['unsubscribed.user@gmail.com', 'unsubscribe', 'Opted out of all email'],
            ['left.company@democorp.com', 'manual', 'Employee offboarded'],
            ['typo.adress@gmail.com', 'bounce', 'Invalid domain'],
            ['do-not-mail@example.com', 'manual', 'Requested by legal'],
        ];

        DB::table('email_suppression_list')->insert(array_map(fn ($e) => [
            'email' => $e[0],
            'reason' => $e[1],
            'note' => $e[2],
            'added_by' => null,
            'created_at' => now()->subDays(random_int(1, 30)),
            'updated_at' => now(),
        ], $entries));
    }

    /** In-app inbox for whoever is the tenant admin — that's who demos the page. */
    private function seedInbox(): void
    {
        if (DB::table('notifications')->count() > 0) {
            return;
        }

        $admin = DB::table('users')->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $items = [
            ['LeaveRequestedNotification', 'Leave request awaiting your approval', 'Priya Nair requested 3 days of annual leave (Jul 18–20).', 'warning', false, 4],
            ['DeliveryFailedNotification', '3 emails failed to deliver', 'Hard bounce from gmail.com — the recipients were moved to the suppression list.', 'danger', false, 51],
            ['PayrollCompletedNotification', 'Payroll run completed', 'June payroll processed for 251 employees — payslips have been sent.', 'success', false, 95],
            ['NewDeviceNotification', 'New sign-in from an unrecognised device', 'Chrome on Windows · 41.72.10.4. If this was not you, reset your password.', 'danger', false, 180],
            ['UserAddedNotification', 'Sarah Reid was added to Finance', 'Role "Manager" granted by Emam Hosen.', 'info', true, 60 * 6],
            ['TimesheetReminderNotification', 'Timesheets close on Friday', '18 employees have not submitted this week.', 'warning', true, 60 * 26],
            ['MaintenanceNotification', 'Scheduled maintenance Saturday 02:00 UTC', 'Billing and reporting will be briefly unavailable. No action is needed.', 'info', true, 60 * 48],
            ['InvoicePaidNotification', 'Invoice INV-2026-0042 was paid', 'BDT 45,000 received — thank you.', 'success', true, 60 * 72],
        ];

        $rows = [];
        foreach ($items as [$type, $title, $message, $severity, $read, $minsAgo]) {
            $created = now()->subMinutes($minsAgo);
            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => "Aero\\Notifications\\Notifications\\{$type}",
                'notifiable_type' => 'Aero\\Core\\Models\\User',
                'notifiable_id' => $admin->id,
                'data' => json_encode([
                    'title' => $title,
                    'message' => $message,
                    'severity' => $severity,
                    'url' => '/dashboard',
                ]),
                'read_at' => $read ? $created->copy()->addMinutes(5) : null,
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }

        DB::table('notifications')->insert($rows);
    }

    /** @param array<string,int> $weights */
    private function weighted(array $weights): string
    {
        $roll = random_int(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
