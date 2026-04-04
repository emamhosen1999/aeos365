<?php
// Create SEO metadata tables using raw SQL
// Read .env file
$envFile = __DIR__ . '/../.env';
$env = [];

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

echo "🔗 Connecting to database: $database@$host:$port\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected\n\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Drop existing tables if they exist (for fresh setup)
echo "🗑️  Dropping existing tables...\n";
try {
    $pdo->exec("DROP TABLE IF EXISTS `cms_seo_keywords`");
    $pdo->exec("DROP TABLE IF EXISTS `cms_seo_metadata`");
    echo "✅ Tables dropped\n\n";
} catch (PDOException $e) {
    echo "⚠️  Error dropping tables: " . $e->getMessage() . "\n\n";
}

// Create cms_seo_metadata table
echo "📋 Creating cms_seo_metadata table...\n";
$createSeoMetadata = "
CREATE TABLE `cms_seo_metadata` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seoable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seoable_id` bigint unsigned NOT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `meta_title` varchar(60) COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci,
  `og_title` varchar(255) COLLATE utf8mb4_unicode_ci,
  `og_description` text COLLATE utf8mb4_unicode_ci,
  `og_image` varchar(500) COLLATE utf8mb4_unicode_ci,
  `og_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'website',
  `twitter_card` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'summary_large_image',
  `twitter_title` varchar(255) COLLATE utf8mb4_unicode_ci,
  `twitter_description` text COLLATE utf8mb4_unicode_ci,
  `twitter_image` varchar(500) COLLATE utf8mb4_unicode_ci,
  `twitter_creator` varchar(100) COLLATE utf8mb4_unicode_ci,
  `canonical_url` varchar(500) COLLATE utf8mb4_unicode_ci,
  `robots_index` enum('index','noindex') COLLATE utf8mb4_unicode_ci DEFAULT 'index',
  `robots_follow` enum('follow','nofollow') COLLATE utf8mb4_unicode_ci DEFAULT 'follow',
  `schema_json` json,
  `schema_type` varchar(50) COLLATE utf8mb4_unicode_ci,
  `seo_score` int DEFAULT 0,
  `seo_issues` json,
  `view_count` int DEFAULT 0,
  `click_count` int DEFAULT 0,
  `avg_click_through_rate` decimal(5,2) DEFAULT 0,
  `last_seo_audit_at` timestamp NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seoable_locale` (`seoable_type`, `seoable_id`, `locale`),
  KEY `idx_seoable_type_id` (`seoable_type`, `seoable_id`),
  KEY `idx_locale` (`locale`),
  KEY `idx_robots_index` (`robots_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $pdo->exec($createSeoMetadata);
    echo "✅ cms_seo_metadata created\n\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Create cms_seo_keywords table
echo "📋 Creating cms_seo_keywords table...\n";
$createKeywords = "
CREATE TABLE `cms_seo_keywords` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seo_metadata_id` bigint unsigned NOT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword_type` enum('primary','secondary','related','lsi') COLLATE utf8mb4_unicode_ci DEFAULT 'secondary',
  `density` int DEFAULT 0,
  `search_volume` int DEFAULT 0,
  `keyword_rank` int,
  `search_intent_score` decimal(5,2) DEFAULT 0,
  `optimization_level` int DEFAULT 0,
  `ranked_at` timestamp NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seo_keyword` (`seo_metadata_id`, `keyword`),
  KEY `idx_keyword` (`keyword`),
  CONSTRAINT `fk_cms_seo_keywords_metadata` FOREIGN KEY (`seo_metadata_id`) 
    REFERENCES `cms_seo_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $pdo->exec($createKeywords);
    echo "✅ cms_seo_keywords created\n\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "✅ SEO metadata tables setup complete!\n";
