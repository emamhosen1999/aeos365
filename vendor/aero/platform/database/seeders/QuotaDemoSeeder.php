<?php

namespace Aero\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QuotaDemoSeeder
 *
 * Seeds the tables behind the platform Quota command center (/quotas) so the
 * fleet utilisation matrix, plan quota catalogue, override ledger and breach
 * warnings are all populated for demo/FYP purposes:
 *
 *   1. tenant_stats           — 90 days of trending daily usage per demo
 *                                tenant, sized against each tenant's REAL plan
 *                                limits (plans.limits / plans.max_users /
 *                                plans.max_storage_gb — the values
 *                                QuotaAdminService::resolveLimit() actually
 *                                reads) so the console's OK / warning /
 *                                critical / exceeded spread is genuine, not
 *                                just plausible-looking numbers.
 *   2. plan_quotas             — the normalized per-plan quota registry (one
 *                                row per canonical resource per plan), tiered
 *                                free/starter/professional/enterprise.
 *   3. tenant_quota_overrides  — ~12 console-set overrides across 6-8 demo
 *                                tenants, mixing permanent / future /
 *                                expiring-within-7-days, every limit a valid
 *                                raise (>= current usage in the resource's
 *                                own unit).
 *   4. quota_warnings          — ~9 breach rows matching the exceeded /
 *                                critical / warning tenants tenant_stats
 *                                produces (a couple dismissed).
 *
 * Idempotent: tenant_stats / tenant_quota_overrides / quota_warnings are
 * cleared for demo tenants, and plan_quotas is cleared for every plan, before
 * rebuilding — re-running produces the same shape (values differ within
 * their designed ranges since mt_srand only fixes the *sequence*, not that
 * every value is byte-identical across the codebase's use of mt_rand
 * elsewhere in the same request).
 *
 * Schema/behaviour assumptions verified against the current codebase:
 *   - QuotaAdminService::resolveLimit() (the console's actual limit source)
 *     reads plans.limits JSON / plans.max_users / plans.max_storage_gb, NOT
 *     the plan_quotas table — plan_quotas today only backs
 *     Plan::getCanonicalQuotasAttribute() (the Plans catalogue editor). Both
 *     are still seeded per spec; tenant_stats sizing is deliberately anchored
 *     to the values the console actually reads (users, storage_gb) so the
 *     fleet spread renders correctly today.
 *   - api_calls / employees / projects have no plan-level default in the
 *     seeded plans (PlanSeeder only sets max_users/max_storage_gb/
 *     max_ai_messages) and fall back to the fleet-wide
 *     quota_enforcement_settings policy row (default_limit = 0 = unlimited)
 *     unless a tenant_quota_overrides row exists for that tenant+resource.
 *     Those three resources are still seeded with realistic, trending usage
 *     for chart/analytics realism even though the console shows them as
 *     unlimited absent an override.
 *   - ai_messages is cache-metered (TenantCache), never touches tenant_stats
 *     — skipped there per spec, and skipped in overrides too because seeded
 *     usage would always read back as 0.
 *   - Landlord staff now live in the central `users` table (post
 *     Auth-Identity Unification), not a separate `landlord_users` table —
 *     used for tenant_quota_overrides.set_by / quota_warnings.dismissed_by.
 *
 * Run: php artisan db:seed --class="Aero\Platform\Database\Seeders\QuotaDemoSeeder"
 */
class QuotaDemoSeeder extends Seeder
{
    /** Canonical resource keys, matching QuotaResources::ALL order. */
    private const RESOURCES = ['users', 'storage_gb', 'api_calls', 'ai_messages', 'employees', 'projects'];

    private const UNITS = [
        'users' => 'seats',
        'storage_gb' => 'GB',
        'api_calls' => 'calls/mo',
        'ai_messages' => 'msgs/mo',
        'employees' => 'people',
        'projects' => 'projects',
    ];

    /** Canonical resource -> plan limit key (mirrors QuotaResources::ALL). */
    private const PLAN_KEYS = [
        'users' => 'max_users',
        'storage_gb' => 'max_storage_gb',
        'api_calls' => 'max_api_calls_monthly',
        'ai_messages' => 'max_ai_messages',
        'employees' => 'max_employees',
        'projects' => 'max_projects',
    ];

    /** Mirrors QuotaEnforcementService::$defaultQuotas tier table. */
    private const TIER_DEFAULTS = [
        'free' => ['users' => 5, 'storage_gb' => 1, 'api_calls' => 10000, 'ai_messages' => 200, 'employees' => 10, 'projects' => 3],
        'starter' => ['users' => 25, 'storage_gb' => 10, 'api_calls' => 100000, 'ai_messages' => 1000, 'employees' => 50, 'projects' => 20],
        'professional' => ['users' => 100, 'storage_gb' => 50, 'api_calls' => 500000, 'ai_messages' => 5000, 'employees' => 200, 'projects' => 100],
        'enterprise' => ['users' => 0, 'storage_gb' => 0, 'api_calls' => 0, 'ai_messages' => 0, 'employees' => 0, 'projects' => 0],
    ];

    public function run(): void
    {
        mt_srand(20260718);
        $conn = DB::connection('central');

        $demoTenants = $conn->table('tenants')
            ->where('id', 'like', 'demo-%')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'status']);

        if ($demoTenants->isEmpty()) {
            $this->command?->info('  - QuotaDemoSeeder: no demo-% tenants found, skipping.');

            return;
        }

        $demoIds = $demoTenants->pluck('id')->all();

        $this->seedPlanQuotas($conn);

        $capacities = $this->resolveCapacities($conn, $demoTenants);
        [$statusByTenant, $plan90] = $this->buildStatusPlan($demoIds, $capacities);
        $finalUsage = $this->generateTenantStats($conn, $demoIds, $demoIds, $capacities, $plan90);
        $overrides = $this->seedOverrides($conn, $demoIds, $statusByTenant, $finalUsage);
        $warningCount = $this->seedWarnings($conn, $demoIds, $statusByTenant, $plan90);

        $this->command?->info(sprintf(
            '  ✓ quota demo data seeded: %d tenants x 90d tenant_stats, plan_quotas for all plans, %d overrides across %d tenants, %d breach warnings',
            count($demoIds),
            $overrides['row_count'],
            $overrides['tenant_count'],
            $warningCount
        ));
    }

    /* ------------------------------------------------------------------ */
    /* 2. plan_quotas                                                      */
    /* ------------------------------------------------------------------ */

    private function seedPlanQuotas(object $conn): void
    {
        $plans = $conn->table('plans')->get(['id', 'slug', 'name']);

        if ($plans->isEmpty()) {
            return;
        }

        $planIds = $plans->pluck('id')->all();
        $conn->table('plan_quotas')->whereIn('plan_id', $planIds)->delete();

        $now = now();
        $order = array_flip(self::RESOURCES);
        $rows = [];

        foreach ($plans as $plan) {
            $slug = strtolower((string) $plan->slug);
            $tier = array_key_exists($slug, self::TIER_DEFAULTS) ? $slug : 'free';

            foreach (self::TIER_DEFAULTS[$tier] as $key => $value) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'key' => $key,
                    'value' => (string) $value,
                    'unit' => self::UNITS[$key],
                    'metadata' => json_encode(['tier' => $tier, 'seeded_by' => 'QuotaDemoSeeder']),
                    'sort_order' => $order[$key] + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $conn->table('plan_quotas')->insert($chunk);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Plan-derived reference capacities per tenant                        */
    /* ------------------------------------------------------------------ */

    /**
     * The effective limit the console will resolve for every tenant x resource,
     * computed with the EXACT same chain as QuotaAdminService::resolveLimit /
     * QuotaEnforcementService::getQuotaLimit — plan.limits -> plan column ->
     * tier default (seat-inferred when the slug is not a named tier). Usage is
     * later sized against these limits so the rendered utilisation % is exactly
     * the designed target, instead of a heuristic ref that silently diverges
     * from the console (a tenant with no active sub resolves to the free tier,
     * not "starter", so its usage must be sized against 5 seats / 1 GB).
     *
     * A resource limit of -1 means unlimited for that resource.
     *
     * @return array<string, array{limits:array<string,int>,monthly_amount:float,all_unlimited:bool}>
     */
    private function resolveCapacities(object $conn, Collection $demoTenants): array
    {
        $demoIds = $demoTenants->pluck('id')->all();

        $subs = $conn->table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.tenant_id', $demoIds)
            ->orderBy('s.created_at')
            ->get(['s.tenant_id', 's.status as sub_status', 's.amount', 'p.slug', 'p.limits', 'p.max_users', 'p.max_storage_gb']);

        // Prefer the active/trialing subscription — the same one the console's
        // plansByTenant join selects.
        $planByTenant = [];
        foreach ($subs as $row) {
            $preferred = in_array($row->sub_status, ['active', 'trialing'], true);
            if (! isset($planByTenant[$row->tenant_id]) || $preferred) {
                $planByTenant[$row->tenant_id] = $row;
            }
        }
        // Drop any non-active leftover when an active one wasn't found but the
        // console would see none either.
        foreach ($planByTenant as $tid => $row) {
            if (! in_array($row->sub_status, ['active', 'trialing'], true)) {
                unset($planByTenant[$tid]);
            }
        }

        $capacities = [];
        foreach ($demoTenants as $t) {
            $sub = $planByTenant[$t->id] ?? null;
            $limits = ($sub && $sub->limits) ? (json_decode((string) $sub->limits, true) ?: []) : [];
            $slug = strtolower((string) ($sub->slug ?? 'free'));
            $maxUsers = (int) ($sub->max_users ?? 0);
            $maxStorage = (int) ($sub->max_storage_gb ?? 0);

            $eff = [];
            foreach (self::RESOURCES as $res) {
                if ($res === 'ai_messages') {
                    continue; // cache-metered, no tenant_stats usage
                }
                $eff[$res] = $this->effectiveLimit($res, $slug, $limits, $maxUsers, $maxStorage, $sub !== null);
            }

            $capacities[$t->id] = [
                'limits' => $eff,
                'monthly_amount' => (float) ($sub->amount ?? 0),
                'all_unlimited' => count(array_filter($eff, fn ($v) => $v !== -1)) === 0,
            ];
        }

        return $capacities;
    }

    /**
     * Mirror of the console's limit resolution for one resource.
     * Returns -1 for unlimited, else a positive cap.
     */
    private function effectiveLimit(string $resource, string $slug, array $planLimits, int $maxUsers, int $maxStorage, bool $hasSub): int
    {
        $planKey = self::PLAN_KEYS[$resource] ?? "max_{$resource}";

        // 1. plan.limits JSON
        if (array_key_exists($planKey, $planLimits) && $planLimits[$planKey] !== null) {
            $v = (int) $planLimits[$planKey];

            return $v === 0 ? -1 : $v;
        }

        // 2. dedicated plan columns (users, storage only)
        if ($resource === 'users' && $maxUsers !== 0) {
            return $maxUsers;
        }
        if ($resource === 'storage_gb' && $maxStorage !== 0) {
            return $maxStorage;
        }
        if (($resource === 'users' && $maxUsers === 0 && $hasSub)
            || ($resource === 'storage_gb' && $maxStorage === 0 && $hasSub)) {
            return -1; // 0 on the column means unlimited
        }

        // 3. tier default (seat-inferred), free when there is no subscription
        $tier = $this->resolveTier($slug, $hasSub ? $maxUsers : null);
        $v = (int) (self::TIER_DEFAULTS[$tier][$resource] ?? self::TIER_DEFAULTS['free'][$resource] ?? 0);

        return $v === 0 ? -1 : $v;
    }

    private function resolveTier(string $slug, ?int $maxUsers): string
    {
        if (array_key_exists($slug, self::TIER_DEFAULTS)) {
            return $slug;
        }

        return match (true) {
            $maxUsers === 0 => 'enterprise',
            $maxUsers === null => 'free',
            $maxUsers >= 100 => 'professional',
            $maxUsers >= 25 => 'starter',
            default => 'free',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Deliberate OK/warning/critical/exceeded spread                      */
    /* ------------------------------------------------------------------ */

    /**
     * @param  list<string>  $tenantIdsInOrder
     * @return array{0: array<string,string>, 1: array<string, array{status:string,hot:string,target:float,start:float,active_frac:float,baseline:array<string,float>}>}
     */
    private function buildStatusPlan(array $tenantIdsInOrder, array $capacities): array
    {
        $limitedTenants = [];
        $unlimitedTenants = [];
        foreach ($tenantIdsInOrder as $tid) {
            // A tenant counts as "unlimited" for spread purposes only when its
            // two headline resources (users + storage) are both uncapped.
            $limits = $capacities[$tid]['limits'];
            if (($limits['users'] ?? -1) === -1 && ($limits['storage_gb'] ?? -1) === -1) {
                $unlimitedTenants[] = $tid;
            } else {
                $limitedTenants[] = $tid;
            }
        }

        // 3 over-limit, 3 critical, 4 warning — remainder OK. Clipped to
        // however many non-unlimited demo tenants actually exist.
        $queue = array_merge(array_fill(0, 3, 'over'), array_fill(0, 3, 'critical'), array_fill(0, 4, 'warning'));
        $queue = array_slice($queue, 0, count($limitedTenants));

        $statusByTenant = [];
        foreach ($limitedTenants as $i => $tid) {
            $statusByTenant[$tid] = $queue[$i] ?? 'ok';
        }
        foreach ($unlimitedTenants as $tid) {
            $statusByTenant[$tid] = 'unlimited';
        }

        $plan90 = [];
        $hotToggle = 0;
        foreach ($tenantIdsInOrder as $idx => $tid) {
            $status = $statusByTenant[$tid];
            $target = match ($status) {
                'over' => mt_rand(1010, 1180) / 10,
                'critical' => mt_rand(900, 999) / 10,
                'warning' => mt_rand(800, 899) / 10,
                'unlimited' => mt_rand(300, 700) / 10,
                default => mt_rand(200, 750) / 10,
            };

            // The hot resource must be one this tenant actually has CAPPED,
            // otherwise the designed target would land on an unlimited row and
            // never render a status. At-risk/over tenants rotate through their
            // capped resources so the breach spread covers the whole registry.
            $capped = array_values(array_filter(
                ['users', 'storage_gb', 'api_calls', 'employees', 'projects'],
                fn (string $r) => ($capacities[$tid]['limits'][$r] ?? -1) > 0
            ));

            if ($capped === []) {
                $hot = 'users';
            } elseif (in_array($status, ['over', 'critical', 'warning'], true)) {
                $hot = $capped[$hotToggle % count($capped)];
                $hotToggle++;
            } else {
                $hot = $capped[$idx % count($capped)];
            }

            $baseline = [
                'users' => mt_rand(200, 650) / 10,
                'storage_gb' => mt_rand(200, 650) / 10,
                'api_calls' => mt_rand(200, 650) / 10,
                'employees' => mt_rand(200, 650) / 10,
                'projects' => mt_rand(200, 650) / 10,
            ];
            $baseline[$hot] = $target;

            $plan90[$tid] = [
                'status' => $status,
                'hot' => $hot,
                'target' => $target,
                'start' => 0.55 + mt_rand(0, 20) / 100,
                'active_frac' => 0.6 + mt_rand(0, 30) / 100,
                'baseline' => $baseline,
            ];
        }

        return [$statusByTenant, $plan90];
    }

    /* ------------------------------------------------------------------ */
    /* 1. tenant_stats                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * @param  list<string>  $demoIds
     * @param  list<string>  $tenantIdsInOrder
     * @return array<string, array{users:int,storage_gb:float,api_calls:int,employees:int,projects:int}>
     */
    private function generateTenantStats(object $conn, array $demoIds, array $tenantIdsInOrder, array $capacities, array $plan90): array
    {
        $conn->table('tenant_stats')->whereIn('tenant_id', $demoIds)->delete();

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth()->startOfDay();
        $rows = [];
        $finalUsage = [];
        $mtdApi = [];

        // Nominal sizes used only for resources the tenant has UNLIMITED, so
        // the 90-day charts still show believable absolute numbers.
        $nominal = ['users' => 140, 'storage_gb' => 220, 'api_calls' => 9000, 'employees' => 260, 'projects' => 60];

        foreach ($tenantIdsInOrder as $tid) {
            $cap = $capacities[$tid];
            $meta = $plan90[$tid];
            $baseline = $meta['baseline'];
            $start = $meta['start'];
            $activeFrac = $meta['active_frac'];

            // Usage is sized against the tenant's REAL effective limit, so the
            // latest day renders at exactly the designed utilisation target.
            $basisFor = function (string $res) use ($cap, $nominal): float {
                $limit = $cap['limits'][$res] ?? -1;

                return $limit > 0 ? (float) $limit : (float) $nominal[$res];
            };

            for ($d = 0; $d < 90; $d++) {
                $date = $today->copy()->subDays(89 - $d);
                $isLast = $d === 89;
                $progress = $d / 89;
                $growth = $start + (1 - $start) * $progress;
                $jitter = 1 + (mt_rand(-400, 400) / 10000);
                // Latest day lands exactly on the deliberate target; earlier
                // days trend upward toward it with small jitter.
                $factor = $isLast ? 1.0 : $growth * $jitter;

                $usersVal = max(1, (int) round($basisFor('users') * ($baseline['users'] / 100) * $factor));
                $storageGbVal = round($basisFor('storage_gb') * ($baseline['storage_gb'] / 100) * $factor, 2);
                $storageMbVal = max(0, (int) round($storageGbVal * 1024));
                // api_calls is a MONTHLY quota but tenant_stats.api_requests is
                // a per-day counter, so a day is ~1/30th of the monthly target.
                $apiVal = max(0, (int) round($basisFor('api_calls') * ($baseline['api_calls'] / 100) * $factor / 30));
                $employeesVal = max(1, (int) round($basisFor('employees') * ($baseline['employees'] / 100) * $factor));
                $projectsVal = max(0, (int) round($basisFor('projects') * ($baseline['projects'] / 100) * $factor));
                $activeUsersVal = min($usersVal, (int) round($usersVal * $activeFrac));

                $mrr = $cap['monthly_amount'];
                $totalRevenue = round($mrr * (1 + $d / 90), 2);
                $totalDocuments = $employeesVal * mt_rand(3, 15);

                $rows[] = [
                    'tenant_id' => $tid,
                    'date' => $date->toDateString(),
                    'total_users' => $usersVal,
                    'active_users' => $activeUsersVal,
                    'total_revenue' => $totalRevenue,
                    'mrr' => $mrr,
                    'active_projects' => $projectsVal,
                    'total_documents' => $totalDocuments,
                    'total_employees' => $employeesVal,
                    'storage_used_mb' => $storageMbVal,
                    'api_requests' => $apiVal,
                    'module_usage' => null,
                    'created_at' => $date->copy()->setTime(23, 55),
                ];

                // api_calls is metered month-to-date by the console, so track
                // the running month total — an override sized against a single
                // day would read as a massive breach once summed.
                if ($date->greaterThanOrEqualTo($monthStart)) {
                    $mtdApi[$tid] = ($mtdApi[$tid] ?? 0) + $apiVal;
                }

                if ($isLast) {
                    $finalUsage[$tid] = [
                        'users' => $usersVal,
                        'storage_gb' => $storageGbVal,
                        'api_calls' => $mtdApi[$tid] ?? $apiVal,
                        'employees' => $employeesVal,
                        'projects' => $projectsVal,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $conn->table('tenant_stats')->insert($chunk);
        }

        return $finalUsage;
    }

    /* ------------------------------------------------------------------ */
    /* 3. tenant_quota_overrides                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  list<string>  $demoIds
     * @return array{tenant_count:int,row_count:int}
     */
    private function seedOverrides(object $conn, array $demoIds, array $statusByTenant, array $finalUsage): array
    {
        $conn->table('tenant_quota_overrides')->whereIn('tenant_id', $demoIds)->delete();

        $overTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'over'));
        $criticalTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'critical'));
        $warningTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'warning'));
        $okTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'ok'));

        // Sales/ops narrative: raise limits for tenants near/at their cap,
        // plus a couple of healthy tenants for migration/trial-style raises.
        $overrideTenants = array_slice(array_values(array_unique(array_merge(
            $overTids,
            $criticalTids,
            array_slice($warningTids, 0, 1),
            array_slice($okTids, 0, 3)
        ))), 0, 8);

        $n = count($overrideTenants);
        if ($n === 0) {
            return ['tenant_count' => 0, 'row_count' => 0];
        }

        $setBy = (int) ($conn->table('users')->orderBy('id')->value('id') ?? 0) ?: null;
        $now = now();

        $reasons = [
            'Black-friday burst — approved by Sales',
            'Migration window — temporary capacity increase',
            'Enterprise trial extension',
            'Quarter-end reporting spike',
            'Customer success escalation — goodwill increase',
            'Data import backlog clearance',
            'Regional expansion pilot',
            'Contract renewal — pre-approved headroom',
            'Onboarding surge for new department rollout',
            'Support ticket backlog — temporary raise',
            'Analytics export project',
            'Seasonal traffic spike coverage',
        ];

        // [slot index into $overrideTenants, resource, expiry mode, unlimited?]
        // ai_messages is deliberately excluded — it is cache-metered and
        // always reads back as 0 usage from a seeder, which would make a
        // "valid raise >= usage" check meaningless.
        $spec = [
            [0, 'storage_gb', 'future', false],
            [0, 'employees', 'permanent', false],
            [1, 'users', 'expiring', false],
            [1, 'api_calls', 'permanent', false],
            [2, 'storage_gb', 'expiring', false],
            [3, 'projects', 'permanent', false],
            [3, 'users', 'future', false],
            [4, 'api_calls', 'expiring', false],
            [4, 'employees', 'future', false],
            [5, 'storage_gb', 'permanent', true],
            [6, 'users', 'permanent', true],
            [7, 'projects', 'expiring', false],
        ];

        $insertRows = [];
        $usedTenants = [];

        foreach ($spec as $i => [$slot, $resource, $mode, $unlimited]) {
            $tid = $overrideTenants[$slot % $n];
            $usage = $finalUsage[$tid][$resource] ?? 0;

            // 0 means "unlimited" on an override — always a valid raise.
            // Otherwise a generous 1.5x-2.0x current usage headroom.
            $limitValue = $unlimited
                ? 0
                : (int) ceil(max(1, $usage) * (1.5 + mt_rand(0, 50) / 100));

            $expiresAt = match ($mode) {
                'future' => $now->copy()->addDays(mt_rand(30, 120)),
                'expiring' => $now->copy()->addDays(mt_rand(1, 6))->addHours(mt_rand(1, 20)),
                default => null, // permanent
            };

            $insertRows[] = [
                'tenant_id' => $tid,
                'resource' => $resource,
                'limit_value' => $limitValue,
                'reason' => $reasons[$i % count($reasons)],
                'expires_at' => $expiresAt,
                'set_by' => $setBy,
                'created_at' => $now->copy()->subDays(mt_rand(1, 45)),
                'updated_at' => $now,
            ];
            $usedTenants[$tid] = true;
        }

        $conn->table('tenant_quota_overrides')->insert($insertRows);

        return ['tenant_count' => count($usedTenants), 'row_count' => count($insertRows)];
    }

    /* ------------------------------------------------------------------ */
    /* 4. quota_warnings                                                   */
    /* ------------------------------------------------------------------ */

    /** @param  list<string>  $demoIds */
    private function seedWarnings(object $conn, array $demoIds, array $statusByTenant, array $plan90): int
    {
        $conn->table('quota_warnings')->whereIn('tenant_id', $demoIds)->delete();

        $overTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'over'));
        $criticalTids = array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'critical'));
        $warningTids = array_slice(array_keys(array_filter($statusByTenant, fn (string $s) => $s === 'warning')), 0, 3);

        $setBy = (int) ($conn->table('users')->orderBy('id')->value('id') ?? 0) ?: null;
        $now = now();
        $rows = [];

        foreach ($overTids as $tid) {
            $rows[] = $this->warningRow($tid, $plan90[$tid], 'block', false, null, null, $now);
        }
        foreach ($criticalTids as $tid) {
            $rows[] = $this->warningRow($tid, $plan90[$tid], 'critical', false, null, null, $now);
        }
        foreach ($warningTids as $i => $tid) {
            $dismissed = $i < 2; // 1-2 of the warning-tier rows are dismissed
            $rows[] = $this->warningRow(
                $tid,
                $plan90[$tid],
                'warning',
                $dismissed,
                $dismissed ? $now->copy()->subDays(mt_rand(1, 3)) : null,
                $dismissed ? $setBy : null,
                $now
            );
        }

        if (! empty($rows)) {
            $conn->table('quota_warnings')->insert($rows);
        }

        return count($rows);
    }

    /** @param  array{hot:string,target:float}  $meta */
    private function warningRow(
        string $tenantId,
        array $meta,
        string $thresholdType,
        bool $dismissed,
        ?Carbon $dismissedAt,
        ?int $dismissedBy,
        Carbon $now
    ): array {
        return [
            'tenant_id' => $tenantId,
            'quota_type' => $meta['hot'],
            'percentage' => round($meta['target'], 2),
            'threshold_type' => $thresholdType,
            'first_warned_at' => $now->copy()->subDays(mt_rand(3, 10)),
            'last_warned_at' => $now->copy()->subDays(mt_rand(0, 2)),
            'warning_count' => mt_rand(1, 6),
            'is_dismissed' => $dismissed,
            'dismissed_at' => $dismissedAt,
            'dismissed_by_user_id' => $dismissedBy,
            'created_at' => $now->copy()->subDays(mt_rand(5, 12)),
            'updated_at' => $now,
        ];
    }
}
