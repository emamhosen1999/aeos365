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

// Check all tables
$result = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "📋 All Tables in database 'aeos365':\n";
$cmsCount = 0;
foreach ($result as $table) {
    if (strpos($table, 'cms') !== false) {
        echo "  CMS: $table\n";
        $cmsCount++;
    }
}

if ($cmsCount === 0) {
    echo "  (no CMS tables found)\n";
}

// Check specifically for SEO tables
echo "\n🔍 Checking for SEO tables...\n";
$seoCheck = $pdo->query("SHOW TABLES LIKE 'cms_seo%'")->fetchAll(PDO::FETCH_COLUMN);
echo "  Found: " . count($seoCheck) . " SEO tables\n";
foreach ($seoCheck as $table) {
    echo "    - $table\n";
}
