<?php

declare(strict_types=1);

namespace Aero\Platform\Console\Commands;

use Aero\Platform\Jobs\RollUpNotificationDeliverabilityJob;
use Illuminate\Console\Command;

/**
 * Trigger the notification fleet deliverability rollup.
 *
 * Usage:
 *   php artisan notifications:rollup                    # today + yesterday
 *   php artisan notifications:rollup --date=2026-07-20   # one specific day
 *
 * NOT registered in AeroPlatformServiceProvider / scheduler yet — see the
 * integration notes returned with this task for the schedule() wiring.
 */
class RollUpNotificationsCommand extends Command
{
    protected $signature = 'notifications:rollup {--date= : Specific Y-m-d day to roll up (defaults to today + yesterday)}';

    protected $description = 'Roll up per-tenant notification deliverability into the central fleet rollup table';

    public function handle(): int
    {
        $date = $this->option('date');

        $this->info($date
            ? "Rolling up notification deliverability for {$date}..."
            : 'Rolling up notification deliverability for today + yesterday...');

        $status = (new RollUpNotificationDeliverabilityJob($date))->handle();

        if ($status === RollUpNotificationDeliverabilityJob::SUCCESS) {
            $this->info('Notification fleet rollup completed successfully.');
        } else {
            $this->warn('Notification fleet rollup completed with one or more tenant failures — check the log.');
        }

        return $status;
    }
}
