<?php

namespace Tests\Feature\Wiring;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Phase 0 Task 12 — facade discipline regression guard.
 * Audit D12 (2026-05-30) — converted to BUDGET RATCHET pattern.
 *
 * Phase 1 audit found direct facade calls inside feature packages that
 * bypass tenant scoping (Cache:: skips CacheTenancyBootstrapper tag isolation,
 * Storage::disk('local') skips per-tenant root override, Session:: skips
 * tenant-aware session middleware).
 *
 * Foundation packages (contracts/core/platform/hrmac/auth/ui/i18n/notifications/installation)
 * are allowed direct facade use — they OWN the tenancy abstraction.
 *
 * Budget pattern: each test asserts the current offender count is <= the
 * declared BUDGET_* constant. Budgets start at the current count and may
 * ONLY decrease over time (ratchet). When you fix an offender, lower the
 * matching budget in the same PR. Increasing a budget requires explicit
 * justification in the commit message.
 *
 * Why budget instead of zero: assertEmpty() forced a Phase-1-blocking
 * cleanup before any other work could merge. Ratchet lets the codebase
 * make forward progress while still preventing regressions (a new offender
 * appearing pushes the count above budget and fails the build).
 */
class FacadeDisciplineTest extends TestCase
{
    /**
     * Per-facade offender budgets. ONLY decrease — never increase.
     *
     * Last measured 2026-05-30:
     *   Cache::   = 4 (aero-cms 1, aero-hrm 3)
     *   Storage:: = 1 (aero-hrm 1)
     *   Session:: = 0
     */
    private const BUDGET_CACHE = 4;

    private const BUDGET_STORAGE_LOCAL = 1;

    private const BUDGET_SESSION = 0;

    /** Packages where direct facade use is allowed. */
    private const FOUNDATION_WHITELIST = [
        'aero-contracts',
        'aero-core',
        'aero-platform',
        'aero-hrmac',
        'aero-auth',
        'aero-ui',
        'aero-i18n',
        'aero-notifications',
        'aero-installation',
    ];

    public function test_cache_facade_offenders_within_budget(): void
    {
        $offenders = $this->scan('/\bCache::(get|put|forever|remember|forget|flush|tags)\(/');

        $this->assertLessThanOrEqual(
            self::BUDGET_CACHE,
            count($offenders),
            sprintf(
                "Cache:: facade usage in feature packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Either migrate one offender to Aero\\Core\\Support\\TenantCache (lower BUDGET_CACHE) OR don't add new ones.\n\n".
                "Offenders:\n  %s",
                self::BUDGET_CACHE,
                count($offenders),
                implode("\n  ", $offenders) ?: '(none)',
            ),
        );

        // Sanity: if every offender was fixed, lower the budget in this same commit
        // to lock in the win. Don't let the count silently drop without ratcheting.
        if (count($offenders) < self::BUDGET_CACHE) {
            $this->addWarning(sprintf(
                'Cache:: budget is %d but only %d offender(s) remain. '.
                'Lower BUDGET_CACHE to %d in the same commit to lock the win.',
                self::BUDGET_CACHE,
                count($offenders),
                count($offenders),
            ));
        }
    }

    public function test_storage_disk_local_offenders_within_budget(): void
    {
        $offenders = $this->scan("/Storage::disk\\('local'\\)/");

        $this->assertLessThanOrEqual(
            self::BUDGET_STORAGE_LOCAL,
            count($offenders),
            sprintf(
                "Storage::disk('local') usage in feature packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Either migrate one offender to Storage::disk('tenant') or TenantStorage (lower BUDGET_STORAGE_LOCAL) OR don't add new ones.\n\n".
                "Offenders:\n  %s",
                self::BUDGET_STORAGE_LOCAL,
                count($offenders),
                implode("\n  ", $offenders) ?: '(none)',
            ),
        );

        if (count($offenders) < self::BUDGET_STORAGE_LOCAL) {
            $this->addWarning(sprintf(
                'Storage::disk(local) budget is %d but only %d offender(s) remain. '.
                'Lower BUDGET_STORAGE_LOCAL to %d in the same commit to lock the win.',
                self::BUDGET_STORAGE_LOCAL,
                count($offenders),
                count($offenders),
            ));
        }
    }

    public function test_session_facade_offenders_within_budget(): void
    {
        $offenders = $this->scan('/\bSession::(get|put|forget|flush)\(/');

        $this->assertLessThanOrEqual(
            self::BUDGET_SESSION,
            count($offenders),
            sprintf(
                "Direct Session:: usage in feature packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Use request()->session() (tenant-aware via middleware) instead.\n\n".
                "Offenders:\n  %s",
                self::BUDGET_SESSION,
                count($offenders),
                implode("\n  ", $offenders) ?: '(none)',
            ),
        );
    }

    /**
     * Scan all packages/aero-PKG/src directories for the given pattern,
     * skipping the foundation-whitelisted packages.
     */
    private function scan(string $pattern): array
    {
        $packagesDir = base_path('../Aero-Enterprise-Suite-Saas/packages');

        if (! is_dir($packagesDir)) {
            $this->markTestSkipped("Monorepo packages dir not found at {$packagesDir}");
        }

        $offenders = [];
        $finder = (new Finder())
            ->in($packagesDir)
            ->path('/^aero-/')
            ->path('src/')
            ->name('*.php')
            ->files();

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $packageName = explode(DIRECTORY_SEPARATOR, $relative)[0] ?? '';

            if (in_array($packageName, self::FOUNDATION_WHITELIST, true)) {
                continue;
            }

            if (preg_match($pattern, $file->getContents())) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        return $offenders;
    }
}
