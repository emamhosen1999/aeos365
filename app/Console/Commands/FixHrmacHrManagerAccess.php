<?php

namespace App\Console\Commands;

use Aero\HRMAC\Contracts\RoleModuleAccessInterface;
use Aero\HRMAC\Models\RoleModuleAccess;
use Aero\HRMAC\Models\SubModule;
use Illuminate\Console\Command;

class FixHrmacHrManagerAccess extends Command
{
    protected $signature = 'aero:fix-hrmac-hr-manager
                            {--tenant= : Tenant ID to run in (required)}';

    protected $description = 'Seed HR Manager (role_id=3) role_module_access entries for all HRM sub_modules';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        if (! $tenantId) {
            $this->error('--tenant option is required');

            return self::FAILURE;
        }

        tenancy()->initialize($tenantId);

        $hrmSubModules = SubModule::whereHas('module', fn ($q) => $q->where('code', 'hrm'))
            ->get(['id', 'code']);

        $this->info("Found {$hrmSubModules->count()} HRM sub_modules.");

        $existing = RoleModuleAccess::where('role_id', 3)->count();
        $this->info("HR Manager existing entries: {$existing}");

        if ($existing === 0) {
            foreach ($hrmSubModules as $sm) {
                RoleModuleAccess::create([
                    'role_id' => 3,
                    'sub_module_id' => $sm->id,
                    'access_scope' => 'all',
                ]);
                $this->line("  Added: {$sm->code} (id={$sm->id})");
            }
            $total = RoleModuleAccess::where('role_id', 3)->count();
            $this->info("HR Manager now has {$total} entries.");
        } else {
            $this->warn('HR Manager already has entries, skipping insert.');
        }

        // Verify getRoleAccessTree returns derived module IDs
        $service = app(RoleModuleAccessInterface::class);
        $tree = $service->getRoleAccessTree(3);
        $this->info('Access tree for HR Manager:');
        $this->line('  modules: '.implode(', ', $tree['modules']));
        $this->line('  sub_modules count: '.count($tree['sub_modules']));

        return self::SUCCESS;
    }
}
