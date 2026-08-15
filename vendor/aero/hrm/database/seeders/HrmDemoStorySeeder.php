<?php

declare(strict_types=1);

namespace Aero\HRM\Database\Seeders;

use Aero\Auth\Models\User;
use Aero\Core\Support\DemoCredentials;
use Aero\HRM\Models\Asset;
use Aero\HRM\Models\AssetAllocation;
use Aero\HRM\Models\AssetCategory;
use Aero\HRM\Models\Attendance;
use Aero\HRM\Models\Department;
use Aero\HRM\Models\Designation;
use Aero\HRM\Models\Employee;
use Aero\HRM\Models\ExpenseCategory;
use Aero\HRM\Models\ExpenseClaim;
use Aero\HRM\Models\HrmAsset;
use Aero\HRM\Models\HrmAssetAllocation;
use Aero\HRM\Models\HrmAssetCategory;
use Aero\HRM\Models\HrmExpenseCategory;
use Aero\HRM\Models\HrmExpenseClaim;
use Aero\HRM\Models\HrmExpenseClaimItem;
use Aero\HRM\Models\HrmPerformanceReview;
use Aero\HRM\Models\Job;
use Aero\HRM\Models\JobApplication;
use Aero\HRM\Models\JobHiringStage;
use Aero\HRM\Models\LeaveApplication;
use Aero\HRM\Models\LeaveBalance;
use Aero\HRM\Models\LeaveType;
use Aero\HRM\Models\PayrollRun;
use Aero\HRM\Models\Payslip;
use Aero\HRM\Models\PerformanceReview;
use Aero\HRM\Models\ReviewCycle;
use Aero\HRM\Models\ReviewTemplate;
use Aero\HRM\Models\TrainingCategory;
use Aero\HRM\Models\TrainingCourse;
use Aero\HRM\Models\TrainingEnrollment;
use Aero\HRM\Models\TrainingSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Democorp story seeder — wipe + rebuild a believable company so the live demo
 * always shows rich, current-dated data and visitor edits are healed on reset.
 *
 * Built from factories and verified column lists ONLY. This seeder deliberately
 * does NOT call HrmDemoSeeder or any part of its chain: that generation drifted
 * away from the current schema (TD-15) and fails against today's migrations.
 *
 * Guarantees on every run:
 *  - the 34-person deterministic roster exists (keyed on employee_code), and any
 *    employee outside the roster is soft-deleted rather than destroyed, so the
 *    ~100 tables carrying employee_id are never orphaned;
 *  - both HRM generations are populated (v1 assets/expenses/performance AND the
 *    v2 hrm_* tables) because routes/web.php still serves both;
 *  - every date is relative to now(), so the demo never looks stale.
 */
class HrmDemoStorySeeder extends Seeder
{
    /**
     * Story-owned transactional tables, ordered child → parent.
     *
     * Everything listed here is re-seeded below; a table is never emptied
     * without being refilled, otherwise a demo page would render blank.
     */
    private const WIPE_ORDER = [
        'attendances',
        'leave_applications',
        'employee_leave_balances',
        'hrm_payslips',
        'hrm_payroll_runs',
        'hrm_expense_claim_receipts',
        'hrm_expense_claim_items',
        'hrm_expense_claims',
        'expense_claims',
        'asset_allocations',
        'assets',
        'hrm_asset_allocations',
        'hrm_assets',
        'job_applications',
        'job_hiring_stages',
        'jobs_recruitment',
        'training_enrollments',
        'training_sessions',
        'training_courses',
        'performance_reviews',
        'hrm_performance_reviews',
        'hrm_review_cycles',
    ];

    /** Deterministic staff roster: [name, gender]. Index 0 is the demo employee persona. */
    private const ROSTER = [
        ['Maya Rahman', 'female'], ['Arif Chowdhury', 'male'], ['Nusrat Jahan', 'female'],
        ['Tanvir Ahmed', 'male'], ['Sadia Islam', 'female'], ['Rakib Hasan', 'male'],
        ['Farhana Akter', 'female'], ['Imran Hossain', 'male'], ['Sharmin Sultana', 'female'],
        ['Mehedi Karim', 'male'], ['Tania Ferdous', 'female'], ['Sajid Rahman', 'male'],
        ['Lamia Haque', 'female'], ['Naimur Rashid', 'male'], ['Rifat Ara', 'female'],
        ['Shakil Mahmud', 'male'], ['Anika Tabassum', 'female'], ['Fahim Shahriar', 'male'],
        ['Mim Akhter', 'female'], ['Rashed Khan', 'male'], ['Puja Saha', 'female'],
        ['Asif Iqbal', 'male'], ['Nadia Noor', 'female'], ['Hasib Molla', 'male'],
        ['Sumaiya Binte', 'female'], ['Rubel Miah', 'male'], ['Ishrat Jerin', 'female'],
        ['Tousif Alam', 'male'], ['Mounota Roy', 'female'], ['Zahid Hasan', 'male'],
        ['Afsana Mimi', 'female'], ['Shuvo Das', 'male'], ['Priya Barua', 'female'],
        ['Emon Sarker', 'male'],
    ];

    private const ASSET_MODELS = [
        'MacBook Pro 14"', 'Dell Latitude 5540', 'ThinkPad X1 Carbon', 'Dell UltraSharp U2723',
        'iPhone 15', 'iPad Air', 'HP LaserJet Pro', 'Herman Miller Aeron', 'Standing Desk Pro',
        'Logitech MX Master 3S', 'Jabra Evolve2 65', 'Samsung 27" Monitor',
    ];

    /** @var array<int, string> employee id => display name (avoids an N+1 back to users) */
    private array $rosterNames = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->wipe();
            $this->seedLookups();

            $admin = $this->seedAdminPersona();
            $employees = $this->seedRoster();
            $active = $employees->where('status', 'active')->values();

            $this->seedAttendance($active);
            $this->seedLeave($employees, $active, $admin);
            $this->seedPayroll($active, $admin);
            $this->seedAssets($active, $admin);
            $this->seedExpenses($active, $admin);
            $this->seedRecruitment($admin);
            $this->seedTraining($active, $admin);
            $this->seedPerformance($active, $admin);
        });
    }

    /**
     * Empty the story-owned transactional tables.
     *
     * Deletes (not truncates) so the statements stay inside the surrounding
     * transaction. Foreign-key checks are disabled as belt-and-braces for MySQL;
     * inside a test transaction that call is a no-op, so WIPE_ORDER carries the
     * real guarantee — children are always removed before their parents.
     */
    private function wipe(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::WIPE_ORDER as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private function seedLookups(): void
    {
        $this->call([
            HrmDepartmentSeeder::class,
            HrmDesignationSeeder::class,
            HrmLeaveTypeSeeder::class,
            HrmAssetCategorySeeder::class,
            HrmExpenseCategorySeeder::class,
        ]);
    }

    private function seedAdminPersona(): User
    {
        $admin = DemoCredentials::admin();

        $user = $this->upsertUser(
            $admin['email'],
            $admin['name'],
            'demo.admin',
            $admin['password'],
        );

        $this->assignRole($user, $admin['role']);

        return $user;
    }

    /**
     * Rebuild the deterministic roster.
     *
     * Employees are upserted on employee_code and everything outside the roster
     * is soft-deleted: visitor-created noise disappears from every screen while
     * the rows that other modules point at stay resolvable.
     *
     * @return Collection<int, Employee>
     */
    private function seedRoster(): Collection
    {
        $departments = Department::orderBy('id')->pluck('id')->values();
        $designations = Designation::orderBy('id')->pluck('id')->values();
        $credentials = DemoCredentials::employee();

        $employees = collect();
        $codes = [];

        foreach (self::ROSTER as $index => [$name, $gender]) {
            $isPersona = $index === 0;
            $slug = str_replace(' ', '.', strtolower($name));
            $code = sprintf('DEMO-%03d', $index + 1);
            $codes[] = $code;

            $user = $this->upsertUser(
                $isPersona ? $credentials['email'] : $slug.'@democorp.demo',
                $name,
                $slug,
                $isPersona ? $credentials['password'] : 'Democorp!'.(100 + $index),
            );

            if ($isPersona) {
                $this->assignRole($user, $credentials['role']);
            }

            $status = match ($index) {
                32 => 'on_leave',
                33 => 'resigned',
                default => 'active',
            };

            $joinedAt = now()->subMonths(4 + ($index * 3) % 56)->startOfMonth();

            $employee = Employee::withTrashed()->updateOrCreate(['employee_code' => $code], [
                'user_id' => $user->id,
                'department_id' => $departments->get($index % max(1, $departments->count())),
                'designation_id' => $designations->get($this->designationIndex($index, $designations->count())),
                'date_of_joining' => $joinedAt,
                'joining_date' => $joinedAt,
                'date_of_leaving' => $status === 'resigned' ? now()->subDays(21) : null,
                'confirmation_date' => $joinedAt->copy()->addMonths(3),
                'employment_type' => $index % 11 === 10 ? 'contract' : 'full_time',
                'status' => $status,
                'basic_salary' => 42000 + (($index * 7) % 13) * 5500,
                'work_location' => ['Dhaka HQ', 'Chattogram Hub', 'Remote'][$index % 3],
                'shift' => 'General (09:00 — 18:00)',
                'birthday' => now()->subYears(24 + ($index * 5) % 20)->subDays(($index * 13) % 360),
                'gender' => $gender,
                'nationality' => 'Bangladeshi',
                'marital_status' => $index % 3 === 0 ? 'single' : 'married',
                'blood_group' => ['A+', 'B+', 'O+', 'AB+', 'O-'][$index % 5],
            ]);

            if ($employee->trashed()) {
                $employee->restore();
            }

            $this->rosterNames[$employee->id] = $name;
            $employees->push($employee);
        }

        Employee::whereNotIn('employee_code', $codes)->delete();

        $this->assignReportingLines($employees);

        return $employees;
    }

    /** First roster member of each department becomes that department's manager. */
    private function assignReportingLines(Collection $employees): void
    {
        $heads = [];

        foreach ($employees as $employee) {
            $key = (string) ($employee->department_id ?? 'unassigned');
            $heads[$key] ??= $employee;
        }

        foreach ($employees as $employee) {
            $head = $heads[(string) ($employee->department_id ?? 'unassigned')];

            if ($head->id === $employee->id || $employee->manager_id === $head->user_id) {
                continue;
            }

            $employee->forceFill(['manager_id' => $head->user_id])->save();
        }
    }

    /**
     * ~3 months of working-day attendance for every active employee.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedAttendance(Collection $employees): void
    {
        $hasEmployeeColumn = Schema::hasColumn('attendances', 'employee_id');
        $today = Carbon::today();
        $timestamp = now();

        $days = collect(range(1, 92))
            ->map(fn (int $offset): Carbon => $today->copy()->subDays($offset))
            ->filter(fn (Carbon $day): bool => $day->isWeekday())
            ->values();

        $rows = [];

        foreach ($employees as $index => $employee) {
            foreach ($days as $dayIndex => $day) {
                $roll = ($index * 7 + $dayIndex * 3) % 23;
                $absent = $roll === 0;
                $late = $roll === 1 || $roll === 2;
                $overtime = $roll % 6 === 0;

                $punchIn = $day->copy()->setTime(9, $late ? 34 + $roll : 2 + $roll);
                $worked = $absent ? 0.0 : ($overtime ? 10.0 : 8.75);
                $punchOut = $punchIn->copy()->addMinutes((int) ($worked * 60));

                $row = [
                    'user_id' => $employee->user_id,
                    'attendance_type_id' => null,
                    'date' => $day->toDateString(),
                    'punchin' => $absent ? null : $punchIn->toDateTimeString(),
                    'punchout' => $absent ? null : $punchOut->toDateTimeString(),
                    'work_hours' => $worked,
                    'overtime_hours' => $worked > 8.75 ? round($worked - 8.75, 2) : 0,
                    'is_late' => ! $absent && $late,
                    'is_early_leave' => false,
                    'status' => $absent ? 'absent' : 'present',
                    'is_manual' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if ($hasEmployeeColumn) {
                    $row['employee_id'] = $employee->id;
                }

                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::insert($chunk);
        }
    }

    /**
     * Balances for everyone, a history of settled requests, and — always — a
     * queue of pending requests starting today or later for the approval tour.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, Employee>  $active
     */
    private function seedLeave(Collection $employees, Collection $active, User $approver): void
    {
        $types = LeaveType::pluck('id', 'code');
        $year = (int) now()->year;
        $timestamp = now();
        $balances = [];

        foreach ($employees as $index => $employee) {
            foreach (['AL' => 21, 'SL' => 15, 'CL' => 12] as $code => $entitled) {
                if (! isset($types[$code])) {
                    continue;
                }

                $balances[] = [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $types[$code],
                    'year' => $year,
                    'entitled' => $entitled,
                    'used' => ($index * 3 + strlen($code)) % ($entitled - 4),
                    'carried_forward' => $code === 'AL' ? ($index % 5) : 0,
                    'encashed' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        LeaveBalance::insert($balances);

        $annual = $types['AL'] ?? $types->first();
        $sick = $types['SL'] ?? $annual;
        $casual = $types['CL'] ?? $annual;

        // Settled history — the leave register is never empty. The factory's
        // approved()/rejected() states are avoided on purpose: both instantiate
        // the legacy Aero\Core\Models\User alias, which only exists once
        // aero-core has booted.
        foreach ($active->take(12) as $index => $employee) {
            LeaveApplication::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $index % 3 === 0 ? $sick : $annual,
                'status' => LeaveApplication::STATUS_APPROVED,
                'start_date' => now()->subDays(120 - $index * 8)->toDateString(),
                'end_date' => now()->subDays(118 - $index * 8)->toDateString(),
                'total_days' => 3,
                'reason' => 'Planned time off — arrangements handed over to the team.',
                'approved_by' => $approver->id,
                'approved_at' => now()->subDays(125 - $index * 8),
            ]);
        }

        foreach ($active->slice(12, 3) as $index => $employee) {
            LeaveApplication::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $casual,
                'status' => LeaveApplication::STATUS_REJECTED,
                'start_date' => now()->subDays(40 - $index * 5)->toDateString(),
                'end_date' => now()->subDays(39 - $index * 5)->toDateString(),
                'total_days' => 2,
                'reason' => 'Short notice request during a delivery freeze.',
                'rejection_reason' => 'Release week — please re-apply for the following sprint.',
                'rejected_by' => $approver->id,
                'rejected_at' => now()->subDays(45 - $index * 5),
            ]);
        }

        // Tour ammo: the persona plus five colleagues awaiting a decision.
        $pending = collect([$active->first()])->merge($active->slice(15, 5))->filter()->values();

        foreach ($pending as $index => $employee) {
            LeaveApplication::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $index % 2 === 0 ? $casual : $annual,
                'status' => LeaveApplication::STATUS_PENDING,
                'start_date' => now()->addDays($index)->toDateString(),
                'end_date' => now()->addDays($index + 2)->toDateString(),
                'total_days' => 3,
                'reason' => 'Family commitments — requesting a short leave.',
            ]);
        }
    }

    /**
     * Last month approved (with payslips) plus the current month still in draft.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedPayroll(Collection $employees, User $admin): void
    {
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $thisMonth = now()->startOfMonth();

        $approved = PayrollRun::factory()->create([
            'label' => 'Payroll '.$lastMonth->format('M Y'),
            'period_start' => $lastMonth->copy(),
            'period_end' => $lastMonth->copy()->endOfMonth(),
            'status' => PayrollRun::STATUS_APPROVED,
            'approved_at' => $lastMonth->copy()->endOfMonth(),
            'approved_by' => $admin->id,
        ]);

        $draft = PayrollRun::factory()->create([
            'label' => 'Payroll '.$thisMonth->format('M Y'),
            'period_start' => $thisMonth->copy(),
            'period_end' => $thisMonth->copy()->endOfMonth(),
            'status' => PayrollRun::STATUS_DRAFT,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        foreach ([$approved, $draft] as $run) {
            $this->seedPayslips($run, $employees);
        }
    }

    /** @param  Collection<int, Employee>  $employees */
    private function seedPayslips(PayrollRun $run, Collection $employees): void
    {
        $totalGross = 0.0;
        $totalNet = 0.0;

        foreach ($employees as $employee) {
            $basic = (float) $employee->basic_salary;
            $housing = round($basic * 0.18, 2);
            $transport = 3500.00;
            $gross = round($basic + $housing + $transport, 2);
            $tax = round($gross * 0.08, 2);
            $deductions = round($gross * 0.05, 2);
            $net = round($gross - $tax - $deductions, 2);

            Payslip::factory()->create([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'gross' => $gross,
                'tax' => $tax,
                'deductions_total' => $deductions,
                'net' => $net,
                'line_items' => [
                    ['code' => 'BASIC', 'label' => 'Basic Salary', 'type' => 'earning', 'amount' => $basic],
                    ['code' => 'HRA', 'label' => 'Housing Allowance', 'type' => 'earning', 'amount' => $housing],
                    ['code' => 'TRP', 'label' => 'Transport Allowance', 'type' => 'earning', 'amount' => $transport],
                    ['code' => 'TAX', 'label' => 'Income Tax', 'type' => 'deduction', 'amount' => $tax],
                    ['code' => 'PF', 'label' => 'Provident Fund', 'type' => 'deduction', 'amount' => $deductions],
                ],
                'employee_snapshot' => [
                    'employee_code' => $employee->employee_code,
                    'name' => $this->rosterNames[$employee->id] ?? $employee->employee_code,
                    'department_id' => $employee->department_id,
                    'designation_id' => $employee->designation_id,
                ],
                'bank_account_number' => null,
                'bank_name' => null,
                'bank_routing_number' => null,
            ]);

            $totalGross += $gross;
            $totalNet += $net;
        }

        $run->forceFill([
            'total_gross' => round($totalGross, 2),
            'total_net' => round($totalNet, 2),
        ])->save();
    }

    /**
     * Both asset generations: v1 assets/asset_allocations and v2 hrm_* tables.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedAssets(Collection $employees, User $admin): void
    {
        $categories = AssetCategory::orderBy('id')->pluck('id')->values();
        $count = max(1, $categories->count());

        for ($i = 0; $i < 16; $i++) {
            $allocatable = $i < 10;

            $asset = Asset::create([
                'category_id' => $categories->get($i % $count),
                'name' => self::ASSET_MODELS[$i % count(self::ASSET_MODELS)],
                'asset_tag' => sprintf('AST-%04d', $i + 1),
                'serial_number' => sprintf('SN-%08d', 4100 + $i * 37),
                'description' => 'Company-issued equipment tracked by the HR asset register.',
                'manufacturer' => ['Apple', 'Dell', 'Lenovo', 'HP'][$i % 4],
                'model' => self::ASSET_MODELS[$i % count(self::ASSET_MODELS)],
                'purchase_date' => now()->subMonths(3 + $i)->startOfMonth()->toDateString(),
                'purchase_price' => 45000 + $i * 3750,
                'warranty_expiry' => now()->addMonths(24 - $i)->toDateString(),
                'status' => match (true) {
                    $allocatable => Asset::STATUS_ALLOCATED,
                    $i === 15 => Asset::STATUS_MAINTENANCE,
                    default => Asset::STATUS_AVAILABLE,
                },
                'location' => 'Dhaka HQ — Level '.(1 + $i % 4),
            ]);

            if (! $allocatable) {
                continue;
            }

            $employee = $employees[$i % $employees->count()];

            AssetAllocation::create([
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'allocated_date' => now()->subMonths(2 + $i % 6)->toDateString(),
                'expected_return_date' => now()->addMonths(8 - $i % 5)->toDateString(),
                'allocation_notes' => 'Issued on joining the '.self::ASSET_MODELS[$i % count(self::ASSET_MODELS)].' pool.',
                'condition_on_allocation' => ['new', 'good', 'good', 'fair'][$i % 4],
                'allocated_by' => $admin->id,
                'is_active' => true,
            ]);
        }

        $hrmCategories = collect([
            'IT Equipment', 'Mobile Devices', 'Office Furniture', 'Access & Identity',
        ])->map(fn (string $name): HrmAssetCategory => HrmAssetCategory::firstOrCreate(
            ['name' => $name],
            ['description' => $name.' issued to employees.', 'active' => true],
        ))->values();

        for ($i = 0; $i < 14; $i++) {
            $allocatable = $i < 9;

            $asset = HrmAsset::create([
                'tag' => sprintf('ASSET-%04d', $i + 1),
                'name' => self::ASSET_MODELS[($i + 3) % count(self::ASSET_MODELS)],
                'category_id' => $hrmCategories[$i % $hrmCategories->count()]->id,
                'serial_number' => sprintf('SN-%06d', 9100 + $i * 53),
                'vendor' => ['Smart Technologies', 'Ryans Computers', 'Startech', 'Global Brand'][$i % 4],
                'purchased_on' => now()->subMonths(2 + $i)->startOfMonth()->toDateString(),
                'purchase_cost' => 38000 + $i * 4200,
                'status' => match (true) {
                    $allocatable => HrmAsset::STATUS_ALLOCATED,
                    $i === 13 => HrmAsset::STATUS_MAINTENANCE,
                    default => HrmAsset::STATUS_AVAILABLE,
                },
                'notes' => null,
            ]);

            if (! $allocatable) {
                continue;
            }

            HrmAssetAllocation::create([
                'asset_id' => $asset->id,
                'employee_id' => $employees[($i + 5) % $employees->count()]->id,
                'allocated_at' => now()->subMonths(1 + $i % 5)->startOfDay(),
                'returned_at' => null,
                'condition_on_allocation' => ['new', 'good', 'fair'][$i % 3],
                'allocation_notes' => 'Handed over by IT support.',
                'allocated_by' => $admin->id,
            ]);
        }
    }

    /**
     * Both expense generations: v1 expense_claims and v2 hrm_expense_claims (+ items).
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedExpenses(Collection $employees, User $admin): void
    {
        $categories = ExpenseCategory::orderBy('id')->pluck('id')->values();
        $count = max(1, $categories->count());
        $statuses = [
            ExpenseClaim::STATUS_SUBMITTED,
            ExpenseClaim::STATUS_PENDING,
            ExpenseClaim::STATUS_APPROVED,
            ExpenseClaim::STATUS_PAID,
            ExpenseClaim::STATUS_REJECTED,
            ExpenseClaim::STATUS_DRAFT,
        ];

        for ($i = 0; $i < 12; $i++) {
            $employee = $employees[$i % $employees->count()];
            $status = $statuses[$i % count($statuses)];
            $submittedAt = now()->subDays(30 - $i * 2);

            ExpenseClaim::create([
                'employee_id' => $employee->id,
                'category_id' => $categories->get($i % $count),
                'claim_number' => sprintf('EXP-%s-%04d', now()->format('Y'), $i + 1),
                'amount' => 1850 + $i * 640,
                'expense_date' => now()->subDays(34 - $i * 2)->toDateString(),
                'description' => 'Reimbursement request raised from the employee self-service portal.',
                'vendor_name' => ['Uber', 'Daraz', 'Star Kabab', 'Aarong', 'Bata'][$i % 5],
                'receipt_number' => sprintf('RCP-%06d', 2200 + $i * 17),
                'status' => $status,
                'current_approval_level' => $status === ExpenseClaim::STATUS_DRAFT ? 0 : 1,
                'submitted_by' => $status === ExpenseClaim::STATUS_DRAFT ? null : $employee->user_id,
                'submitted_at' => $status === ExpenseClaim::STATUS_DRAFT ? null : $submittedAt,
                'approved_by' => in_array($status, [ExpenseClaim::STATUS_APPROVED, ExpenseClaim::STATUS_PAID], true) ? $admin->id : null,
                'approved_at' => in_array($status, [ExpenseClaim::STATUS_APPROVED, ExpenseClaim::STATUS_PAID], true) ? $submittedAt->copy()->addDays(2) : null,
                'rejected_by' => $status === ExpenseClaim::STATUS_REJECTED ? $admin->id : null,
                'rejected_at' => $status === ExpenseClaim::STATUS_REJECTED ? $submittedAt->copy()->addDay() : null,
                'rejection_reason' => $status === ExpenseClaim::STATUS_REJECTED ? 'Receipt is unreadable — please re-upload a clear copy.' : null,
                'payment_method' => $status === ExpenseClaim::STATUS_PAID ? 'bank_transfer' : null,
                'payment_reference' => $status === ExpenseClaim::STATUS_PAID ? sprintf('PAY-%06d', 7700 + $i) : null,
                'payment_date' => $status === ExpenseClaim::STATUS_PAID ? $submittedAt->copy()->addDays(5)->toDateString() : null,
            ]);
        }

        $hrmCategories = collect([
            'Travel', 'Meals & Entertainment', 'Office Supplies', 'Training & Conferences',
        ])->map(fn (string $name): HrmExpenseCategory => HrmExpenseCategory::firstOrCreate(
            ['name' => $name],
            ['description' => $name.' claimed by employees.', 'active' => true],
        ))->values();

        $hrmStatuses = [
            HrmExpenseClaim::STATUS_SUBMITTED,
            HrmExpenseClaim::STATUS_APPROVED,
            HrmExpenseClaim::STATUS_REJECTED,
            HrmExpenseClaim::STATUS_DRAFT,
        ];

        for ($i = 0; $i < 10; $i++) {
            $status = $hrmStatuses[$i % count($hrmStatuses)];
            $reviewed = in_array($status, [HrmExpenseClaim::STATUS_APPROVED, HrmExpenseClaim::STATUS_REJECTED], true);

            $claim = HrmExpenseClaim::create([
                'reference' => sprintf('EXPC-%s-%04d', now()->format('Y'), $i + 1),
                'employee_id' => $employees[($i + 4) % $employees->count()]->id,
                'claim_date' => now()->subDays(26 - $i * 2)->toDateString(),
                'title' => ['Client visit — Chattogram', 'Team lunch', 'Stationery restock', 'Conference ticket'][$i % 4],
                'description' => 'Itemised claim submitted through the HRM expense workspace.',
                'total_amount' => 0,
                'currency' => 'BDT',
                'status' => $status,
                'reviewed_by' => $reviewed ? $admin->id : null,
                'reviewed_at' => $reviewed ? now()->subDays(24 - $i * 2) : null,
                'rejection_reason' => $status === HrmExpenseClaim::STATUS_REJECTED ? 'Outside the approved per-diem policy.' : null,
            ]);

            $total = 0.0;

            for ($line = 0; $line < 3; $line++) {
                $amount = 950 + ($i * 3 + $line) * 275;
                $total += $amount;

                HrmExpenseClaimItem::create([
                    'claim_id' => $claim->id,
                    'category_id' => $hrmCategories[($i + $line) % $hrmCategories->count()]->id,
                    'expense_date' => now()->subDays(27 - $i * 2 + $line)->toDateString(),
                    'amount' => $amount,
                    'description' => ['Intercity transport', 'Meals', 'Printing & stationery'][$line],
                ]);
            }

            $claim->forceFill(['total_amount' => round($total, 2)])->save();
        }
    }

    /** Open requisitions with a hiring pipeline behind each one. */
    private function seedRecruitment(User $admin): void
    {
        $departments = Department::orderBy('id')->pluck('id')->values();
        $stages = ['Applied', 'Screening', 'Technical Interview', 'Final Interview', 'Offer'];
        $statuses = ['applied', 'screening', 'shortlisted', 'interview', 'offered', 'hired', 'rejected'];

        $postings = [
            'Senior Product Designer',
            'Backend Engineer (Laravel)',
            'HR Business Partner',
            'Financial Analyst',
        ];

        $candidates = [
            ['Rezaul', 'Karim'], ['Sabrina', 'Hoque'], ['Mahmudul', 'Islam'], ['Tahmina', 'Akter'],
            ['Junaid', 'Ahsan'], ['Nabila', 'Chowdhury'], ['Sourav', 'Dey'], ['Farzana', 'Yasmin'],
        ];

        foreach ($postings as $jobIndex => $title) {
            $job = Job::create([
                'title' => $title,
                'department_id' => $departments->get($jobIndex % max(1, $departments->count())),
                'type' => $jobIndex === 3 ? 'contract' : 'full_time',
                'location' => ['Dhaka', 'Dhaka', 'Chattogram', 'Remote'][$jobIndex],
                'is_remote_allowed' => $jobIndex === 3,
                'description' => 'We are growing the team and looking for a '.$title.' to join Democorp.',
                'responsibilities' => ['Own delivery end to end', 'Collaborate across squads', 'Mentor teammates'],
                'requirements' => ['3+ years of relevant experience', 'Strong communication skills'],
                'qualifications' => ['Bachelor degree in a related field'],
                'salary_min' => 60000 + $jobIndex * 15000,
                'salary_max' => 120000 + $jobIndex * 20000,
                'salary_currency' => 'BDT',
                'salary_visible' => true,
                'benefits' => ['Health insurance', 'Annual bonus', 'Flexible hours'],
                'posting_date' => now()->subDays(30 - $jobIndex * 5)->toDateString(),
                'closing_date' => now()->addDays(20 + $jobIndex * 5)->toDateString(),
                'status' => $jobIndex === 3 ? 'draft' : 'published',
                'hiring_manager_id' => $admin->id,
                'positions' => 1 + $jobIndex % 3,
                'is_featured' => $jobIndex === 0,
                'skills_required' => ['Communication', 'Ownership', 'Problem solving'],
                'created_by' => $admin->id,
            ]);

            $stageIds = [];

            foreach ($stages as $order => $stage) {
                $stageIds[] = JobHiringStage::create([
                    'job_id' => $job->id,
                    'name' => $stage,
                    'description' => $stage.' round for the '.$title.' pipeline.',
                    'sequence' => $order + 1,
                    'stage_order' => $order + 1,
                    'is_active' => true,
                    'requires_approval' => $order >= 3,
                    'is_final' => $order === count($stages) - 1,
                ])->id;
            }

            foreach ($candidates as $candidateIndex => [$first, $last]) {
                $offset = $jobIndex * count($candidates) + $candidateIndex;
                $status = $statuses[$offset % count($statuses)];

                JobApplication::create([
                    'job_id' => $job->id,
                    'current_stage_id' => $stageIds[$offset % count($stageIds)],
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => sprintf('%s.%s%d@example.com', strtolower($first), strtolower($last), $jobIndex + 1),
                    'phone' => sprintf('+8801%09d', 300000000 + $offset * 137),
                    'city' => ['Dhaka', 'Chattogram', 'Sylhet', 'Khulna'][$offset % 4],
                    'country' => 'Bangladesh',
                    'cover_letter_text' => 'I am excited to apply for the '.$title.' role at Democorp.',
                    'expected_salary' => 70000 + $offset * 4500,
                    'years_of_experience' => 2 + $offset % 9,
                    'available_from' => now()->addDays(15 + $offset)->toDateString(),
                    'status' => $status,
                    'overall_score' => 55 + ($offset * 7) % 40,
                    'assigned_to' => $admin->id,
                    'notes' => 'Sourced via the Democorp careers page.',
                    'rejection_reason' => $status === 'rejected' ? 'Stronger candidates progressed to the interview round.' : null,
                    'rejected_at' => $status === 'rejected' ? now()->subDays(7) : null,
                    'rejected_by' => $status === 'rejected' ? $admin->id : null,
                ]);
            }
        }
    }

    /**
     * A live training catalogue: courses, delivered + upcoming sessions, enrolments.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedTraining(Collection $employees, User $admin): void
    {
        // HrmTrainingCategorySeeder cannot be reused here: training_categories.created_by
        // is NOT NULL and that seeder never supplies it.
        $categories = collect([
            'Compliance & Regulatory',
            'Technical Skills',
            'Leadership & Management',
            'Health & Safety',
        ])->map(fn (string $name): TrainingCategory => TrainingCategory::firstOrCreate(
            ['name' => $name],
            [
                'slug' => str_replace([' & ', ' '], ['-', '-'], strtolower($name)),
                'description' => $name.' programmes offered to Democorp employees.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        ))->values();

        $courses = [
            'Workplace Safety Essentials',
            'Secure Coding Fundamentals',
            'Coaching for New Managers',
            'Data Protection & Privacy',
            'Effective Business Writing',
        ];

        // The Training* factories are bypassed here for the same reason as the
        // leave states: their definitions instantiate Aero\Core\Models\User.
        foreach ($courses as $courseIndex => $title) {
            $course = TrainingCourse::create([
                'category_id' => $categories[$courseIndex % $categories->count()]->id,
                'title' => $title,
                'slug' => str_replace([' & ', ' '], ['-', '-'], strtolower($title)),
                'summary' => $title.' — a practical session for Democorp teams.',
                'description' => 'Instructor-led programme covering the core of '.$title.'.',
                'delivery_mode' => ['in_person', 'virtual', 'self_paced'][$courseIndex % 3],
                'duration_minutes' => 90 + $courseIndex * 30,
                'learning_objectives' => ['Understand the fundamentals', 'Apply it to daily work'],
                'is_mandatory' => $courseIndex < 2,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            foreach ([0, 1] as $slot) {
                $delivered = $slot === 0;
                $startsAt = $delivered
                    ? now()->subDays(30 - $courseIndex * 3)->setTime(10, 0)
                    : now()->addDays(9 + $courseIndex * 4)->setTime(10, 0);

                $session = TrainingSession::create([
                    'course_id' => $course->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes(90 + $courseIndex * 30),
                    'location' => $delivered ? 'Training Room A — Dhaka HQ' : 'Training Room B — Dhaka HQ',
                    'meeting_link' => null,
                    'capacity' => 24,
                    'instructor_id' => $admin->id,
                    'status' => $delivered ? TrainingSession::STATUS_COMPLETED : TrainingSession::STATUS_SCHEDULED,
                    'created_by' => $admin->id,
                ]);

                for ($seat = 0; $seat < 8; $seat++) {
                    $employee = $employees[($courseIndex * 5 + $slot * 8 + $seat) % $employees->count()];

                    TrainingEnrollment::create([
                        'session_id' => $session->id,
                        'employee_id' => $employee->id,
                        'status' => $delivered
                            ? ($seat === 7 ? TrainingEnrollment::STATUS_NO_SHOW : TrainingEnrollment::STATUS_ATTENDED)
                            : TrainingEnrollment::STATUS_ENROLLED,
                        'source' => $seat % 3 === 0 ? 'self' : 'admin',
                        'enrolled_by' => $admin->id,
                    ]);
                }
            }
        }
    }

    /**
     * Both performance generations: v1 performance_reviews and the v2 cycle model.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedPerformance(Collection $employees, User $admin): void
    {
        $periodStart = now()->subMonths(6)->startOfMonth();
        $periodEnd = now()->subMonthNoOverflow()->endOfMonth();
        $v1Statuses = ['completed', 'completed', 'manager_review_pending', 'self_assessment_pending', 'draft'];

        foreach ($employees->take(16) as $index => $employee) {
            $status = $v1Statuses[$index % count($v1Statuses)];
            $completed = $status === 'completed';

            PerformanceReview::create([
                'employee_id' => $employee->id,
                'reviewer_id' => $employee->manager_id ?? $admin->id,
                'department_id' => $employee->department_id,
                'review_period' => 'H1 '.$periodEnd->format('Y'),
                'review_start_date' => $periodStart->toDateString(),
                'review_end_date' => $periodEnd->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'overall_rating' => $completed ? round(3 + ($index % 5) * 0.4, 2) : null,
                'strengths' => 'Reliable delivery, strong collaboration across squads.',
                'areas_of_improvement' => 'Delegate more and document decisions earlier.',
                'goals_for_next_period' => 'Lead one cross-team initiative to completion.',
                'status' => $status,
                'completed_at' => $completed ? now()->subDays(20 - $index) : null,
            ]);
        }

        $template = ReviewTemplate::factory()->create([
            'name' => 'Democorp Standard Review',
            'sections' => [
                ['key' => 'delivery', 'title' => 'Delivery & Impact', 'questions' => ['What did you ship this cycle?']],
                ['key' => 'collaboration', 'title' => 'Collaboration', 'questions' => ['How did you support the team?']],
                ['key' => 'growth', 'title' => 'Growth', 'questions' => ['What do you want to learn next?']],
            ],
            'rating_scale' => ['min' => 1, 'max' => 5, 'labels' => ['Needs focus', 'Developing', 'Solid', 'Strong', 'Exceptional']],
            'active' => true,
        ]);

        $closed = ReviewCycle::factory()->closed()->create([
            'name' => 'Annual Review '.now()->subYear()->format('Y'),
            'template_id' => $template->id,
            'starts_on' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'ends_on' => now()->subMonths(11)->endOfMonth()->toDateString(),
        ]);

        $active = ReviewCycle::factory()->active()->create([
            'name' => 'Mid-Year Review '.now()->format('Y'),
            'template_id' => $template->id,
            'starts_on' => now()->subDays(20)->toDateString(),
            'ends_on' => now()->addDays(25)->toDateString(),
        ]);

        foreach ($employees->take(18) as $index => $employee) {
            HrmPerformanceReview::factory()->finalized()->create([
                'cycle_id' => $closed->id,
                'employee_id' => $employee->id,
                'manager_id' => null,
                'final_rating' => round(3 + ($index % 5) * 0.4, 2),
                'final_comment' => 'Consistent contributor; ready for broader ownership next cycle.',
                'finalized_at' => now()->subMonths(11),
                'finalized_by' => $admin->id,
            ]);

            $factory = match ($index % 3) {
                0 => HrmPerformanceReview::factory()->managerSubmitted(),
                1 => HrmPerformanceReview::factory()->state(['status' => HrmPerformanceReview::STATUS_SELF_SUBMITTED, 'self_answers' => ['delivery' => 'Shipped the reporting revamp.']]),
                default => HrmPerformanceReview::factory()->draft(),
            };

            $factory->create([
                'cycle_id' => $active->id,
                'employee_id' => $employee->id,
                'manager_id' => null,
            ]);
        }
    }

    private function upsertUser(string $email, string $name, string $userName, string $password): User
    {
        /** @var User $user */
        $user = User::withTrashed()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'user_name' => $userName,
            // The `hashed` cast on users.password hashes this on write.
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        return $user;
    }

    /**
     * Attach a role only when a roles table, the Spatie trait and the role row all
     * exist — aero-hrm does not depend on aero-hrmac, so package tests have none.
     */
    private function assignRole(User $user, string $role): void
    {
        if (! Schema::hasTable('roles') || ! method_exists($user, 'assignRole')) {
            return;
        }

        if (! DB::table('roles')->where('name', $role)->exists()) {
            return;
        }

        try {
            $user->assignRole($role);
        } catch (Throwable) {
            // Guard mismatch between the role row and the user model — skip silently.
        }
    }

    /** Seniority skew: the first roster members hold the senior designations. */
    private function designationIndex(int $index, int $total): int
    {
        if ($total < 1) {
            return 0;
        }

        return $index < 6 ? $index % $total : (6 + ($index * 3) % max(1, $total - 6)) % $total;
    }
}
