<?php

// Run via: php artisan tinker --no-interaction < nav-check-cmd.php
$items = app('Aero\Core\Services\NavigationRegistry')->all();
foreach ($items as $mod => $arr) {
    foreach ($arr as $item) {
        $count = count($item['children'] ?? []);
        echo "$mod -> {$item['name']} (children=$count)\n";
        foreach (($item['children'] ?? []) as $child) {
            $ccount = count($child['children'] ?? []);
            echo "  [{$child['name']}] route=".($child['path'] ?? 'none').' access='.($child['access'] ?? '')." sub=$ccount\n";
        }
    }
}
