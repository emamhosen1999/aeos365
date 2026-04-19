<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduler
|--------------------------------------------------------------------------
|
| Here we define all scheduled commands for the AEOS365 host application.
| Platform-level commands are registered here so the Laravel scheduler
| can invoke them without manual cron entries beyond the standard
| "* * * * * php artisan schedule:run" crontab entry.
|
*/

// Process subscription renewals — advances ended billing periods.
Schedule::command('subscriptions:process-renewals')->daily();

// Process pending subscription downgrades — applies scheduled plan changes.
Schedule::command('subscriptions:process-pending')->daily();

// Expire grace periods — marks subscriptions past their grace window as expired.
Schedule::command('subscriptions:expire-grace-periods')->daily();

// Aggregate tenant statistics into the central tenant_stats table.
Schedule::command('tenants:aggregate-stats')->dailyAt('02:00');

// Clean up logs older than the configured retention period.
Schedule::command('logs:cleanup')->weekly();
