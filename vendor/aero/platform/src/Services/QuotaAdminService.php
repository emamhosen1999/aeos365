<?php

declare(strict_types=1);

namespace Aero\Platform\Services;

use Aero\Contracts\AuditServiceInterface;
use Aero\Platform\Models\QuotaEnforcementSetting;
use Aero\Platform\Models\QuotaWarning;
use Aero\Platform\Models\Tenant;
use Aero\Platform\Models\TenantQuotaOverride;
use Aero\Platform\Services\Quotas\QuotaEnforcementService;
use Aero\Platform\Services\Quotas\QuotaResources;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The Quota command centre's engine.
 *
 * Builds the tenant x resource utilisation matrix in bulk (a handful of
 * queries rather than one per cell), and owns every mutation the console
 * offers: overrides, enforcement policies and breach warnings.
 *
 * Limit resolution deliberately mirrors QuotaEnforcementService::getQuotaLimit()
 * — override -> legacy tenant.data -> plan.limits -> plan column -> policy
 * default -> tier default — but resolved from pre-loaded maps so the fleet view
 * costs a constant number of queries. The gate stays the authority for a single
 * runtime check; this is the same chain applied set-wise.
 */
class QuotaAdminService
{
    public function __construct(
        private AuditServiceInterface $audit,
        private QuotaEnforcementService $gate,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Console payload                                                     */
    /* ------------------------------------------------------------------ */

    public function overview(): array
    {
        $matrix = $this->buildMatrix();
        $policies = $this->policies();
        $overrides = $this->overridesLedger();
        $warnings = $this->warningsLedger();

        // Only rows with a real numeric cap count toward fleet metrics —
        // unlimited and uncapped ('unset', limit 0) rows have no utilisation.
        $counted = collect($matrix)->filter(fn ($r) => ! $r['unlimited'] && $r['limit'] > 0);
        $atRisk = $counted->whereIn('status', ['warning', 'critical']);
        $over = $counted->where('status', 'exceeded');

        // Fleet utilisation is the MEAN of each capped row's utilisation, not a
        // sum across resources — you can't add GB to API calls to seats. Each
        // tenant x resource cell weighs equally.
        $stats = [
            'fleet_utilization' => $counted->isNotEmpty() ? round($counted->avg('pct'), 1) : 0.0,
            'at_risk' => $atRisk->count(),
            'over_limit' => $over->count(),
            'tenants_over' => $over->pluck('tenant_id')->unique()->count(),
            'overrides_active' => collect($overrides)->where('active', true)->count(),
            'overrides_tenants' => collect($overrides)->where('active', true)->pluck('tenant_id')->unique()->count(),
            'expiring_soon' => collect($overrides)->where('expiring_soon', true)->count(),
            'warnings_open' => collect($warnings)->where('dismissed', false)->count(),
            'warnings_critical' => collect($warnings)->where('dismissed', false)->whereIn('threshold_type', ['critical', 'block'])->count(),
            'tenants_tracked' => collect($matrix)->pluck('tenant_id')->unique()->count(),
            'rows' => count($matrix),
        ];

        return [
            'matrix' => $matrix,
            'overrides' => $overrides,
            'warnings' => $warnings,
            'policies' => $policies,
            'stats' => $stats,
            'headroom' => $this->headroomByResource($matrix),
            'policyMix' => $this->policyMix($policies),
            'pressure' => $this->pressure($matrix),
            'resources' => QuotaResources::forFrontend(),
            'tenants' => $this->tenantOptions(),
            'actions' => [
                QuotaEnforcementSetting::ACTION_WARN,
                QuotaEnforcementSetting::ACTION_THROTTLE,
                QuotaEnforcementSetting::ACTION_BLOCK,
            ],
        ];
    }

    /**
     * The tenant x resource grid with effective limit, where it came from,
     * live usage, utilisation and status.
     *
     * @return list<array<string,mixed>>
     */
    public function buildMatrix(): array
    {
        $tenants = $this->activeTenants();
        if ($tenants->isEmpty()) {
            return [];
        }

        $tenantIds = $tenants->pluck('id')->all();
        $stats = $this->latestStats($tenantIds);
        $monthly = $this->monthToDateStats($tenantIds);
        $plans = $this->plansByTenant($tenantIds);
        $overrides = TenantQuotaOverride::query()
            ->whereIn('tenant_id', $tenantIds)->active()->get()
            ->keyBy(fn ($o) => $o->tenant_id.'|'.QuotaResources::canonical($o->resource));
        $policies = QuotaEnforcementSetting::query()->whereNotNull('resource')->get()->keyBy('resource');

        $rows = [];

        foreach ($tenants as $tenant) {
            $plan = $plans[$tenant->id] ?? null;
            $stat = $stats[$tenant->id] ?? null;

            foreach (QuotaResources::keys() as $resource) {
                $policy = $policies[$resource] ?? null;
                [$limit, $source] = $this->resolveLimit($tenant, $resource, $overrides, $plan, $policy);
                $used = $this->resolveUsage($tenant, $resource, $stat, $monthly[$tenant->id] ?? null);
                $unlimited = $limit === -1;
                $pct = $unlimited || $limit <= 0 ? 0.0 : round($used / $limit * 100, 1);

                $rows[] = [
                    'id' => $tenant->id.'|'.$resource,
                    'tenant_id' => $tenant->id,
                    'tenant' => $tenant->name,
                    'subdomain' => $tenant->subdomain,
                    'plan' => $plan->name ?? null,
                    'resource' => $resource,
                    'resource_label' => QuotaResources::label($resource),
                    'unit' => QuotaResources::unit($resource),
                    'limit' => $unlimited ? -1 : $limit,
                    'source' => $source,
                    'used' => $used,
                    'pct' => $pct,
                    'headroom' => $unlimited ? -1 : round($limit - $used, 2),
                    'unlimited' => $unlimited,
                    'status' => $this->statusFor($pct, $limit, $policy),
                    'action' => (string) ($policy->action ?? QuotaEnforcementSetting::ACTION_WARN),
                    'warn_at' => (int) ($policy->warning_threshold_pct ?? 80),
                    'hard_at' => (int) ($policy->hard_limit_pct ?? 100),
                ];
            }
        }

        return $rows;
    }

    /**
     * Same priority chain as QuotaEnforcementService::getQuotaLimit(), resolved
     * from pre-loaded maps.
     *
     * @return array{0:int,1:string} [limit, source]
     */
    private function resolveLimit(object $tenant, string $resource, Collection $overrides, ?object $plan, ?QuotaEnforcementSetting $policy): array
    {
        $override = $overrides[$tenant->id.'|'.$resource] ?? null;
        if ($override !== null) {
            $v = (int) $override->limit_value;

            return [$v === 0 ? -1 : $v, 'override'];
        }

        $data = $this->tenantData($tenant);
        $legacy = $data['quota_overrides']["max_{$resource}"] ?? null;
        if ($legacy !== null) {
            $v = (int) $legacy;

            return [$v === 0 ? -1 : $v, 'legacy'];
        }

        $meta = QuotaResources::meta($resource) ?? [];
        $planKey = $meta['plan_key'] ?? "max_{$resource}";

        if ($plan !== null) {
            $limits = $this->planLimits($plan);
            if (array_key_exists($planKey, $limits) && $limits[$planKey] !== null) {
                $v = (int) $limits[$planKey];

                return [$v === 0 ? -1 : $v, 'plan'];
            }

            $planCol = $meta['plan_col'] ?? null;
            if ($planCol !== null && ($plan->{$planCol} ?? null) !== null) {
                $v = (int) $plan->{$planCol};

                return [$v === 0 ? -1 : $v, 'plan'];
            }
        }

        // Fleet policy default — 0 means "no cap set", fall through (mirrors
        // QuotaEnforcementService::policyDefault, not "unlimited").
        if ($policy !== null && $policy->default_limit !== null && (int) $policy->default_limit !== 0) {
            return [(int) $policy->default_limit, 'policy'];
        }

        // Hardcoded tier default keyed by plan slug — the exact final fallback
        // the runtime gate uses, so the fleet matrix and live enforcement agree.
        $slug = $plan !== null ? strtolower($plan->slug ?? 'free') : 'free';
        $maxUsers = $plan !== null ? ((int) ($plan->max_users ?? 0) ?: null) : null;
        $tier = $this->gate->tierDefault($slug, $resource, $maxUsers);

        if ($tier !== 0) {
            // -1 (enterprise unlimited) or a positive tier limit — both are the
            // plan's entitlement.
            return [$tier, 'plan'];
        }

        return [0, 'unset'];
    }

    private function resolveUsage(object $tenant, string $resource, ?object $stat, ?object $monthly): float
    {
        if ($resource === 'ai_messages') {
            return (float) $this->gate->getAiMessagesUsed((string) $tenant->id);
        }

        $column = QuotaResources::meta($resource)['stat'] ?? null;

        if ($column === null) {
            return 0.0;
        }

        // A monthly quota must be measured month-to-date: tenant_stats holds a
        // per-DAY counter, so reading the latest row alone would report ~1/30th
        // of real consumption against a monthly cap.
        if (QuotaResources::isMonthly($resource)) {
            return QuotaResources::toUnit($resource, $monthly->{$column} ?? 0);
        }

        if ($stat === null) {
            return 0.0;
        }

        return QuotaResources::toUnit($resource, $stat->{$column} ?? 0);
    }

    private function statusFor(float $pct, int $limit, ?QuotaEnforcementSetting $policy): string
    {
        if ($limit === -1) {
            return 'unlimited';
        }
        if ($limit <= 0) {
            return 'unset';
        }

        $warnAt = (int) ($policy->warning_threshold_pct ?? 80);
        $hardAt = (int) ($policy->hard_limit_pct ?? 100);
        $criticalAt = $warnAt + (int) round(($hardAt - $warnAt) / 2);

        return match (true) {
            $pct >= $hardAt => 'exceeded',
            $pct >= $criticalAt => 'critical',
            $pct >= $warnAt => 'warning',
            default => 'ok',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Panels                                                              */
    /* ------------------------------------------------------------------ */

    private function headroomByResource(array $matrix): array
    {
        return collect($matrix)
            ->groupBy('resource')
            ->map(function (Collection $rows, string $resource) {
                $counted = $rows->filter(fn ($r) => ! $r['unlimited'] && $r['limit'] > 0);
                $granted = (float) $counted->sum('limit');
                $used = (float) $counted->sum('used');
                $first = $rows->first();

                return [
                    'resource' => $resource,
                    'label' => QuotaResources::label($resource),
                    'unit' => QuotaResources::unit($resource),
                    'granted' => round($granted, 2),
                    'used' => round($used, 2),
                    'pct' => $granted > 0 ? round($used / $granted * 100, 1) : 0.0,
                    'headroom' => round($granted - $used, 2),
                    'over' => $rows->where('status', 'exceeded')->count(),
                    'at_risk' => $rows->whereIn('status', ['warning', 'critical'])->count(),
                    // A COUNT of uncapped tenants for this resource — named
                    // explicitly so it is never mistaken for a boolean flag.
                    'unlimited_rows' => $rows->where('unlimited', true)->count(),
                    'capped_rows' => $counted->count(),
                    'warn_at' => $first['warn_at'],
                    'hard_at' => $first['hard_at'],
                ];
            })
            ->values()->all();
    }

    private function policyMix(array $policies): array
    {
        $by = collect($policies)->groupBy('action')->map->count();

        return collect([QuotaEnforcementSetting::ACTION_BLOCK, QuotaEnforcementSetting::ACTION_THROTTLE, QuotaEnforcementSetting::ACTION_WARN])
            ->map(fn (string $a) => ['action' => $a, 'label' => ucfirst($a), 'count' => (int) ($by[$a] ?? 0)])
            ->all();
    }

    /** Tenants ranked by their single tightest resource. */
    private function pressure(array $matrix): array
    {
        return collect($matrix)
            ->reject(fn ($r) => $r['unlimited'] || $r['limit'] <= 0)
            ->groupBy('tenant_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('pct')->first())
            ->sortByDesc('pct')
            ->take(6)
            ->map(fn ($r) => [
                'tenant_id' => $r['tenant_id'],
                'tenant' => $r['tenant'],
                'resource' => $r['resource'],
                'label' => $r['resource_label'],
                'pct' => $r['pct'],
                'status' => $r['status'],
            ])
            ->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /* Ledgers                                                             */
    /* ------------------------------------------------------------------ */

    public function overridesLedger(): array
    {
        return TenantQuotaOverride::query()
            ->with(['tenant:id,name,subdomain', 'setter:id,name,email'])
            ->orderByDesc('id')->get()
            ->map(fn (TenantQuotaOverride $o) => [
                'id' => $o->id,
                'tenant_id' => $o->tenant_id,
                'tenant' => $o->tenant->name ?? $o->tenant_id,
                'resource' => QuotaResources::canonical($o->resource),
                'resource_label' => QuotaResources::label($o->resource),
                'unit' => QuotaResources::unit($o->resource),
                'limit_value' => (int) $o->limit_value,
                'unlimited' => (int) $o->limit_value === 0,
                'reason' => $o->reason,
                'set_by' => $o->setter->name ?? null,
                'expires_at' => $o->expires_at?->toIso8601String(),
                'active' => $o->isActive(),
                'expiring_soon' => $o->expires_at !== null && $o->isActive() && $o->expires_at->lte(now()->addDays(7)),
                'created_at' => $o->created_at?->toIso8601String(),
            ])->values()->all();
    }

    public function warningsLedger(): array
    {
        return QuotaWarning::query()
            ->with(['tenant:id,name', 'dismissedBy:id,name'])
            ->orderByDesc('last_warned_at')->orderByDesc('id')->limit(300)->get()
            ->map(fn (QuotaWarning $w) => [
                'id' => $w->id,
                'tenant_id' => $w->tenant_id,
                'tenant' => $w->tenant->name ?? $w->tenant_id,
                'resource' => QuotaResources::canonical($w->quota_type),
                'resource_label' => QuotaResources::label($w->quota_type),
                'percentage' => round((float) $w->percentage, 1),
                'threshold_type' => $w->threshold_type,
                'warning_count' => (int) $w->warning_count,
                'first_warned_at' => $w->first_warned_at?->toIso8601String(),
                'last_warned_at' => $w->last_warned_at?->toIso8601String(),
                'dismissed' => (bool) $w->is_dismissed,
                'dismissed_at' => $w->dismissed_at?->toIso8601String(),
                'dismissed_by' => $w->dismissedBy->name ?? null,
            ])->values()->all();
    }

    public function policies(): array
    {
        return QuotaEnforcementSetting::query()
            ->whereNotNull('resource')->orderBy('resource')->get()
            ->map(fn (QuotaEnforcementSetting $s) => [
                'id' => $s->id,
                'resource' => $s->resource,
                'label' => QuotaResources::label($s->resource),
                'unit' => QuotaResources::unit($s->resource),
                'default_limit' => (int) $s->default_limit,
                'unlimited' => (int) $s->default_limit === 0,
                'warning_threshold_pct' => (int) ($s->warning_threshold_pct ?? 80),
                'hard_limit_pct' => (int) ($s->hard_limit_pct ?? 100),
                'action' => $s->action ?? QuotaEnforcementSetting::ACTION_WARN,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ])->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /* Mutations — overrides                                               */
    /* ------------------------------------------------------------------ */

    public function setOverride(Tenant $tenant, string $resource, int $limit, ?string $reason, ?string $expiresAt): TenantQuotaOverride
    {
        $resource = QuotaResources::canonical($resource);

        return DB::transaction(function () use ($tenant, $resource, $limit, $reason, $expiresAt) {
            $current = $this->currentUsage($tenant, $resource);

            // 0 means "unlimited" on an override, so it can never be too low.
            if ($limit !== 0 && $limit < $current) {
                abort(422, sprintf(
                    'Limit (%s) cannot be below current usage (%s %s).',
                    $limit, $current, QuotaResources::unit($resource)
                ));
            }

            $override = TenantQuotaOverride::updateOrCreate(
                ['tenant_id' => $tenant->id, 'resource' => $resource],
                [
                    'limit_value' => $limit,
                    'reason' => $reason,
                    'expires_at' => $expiresAt,
                    'set_by' => Auth::guard('landlord')->id(),
                ]
            );

            $this->gate->clearCache($tenant, $resource);

            $this->audit->log(
                event: 'platform.quota.override.set',
                action: 'override',
                subject: $override,
                description: "Quota override for tenant {$tenant->name} resource={$resource} limit={$limit}",
                metadata: ['tenant_id' => $tenant->id, 'resource' => $resource, 'limit' => $limit, 'usage_at_set' => $current],
            );

            return $override;
        });
    }

    public function removeOverride(Tenant $tenant, string $resource): void
    {
        $resource = QuotaResources::canonical($resource);

        DB::transaction(function () use ($tenant, $resource) {
            $override = TenantQuotaOverride::where('tenant_id', $tenant->id)
                ->where('resource', $resource)
                ->firstOrFail();

            $this->audit->log(
                event: 'platform.quota.override.removed',
                action: 'override',
                subject: $override,
                description: "Removed quota override for tenant {$tenant->name} resource={$resource}",
                metadata: ['tenant_id' => $tenant->id, 'resource' => $resource],
            );

            $override->delete();
            $this->gate->clearCache($tenant, $resource);
        });
    }

    /** Push an override's expiry out (or make it permanent with null). */
    public function extendOverride(TenantQuotaOverride $override, ?string $expiresAt): TenantQuotaOverride
    {
        return DB::transaction(function () use ($override, $expiresAt) {
            $before = $override->expires_at?->toIso8601String();
            $override->update(['expires_at' => $expiresAt]);

            $this->audit->log(
                event: 'platform.quota.override.set',
                action: 'override',
                subject: $override,
                description: "Quota override expiry changed for resource={$override->resource}",
                before: ['expires_at' => $before],
                after: ['expires_at' => $expiresAt],
            );

            return $override->fresh();
        });
    }

    /** Delete every override that has already lapsed. */
    public function clearExpiredOverrides(): int
    {
        return DB::transaction(function (): int {
            $expired = TenantQuotaOverride::query()->expired()->get();
            $count = $expired->count();

            foreach ($expired as $o) {
                $o->delete();
            }

            if ($count > 0) {
                $this->audit->log(
                    event: 'platform.quota.override.removed',
                    action: 'override',
                    description: "Cleared {$count} expired quota override(s).",
                    metadata: ['count' => $count],
                );
            }

            return $count;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Mutations — policies                                                */
    /* ------------------------------------------------------------------ */

    public function updateSettings(string $resource, array $data): QuotaEnforcementSetting
    {
        $resource = QuotaResources::canonical($resource);

        return DB::transaction(function () use ($resource, $data) {
            $setting = QuotaEnforcementSetting::updateOrCreate(
                ['resource' => $resource],
                [
                    'default_limit' => $data['default_limit'],
                    'warning_threshold_pct' => $data['warning_threshold_pct'] ?? 80,
                    'hard_limit_pct' => $data['hard_limit_pct'] ?? 100,
                    'action' => $data['action'] ?? QuotaEnforcementSetting::ACTION_WARN,
                    // Keep the legacy column in step so nothing reading the old
                    // schema sees a row with a null quota_type.
                    'quota_type' => $resource,
                ]
            );

            QuotaEnforcementService::flushPolicyCache();

            $this->audit->log(
                event: 'platform.quota.settings.updated',
                action: 'edit',
                subject: $setting,
                description: "Quota enforcement policy updated for resource={$resource}",
                metadata: $data + ['resource' => $resource],
            );

            return $setting;
        });
    }

    public function deletePolicy(QuotaEnforcementSetting $setting): void
    {
        DB::transaction(function () use ($setting) {
            $resource = $setting->resource;

            $this->audit->log(
                event: 'platform.quota.settings.updated',
                action: 'edit',
                subject: $setting,
                description: "Quota enforcement policy deleted for resource={$resource}",
                metadata: ['resource' => $resource],
            );

            $setting->delete();
            QuotaEnforcementService::flushPolicyCache();
        });
    }

    /**
     * How many rows would change status if a policy were saved with these
     * thresholds — shown in the editor before the operator commits.
     */
    public function previewPolicy(string $resource, int $defaultLimit, int $warnAt, int $hardAt): array
    {
        $resource = QuotaResources::canonical($resource);
        $rows = collect($this->buildMatrix())->where('resource', $resource);

        $probe = new QuotaEnforcementSetting;
        $probe->warning_threshold_pct = $warnAt;
        $probe->hard_limit_pct = $hardAt;

        $after = $rows->map(function (array $r) use ($defaultLimit, $probe) {
            // Rows on the policy default follow the new limit; overridden and
            // plan-granted rows keep theirs.
            $limit = in_array($r['source'], ['policy', 'unset'], true)
                ? ($defaultLimit === 0 ? -1 : $defaultLimit)
                : $r['limit'];
            $pct = $limit <= 0 ? 0.0 : round($r['used'] / $limit * 100, 1);

            return $this->statusFor($pct, $limit, $probe);
        });

        return [
            'rows' => $rows->count(),
            'exceeded' => $after->filter(fn ($s) => $s === 'exceeded')->count(),
            'at_risk' => $after->filter(fn ($s) => in_array($s, ['warning', 'critical'], true))->count(),
            'newly_exceeded' => max(0, $after->filter(fn ($s) => $s === 'exceeded')->count() - $rows->where('status', 'exceeded')->count()),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Mutations — warnings                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Walk the fleet and persist a warning for every resource at or past its
     * warning threshold. This is what finally makes quota_warnings live: the
     * table has existed since 2025 but nothing reachable ever wrote to it.
     *
     * @return array{scanned:int,raised:int,cleared:int}
     */
    public function scanBreaches(): array
    {
        $matrix = $this->buildMatrix();
        $raised = 0;
        $cleared = 0;

        foreach ($matrix as $row) {
            if ($row['unlimited'] || $row['limit'] <= 0) {
                continue;
            }

            $breaching = in_array($row['status'], ['warning', 'critical', 'exceeded'], true);

            if ($breaching) {
                QuotaWarning::recordWarning(
                    (string) $row['tenant_id'],
                    $row['resource'],
                    (float) $row['pct'],
                    $this->thresholdTypeFor($row['status']),
                );
                $raised++;

                continue;
            }

            // Recovered: an open warning whose resource is now healthy is
            // auto-dismissed rather than left to rot in the queue.
            $open = QuotaWarning::query()
                ->where('tenant_id', $row['tenant_id'])
                ->where('quota_type', $row['resource'])
                ->where('is_dismissed', false)
                ->get();

            foreach ($open as $w) {
                $w->dismiss(Auth::guard('landlord')->id());
                $cleared++;
            }
        }

        $this->audit->log(
            event: 'platform.quota.warnings.scanned',
            action: 'dismiss-warnings',
            description: "Quota breach scan: {$raised} warning(s) raised, {$cleared} auto-cleared across ".count($matrix).' rows.',
            metadata: ['scanned' => count($matrix), 'raised' => $raised, 'cleared' => $cleared],
        );

        return ['scanned' => count($matrix), 'raised' => $raised, 'cleared' => $cleared];
    }

    private function thresholdTypeFor(string $status): string
    {
        return match ($status) {
            'exceeded' => 'block',
            'critical' => 'critical',
            default => 'warning',
        };
    }

    public function dismissWarning(QuotaWarning $warning): QuotaWarning
    {
        return DB::transaction(function () use ($warning) {
            $warning->dismiss(Auth::guard('landlord')->id());

            $this->audit->log(
                event: 'platform.quota.warning.dismissed',
                action: 'dismiss-warnings',
                subject: $warning,
                description: "Dismissed quota warning for tenant {$warning->tenant_id} resource={$warning->quota_type}",
                metadata: ['tenant_id' => $warning->tenant_id, 'resource' => $warning->quota_type],
            );

            return $warning->fresh();
        });
    }

    /** Re-open a dismissed warning. */
    public function reopenWarning(QuotaWarning $warning): QuotaWarning
    {
        return DB::transaction(function () use ($warning) {
            $warning->update(['is_dismissed' => false, 'dismissed_at' => null, 'dismissed_by_user_id' => null]);

            $this->audit->log(
                event: 'platform.quota.warning.dismissed',
                action: 'dismiss-warnings',
                subject: $warning,
                description: "Re-opened quota warning for tenant {$warning->tenant_id} resource={$warning->quota_type}",
            );

            return $warning->fresh();
        });
    }

    /** @param  list<int>  $ids */
    public function dismissWarnings(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $warnings = QuotaWarning::whereIn('id', $ids)->where('is_dismissed', false)->get();
            $userId = Auth::guard('landlord')->id();

            foreach ($warnings as $w) {
                $w->dismiss($userId);
            }

            $count = $warnings->count();

            if ($count > 0) {
                $this->audit->log(
                    event: 'platform.quota.warning.dismissed',
                    action: 'dismiss-warnings',
                    description: "Dismissed {$count} quota warning(s).",
                    metadata: ['count' => $count, 'ids' => $ids],
                );
            }

            return $count;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** @return Collection<int, object> */
    private function activeTenants(): Collection
    {
        return DB::connection(central_connection())->table('tenants')
            ->whereNull('deleted_at')
            ->whereIn('status', [Tenant::STATUS_ACTIVE, 'trial', Tenant::STATUS_SUSPENDED])
            ->orderBy('name')
            ->get(['id', 'name', 'subdomain', 'status', 'data']);
    }

    /** Newest stats row per tenant, keyed by tenant id. */
    private function latestStats(array $tenantIds): Collection
    {
        $conn = central_connection();
        $latest = DB::connection($conn)->table('tenant_stats')
            ->select('tenant_id', DB::raw('MAX(date) as d'))
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id');

        return DB::connection($conn)->table('tenant_stats as ts')
            ->joinSub($latest, 'l', fn ($j) => $j->on('ts.tenant_id', '=', 'l.tenant_id')->on('ts.date', '=', 'l.d'))
            ->get(['ts.*'])
            ->keyBy('tenant_id');
    }

    /**
     * Month-to-date sums of the per-day counter columns, keyed by tenant id —
     * the correct basis for monthly quotas (api calls).
     */
    private function monthToDateStats(array $tenantIds): Collection
    {
        return DB::connection(central_connection())->table('tenant_stats')
            ->whereIn('tenant_id', $tenantIds)
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->groupBy('tenant_id')
            ->get([
                'tenant_id',
                DB::raw('SUM(api_requests) as api_requests'),
            ])
            ->keyBy('tenant_id');
    }

    /** Plan per tenant via the active subscription, keyed by tenant id. */
    private function plansByTenant(array $tenantIds): Collection
    {
        return DB::connection(central_connection())->table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.status', ['active', 'trialing'])
            ->whereIn('s.tenant_id', $tenantIds)
            ->get(['s.tenant_id', 'p.name', 'p.slug', 'p.limits', 'p.max_users', 'p.max_storage_gb'])
            ->keyBy('tenant_id');
    }

    private function planLimits(object $plan): array
    {
        $raw = $plan->limits ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    private function tenantData(object $tenant): array
    {
        $raw = $tenant->data ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    private function tenantOptions(): array
    {
        return $this->activeTenants()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'subdomain' => $t->subdomain])
            ->values()->all();
    }

    /**
     * Current usage in the resource's own unit.
     *
     * The original read `tenant_stats.storage_mb` — a column that has never
     * existed — and used it as the `default` branch, so saving a storage or
     * modules override always 500ed. It also compared MB against a GB limit.
     */
    private function currentUsage(Tenant $tenant, string $resource): float
    {
        $resource = QuotaResources::canonical($resource);

        if ($resource === 'ai_messages') {
            return (float) $this->gate->getAiMessagesUsed($tenant->id);
        }

        $column = QuotaResources::meta($resource)['stat'] ?? null;

        if ($column === null) {
            return 0.0;
        }

        $raw = DB::connection(central_connection())->table('tenant_stats')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('date')
            ->value($column);

        return QuotaResources::toUnit($resource, $raw ?? 0);
    }
}
