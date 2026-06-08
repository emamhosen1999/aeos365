<?php

namespace Database\Seeders;

use Aero\Core\Models\User;
use Aero\HRM\Database\Seeders\HrmDepartmentSeeder;
use Aero\HRM\Database\Seeders\HrmDesignationSeeder;
use Aero\HRM\Models\Department;
use Aero\HRM\Models\Designation;
use Aero\HRM\Models\Employee;
use Aero\HRM\Models\LeaveType;
use Aero\HRMAC\Models\Role;
use Aero\HRMAC\Models\SubModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic UAT dataset for HRM E2E. Idempotent.
 *
 * Runs inside a tenant DB (SaaS) or the standalone DB — both have the full
 * HRM schema. Creates: the four canonical roles (ensured), one user per role
 * to drive HRMAC allow/deny, HRM lookup/config data via HrmDemoSeeder, and
 * ~10 employees attached to seeded departments/designations.
 *
 * Credentials (match e2e/.env): password "Password123!", emails
 * superadmin@uatco.test / hr@uatco.test / employee@uatco.test.
 */
class UatSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        // 0. Sync the module hierarchy (modules/sub_modules/components/actions)
        //    from package configs. SaaS provisioning already does this in the
        //    tenant DB, but standalone has no installer step in this flow, so
        //    without it sub_modules is empty and HRMAC grants below are no-ops.
        Artisan::call('aero:sync-module', ['--force' => true]);

        // 1. Ensure the four canonical roles (guard 'web'). Provisioning seeds
        //    them in SaaS; standalone install seeds Super Administrator only.
        foreach ([
            ['Super Administrator', true],
            ['Administrator', false],
            ['HR Manager', false],
            ['Employee', false],
        ] as [$name, $protected]) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['is_protected' => $protected]
            );
        }

        // 2. Grant module access mirroring ProvisionTenant::seedDefaultRoles so
        //    the dataset is identical in standalone (no provisioning) and SaaS:
        //    Super Administrator / Administrator / HR Manager → all sub-modules;
        //    Employee → self-service + dashboard only (drives HRMAC allow/deny).
        $allSubModuleIds = SubModule::pluck('id');
        $selfServiceIds = SubModule::whereIn('code', ['self_service', 'dashboard'])->pluck('id');

        $grantAll = ['Super Administrator', 'Administrator', 'HR Manager'];
        foreach ($grantAll as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            foreach ($allSubModuleIds as $subModuleId) {
                DB::table('role_module_access')->insertOrIgnore([
                    'role_id' => $role->id,
                    'sub_module_id' => $subModuleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $employee = Role::where('name', 'Employee')->where('guard_name', 'web')->first();
        foreach ($selfServiceIds as $subModuleId) {
            DB::table('role_module_access')->insertOrIgnore([
                'role_id' => $employee->id,
                'sub_module_id' => $subModuleId,
                'access_scope' => 'own',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. One user per role (known credentials).
        foreach ([
            ['superadmin@uatco.test', 'UAT Super Admin', 'Super Administrator'],
            ['hr@uatco.test', 'UAT HR Manager', 'HR Manager'],
            ['employee@uatco.test', 'UAT Employee', 'Employee'],
        ] as [$email, $name, $roleName]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'user_name' => explode('@', $email)[0],
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'active' => true,
                    'is_active' => true,
                ]
            );
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }

        // 4. HRM lookup data. NOTE: HrmDemoSeeder is intentionally NOT used — its
        //    chained seeders drifted from current schemas (see tech-debt TD-15).
        //    We call only the verified-working lookup seeders and seed the rest
        //    directly against the current schema.
        $this->call([
            HrmDepartmentSeeder::class,
            HrmDesignationSeeder::class,
        ]);
        $this->seedLeaveTypes();

        // 5. ~10 employees attached to seeded departments/designations.
        $this->seedEmployees(10);
    }

    /**
     * Seed leave types directly against the current `leave_types` schema
     * (name, code, color, days_per_year, is_paid, requires_approval,
     * carry_forward, encashable, max_carry_forward, is_active).
     */
    private function seedLeaveTypes(): void
    {
        $types = [
            ['Annual Leave', 'AL', '#3B82F6', 21, true, true, true, true, 10],
            ['Sick Leave', 'SL', '#EF4444', 15, true, true, false, false, 0],
            ['Casual Leave', 'CL', '#F59E0B', 12, true, true, false, false, 0],
            ['Unpaid Leave', 'UL', '#6B7280', 0, false, true, false, false, 0],
        ];

        foreach ($types as [$name, $code, $color, $days, $paid, $approval, $carry, $encash, $maxCarry]) {
            LeaveType::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'color' => $color,
                    'days_per_year' => $days,
                    'is_paid' => $paid,
                    'requires_approval' => $approval,
                    'carry_forward' => $carry,
                    'encashable' => $encash,
                    'max_carry_forward' => $maxCarry,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Create N employees if fewer than N exist. EmployeeFactory creates the
     * linked User automatically; department/designation are taken from the
     * HrmDemoSeeder-seeded lookups.
     */
    private function seedEmployees(int $n): void
    {
        $existing = Employee::count();
        if ($existing >= $n) {
            return;
        }

        $departmentIds = Department::pluck('id');
        $designationIds = Designation::pluck('id');

        for ($i = $existing; $i < $n; $i++) {
            $factory = Employee::factory();
            if ($departmentIds->isNotEmpty()) {
                $factory = $factory->withDepartment($departmentIds->random());
            }
            if ($designationIds->isNotEmpty()) {
                $factory = $factory->withDesignation($designationIds->random());
            }
            $factory->create();
        }
    }
}
