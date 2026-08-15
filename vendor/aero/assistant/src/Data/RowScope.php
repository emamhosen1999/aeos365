<?php

declare(strict_types=1);

namespace Aero\Assistant\Data;

use Aero\Contracts\RoleModuleAccessInterface;
use Illuminate\Support\Facades\DB;

/**
 * Row-level (own / team / department) scoping for Aeon's data tool.
 *
 * The HRMAC module gate answers "may this user open HR data at all". It does NOT
 * answer the second question — WHICH ROWS inside it. Every HRMAC grant carries an
 * access_scope, and a user scoped to 'own' must not be able to read the whole
 * payroll table through the assistant just because the module is open to them.
 *
 * Ownership is derived from the live schema instead of a hand-maintained per-table
 * map, so tables that don't exist yet are covered the day they ship. A row belongs
 * to a user when it points at their employee record (employee_id), points at them
 * (user_id / created_by / owner_id / assigned_to / requested_by), or IS them
 * (users.id, employees.id).
 *
 * Tables with NO ownership column are reference data (leave types, departments,
 * tax brackets) — there is no "own row" to speak of, so they stay readable; the
 * module gate, PII stripping and the audit trail still apply. Where a user's scope
 * says they own nothing (e.g. an 'own'-scoped account with no employee record),
 * the query is constrained to match nothing rather than silently falling open.
 */
class RowScope
{
    /** Columns that identify the EMPLOYEE a row belongs to. */
    private const OWNER_BY_EMPLOYEE = ['employee_id'];

    /** Columns that identify the USER a row belongs to, in priority order. */
    private const OWNER_BY_USER = ['user_id', 'created_by', 'owner_id', 'assigned_to', 'assigned_to_id', 'requested_by'];

    /** Columns an employees row uses to point at its manager. */
    private const MANAGER_COLUMNS = ['manager_id', 'reports_to', 'reporting_to', 'supervisor_id'];

    public function __construct(private SchemaCatalog $catalog) {}

    /**
     * Constrain $query to the rows the signed-in user is allowed to see.
     *
     * @return array{scope:string,applied:bool} the governing scope, and whether a
     *                                          row constraint was actually applied
     */
    public function apply($query, string $table, string $moduleCode): array
    {
        $scope = $this->resolveScope($moduleCode);

        if ($scope === 'all') {
            return ['scope' => 'all', 'applied' => false];
        }

        $columns = $this->columns($table);
        $userId = $this->userId();

        if ($userId === null || $columns === []) {
            return ['scope' => $scope, 'applied' => false];
        }

        $employeeIds = $this->visibleEmployeeIds($scope, $userId);
        $userIds = $this->visibleUserIds($employeeIds, $userId);

        // The table IS the person.
        if ($table === 'employees') {
            $query->whereIn('id', $employeeIds ?: [-1]);

            return ['scope' => $scope, 'applied' => true];
        }

        if ($table === 'users') {
            $query->whereIn('id', $userIds ?: [-1]);

            return ['scope' => $scope, 'applied' => true];
        }

        // The table points at the person.
        foreach (self::OWNER_BY_EMPLOYEE as $col) {
            if (in_array($col, $columns, true)) {
                $query->whereIn($col, $employeeIds ?: [-1]);

                return ['scope' => $scope, 'applied' => true];
            }
        }

        foreach (self::OWNER_BY_USER as $col) {
            if (in_array($col, $columns, true)) {
                $query->whereIn($col, $userIds ?: [-1]);

                return ['scope' => $scope, 'applied' => true];
            }
        }

        // A department-scoped user reading a table that carries a department but no
        // owner (e.g. a departmental budget) still gets narrowed to their department.
        if ($scope === 'department' && in_array('department_id', $columns, true)) {
            $deptId = $this->departmentId($userId);
            $query->where('department_id', $deptId ?? -1);

            return ['scope' => $scope, 'applied' => true];
        }

        // No ownership dimension: reference/config data. Nothing to scope.
        return ['scope' => $scope, 'applied' => false];
    }

    /**
     * The user's effective scope for the module that owns the table. Consumer-guard:
     * with no HRMAC binding or no signed-in user (isolated package tests, console)
     * scoping is a no-op, matching how the module gate behaves in those contexts.
     */
    private function resolveScope(string $moduleCode): string
    {
        try {
            if (! function_exists('auth') || ! app()->bound(RoleModuleAccessInterface::class)) {
                return 'all';
            }

            $user = auth()->user();

            if (! $user) {
                return 'all';
            }

            $scope = app(RoleModuleAccessInterface::class)->getUserModuleScope($user, $moduleCode);

            return in_array($scope, ['all', 'department', 'team', 'own'], true) ? $scope : 'own';
        } catch (\Throwable) {
            return 'own'; // a broken scope resolver must narrow, never widen
        }
    }

    /**
     * Employee ids this user may see. 'team' = themselves plus their direct reports;
     * 'department' = everyone in their department; 'own' = just themselves.
     *
     * @return array<int,int|string>
     */
    private function visibleEmployeeIds(string $scope, int|string $userId): array
    {
        $mine = $this->employeeId($userId);

        if ($mine === null) {
            return [];
        }

        if ($scope === 'own') {
            return [$mine];
        }

        try {
            if ($scope === 'department') {
                $deptId = $this->departmentId($userId);

                if ($deptId === null) {
                    return [$mine];
                }

                return DB::table('employees')->where('department_id', $deptId)->pluck('id')->all();
            }

            if ($scope === 'team') {
                $managerCol = $this->managerColumn();

                if ($managerCol === null) {
                    return [$mine]; // no reporting line modelled — stay narrow
                }

                $reports = DB::table('employees')->where($managerCol, $mine)->pluck('id')->all();

                return array_values(array_unique(array_merge([$mine], $reports)));
            }
        } catch (\Throwable) {
            return [$mine];
        }

        return [$mine];
    }

    /**
     * User ids matching the visible employees, plus the user themselves (rows can be
     * owned by a user that has no employee record, e.g. created_by on a setting).
     *
     * @param  array<int,int|string>  $employeeIds
     * @return array<int,int|string>
     */
    private function visibleUserIds(array $employeeIds, int|string $userId): array
    {
        $ids = [$userId];

        if ($employeeIds !== [] && $this->hasColumn('employees', 'user_id')) {
            try {
                $ids = array_merge($ids, DB::table('employees')->whereIn('id', $employeeIds)->pluck('user_id')->all());
            } catch (\Throwable) {
                // fall through with just the user
            }
        }

        return array_values(array_unique(array_filter($ids, static fn ($v) => $v !== null)));
    }

    private function employeeId(int|string $userId): int|string|null
    {
        if (! $this->hasColumn('employees', 'user_id')) {
            return null;
        }

        try {
            return DB::table('employees')->where('user_id', $userId)->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    private function departmentId(int|string $userId): int|string|null
    {
        if (! $this->hasColumn('employees', 'department_id')) {
            return null;
        }

        try {
            return DB::table('employees')->where('user_id', $userId)->value('department_id');
        } catch (\Throwable) {
            return null;
        }
    }

    private function managerColumn(): ?string
    {
        foreach (self::MANAGER_COLUMNS as $col) {
            if ($this->hasColumn('employees', $col)) {
                return $col;
            }
        }

        return null;
    }

    private function userId(): int|string|null
    {
        try {
            return auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int,string> */
    private function columns(string $table): array
    {
        $entity = $this->catalog->entity($table);

        return $entity['columns'] ?? [];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }
}
