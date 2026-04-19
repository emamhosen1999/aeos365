<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Plans ===\n";
$plans = DB::table('plans')->where('is_active', true)->get(['name', 'slug', 'module_codes']);
foreach ($plans as $p) {
    echo "  {$p->name} ({$p->slug}): {$p->module_codes}\n";
}

echo "\n=== Config checks ===\n";
echo 'APP_DEBUG: '.(config('app.debug') ? 'true' : 'false')."\n";
echo 'QUEUE: '.config('queue.default')."\n";
echo 'DB: '.config('database.default')."\n";

echo "\n=== Central DB tables ===\n";
$tables = ['plans', 'tenants', 'domains', 'platform_settings'];
foreach ($tables as $t) {
    echo "  {$t}: ".DB::table($t)->count()." rows\n";
}

echo "\n=== Migration paths check ===\n";
$paths = [
    'vendor/aero/core/database/migrations',
    'vendor/aero/hrm/database/migrations',
    'vendor/aero/hrmac/database/migrations',
    'vendor/aero/platform/database/migrations',
    'vendor/aero/crm/database/migrations',
    'vendor/aero/project/database/migrations',
];
foreach ($paths as $path) {
    $exists = file_exists(base_path($path));
    $count = $exists ? count(glob(base_path($path).'/*.php')) : 0;
    echo "  {$path}: ".($exists ? "{$count} files" : 'NOT FOUND')."\n";
}

echo "\n=== Module config files ===\n";
$vendorPath = base_path('vendor/aero');
foreach (glob($vendorPath.'/*/config/module.php') as $configPath) {
    $config = require $configPath;
    echo "  {$config['code']}: {$config['name']} (scope: ".($config['scope'] ?? 'tenant').")\n";
}
