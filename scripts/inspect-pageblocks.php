<?php
$env = [];
$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? 3306;
$database = $env['DB_DATABASE'] ?? 'eos365';
$user = $env['DB_USERNAME'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';

$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "📋 cms_page_blocks columns:\n";
$result = $pdo->query("DESCRIBE cms_page_blocks")->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n📋 First few records in cms_page_blocks:\n";
$result = $pdo->query("SELECT * FROM cms_page_blocks LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
    echo "  ID: {$row['id']}, ";
    foreach ($row as $k => $v) {
        if ($k !== 'id' && $v) {
            echo "$k: " . (strlen($v) > 30 ? substr($v, 0, 30) . '...' : $v) . "; ";
        }
    }
    echo "\n";
}
