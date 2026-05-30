<?php

namespace Tests\Feature\Wiring;

use Aero\Platform\Bootstrappers\CachePrefixTenancyBootstrapper;
use Aero\Platform\Bootstrappers\FailClosedQueueTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Tests\TestCase;

/**
 * Axis A A5 + A6 (2026-05-30) — pins the BOOTED runtime tenancy config.
 *
 * The previous regression test (TenancyConfigTest in aero-platform) asserts on
 * the config FILE via require(). That was a false-green: AeroPlatformServiceProvider::boot()
 * overrode config/tenancy.php at runtime with a list that dropped
 * FilesystemTenancyBootstrapper entirely (cross-tenant file leak) and reverted the
 * fail-closed queue bootstrapper to stock. The file said one thing; the running
 * app did another.
 *
 * This test reads config('tenancy.bootstrappers') AFTER full app boot, so the
 * file and the runtime can never silently diverge again.
 */
class TenancyRuntimeConfigTest extends TestCase
{
    public function test_runtime_bootstrappers_include_filesystem_isolation(): void
    {
        $bootstrappers = config('tenancy.bootstrappers');

        $this->assertContains(
            FilesystemTenancyBootstrapper::class,
            $bootstrappers,
            'FilesystemTenancyBootstrapper MUST be in the booted tenancy.bootstrappers list. '.
            'Without it, every tenant Storage::disk(local|public|s3) writes to one shared '.
            'root = cross-tenant file leak (Axis A A5).'
        );
    }

    public function test_runtime_bootstrappers_use_fail_closed_queue(): void
    {
        $bootstrappers = config('tenancy.bootstrappers');

        $this->assertContains(
            FailClosedQueueTenancyBootstrapper::class,
            $bootstrappers,
            'FailClosedQueueTenancyBootstrapper (Audit D5c) MUST be the active queue bootstrapper.'
        );

        $this->assertNotContains(
            QueueTenancyBootstrapper::class,
            $bootstrappers,
            'Stock QueueTenancyBootstrapper must NOT be active — FailClosedQueueTenancyBootstrapper '.
            'extends it and adds the suspended/deleted-tenant guard.'
        );
    }

    public function test_runtime_uses_driver_agnostic_cache_bootstrapper(): void
    {
        $bootstrappers = config('tenancy.bootstrappers');

        $this->assertContains(
            CachePrefixTenancyBootstrapper::class,
            $bootstrappers,
            'CachePrefixTenancyBootstrapper (driver-agnostic key prefix) is the chosen cache '.
            'isolation bootstrapper — works without a tagging store (Axis A A5).'
        );
    }

    public function test_runtime_database_bootstrapper_present(): void
    {
        $this->assertContains(
            DatabaseTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
        );
    }

    public function test_runtime_filesystem_disks_cover_local_public_s3(): void
    {
        $disks = config('tenancy.filesystem.disks');

        foreach (['local', 'public', 's3'] as $disk) {
            $this->assertContains(
                $disk,
                $disks,
                "tenancy.filesystem.disks must contain '{$disk}' so it is tenant-namespaced at runtime.",
            );
        }
    }
}
