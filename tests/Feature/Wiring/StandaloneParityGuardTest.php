<?php

namespace Tests\Feature\Wiring;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Axis B B4 — static parity guard for standalone-eligible packages.
 *
 * Standalone ships exactly 8 packages and does NOT load aero-platform, so a
 * standalone-eligible package must never depend on a SaaS-only construct:
 *   - no HARD `use Aero\Platform\...;` imports (would be class-not-found in
 *     standalone). Guarded soft-references — class_exists('Aero\\Platform\\...')
 *     / runtime resolution — are the SANCTIONED pattern and are allowed: they
 *     no-op in standalone where the class is absent.
 *   - no NEW literal connection('central') (the 'central' connection is registered
 *     only by aero-platform). Central data must be reached via central_connection().
 *
 * The B1-B3 fixes routed audit + CentralModel through central_connection(). This
 * guard stops a regression from re-introducing a hard SaaS dependency.
 */
class StandaloneParityGuardTest extends TestCase
{
    private const STANDALONE_PACKAGES = [
        'aero-core',
        'aero-auth',
        'aero-installation',
        'aero-i18n',
        'aero-notifications',
        'aero-hrmac',
        'aero-ui',
        'aero-hrm',
    ];

    /**
     * Budget for literal connection('central') in standalone packages.
     * Last measured 2026-05-30: 1 — the try/guarded mode-detection probe in
     * aero-core helpers.php (Schema::connection('central')->hasTable('tenants')),
     * which is parity-safe (catches the missing connection and falls back to
     * standalone). ONLY decrease.
     */
    private const CENTRAL_LITERAL_BUDGET = 1;

    /**
     * Pre-existing hard `use Aero\Platform\...;` imports in standalone packages.
     * Last measured 2026-05-30: 5 files —
     *   aero-auth: AdminSetupController, ImpersonationController, LoginController,
     *              UserController (import Tenant / TenantImpersonationToken /
     *              platform Http Requests+Resources / IdentifyDomainContext)
     *   aero-hrm:  AeroHrmServiceProvider (imports AeroPlatformServiceProvider)
     *
     * These are genuine parity violations (would be class-not-found in standalone
     * if the code path is hit) and need decoupling — tracked as Axis B follow-up
     * debt. This is a RATCHET: the count may ONLY decrease. A NEW hard import
     * fails the build. Guarded class_exists('Aero\\Platform\\...') soft-references
     * are the sanctioned alternative and are not counted.
     */
    private const PLATFORM_IMPORT_BUDGET = 5;

    public function test_hard_platform_imports_within_budget(): void
    {
        $offenders = $this->scanStandalone('/^\s*use\s+Aero\\\\Platform\\\\/m');

        $this->assertLessThanOrEqual(
            self::PLATFORM_IMPORT_BUDGET,
            count($offenders),
            sprintf(
                "Hard `use Aero\\Platform\\...;` imports in standalone packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Standalone does not load aero-platform — these are class-not-found risks.\n".
                "Decouple one (or use a guarded class_exists soft-reference) and lower the budget;\n".
                "do NOT add new ones.\n\nOffenders:\n  %s",
                self::PLATFORM_IMPORT_BUDGET,
                count($offenders),
                implode("\n  ", $offenders) ?: '(none)',
            ),
        );
    }

    public function test_literal_central_connection_within_budget(): void
    {
        $offenders = $this->scanStandalone("/connection\\(['\"]central['\"]\\)/");

        $this->assertLessThanOrEqual(
            self::CENTRAL_LITERAL_BUDGET,
            count($offenders),
            sprintf(
                "Literal connection('central') in standalone packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Use central_connection() (SaaS->'central', standalone->default) instead.\n\n".
                "Offenders:\n  %s",
                self::CENTRAL_LITERAL_BUDGET,
                count($offenders),
                implode("\n  ", $offenders) ?: '(none)',
            ),
        );
    }

    private function scanStandalone(string $pattern): array
    {
        $packagesDir = base_path('../Aero-Enterprise-Suite-Saas/packages');

        if (! is_dir($packagesDir)) {
            $this->markTestSkipped("Monorepo packages dir not found at {$packagesDir}");
        }

        $offenders = [];

        foreach (self::STANDALONE_PACKAGES as $pkg) {
            $src = $packagesDir.DIRECTORY_SEPARATOR.$pkg.DIRECTORY_SEPARATOR.'src';
            if (! is_dir($src)) {
                continue;
            }

            $finder = (new Finder())->in($src)->name('*.php')->files();
            foreach ($finder as $file) {
                if (preg_match($pattern, $file->getContents())) {
                    $offenders[] = $pkg.'/src/'.$file->getRelativePathname();
                }
            }
        }

        sort($offenders);

        return $offenders;
    }
}
