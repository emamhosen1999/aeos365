<?php

namespace Tests\Feature\Wiring;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Phase 0 Task 12 — facade discipline regression guard.
 *
 * Phase 1 audit found 20+ direct `Cache::` calls inside feature packages that
 * bypass tenant scoping (since Stancl's CacheTenancyBootstrapper relies on the
 * driver supporting tagging; before Phase 0 T4 it was disabled, so direct uses
 * actively leaked across tenants). Similar risks exist with `Session::` and
 * with `Storage::disk('local')` calls that bypass the tenant disk root.
 *
 * Foundation packages (contracts/core/platform/hrmac/auth/ui/i18n/notifications/installation)
 * are allowed direct facade use — they OWN the tenancy abstraction. Everything
 * else MUST route through TenantCache / TenantStorage / the session middleware
 * that initializes tenancy.
 *
 * This test ships RED initially (Phase 1 found violations). Per-package plans
 * resolve them; this guard prevents regressions.
 */
class FacadeDisciplineTest extends TestCase
{
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

    public function test_no_direct_cache_facade_in_feature_packages(): void
    {
        $offenders = $this->scan('/\bCache::(get|put|forever|remember|forget|flush|tags)\(/');

        $this->assertEmpty(
            $offenders,
            "Direct Cache:: usage in feature packages bypasses tenant cache scoping.\n".
            "Use Aero\\Core\\Support\\TenantCache instead.\n\n".
            "Offenders:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_no_storage_disk_local_in_feature_packages(): void
    {
        $offenders = $this->scan("/Storage::disk\\('local'\\)/");

        $this->assertEmpty(
            $offenders,
            "Storage::disk('local') in feature packages bypasses tenant filesystem scoping.\n".
            "Use Storage::disk('tenant') or TenantStorage instead.\n\n".
            "Offenders:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_no_direct_session_facade_in_feature_packages(): void
    {
        $offenders = $this->scan('/\bSession::(get|put|forget|flush)\(/');

        $this->assertEmpty(
            $offenders,
            "Direct Session:: usage in feature packages bypasses tenant session scoping.\n".
            "Use request()->session() (which is tenant-aware via middleware) instead.\n\n".
            "Offenders:\n  ".implode("\n  ", $offenders)
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
