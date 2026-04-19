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

// Test all deferred props
echo "=== PAYROLL TEST ===\n";
try {
    $pd = $service->getPayrollData($employee);
    echo "payrollData OK: " . json_encode($pd) . "\n";
} catch (\Throwable $e) {
    echo "payrollData ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$tests = [
    'performanceData' => fn () => $service->getPerformanceData($employee),
    'trainingData' => fn () => $service->getTrainingData($employee),
    'expenseData' => fn () => $service->getExpenseData($employee),
    'assetData' => fn () => $service->getAssetData($employee),
    'documentData' => fn () => $service->getDocumentData($employee),
    'careerData' => fn () => $service->getCareerData($employee),
    'feedbackData' => fn () => $service->getFeedbackData($employee),
    'onboardingData' => fn () => $service->getOnboardingData($employee),
    'teamData' => fn () => $service->getTeamData($employee),
    'overtimeData' => fn () => $service->getOvertimeData($employee),
    'grievanceData' => fn () => $service->getGrievanceData($employee),
    'surveyData' => fn () => $service->getSurveyData($employee),
    'benefitData' => fn () => $service->getBenefitData($employee),
    'managerApprovals' => fn () => $service->getManagerApprovals($employee),
];

foreach ($tests as $name => $fn) {
    try {
        $result = $fn();
        $json = json_encode($result);
        $ok = json_last_error() === JSON_ERROR_NONE;
        echo sprintf("%-20s %s (keys: %s) %s\n",
            $name,
            $ok ? 'OK' : 'JSON_ERROR: '.json_last_error_msg(),
            implode(',', array_keys($result)),
            $ok ? substr($json, 0, 80) : ''
        );
    } catch (Throwable $e) {
        echo sprintf("%-20s ERROR: %s\n", $name, $e->getMessage());
    }
}
