<?php

/**
 * Fix HRMAC data bugs:
 * 1. Add HR Manager (role_id=3) access to all HRM sub_modules
 * 2. Verify the fix
 *
 * Run via: php artisan tinker < fix-hrmac.php
 * Or: Get-Content fix-hrmac.php | php artisan tinker --no-interaction
 */

// Initialize tenant context
tenancy()->initialize('9d5934f2-99a2-4a6e-99dc-3e949b3fa654');

$subModuleClass = 'Aero\HRMAC\Models\SubModule';
$accessClass = 'Aero\HRMAC\Models\RoleModuleAccess';

// Get all HRM sub_module IDs
$hrmSubModules = $subModuleClass::whereHas('module', function ($q) {
    $q->where('code', 'hrm');
})->get(['id', 'code']);

echo 'HRM SubModules found: '.$hrmSubModules->count()."\n";
foreach ($hrmSubModules as $sm) {
    echo "  id={$sm->id} code={$sm->code}\n";
}

// Check existing HR Manager (role_id=3) entries
$existing = $accessClass::where('role_id', 3)->count();
echo "\nHR Manager existing entries: $existing\n";

if ($existing === 0) {
    echo "Adding HR Manager access to all HRM sub_modules...\n";
    foreach ($hrmSubModules as $sm) {
        $accessClass::create([
            'role_id' => 3,
            'sub_module_id' => $sm->id,
            'access_scope' => 'all',
        ]);
        echo "  Added: {$sm->code} (id={$sm->id})\n";
    }
    echo 'Done! HR Manager now has '.$accessClass::where('role_id', 3)->count()." entries.\n";
} else {
    echo "HR Manager already has entries, skipping.\n";
}

// Verify getRoleAccessTree fix by testing derivation
$service = app('Aero\HRMAC\Contracts\RoleModuleAccessInterface');
$tree = $service->getRoleAccessTree(3);
echo "\nHR Manager access tree:\n";
echo '  modules: '.json_encode($tree['modules'])."\n";
echo '  sub_modules count: '.count($tree['sub_modules'])."\n";
