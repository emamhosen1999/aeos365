<?php

use Aero\Platform\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

/**
 * Temporary script to dump HRMAC hierarchy from tenant DB
 * for navigation verification. Delete after use.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenant = Tenant::find('9d5934f2-99a2-4a6e-99dc-3e949b3fa654');

$tenant->run(function () {
    // Modules
    echo "=== MODULES ===\n";
    $modules = DB::table('modules')->select('id', 'code', 'name', 'is_active')->get();
    foreach ($modules as $m) {
        echo "{$m->id} | {$m->code} | {$m->name} | active={$m->is_active}\n";
    }

    // Sub-modules
    echo "\n=== SUB_MODULES ===\n";
    $subs = DB::table('sub_modules')
        ->select('id', 'module_id', 'code', 'name', 'is_active', 'icon', 'route')
        ->orderBy('module_id')->orderBy('id')->get();
    foreach ($subs as $s) {
        echo "  mod={$s->module_id} | {$s->code} | {$s->name} | route={$s->route} | icon={$s->icon} | active={$s->is_active}\n";
    }

    // Components
    echo "\n=== COMPONENTS (by sub_module) ===\n";
    $comps = DB::table('module_components')
        ->join('sub_modules', 'module_components.sub_module_id', '=', 'sub_modules.id')
        ->select('sub_modules.code as sub_code', 'module_components.id', 'module_components.code', 'module_components.name', 'module_components.is_active')
        ->orderBy('sub_modules.id')->orderBy('module_components.id')->get();
    $currentSub = '';
    foreach ($comps as $c) {
        if ($c->sub_code !== $currentSub) {
            $currentSub = $c->sub_code;
            echo "  [{$currentSub}]\n";
        }
        echo "    {$c->id} | {$c->code} | {$c->name} | active={$c->is_active}\n";
    }

    // Actions count
    echo "\n=== ACTIONS ===\n";
    $actCount = DB::table('module_component_actions')->count();
    echo "Total actions: {$actCount}\n";

    // Role module access
    echo "\n=== ROLE MODULE ACCESS ===\n";
    $roles = DB::table('roles')->select('id', 'name')->get()->keyBy('id');
    $access = DB::table('role_module_access')
        ->join('modules', 'role_module_access.module_id', '=', 'modules.id')
        ->join('sub_modules', 'role_module_access.sub_module_id', '=', 'sub_modules.id')
        ->select(
            'role_module_access.role_id',
            'modules.code as module_code',
            'sub_modules.code as sub_code',
            'role_module_access.has_access'
        )
        ->orderBy('role_module_access.role_id')
        ->orderBy('modules.id')
        ->orderBy('sub_modules.id')
        ->get();

    $currentRole = null;
    foreach ($access as $a) {
        if ($a->role_id !== $currentRole) {
            $currentRole = $a->role_id;
            $roleName = $roles[$currentRole]->name ?? 'Unknown';
            echo "\n  Role: {$roleName} (id={$currentRole})\n";
        }
        $icon = $a->has_access ? '✓' : '✗';
        echo "    {$icon} {$a->module_code}.{$a->sub_code}\n";
    }

    // Super admin user check
    echo "\n=== ADMIN USER ===\n";
    $admin = DB::table('users')->where('email', 'admin@testcorp.com')->first();
    if ($admin) {
        echo "User: {$admin->name} | {$admin->email} | active={$admin->is_active}\n";
        $userRoles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $admin->id)
            ->select('roles.name')
            ->get();
        echo 'Roles: '.$userRoles->pluck('name')->join(', ')."\n";
    }
});
