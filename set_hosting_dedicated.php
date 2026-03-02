<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Update hosting_settings to use dedicated (direct SQL) mode
$raw = \Illuminate\Support\Facades\DB::table('platform_settings')->where('id', 1)->first();
$settings = json_decode($raw->hosting_settings, true);
echo "Before: mode=" . $settings['mode'] . "\n";

$settings['mode'] = 'dedicated';
\Illuminate\Support\Facades\DB::table('platform_settings')
    ->where('id', 1)
    ->update(['hosting_settings' => json_encode($settings), 'updated_at' => now()]);

// Verify
$setting = \Aero\Platform\Models\PlatformSetting::current();
echo "After: mode=" . $setting->getHostingMode() . "\n";
echo "Done!\n";
