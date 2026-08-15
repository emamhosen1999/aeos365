<?php

namespace Aero\HRM\Tests\Feature\Seeders;

use Aero\Auth\Models\User;
use Aero\Core\Support\DemoCredentials;
use Aero\HRM\Database\Seeders\HrmDemoStorySeeder;
use Aero\HRM\Models\Attendance;
use Aero\HRM\Models\Employee;
use Aero\HRM\Models\LeaveApplication;
use Aero\HRM\Models\PayrollRun;
use Aero\HRM\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class HrmDemoStorySeederTest extends TestCase
{
    public function test_builds_the_democorp_story(): void
    {
        $this->seed(HrmDemoStorySeeder::class);

        // People
        $this->assertGreaterThanOrEqual(30, Employee::where('status', 'active')->count());

        // Personas exist with enforced credentials
        $admin = User::where('email', DemoCredentials::admin()['email'])->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check(DemoCredentials::admin()['password'], $admin->password));

        $employee = User::where('email', DemoCredentials::employee()['email'])->first();
        $this->assertNotNull($employee);
        $this->assertTrue(Hash::check(DemoCredentials::employee()['password'], $employee->password));

        // The employee persona must own an Employee record — the self-service tour needs it
        $this->assertNotNull(Employee::where('user_id', $employee->id)->first());

        // Tour ammo: pending leave dated today or later
        $this->assertGreaterThanOrEqual(3, LeaveApplication::where('status', 'pending')
            ->whereDate('start_date', '>=', now()->toDateString())->count());

        // Payroll: last month finalized + current month draft
        $this->assertSame(1, PayrollRun::where('status', PayrollRun::STATUS_DRAFT)
            ->whereDate('period_start', now()->startOfMonth()->toDateString())->count());
        $this->assertSame(1, PayrollRun::where('status', PayrollRun::STATUS_APPROVED)
            ->whereDate('period_start', now()->subMonthNoOverflow()->startOfMonth()->toDateString())->count());

        // Attendance history exists
        $this->assertGreaterThan(30 * 40, Attendance::count());
    }

    public function test_wipe_and_rebuild_is_idempotent(): void
    {
        $this->seed(HrmDemoStorySeeder::class);
        $employees = Employee::count();
        $leaves = LeaveApplication::count();
        $attendance = Attendance::count();

        $this->seed(HrmDemoStorySeeder::class); // second run must not accumulate

        $this->assertSame($employees, Employee::count());
        $this->assertSame($leaves, LeaveApplication::count());
        $this->assertSame($attendance, Attendance::count());
    }
}
