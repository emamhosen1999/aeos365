<?php

declare(strict_types=1);

namespace Aero\Platform\Services\Quotas;

use Aero\Core\Models\User;
use Aero\Core\Support\TenantCache;
use Aero\Platform\Models\QuotaEnforcementSetting;
use Aero\Platform\Models\Tenant;
use Aero\Platform\Models\TenantQuotaOverride;
use Aero\Platform\Models\TenantStat;
use Aero\Platform\Models\UsageRecord;
use Illuminate\Support\Facades\Storage;

/**
 * Quota Enforcement Service
 *
 * Enforces usage quotas based on tenant subscription plan.
 * Tracks and limits: users, storage, API calls, modules, etc.
 *
 * Quota Types:
 * - max_users: Maximum number of users in tenant
 * - max_storage_gb: Maximum storage in GB
 * - max_api_calls_monthly: Monthly API call limit
 * - max_employees: Maximum employees (HRM)
 * - max_projects: Maximum projects (Project module)
 *
 * Usage:
 * ```php
 * $quotaService = app(QuotaEnforcementService::class);
 *
 * // Check before creating user
 * if (!$quotaService->canCreate($tenant, 'users')) {
 *     throw new QuotaExceededException('User limit reached');
 * }
 *
 * // Increment usage after creation
 * $quotaService->increment($tenant, 'users');
 * ```
 */
class QuotaEnforcementService
{
    /**
     * Default quotas per plan.
     */
    protected array $defaultQuotas = [
        'free' => [
            'max_users' => 5,
            'max_storage_gb' => 1,
            'max_api_calls_monthly' => 10000,
            'max_ai_messages' => 200,
            'max_employees' => 10,
            'max_projects' => 3,
            'max_customers' => 50,
            'max_rfis' => 100,
        ],
        'starter' => [
            'max_users' => 25,
            'max_storage_gb' => 10,
            'max_api_calls_monthly' => 100000,
            'max_ai_messages' => 1000,
            'max_employees' => 50,
            'max_projects' => 20,
            'max_customers' => 500,
            'max_rfis' => 1000,
        ],
        'professional' => [
            'max_users' => 100,
            'max_storage_gb' => 50,
            'max_api_calls_monthly' => 500000,
            'max_ai_messages' => 5000,
            'max_employees' => 200,
            'max_projects' => 100,
            'max_customers' => 5000,
            'max_rfis' => 10000,
        ],
        'enterprise' => [
            'max_users' => -1, // Unlimited
            'max_storage_gb' => -1,
            'max_api_calls_monthly' => -1,
            'max_ai_messages' => -1,
            'max_employees' => -1,
            'max_projects' => -1,
            'max_customers' => -1,
            'max_rfis' => -1,
        ],
    ];

    /**
     * Mapping of quota types to their model classes for counting.
     */
    protected array $quotaModels = [
        'users' => User::class,
        // These will be resolved dynamically if the module is available
        'employees' => 'Aero\\HRM\\Models\\Employee',
        'projects' => 'Aero\\Project\\Models\\Project',
        'customers' => 'Aero\\CRM\\Models\\Customer',
        'rfis' => 'Aero\\Rfi\\Models\\Rfi',
    ];

    /**
     * Cache TTL for quota checks (in seconds).
     * Kept short because model observers proactively bust the cache on every
     * create / delete / restore, so TTL is only a safety-net for edge cases.
     */
    protected int $cacheTtl = 60; // 1 minute (observers handle proactive busting)

    /**
     * Check if tenant can create a new resource of the given type.
     *
     * @param  string  $quotaType  users, employees, projects, etc.
     */
    public function canCreate(Tenant|string $tenant, string $quotaType): bool
    {
        $limit = $this->getQuotaLimit($tenant, $quotaType);

        // -1 means unlimited
        if ($limit === -1) {
            return true;
        }

        $current = $this->getCurrentUsage($tenant, $quotaType);

        return $current < $limit;
    }

    /**
     * Check storage quota.
     *
     * @param  int  $additionalBytes  Additional bytes to be added
     */
    public function canUseStorage(Tenant|string $tenant, int $additionalBytes = 0): bool
    {
        $limitGb = $this->getQuotaLimit($tenant, 'storage_gb');

        // -1 means unlimited
        if ($limitGb === -1) {
            return true;
        }

        $currentBytes = $this->getStorageUsage($tenant);
        $limitBytes = $limitGb * 1024 * 1024 * 1024; // Convert GB to bytes

        return ($currentBytes + $additionalBytes) <= $limitBytes;
    }

    /**
     * Check monthly API call quota.
     */
    public function canMakeApiCall(Tenant|string $tenant): bool
    {
        $limit = $this->getQuotaLimit($tenant, 'api_calls_monthly');

        // -1 means unlimited
        if ($limit === -1) {
            return true;
        }

        $current = $this->getMonthlyApiCalls($tenant);

        return $current < $limit;
    }

    /**
     * Increment API call count for the current month.
     */
    public function incrementApiCalls(Tenant|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $month = now()->format('Y-m');
        $key = "quota:api_calls:{$tenantId}:{$month}";

        TenantCache::increment($key);

        // Set expiry to end of next month (to handle month transitions)
        if (TenantCache::get($key) === 1) {
            TenantCache::put($key, 1, now()->addMonths(2)->startOfMonth());
        }
    }

    /**
     * Get quota limit for a tenant and type.
     *
     * Resolution order (highest priority first):
     *   1. TenantQuotaOverride row — what the Quotas console writes
     *   2. Legacy tenant.data['quota_overrides'] (pre-console overrides)
     *   3. Plan.limits JSON array (e.g. {"max_users": 50, "max_storage_gb": 20})
     *   4. Plan dedicated integer columns (max_users, max_storage_gb)
     *   5. QuotaEnforcementSetting.default_limit — the fleet policy
     *   6. Hardcoded tier defaults keyed by plan.slug
     *
     * Step 1 is the fix for the split-brain that made this system a lie: the
     * admin UI has always written tenant_quota_overrides, while this gate only
     * ever read tenant.data — so for every resource except ai_messages, setting
     * an override in the console changed nothing. The table is now the single
     * source of truth for all resources; step 2 keeps any pre-existing legacy
     * override honoured until it is migrated.
     *
     * @return int -1 for unlimited, 0 if not found
     */
    public function getQuotaLimit(Tenant|string $tenant, string $quotaType): int
    {
        $resource = QuotaResources::canonical($quotaType);
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        // 1. Console-set override (authoritative, all resources)
        $override = TenantQuotaOverride::activeFor((string) $tenantId, $resource);
        if ($override !== null) {
            // 0 means "explicitly unlimited" on an override row.
            return (int) $override->limit_value === 0 ? -1 : (int) $override->limit_value;
        }

        if (is_string($tenant)) {
            $tenant = Tenant::find($tenant);
        }

        if (! $tenant) {
            return $this->policyDefault($resource) ?? $this->tierDefault('free', $resource);
        }

        // 2. Legacy per-tenant override (tenant.data['quota_overrides'])
        $tenantData = $tenant->data instanceof \ArrayObject ? $tenant->data->getArrayCopy() : (array) ($tenant->data ?? []);
        $overrideValue = $tenantData['quota_overrides']["max_{$resource}"]
            ?? $tenantData['quota_overrides']["max_{$quotaType}"]
            ?? null;
        if ($overrideValue !== null) {
            return (int) $overrideValue === 0 ? -1 : (int) $overrideValue;
        }

        $plan = $tenant->plan ?? $tenant->subscription?->plan;

        if (! $plan) {
            return $this->policyDefault($resource) ?? $this->tierDefault('free', $resource);
        }

        $planKey = QuotaResources::meta($resource)['plan_key'] ?? "max_{$resource}";

        // 3. Plan.limits JSON array (flexible per-plan overrides)
        $planLimits = is_array($plan->limits) ? $plan->limits : [];
        $limitsValue = $planLimits[$planKey] ?? null;
        if ($limitsValue !== null) {
            return (int) $limitsValue === 0 ? -1 : (int) $limitsValue;
        }

        // 4. Plan dedicated columns (max_users, max_storage_gb)
        $planCol = QuotaResources::meta($resource)['plan_col'] ?? null;
        if ($planCol !== null && ($plan->{$planCol} ?? null) !== null) {
            $colValue = (int) $plan->{$planCol};

            // 0 on these columns means unlimited
            return $colValue === 0 ? -1 : $colValue;
        }

        // 5. Fleet policy default
        $policyDefault = $this->policyDefault($resource);
        if ($policyDefault !== null) {
            return $policyDefault;
        }

        // 6. Hardcoded tier defaults keyed by plan slug (seat-count inferred
        //    when the slug is not a named tier).
        return $this->tierDefault(strtolower($plan->slug ?? 'free'), $resource, (int) ($plan->max_users ?? 0) ?: null);
    }

    /**
     * The enforcement policy row for a resource, or null when unconfigured.
     * Statically memoised — the gate is hit many times per request.
     */
    public function getPolicy(string $resource): ?QuotaEnforcementSetting
    {
        $resource = QuotaResources::canonical($resource);

        if (! array_key_exists($resource, self::$policyCache)) {
            self::$policyCache[$resource] = QuotaEnforcementSetting::query()
                ->where('resource', $resource)
                ->first();
        }

        return self::$policyCache[$resource];
    }

    /** @var array<string, QuotaEnforcementSetting|null> */
    private static array $policyCache = [];

    public static function flushPolicyCache(): void
    {
        self::$policyCache = [];
    }

    /**
     * The fleet-wide default limit from the policy row, or null to fall
     * through to the tier default.
     *
     * default_limit = 0 means "no fleet cap configured for this resource" —
     * NOT unlimited. Unlimited is expressed by the plan or an override (both
     * of which use 0 = unlimited); the policy row is primarily thresholds +
     * action, and its default_limit is an optional floor. Treating 0 as
     * unlimited here would make every resource with a backfilled policy row
     * unlimited and silently kill the tier defaults.
     */
    private function policyDefault(string $resource): ?int
    {
        $policy = $this->getPolicy($resource);

        if ($policy === null || $policy->default_limit === null) {
            return null;
        }

        $value = (int) $policy->default_limit;

        return $value === 0 ? null : $value;
    }

    /**
     * Hardcoded tier default for a plan + resource (-1 unlimited, 0 none).
     *
     * Keyed by the resource's canonical plan_key (so 'api_calls' correctly
     * reads 'max_api_calls_monthly', not the non-existent 'max_api_calls').
     * When the plan's slug is not one of the four named tiers — real catalogues
     * use slugs like "business" / "growth" / "scale" — the tier is INFERRED
     * from the plan's seat count rather than silently collapsing to the free
     * tier, which used to hand a 100-seat plan a 10-employee free cap.
     *
     * Public so QuotaAdminService's fleet matrix resolves through the exact
     * same final fallback the runtime gate uses — the two must agree.
     */
    public function tierDefault(string $slug, string $resource, ?int $maxUsers = null): int
    {
        $resource = QuotaResources::canonical($resource);
        $key = QuotaResources::meta($resource)['plan_key'] ?? "max_{$resource}";
        $tier = $this->resolveTier($slug, $maxUsers);

        $value = $this->defaultQuotas[$tier][$key]
            ?? $this->defaultQuotas['free'][$key]
            ?? 0;

        return (int) $value;
    }

    /** Map a plan slug (or seat count) onto one of the four tier tables. */
    private function resolveTier(string $slug, ?int $maxUsers): string
    {
        $slug = strtolower($slug);
        if (isset($this->defaultQuotas[$slug])) {
            return $slug;
        }

        // 0 seats on a plan means "unlimited seats" → treat as enterprise.
        return match (true) {
            $maxUsers === 0 => 'enterprise',
            $maxUsers === null => 'free',
            $maxUsers >= 100 => 'professional',
            $maxUsers >= 25 => 'starter',
            default => 'free',
        };
    }

    /**
     * TenantStat columns per resource now come from QuotaResources — the one
     * registry that also knows each resource's unit divisor (storage is stored
     * in MB but quoted in GB).
     *
     * @deprecated Use QuotaResources::meta()['stat'].
     */
    protected array $statColumns = [];

    /**
     * Get current usage for a quota type.
     *
     * Resolution order (fastest → most accurate):
     * 1. TenantCache — busted by model observers on every create/delete/restore.
     * 2. TenantStat (today's row on the central DB) — avoids tenant DB query when
     *    cache is warm from a recent stats collection run.
     * 3. Live $modelClass::count() in the tenant DB — always accurate, used as
     *    ultimate fallback and to warm TenantStat-less cache entries.
     */
    public function getCurrentUsage(Tenant|string $tenant, string $quotaType): int
    {
        $resource = QuotaResources::canonical($quotaType);
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $cacheKey = "quota:usage:{$tenantId}:{$resource}";

        return TenantCache::remember($cacheKey, $this->cacheTtl, function () use ($tenantId, $resource) {
            // Cache-metered resources (AI messages) never touch tenant_stats.
            if ($resource === 'ai_messages') {
                return $this->getAiMessagesUsed((string) $tenantId);
            }

            // --- Try TenantStat (central DB, no tenant connection required) ---
            $statColumn = QuotaResources::meta($resource)['stat'] ?? null;

            if ($statColumn !== null) {
                // A monthly quota is measured month-to-date: tenant_stats holds
                // a per-DAY counter, so the latest row alone would report a
                // fraction of real consumption against a monthly cap.
                if (QuotaResources::isMonthly($resource)) {
                    $sum = TenantStat::query()
                        ->where('tenant_id', $tenantId)
                        ->whereDate('date', '>=', now()->startOfMonth())
                        ->sum($statColumn);

                    return (int) round(QuotaResources::toUnit($resource, $sum));
                }

                /** @var TenantStat|null $stat */
                $stat = TenantStat::query()
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('date')
                    ->first();

                if ($stat !== null) {
                    // toUnit converts the raw column into the resource's own
                    // unit — storage_used_mb (MB) → GB. Comparing the raw MB
                    // value against a GB limit was wrong by 1024x.
                    return (int) round(QuotaResources::toUnit($resource, $stat->{$statColumn}));
                }
            }

            // --- Fallback: live count from tenant DB ---
            $modelClass = $this->quotaModels[$resource] ?? null;

            if (! $modelClass || ! class_exists($modelClass)) {
                return 0;
            }

            return $modelClass::count();
        });
    }

    /**
     * Get storage usage in bytes.
     */
    public function getStorageUsage(Tenant|string $tenant): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $cacheKey = "quota:storage:{$tenantId}";

        return TenantCache::remember($cacheKey, $this->cacheTtl, function () use ($tenantId) {
            // Calculate storage from tenant's storage directory
            $path = "tenants/{$tenantId}";

            if (! Storage::exists($path)) {
                return 0;
            }

            return $this->calculateDirectorySize($path);
        });
    }

    /**
     * Get monthly API calls count.
     */
    public function getMonthlyApiCalls(Tenant|string $tenant): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $month = now()->format('Y-m');
        $key = "quota:api_calls:{$tenantId}:{$month}";

        return (int) TenantCache::get($key, 0);
    }

    // ── AI Assistant (Aeon) quota ──────────────────────────────────────────
    // AI is a plan attribute like any other quota: `max_ai_messages` (monthly
    // message allowance) + `ai_model` (capability tier). Enforced the same way
    // as monthly API calls; the tenant-facing assistant checks these via a
    // contract so it never depends on this package directly.

    /** Is the AI assistant available to this tenant (plan allowance or override)? */
    public function aiEnabled(Tenant|string $tenant): bool
    {
        $limit = $this->getAiMessageLimit($tenant);

        return $limit === -1 || $limit > 0;
    }

    /**
     * Monthly AI message allowance (-1 unlimited, 0 = AI not in plan).
     *
     * The override lookup that used to live here is now step 1 of
     * getQuotaLimit() for every resource, so this is a plain delegation —
     * ai_messages is no longer the one privileged resource whose console
     * override actually took effect.
     */
    public function getAiMessageLimit(Tenant|string $tenant): int
    {
        return $this->getQuotaLimit($tenant, 'ai_messages');
    }

    /** AI messages the tenant has used this billing month. */
    public function getAiMessagesUsed(Tenant|string $tenant): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $month = now()->format('Y-m');

        return (int) TenantCache::get("quota:ai_messages:{$tenantId}:{$month}", 0);
    }

    /** May the tenant send another AI message this period? */
    public function canSendAiMessage(Tenant|string $tenant): bool
    {
        if (! $this->aiEnabled($tenant)) {
            return false;
        }
        $limit = $this->getAiMessageLimit($tenant);
        if ($limit === -1) {
            return true;
        }

        return $this->getAiMessagesUsed($tenant) < $limit;
    }

    /**
     * Count one AI message against the monthly allowance.
     *
     * Read-modify-write via get/put rather than TenantCache::increment (which
     * doesn't exist) so it persists on any cache store; the ±1 race under truly
     * simultaneous requests is immaterial for message metering. Expires two
     * months out so a month boundary can never strand a stale counter.
     */
    public function incrementAiMessages(Tenant|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $month = now()->format('Y-m');
        $key = "quota:ai_messages:{$tenantId}:{$month}";

        $used = (int) TenantCache::get($key, 0);
        TenantCache::put($key, $used + 1, now()->addMonths(2)->startOfMonth());
    }

    /**
     * Model tier this plan may use: 'flash' (fast, all tiers), 'pro'
     * (premium unlocked) or 'all'. Read from the plan's `ai_model` quota,
     * default 'flash'.
     */
    public function getAiModelTier(Tenant|string $tenant): string
    {
        if (is_string($tenant)) {
            $tenant = Tenant::find($tenant);
        }
        $plan = $tenant?->plan ?? $tenant?->subscription?->plan;
        $limits = is_array($plan?->limits) ? $plan->limits : [];
        $tier = (string) ($limits['ai_model'] ?? 'flash');

        return in_array($tier, ['flash', 'pro', 'all'], true) ? $tier : 'flash';
    }

    /**
     * Compact AI usage snapshot for dashboards / the tenant-side gate.
     *
     * @return array{enabled:bool,limit:int,used:int,remaining:int,model:string,unlimited:bool,resets_at:string}
     */
    public function getAiSummary(Tenant|string $tenant): array
    {
        $limit = $this->getAiMessageLimit($tenant);
        $used = $this->getAiMessagesUsed($tenant);

        return [
            'enabled' => $limit === -1 || $limit > 0,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === -1 ? -1 : max(0, $limit - $used),
            'model' => $this->getAiModelTier($tenant),
            'unlimited' => $limit === -1,
            'resets_at' => now()->addMonth()->startOfMonth()->toIso8601String(),
        ];
    }

    /**
     * Get all quotas and usage for a tenant.
     */
    public function getQuotaSummary(Tenant|string $tenant): array
    {
        $summary = [];

        foreach (['users', 'employees', 'projects', 'customers', 'rfis'] as $type) {
            $limit = $this->getQuotaLimit($tenant, $type);
            $usage = $this->getCurrentUsage($tenant, $type);
            $percentage = $limit === -1 ? 0 : ($limit > 0 ? round(($usage / $limit) * 100, 2) : 100);

            $summary[$type] = [
                'limit' => $limit,
                'used' => $usage,
                'remaining' => $limit === -1 ? -1 : max(0, $limit - $usage),
                'percentage' => $percentage,
                'unlimited' => $limit === -1,
                'status' => $this->resolveStatus($percentage, $limit, $type),
            ];
        }

        // Storage (special handling for GB)
        $storageLimit = $this->getQuotaLimit($tenant, 'storage_gb');
        $storageUsed = $this->getStorageUsage($tenant);
        $storageUsedGb = round($storageUsed / (1024 * 1024 * 1024), 2);

        $storagePercentage = $storageLimit === -1 ? 0 : ($storageLimit > 0 ? round(($storageUsedGb / $storageLimit) * 100, 2) : 100);

        $summary['storage_gb'] = [
            'limit' => $storageLimit,
            'used' => $storageUsedGb,
            'remaining' => $storageLimit === -1 ? -1 : max(0, $storageLimit - $storageUsedGb),
            'percentage' => $storagePercentage,
            'unlimited' => $storageLimit === -1,
            'status' => $this->resolveStatus($storagePercentage, $storageLimit, 'storage_gb'),
        ];

        // Monthly API calls
        $apiLimit = $this->getQuotaLimit($tenant, 'api_calls_monthly');
        $apiUsed = $this->getMonthlyApiCalls($tenant);

        $summary['api_calls_monthly'] = [
            'limit' => $apiLimit,
            'used' => $apiUsed,
            'remaining' => $apiLimit === -1 ? -1 : max(0, $apiLimit - $apiUsed),
            'percentage' => $apiLimit === -1 ? 0 : ($apiLimit > 0 ? round(($apiUsed / $apiLimit) * 100, 2) : 100),
            'unlimited' => $apiLimit === -1,
            'resets_at' => now()->addMonth()->startOfMonth()->toIso8601String(),
        ];

        return $summary;
    }

    /**
     * Clear cached quota usage for a tenant.
     *
     * @param  string|null  $quotaType  If null, clears all
     */
    public function clearCache(Tenant|string $tenant, ?string $quotaType = null): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if ($quotaType) {
            TenantCache::forget("quota:usage:{$tenantId}:{$quotaType}");
        } else {
            foreach (array_keys($this->quotaModels) as $type) {
                TenantCache::forget("quota:usage:{$tenantId}:{$type}");
            }
            TenantCache::forget("quota:storage:{$tenantId}");
        }
    }

    /**
     * Set custom quota for a tenant (overrides plan limits).
     *
     * Stored in tenant.data['quota_overrides'] so it takes priority over
     * plan limits without needing a separate DB column.
     *
     * @param  int  $limit  -1 for unlimited, 0 to remove override
     */
    public function setCustomQuota(Tenant $tenant, string $quotaType, int $limit): void
    {
        $tenantData = $tenant->data instanceof \ArrayObject
            ? $tenant->data->getArrayCopy()
            : (array) ($tenant->data ?? []);

        $overrides = $tenantData['quota_overrides'] ?? [];

        if ($limit === 0) {
            // 0 means "remove override" — fall back to plan default
            unset($overrides["max_{$quotaType}"]);
        } else {
            $overrides["max_{$quotaType}"] = $limit;
        }

        $tenantData['quota_overrides'] = $overrides;
        $tenant->data = $tenantData;
        $tenant->save();

        $this->clearCache($tenant, $quotaType);
    }

    /**
     * Record usage for audit/billing purposes.
     *
     * @param  string  $action  increment|decrement
     */
    public function recordUsage(Tenant $tenant, string $quotaType, string $action, int $amount = 1): void
    {
        UsageRecord::create([
            'tenant_id' => $tenant->id,
            'type' => $quotaType,
            'action' => $action,
            'amount' => $amount,
            'recorded_at' => now(),
        ]);

        // Clear cache so next check gets fresh data
        $this->clearCache($tenant, $quotaType);
    }

    /**
     * Resolve a human-readable status from a usage percentage.
     *
     * Thresholds come from the resource's QuotaEnforcementSetting when one is
     * configured — the whole point of the Enforcement Settings screen, which
     * until now persisted rows that nothing ever read. 80/90/100 remain the
     * fallback for an unconfigured resource.
     *
     * @return string 'ok' | 'warning' | 'critical' | 'exceeded' | 'unlimited'
     */
    protected function resolveStatus(float $percentage, int $limit, ?string $resource = null): string
    {
        if ($limit === -1) {
            return 'unlimited';
        }

        $policy = $resource !== null ? $this->getPolicy($resource) : null;
        $warnAt = (int) ($policy->warning_threshold_pct ?? 80);
        $hardAt = (int) ($policy->hard_limit_pct ?? 100);
        $criticalAt = $warnAt + (int) round(($hardAt - $warnAt) / 2);

        if ($percentage >= $hardAt) {
            return 'exceeded';
        }

        if ($percentage >= $criticalAt) {
            return 'critical';
        }

        if ($percentage >= $warnAt) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * What enforcement does when a resource passes its hard limit:
     * 'warn' | 'throttle' | 'block'. Defaults to warn when unconfigured.
     */
    public function enforcementAction(string $resource): string
    {
        return (string) ($this->getPolicy($resource)->action ?? QuotaEnforcementSetting::ACTION_WARN);
    }

    /**
     * Calculate directory size recursively.
     *
     * @return int Size in bytes
     */
    protected function calculateDirectorySize(string $path): int
    {
        $size = 0;

        foreach (Storage::allFiles($path) as $file) {
            $size += Storage::size($file);
        }

        return $size;
    }

    /**
     * Check if tenant is approaching quota limit (80% threshold).
     */
    public function isApproachingLimit(Tenant|string $tenant, string $quotaType): bool
    {
        $limit = $this->getQuotaLimit($tenant, $quotaType);

        if ($limit === -1) {
            return false;
        }

        $current = $quotaType === 'api_calls_monthly'
            ? $this->getMonthlyApiCalls($tenant)
            : $this->getCurrentUsage($tenant, $quotaType);

        return $current >= ($limit * 0.8);
    }

    /**
     * Get tenants that are approaching or exceeding quotas.
     */
    public function getTenantsNearingQuotas(): array
    {
        $tenants = Tenant::where('status', 'active')->get();
        $warnings = [];

        foreach ($tenants as $tenant) {
            foreach (array_keys($this->quotaModels) as $type) {
                if ($this->isApproachingLimit($tenant, $type)) {
                    $warnings[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'quota_type' => $type,
                        'current' => $this->getCurrentUsage($tenant, $type),
                        'limit' => $this->getQuotaLimit($tenant, $type),
                    ];
                }
            }
        }

        return $warnings;
    }
}
