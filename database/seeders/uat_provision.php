<?php

/**
 * UAT SaaS tenant provisioning script.
 *
 * Run via: php artisan tinker --execute="require database_path('seeders/uat_provision.php');"
 * Idempotent: drops + recreates the known UAT tenant each run.
 *
 * Creates the test tenant (subdomain "uatco"), attaches the HRM module via the
 * tenant_module pivot so ProvisionTenant::getActiveModules() returns ['hrm'],
 * provisions synchronously, then seeds the tenant DB with the HRM dataset.
 */

use Aero\Platform\Jobs\ProvisionTenant;
use Aero\Platform\Models\Module;
use Aero\Platform\Models\Plan;
use Aero\Platform\Models\Tenant;

$subdomain = 'uatco';

// Clean any prior UAT tenant so provisioning starts fresh.
$existing = Tenant::where('subdomain', $subdomain)->first();
if ($existing) {
    try {
        $dbName = $existing->database()->getName();
        if ($dbName) {
            \DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        }
    } catch (\Throwable $e) {
        // ignore — best effort cleanup
    }
    $existing->forceDelete();
}

$plan = Plan::where('slug', 'uat-hrm')->first() ?? Plan::firstOrFail();
$hrmModuleId = Module::where('code', 'hrm')->value('id');
if (! $hrmModuleId) {
    throw new \RuntimeException('No central module with code "hrm" found — run UatPlatformSeeder first.');
}

$tenant = Tenant::create([
    'name' => 'UAT Co',
    'subdomain' => $subdomain,
    'email' => 'landlord@aeos365.test',
    'type' => 'company',
    'data' => ['plan_id' => $plan->id, 'selected_modules' => ['core', 'hrm']],
]);
$tenant->domains()->create(['domain' => $subdomain.'.aeos365.test']);

// Activate HRM via the tenant_module pivot (getActiveModules reads is_active=true).
$tenant->modules()->syncWithoutDetaching([
    $hrmModuleId => ['is_active' => true, 'subscribed_at' => now()],
]);

ProvisionTenant::dispatchSync($tenant);
$tenant->refresh();

if ($tenant->status !== 'active') {
    throw new \RuntimeException(
        "Provisioning failed: status={$tenant->status} step={$tenant->provisioning_step}"
    );
}

// Seed the tenant DB with the HRM dataset + role users.
$tenant->run(function () {
    \Illuminate\Support\Facades\Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\UatSeeder',
        '--force' => true,
    ]);
});

echo "UAT tenant provisioned: {$tenant->id} ({$subdomain}.aeos365.test)\n";
