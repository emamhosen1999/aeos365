<?php

namespace Database\Seeders;

use Aero\Platform\Database\Seeders\PlatformDatabaseSeeder;
use Aero\Platform\Database\Seeders\PlatformHrmacSeeder;
use Aero\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * SaaS central-DB UAT seed. Runs on the central connection.
 *
 * Composes the platform's own seeders (plans/products/pricing + landlord HRMAC
 * roles + module-hierarchy sync) and then creates a known landlord admin
 * (landlord@aeos365.test / Password123!) for the admin domain. Idempotent.
 */
class UatPlatformSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        // 1. Plans, products, module pricing.
        $this->call(PlatformDatabaseSeeder::class);

        // 2. Seed landlord HRMAC roles ("Super Platform Admin", "Platform Admin").
        //    (This runs aero:sync-module at platform scope internally.)
        \Aero\Contracts\AeroMode::withoutTenantContextGuard(function () {
            $this->call(PlatformHrmacSeeder::class);
        });

        // 3. Sync the FULL module registry (all scopes) into central `modules`.
        //    tenant_module maps selected_modules codes -> central modules rows
        //    (TenantCreatedListener), so central must hold every code (core, hrm,
        //    ...), not just platform-scoped ones. Done AFTER PlatformHrmacSeeder so
        //    its platform-scope sync can't clear the tenant/infra modules. Run
        //    guard-disabled: sync queries tenant-scoped HRMAC Module on central.
        \Aero\Contracts\AeroMode::withoutTenantContextGuard(function () {
            Artisan::call('aero:sync-module', ['--scope' => 'all']);
        });

        // 3. Known landlord admin for the platform admin domain. assignRole()
        // resolves the (tenant-scoped) HRMAC Role model on central, so run under
        // the guard-disabled helper like PlatformHrmacSeeder does.
        \Aero\Contracts\AeroMode::withoutTenantContextGuard(function () {
            // Landlords are unified Aero\Auth\Models\User rows on the CENTRAL
            // connection (auth-identity unification, Unit 4 — LandlordUser eliminated).
            $admin = User::on('central')->firstOrCreate(
                ['email' => 'landlord@aeos365.test'],
                [
                    'name' => 'UAT Landlord',
                    'user_name' => 'uat_landlord',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );

            if (! $admin->hasAnyRole(['Super Platform Admin'])) {
                $admin->assignRole('Super Platform Admin');
            }
        });
    }
}
