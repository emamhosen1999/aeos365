<?php

// Direct database setup script for SEO metadata tables
// This script bypasses Laravel bootstrap to avoid provider issues

$dbConnection = getenv('DB_CONNECTION') ?: 'mysql';

// Get database config from .env
$dotenv = parse_ini_file(__DIR__ . '/../.env');

$host = $dotenv['DB_HOST'] ?: 'localhost';
$port = $dotenv['DB_PORT'] ?: 3306;
$database = $dotenv['DB_DATABASE'] ?: 'eos365';
$user = $dotenv['DB_USERNAME'] ?: 'root';
$password = $dotenv['DB_PASSWORD'] ?: '';

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Database connection established\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create cms_seo_metadata table
$createSeoMetadata = <<<SQL
CREATE TABLE IF NOT EXISTS `cms_seo_metadata` (
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
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seoable_locale` (`seoable_type`, `seoable_id`, `locale`),
  KEY `idx_seoable_type_id` (`seoable_type`, `seoable_id`),
  KEY `idx_locale` (`locale`),
  KEY `idx_robots_index` (`robots_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($createSeoMetadata);
    echo "✅ cms_seo_metadata table created\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
        echo "❌ Error creating cms_seo_metadata table: " . $e->getMessage() . "\n";
    } else {
        echo "✅ cms_seo_metadata table already exists\n";
    }
}

// Create cms_seo_keywords table
$createSeoKeywords = <<<SQL
CREATE TABLE IF NOT EXISTS `cms_seo_keywords` (
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
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seo_keyword` (`seo_metadata_id`, `keyword`),
  KEY `idx_keyword` (`keyword`),
  CONSTRAINT `fk_cms_seo_keywords_metadata` FOREIGN KEY (`seo_metadata_id`) 
    REFERENCES `cms_seo_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($createSeoKeywords);
    echo "✅ cms_seo_keywords table created\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
        echo "❌ Error creating cms_seo_keywords table: " . $e->getMessage() . "\n";
    } else {
        echo "✅ cms_seo_keywords table already exists\n";
    }
}

echo "\n✅ SEO metadata tables setup complete!\n";
