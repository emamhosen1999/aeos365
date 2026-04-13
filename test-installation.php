<?php

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test database configuration step
try {
    $orchest = app('Aero\Core\Installation\Orchestrator\InstallationOrchestrator');
    
    echo "=== Installation Status ===\n";
    $status = $orchest->getStatus();
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    // Execute database step
    echo "=== Executing Database Configuration Step ===\n";
    $result = $orchest->executeNextStep('database', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'username' => 'root',
        'password' => '',
        'database' => 'aeos365_platform'
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    echo "=== Installation Status After Step ===\n";
    $status = $orchest->getStatus();
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
