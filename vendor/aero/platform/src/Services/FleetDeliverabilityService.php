<?php

declare(strict_types=1);

namespace Aero\Platform\Services;

use Aero\Platform\Models\NotificationFleetRollup;
use Aero\Platform\Models\Tenant;

/**
 * Reads ONLY the central notification_fleet_rollups table — never tenant DBs.
 *
 * The rollup is populated on a schedule by RollUpNotificationDeliverabilityJob
 * (via the notifications:rollup command). This service is the single read path
 * for the platform "Fleet" observability tab on the shared notifications page.
 */
class FleetDeliverabilityService
{
    /**
     * Fleet-wide send/delivery totals + a per-day trend for the last N days.
     *
     * @return array{
     *     days:int, sent:int, delivered:int, failed:int, bounced:int, suppressed:int,
     *     delivery_rate:float, trend:array<int,array<string,mixed>>
     * }
     */
    public function summary(int $days = 7): array
    {
        $days = max(1, $days);
        $since = now()->subDays($days - 1)->toDateString();

        $rows = NotificationFleetRollup::query()
            ->where('date', '>=', $since)
            ->get(['date', 'sent', 'delivered', 'failed', 'bounced', 'suppressed']);

        $sent = (int) $rows->sum('sent');
        $delivered = (int) $rows->sum('delivered');
        $failed = (int) $rows->sum('failed');
        $bounced = (int) $rows->sum('bounced');
        $suppressed = (int) $rows->sum('suppressed');
        $total = $sent + $delivered + $failed + $bounced;

        $trend = $rows->groupBy(fn (NotificationFleetRollup $r) => $r->date->toDateString())
            ->map(function ($dayRows, string $date): array {
                $s = (int) $dayRows->sum('sent');
                $d = (int) $dayRows->sum('delivered');
                $f = (int) $dayRows->sum('failed');
                $b = (int) $dayRows->sum('bounced');
                $t = $s + $d + $f + $b;

                return [
                    'date' => $date,
                    'sent' => $s,
                    'delivered' => $d,
                    'failed' => $f,
                    'bounced' => $b,
                    'delivery_rate' => $t > 0 ? round(($d / $t) * 100, 2) : 0.0,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return [
            'days' => $days,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'bounced' => $bounced,
            'suppressed' => $suppressed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0.0,
            'trend' => $trend,
        ];
    }

    /**
     * Tenants ranked by (failed + bounced) / total rate, worst first.
     * Tenant name is resolved once from the central Tenant table (never joined
     * across DBs — a separate lookup keyed by id).
     *
     * @return array<int,array<string,mixed>>
     */
    public function worstOffenders(int $limit = 10): array
    {
        $limit = max(1, $limit);

        $rows = NotificationFleetRollup::query()
            ->selectRaw('tenant_id, SUM(sent) as sent, SUM(delivered) as delivered, SUM(failed) as failed, SUM(bounced) as bounced, SUM(suppressed) as suppressed')
            ->groupBy('tenant_id')
            ->get();

        $tenantNames = Tenant::query()
            ->whereIn('id', $rows->pluck('tenant_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(function ($r) use ($tenantNames): array {
                $sent = (int) $r->sent;
                $delivered = (int) $r->delivered;
                $failed = (int) $r->failed;
                $bounced = (int) $r->bounced;
                $total = $sent + $delivered + $failed + $bounced;

                return [
                    'tenant_id' => (string) $r->tenant_id,
                    'tenant_name' => $tenantNames->get($r->tenant_id) ?? (string) $r->tenant_id,
                    'sent' => $sent,
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'bounced' => $bounced,
                    'suppressed' => (int) $r->suppressed,
                    'total' => $total,
                    'bounce_fail_rate' => $total > 0 ? round((($failed + $bounced) / $total) * 100, 2) : 0.0,
                ];
            })
            ->filter(fn (array $r): bool => $r['total'] > 0)
            ->sortByDesc('bounce_fail_rate')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * One tenant's deliverability breakdown by channel + daily trend over the
     * last N days.
     *
     * @return array{
     *     tenant_id:string, tenant_name:string, days:int,
     *     by_channel:array<int,array<string,mixed>>, trend:array<int,array<string,mixed>>
     * }
     */
    public function perTenant(string $tenantId, int $days = 30): array
    {
        $days = max(1, $days);
        $since = now()->subDays($days - 1)->toDateString();

        $rows = NotificationFleetRollup::query()
            ->where('tenant_id', $tenantId)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get(['date', 'channel', 'sent', 'delivered', 'failed', 'bounced', 'suppressed']);

        $byChannel = $rows
            ->groupBy('channel')
            ->map(function ($channelRows, string $channel): array {
                return [
                    'channel' => $channel,
                    'sent' => (int) $channelRows->sum('sent'),
                    'delivered' => (int) $channelRows->sum('delivered'),
                    'failed' => (int) $channelRows->sum('failed'),
                    'bounced' => (int) $channelRows->sum('bounced'),
                    'suppressed' => (int) $channelRows->sum('suppressed'),
                ];
            })
            ->values()
            ->all();

        $trend = $rows
            ->groupBy(fn (NotificationFleetRollup $r) => $r->date->toDateString())
            ->map(function ($dayRows, string $date): array {
                $s = (int) $dayRows->sum('sent');
                $d = (int) $dayRows->sum('delivered');
                $f = (int) $dayRows->sum('failed');
                $b = (int) $dayRows->sum('bounced');
                $t = $s + $d + $f + $b;

                return [
                    'date' => $date,
                    'sent' => $s,
                    'delivered' => $d,
                    'failed' => $f,
                    'bounced' => $b,
                    'delivery_rate' => $t > 0 ? round(($d / $t) * 100, 2) : 0.0,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $tenant = Tenant::query()->find($tenantId, ['id', 'name']);

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant?->name ?? $tenantId,
            'days' => $days,
            'by_channel' => $byChannel,
            'trend' => $trend,
        ];
    }
}
