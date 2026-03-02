<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$setting = \Aero\Platform\Models\PlatformSetting::current();
echo "Current hosting settings: " . json_encode($setting->getHostingSettings()) . "\n";
echo "Current hosting mode: " . $setting->getHostingMode() . "\n";

// Update to dedicated (direct SQL) mode for local testing
$hosting = $setting->getHostingSettings();
$hosting['mode'] = 'dedicated';
$setting->setHostingSettings($hosting);
$setting->save();

echo "Updated hosting mode to: " . $setting->getHostingMode() . "\n";
echo "Done!\n";
