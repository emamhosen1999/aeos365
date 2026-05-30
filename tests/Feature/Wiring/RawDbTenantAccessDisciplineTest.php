<?php

namespace Tests\Feature\Wiring;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Axis A A3 — raw DB access discipline ratchet.
 *
 * The TenantModel global scope (and EnforcesTenantContext trait) only guards
 * Eloquent queries. Raw DB::table('employees') / DB::statement() bypass the
 * tenant-context guard entirely and run against whatever connection is active.
 *
 * Foundation packages OWN the tenancy/DB abstraction and legitimately use raw
 * DB; this ratchet targets the FEATURE packages, where raw tenant-table access
 * should go through Eloquent (which carries the guard).
 *
 * Budget pattern (same as FacadeDisciplineTest): the offender FILE count must
 * stay <= BUDGET and may ONLY decrease. Migrate a file to Eloquent -> lower the
 * budget in the same commit. A new raw-DB file pushes the count over budget and
 * fails the build.
 */
class RawDbTenantAccessDisciplineTest extends TestCase
{
    /**
     * Offender files using raw DB::table()/DB::statement() in feature packages.
     * Last measured 2026-05-30: 70 (aero-hrm 61, aero-project 5, aero-rfi 2,
     * aero-compliance 1, aero-pos 1). ONLY decrease.
     */
    private const BUDGET = 70;

    /** Packages that OWN the tenancy/DB abstraction — raw DB allowed. */
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

    public function test_raw_db_access_in_feature_packages_within_budget(): void
    {
        $offenders = $this->scan('/\bDB::(table|statement)\(/');

        $this->assertLessThanOrEqual(
            self::BUDGET,
            count($offenders),
            sprintf(
                "Raw DB::table()/DB::statement() in feature packages exceeded budget.\n".
                "Budget: %d, current: %d.\n".
                "Raw DB access bypasses the TenantModel tenant-context guard. Migrate one to\n".
                "Eloquent (which carries the guard) and lower BUDGET, OR don't add new ones.\n\n".
                "Offenders (first 15):\n  %s",
                self::BUDGET,
                count($offenders),
                implode("\n  ", array_slice($offenders, 0, 15)) ?: '(none)',
            ),
        );

        if (count($offenders) < self::BUDGET) {
            $this->addWarning(sprintf(
                'Raw-DB budget is %d but only %d offender file(s) remain. Lower BUDGET to %d to lock the win.',
                self::BUDGET,
                count($offenders),
                count($offenders),
            ));
        }
    }

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

            // Only count application source — migrations (database/) and tests/
            // legitimately use raw DB and would inflate the budget. (The shared
            // Finder ->path() calls are OR'd, so filter to src/ explicitly here.)
            if (! str_contains($relative, DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR)) {
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
