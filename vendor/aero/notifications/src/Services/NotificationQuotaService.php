<?php

declare(strict_types=1);

namespace Aero\Notifications\Services;

use Aero\Notifications\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves + enforces the current tenant's monthly OUTBOUND EMAIL quota.
 *
 * Reuses the exact PlanQuota + TenantQuotaOverride mechanism the Aeon AI
 * assistant quota already runs on (see Aero\Platform\Ai\PlatformAeonQuota and
 * Aero\Platform\Services\Quotas\QuotaEnforcementService::getQuotaLimit()).
 * Resource key: 'emails_per_month' (mirrors Aeon's 'ai_messages' canonical
 * key — no 'max_' prefix, matching how QuotaResources::canonical() strips
 * that prefix and how TenantQuotaOverride::activeFor() stores resources).
 *
 * Resolution order (highest priority first) — TenantQuotaOverride wins:
 *   1. TenantQuotaOverride (resource = 'emails_per_month') for the current
 *      tenant — the console-set override, authoritative when present.
 *   2. PlanQuota (key = 'emails_per_month') for the tenant's current plan —
 *      the same table Aero\Platform\Services\PlanCanonicalService writes
 *      when a platform admin edits a plan's quotas in the canonical Plans
 *      editor, so this resolves live once 'emails_per_month' is added to
 *      that editor's canonical key list (no schema change required).
 *   3. Neither configured -> fail open (unlimited).
 *
 * 0/null semantics — confirmed against QuotaEnforcementService::getQuotaLimit()
 * (packages/aero-platform/src/Services/Quotas/QuotaEnforcementService.php):
 *   - override->limit_value === 0                  -> "explicitly unlimited"
 *   - Plan.limits[...] === 0 / Plan column === 0    -> "explicitly unlimited"
 *   Applied identically here: a PlanQuota row with value === '0' also means
 *   unlimited. This service's own contract represents "unlimited" as a null
 *   `limit` (not the -1 sentinel QuotaEnforcementService returns internally)
 *   — there is no ambiguity between "unconfigured" and "unlimited" in the
 *   emailQuota() return shape: both collapse to limit = null.
 *
 * Deliberate divergence from QuotaEnforcementService::getQuotaLimit(): that
 * resolver falls through several more layers after the plan (legacy
 * tenant.data override, Plan.limits JSON, plan columns, fleet policy
 * default) and — critically — its FINAL fallback is a hardcoded tier table
 * that has no 'emails_per_month' entry, so an unconfigured tenant resolves
 * to 0 there (blocked), not unlimited. That resolver lives in aero-platform
 * and is out of this package's territory to depend on for a brand-new
 * resource; delegating to it would make every tenant "email quota exhausted"
 * the moment this ships, before any platform admin has configured anything.
 * This service instead fails OPEN (unlimited) when neither an override nor a
 * PlanQuota row exists, so shipping it can never silently start blocking
 * outbound mail for a tenant nobody has configured yet.
 */
class NotificationQuotaService
{
    /** Canonical resource / plan-quota key for outbound email. */
    public const RESOURCE = 'emails_per_month';

    public function __construct(private ?string $tenantId = null) {}

    /**
     * @return array{used:int,limit:int|null,remaining:int|null,unlimited:bool,exhausted:bool}
     */
    public function emailQuota(): array
    {
        $used = $this->used();
        $limit = $this->resolveLimit(); // null === unlimited

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'unlimited' => $limit === null,
            'exhausted' => $limit !== null && $used >= $limit,
        ];
    }

    /** Has the tenant used up its monthly email allowance? */
    public function isExhausted(): bool
    {
        return $this->emailQuota()['exhausted'];
    }

    /**
     * Pre-flight check for sending $n more emails this month. Usage itself is
     * derived from notification_logs (the single source of truth — the mail
     * pipeline writes that row when it actually sends), so there is no
     * separate counter to increment here; this simply confirms there is
     * headroom before the caller enqueues the send.
     *
     * @return bool true when the send may proceed, false when it would exceed
     *              the resolved monthly limit
     */
    public function consume(int $n = 1): bool
    {
        $quota = $this->emailQuota();

        if ($quota['unlimited']) {
            return true;
        }

        if (($quota['used'] + $n) > $quota['limit']) {
            Log::warning('Tenant email quota exceeded', [
                'tenant_id' => $this->currentTenantId(),
                'used' => $quota['used'],
                'requested' => $n,
                'limit' => $quota['limit'],
            ]);

            return false;
        }

        return true;
    }

    /** Live count of this month's `mail`-channel notification_logs rows. */
    private function used(): int
    {
        try {
            return (int) NotificationLog::query()
                ->where('channel', 'mail')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        } catch (Throwable $e) {
            Log::error('NotificationQuotaService: failed to count mail usage', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @return int|null null === unlimited
     *
     * The PlanQuota / TenantQuotaOverride / Tenant models live in aero-platform.
     * aero-notifications must NOT hard-depend on aero-platform (wrong dependency
     * direction, and aero-platform is absent in standalone), so they are resolved
     * softly by class-string: when the platform package is not installed — every
     * standalone deployment — there is no plan quota and email is unmetered.
     */
    private function resolveLimit(): ?int
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            return null; // no tenant in context -> unmetered (fail open)
        }

        $overrideClass = 'Aero\\Platform\\Models\\TenantQuotaOverride';
        $tenantClass = 'Aero\\Platform\\Models\\Tenant';
        $planQuotaClass = 'Aero\\Platform\\Models\\PlanQuota';

        if (! class_exists($overrideClass) || ! class_exists($tenantClass) || ! class_exists($planQuotaClass)) {
            return null; // standalone / no platform quota engine -> unmetered
        }

        try {
            // 1. Console-set override — authoritative when present.
            $override = $overrideClass::activeFor($tenantId, self::RESOURCE);
            if ($override !== null) {
                return (int) $override->limit_value === 0 ? null : (int) $override->limit_value;
            }

            // 2. Plan-level quota row for the tenant's current plan.
            $planId = $tenantClass::find($tenantId)?->plan?->id;
            if ($planId === null) {
                return null;
            }

            $planQuota = $planQuotaClass::query()
                ->where('plan_id', $planId)
                ->where('key', self::RESOURCE)
                ->first();

            if ($planQuota === null || $planQuota->value === null || $planQuota->value === '') {
                return null; // 3. nothing configured -> fail open
            }

            $value = (int) $planQuota->value;

            return $value === 0 ? null : $value;
        } catch (Throwable $e) {
            Log::error('NotificationQuotaService: quota resolution failed, failing open', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function currentTenantId(): ?string
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        if (! function_exists('tenant')) {
            return null;
        }

        $current = tenant();

        return $current?->id !== null ? (string) $current->id : null;
    }
}
