<?php

use Aero\Core\Services\NavigationRegistry;
use Illuminate\Contracts\Http\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->boot();
// Run in central context
$nav = $app->make(NavigationRegistry::class);
$items = $nav->getItems('tenant');
foreach ($items as $mod => $group) {
    foreach ($group as $item) {
        $count = count($item['children'] ?? []);
        echo "module=$mod name={$item['name']} children=$count\n";
        foreach (($item['children'] ?? []) as $child) {
            echo "  submodule={$child['name']} route=".($child['path'] ?? '(none)').' childCount='.count($child['children'] ?? [])."\n";
        }
    }
}
