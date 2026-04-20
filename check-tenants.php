<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenants = \Aero\Platform\Models\Tenant::with('domains')->get();
echo "Tenants: {$tenants->count()}\n";
foreach ($tenants as $t) {
    $domains = $t->domains->pluck('domain')->implode(', ');
    echo "{$t->id} => {$domains}\n";
}
