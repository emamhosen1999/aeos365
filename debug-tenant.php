<?php

use Aero\Platform\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Use Platform's Tenant model (with AsArrayObject cast) as it is in real requests
$tenant = Tenant::find('9d5934f2-99a2-4a6e-99dc-3e949b3fa654');

echo 'plan_id: '.json_encode($tenant->plan_id)."\n";
echo 'modules type: '.gettype($tenant->modules)."\n";
echo 'modules class: '.(is_object($tenant->modules) ? get_class($tenant->modules) : 'n/a')."\n";
echo 'modules instanceof ArrayObject: '.($tenant->modules instanceof ArrayObject ? 'YES' : 'no')."\n";

if ($tenant->modules instanceof ArrayObject) {
    $arr = $tenant->modules->getArrayCopy();
    echo 'modules (getArrayCopy): '.json_encode($arr)."\n";
} elseif (is_string($tenant->modules)) {
    $arr = json_decode($tenant->modules, true) ?? [];
    echo 'modules (json_decode): '.json_encode($arr)."\n";
} elseif (is_array($tenant->modules)) {
    echo 'modules (array): '.json_encode($tenant->modules)."\n";
} else {
    echo 'modules (raw): '.json_encode($tenant->modules)."\n";
}

// Try plan relation
try {
    $plan = $tenant->plan;
    echo 'plan: '.($plan ? $plan->name : 'null')."\n";
    if ($plan) {
        $mods = $plan->modules()->where('is_active', true)->pluck('modules.code')->toArray();
        echo 'plan modules: '.json_encode($mods)."\n";
    }
} catch (Throwable $e) {
    echo 'plan error: '.$e->getMessage()."\n";
}

// Simulate getSubscribedModuleCodes logic
echo "\n--- Simulated getSubscribedModuleCodes ---\n";
$modules = ['core', 'platform'];
$tenantModules = $tenant->modules;
if ($tenantModules instanceof ArrayObject) {
    $tenantModules = $tenantModules->getArrayCopy();
} elseif (is_string($tenantModules)) {
    $tenantModules = json_decode($tenantModules, true) ?? [];
}
if (! empty($tenantModules) && is_array($tenantModules)) {
    $modules = array_merge($modules, $tenantModules);
}
echo 'Result: '.json_encode(array_values(array_unique($modules)))."\n";
