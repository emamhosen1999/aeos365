<?php
$env = parse_ini_file(__DIR__ . '/../.env');

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

$result = $pdo->query("SHOW TABLES LIKE 'cms_%'")->fetchAll(PDO::FETCH_COLUMN);

echo "CMS Tables in database:\n";
foreach ($result as $table) {
    echo "  - $table\n";
}

if (empty($result)) {
    echo "  (none found)\n";
}
