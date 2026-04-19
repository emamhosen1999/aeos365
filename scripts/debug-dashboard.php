<?php

use Aero\Core\Models\User;
use Aero\HRM\Models\Employee;
use Aero\HRM\Services\EmployeeDashboardService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

tenancy()->initialize('9d5934f2-99a2-4a6e-99dc-3e949b3fa654');

$user = User::first();
$employee = Employee::where('user_id', $user->id)
    ->with(['department', 'designation', 'user', 'managerEmployee.user'])
    ->first();

$service = app(EmployeeDashboardService::class);

$attendance = $service->getAttendanceData($employee);
$leave = $service->getLeaveData($employee);
$holidays = $service->getHolidayAndEventData();

echo "=== ATTENDANCE KEYS ===\n";
echo json_encode(array_keys($attendance))."\n";

echo "=== LEAVE KEYS ===\n";
echo json_encode(array_keys($leave))."\n";

echo "=== HOLIDAY KEYS ===\n";
echo json_encode(array_keys($holidays))."\n";

// Check for any nested non-serializable objects
echo "=== LEAVE BALANCES TYPE ===\n";
$lb = $leave['leaveBalances'] ?? 'MISSING';
echo gettype($lb).' - '.(is_array($lb) ? count($lb).' items' : json_encode($lb))."\n";

echo "=== PENDING LEAVES ===\n";
$pl = $leave['pendingLeaves'] ?? 'MISSING';
echo gettype($pl).' - '.(is_array($pl) ? count($pl).' items' : json_encode($pl))."\n";
