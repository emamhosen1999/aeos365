<?php

namespace Tests\Feature\Wiring;

use Aero\Platform\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Tests\TestCase;

/**
 * Axis A A2 — prove FilesystemTenancyBootstrapper actually isolates tenant storage.
 *
 * A5 restored the bootstrapper to the runtime list (it had been stripped). This
 * test pins the behavior: a file written under tenant A's filesystem context is
 * NOT visible under tenant B's — the per-tenant root override is really applied.
 * Without it, the cross-tenant file leak silently returns.
 */
class FilesystemTenancyIsolationTest extends TestCase
{
    private array $dirsToClean = [];

    protected function tearDown(): void
    {
        // Revert any tenant filesystem state and remove the scratch dirs.
        app(FilesystemTenancyBootstrapper::class)->revert();
        foreach ($this->dirsToClean as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_local_file(): void
    {
        $bootstrapper = app(FilesystemTenancyBootstrapper::class);

        $tenantA = new Tenant();
        $tenantA->id = 'fsiso-a-'.uniqid();
        $tenantB = new Tenant();
        $tenantB->id = 'fsiso-b-'.uniqid();

        // --- Tenant A writes a file ---
        $bootstrapper->bootstrap($tenantA);
        $this->dirsToClean[] = dirname(config('filesystems.disks.local.root'), 1);
        Storage::disk('local')->put('secret.txt', 'A-only');
        $this->assertTrue(Storage::disk('local')->exists('secret.txt'), 'Tenant A should see its own file.');
        $bootstrapper->revert();

        // --- Tenant B must NOT see it ---
        $bootstrapper->bootstrap($tenantB);
        $this->dirsToClean[] = dirname(config('filesystems.disks.local.root'), 1);
        $this->assertFalse(
            Storage::disk('local')->exists('secret.txt'),
            'Tenant B must not see Tenant A\'s file — filesystem tenancy is not isolating (A5 regression).'
        );
        $bootstrapper->revert();
    }

    public function test_local_root_is_suffixed_per_tenant(): void
    {
        $bootstrapper = app(FilesystemTenancyBootstrapper::class);

        $tenant = new Tenant();
        $tenant->id = 'fsiso-root-'.uniqid();

        $bootstrapper->bootstrap($tenant);
        $root = config('filesystems.disks.local.root');
        $bootstrapper->revert();

        $this->assertStringContainsString(
            $tenant->id,
            (string) $root,
            'The local disk root must be namespaced with the tenant id when tenancy is bootstrapped.'
        );
    }
}
