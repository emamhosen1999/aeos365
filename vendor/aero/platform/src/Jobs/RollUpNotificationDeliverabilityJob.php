<?php

declare(strict_types=1);

namespace Aero\Platform\Jobs;

use Aero\Platform\Models\NotificationFleetRollup;
use Aero\Platform\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Roll up per-tenant notification deliverability (notification_logs +
 * email_suppression_list) into the central notification_fleet_rollups table.
 *
 * The platform has NO cross-tenant deliverability view — those tables live in
 * each tenant DB. This job initializes tenant context ONE tenant at a time,
 * aggregates that tenant's day(s), then re-enters central context to persist
 * the summary. A failure on one tenant is caught and logged so it can never
 * abort the run for the remaining tenants (see collectTenantDay/persist).
 *
 * The fleet console (FleetDeliverabilityService) reads ONLY the rollup table —
 * never tenant DBs — so this job is the sole writer of that view.
 */
class RollUpNotificationDeliverabilityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Mirrors Illuminate\Console\Command::SUCCESS/FAILURE for the calling Artisan command. */
    public const SUCCESS = 0;

    public const FAILURE = 1;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [60, 300, 600];

    /** @var array<int,string> Y-m-d dates to roll up */
    protected array $dates;

    /**
     * @param  array<int,string>|string|null  $dates  One or more Y-m-d dates.
     *                                                Defaults to today + yesterday, so a
     *                                                late-arriving day is always re-covered
     *                                                by the next scheduled run.
     */
    public function __construct(array|string|null $dates = null)
    {
        $this->onQueue('maintenance');

        $this->dates = match (true) {
            $dates === null => [now()->toDateString(), now()->subDay()->toDateString()],
            is_array($dates) => array_values($dates),
            default => [$dates],
        };
    }

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->whereNull('deleted_at')
            ->where('status', Tenant::STATUS_ACTIVE)
            ->get(['id', 'name']);

        $synced = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            foreach ($this->dates as $date) {
                try {
                    $rows = $this->collectTenantDay($tenant, $date);
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('notification-fleet-rollup: tenant aggregation failed', [
                        'tenant_id' => $tenant->id,
                        'date' => $date,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                if ($rows === []) {
                    continue; // nothing happened for this tenant/day — no row to write
                }

                try {
                    $this->persist($tenant, $date, $rows);
                    $synced++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('notification-fleet-rollup: persist failed', [
                        'tenant_id' => $tenant->id,
                        'date' => $date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('notification-fleet-rollup: run complete', [
            'dates' => $this->dates,
            'tenants' => $tenants->count(),
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Aggregate ONE tenant's notification_logs + suppression additions for ONE
     * day, grouped by channel. Runs entirely inside the tenant's DB context —
     * the platform can never read these tables directly.
     *
     * @return array<string,array<string,int>> channel => [sent, delivered, failed, bounced, suppressed]
     */
    protected function collectTenantDay(Tenant $tenant, string $date): array
    {
        $rows = [];

        try {
            tenancy()->initialize($tenant);

            if (Schema::hasTable('notification_logs')) {
                $grouped = DB::table('notification_logs')
                    ->selectRaw('channel, status, COUNT(*) as aggregate')
                    ->whereDate('created_at', $date)
                    ->groupBy('channel', 'status')
                    ->get();

                foreach ($grouped as $r) {
                    $channel = (string) $r->channel;
                    $rows[$channel] ??= ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'bounced' => 0, 'suppressed' => 0];

                    $count = (int) $r->aggregate;

                    match ((string) $r->status) {
                        'sent' => $rows[$channel]['sent'] += $count,
                        'delivered' => $rows[$channel]['delivered'] += $count,
                        'failed' => $rows[$channel]['failed'] += $count,
                        'bounced' => $rows[$channel]['bounced'] += $count,
                        default => null, // pending/read/retrying/etc. — not tracked in the fleet rollup
                    };
                }
            }

            if (Schema::hasTable('email_suppression_list')) {
                $suppressed = (int) DB::table('email_suppression_list')
                    ->whereDate('created_at', $date)
                    ->count();

                if ($suppressed > 0) {
                    $rows['mail'] ??= ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'bounced' => 0, 'suppressed' => 0];
                    $rows['mail']['suppressed'] += $suppressed;
                }
            }
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $rows;
    }

    /**
     * Re-enters central context (tenancy()->end() already ran in
     * collectTenantDay's finally) and idempotently upserts one row per channel
     * on the unique (tenant_id, date, channel) key.
     *
     * @param  array<string,array<string,int>>  $rows
     */
    protected function persist(Tenant $tenant, string $date, array $rows): void
    {
        DB::connection(NotificationFleetRollup::centralConnectionName())
            ->transaction(function () use ($tenant, $date, $rows): void {
                foreach ($rows as $channel => $counts) {
                    NotificationFleetRollup::updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'date' => $date,
                            'channel' => $channel,
                        ],
                        [
                            'sent' => $counts['sent'],
                            'delivered' => $counts['delivered'],
                            'failed' => $counts['failed'],
                            'bounced' => $counts['bounced'],
                            'suppressed' => $counts['suppressed'],
                        ],
                    );
                }
            });
    }
}
