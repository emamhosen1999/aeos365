<?php
/**
 * Setup Multilingual CMS Blocks for Testing
 * 
 * This script creates the CMS blocks and translations tables,
 * then seeds them with multilingual test data.
 */

$rootPath = dirname(__DIR__);
$composerPath = $rootPath . '/vendor/autoload.php';

if (!file_exists($composerPath)) {
    die("❌ Composer autoload not found at: $composerPath\n");
}

require_once $composerPath;

// Try to use Laravel config
try {
    $app = require $rootPath . '/bootstrap/app.php';
    $db = $app['db'];
    $pdo = $db->connection()->getPdo();
    echo "✅ Connected to database via Laravel\n";
} catch (Exception $e) {
    // Fallback to direct PDO connection
    echo "⚠️  Laravel connection failed, using PDO directly\n";
    
    $dbConfig = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_DATABASE'] ?? 'eos365',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ];
    
    $dsn = sprintf('mysql:host=%s;dbname=%s', $dbConfig['host'], $dbConfig['database']);
    
    try {
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Connected to database directly\n";
    } catch (PDOException $e) {
        die("❌ Database connection failed: " . $e->getMessage() . "\n");
    }
}

// Create cms_pages table if it doesn't exist
echo "\n📋 Creating cms_pages table...\n";
$createPagesTable = <<<SQL
CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    meta_title VARCHAR(255),
    meta_description TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($createPagesTable);
    echo "✅ cms_pages table created\n";
} catch (PDOException $e) {
    echo "⚠️  cms_pages table error: " . $e->getMessage() . "\n";
}

// Drop existing tables to clean up (for clean setup)
echo "\n🗑️  Dropping existing tables for clean setup...\n";
try {
    $pdo->exec("PRAGMA foreign_keys = OFF");
    $pdo->exec("DROP TABLE IF EXISTS cms_block_translations");
    $pdo->exec("DROP TABLE IF EXISTS cms_blocks");
    $pdo->exec("PRAGMA foreign_keys = ON");
    echo "✅ Dropped existing tables\n";
} catch (PDOException $e) {
    // Try MySQL-specific approach
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("DROP TABLE IF EXISTS cms_block_translations");
        $pdo->exec("DROP TABLE IF EXISTS cms_blocks");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "✅ Dropped existing tables\n";
    } catch (PDOException $e2) {
        echo "⚠️  Could not drop tables: " . $e2->getMessage() . "\n";
    }
}

// Create cms_blocks table
echo "\n📋 Creating cms_blocks table...\n";
$createBlocksTable = <<<SQL
CREATE TABLE IF NOT EXISTS cms_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    block_type_id INT NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    sort_order INT DEFAULT 0,
    config JSON NULL,
    is_visible BOOLEAN DEFAULT TRUE,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_page_sort (page_id, sort_order),
    INDEX idx_block_type (block_type_id),
    INDEX idx_visible (is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($createBlocksTable);
    echo "✅ cms_blocks table created\n";
} catch (PDOException $e) {
    echo "⚠️  cms_blocks table already exists or error: " . $e->getMessage() . "\n";
}

// Create cms_block_translations table
echo "\n📋 Creating cms_block_translations table...\n";
$createTranslationsTable = <<<SQL
CREATE TABLE IF NOT EXISTS cms_block_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    content JSON NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block_locale (block_id, locale),
    INDEX idx_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($createTranslationsTable);
    echo "✅ cms_block_translations table created\n";
} catch (PDOException $e) {
    echo "⚠️  cms_block_translations table already exists or error: " . $e->getMessage() . "\n";
}

// Check if CMS page exists
echo "\n🔍 Checking for CMS pages...\n";
$pageSql = "SELECT id FROM cms_pages LIMIT 1";
$result = $pdo->query($pageSql);
$page = $result->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    echo "❌ No CMS pages found. Creating test page...\n";
    $pageInsert = "
    INSERT INTO cms_pages (title, slug, meta_title, meta_description, is_published, created_at, updated_at)
    VALUES ('Multilingual Test Page', 'multilingual-test-page', 'Multilingual Test', 'Testing multilingual blocks', 1, NOW(), NOW())
    ";
    $pdo->exec($pageInsert);
    $pageId = $pdo->lastInsertId();
    echo "✅ Created test page with ID: $pageId\n";
} else {
    $pageId = $page['id'];
    echo "✅ Using existing page with ID: $pageId\n";
}

// Get block types
echo "\n🔍 Checking for block types...\n";
$blockTypesSql = "SELECT id, slug FROM cms_block_types LIMIT 2";
$result = $pdo->query($blockTypesSql);
$blockTypes = $result->fetchAll(PDO::FETCH_ASSOC);

if (count($blockTypes) < 2) {
    die("❌ Not enough block types found. Please seed cms_block_types first.\n");
}

$titleBlockTypeId = $blockTypes[0]['id'];
$descBlockTypeId = $blockTypes[1]['id'];

echo "✅ Using block types: $titleBlockTypeId, $descBlockTypeId\n";

// Define translations
$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];
$translations = [
    'en' => ['title' => 'Welcome to Our Platform', 'description' => 'Join us today and experience excellence'],
    'es' => ['title' => 'Bienvenido a Nuestra Plataforma', 'description' => 'Únete a nosotros hoy y experimenta la excelencia'],
    'fr' => ['title' => 'Bienvenue sur Notre Plateforme', 'description' => 'Rejoignez-nous aujourd\'hui et vivez l\'excellence'],
    'de' => ['title' => 'Willkommen auf Unserer Plattform', 'description' => 'Treten Sie uns heute bei und erleben Sie Exzellenz'],
    'it' => ['title' => 'Benvenuto sulla Nostra Piattaforma', 'description' => 'Unisciti a noi oggi e sperimenta l\'eccellenza'],
    'pt' => ['title' => 'Bem-vindo à Nossa Plataforma', 'description' => 'Junte-se a nós hoje e experimente excelência'],
    'zh' => ['title' => '欢迎来到我们的平台', 'description' => '立即加入我们并体验卓越'],
    'ja' => ['title' => '私たちのプラットフォームへようこそ', 'description' => '今日から参加して卓越さを体験してください'],
];

// Clear existing blocks for this page (for clean testing)
echo "\n🗑️  Clearing existing blocks for page $pageId...\n";
$pdo->exec("DELETE FROM cms_block_translations WHERE block_id IN (SELECT id FROM cms_blocks WHERE page_id = $pageId)");
$pdo->exec("DELETE FROM cms_blocks WHERE page_id = $pageId");
echo "✅ Cleared existing blocks\n";

// Insert blocks and translations
echo "\n📝 Inserting multilingual test data...\n";

// Title block
$titleBlockInsert = "
INSERT INTO cms_blocks (page_id, block_type_id, slug, sort_order, is_visible, published_at, created_at, updated_at)
VALUES ($pageId, $titleBlockTypeId, 'test-title-block', 1, 1, NOW(), NOW(), NOW())
";
$pdo->exec($titleBlockInsert);
$titleBlockId = $pdo->lastInsertId();
echo "✅ Created title block with ID: $titleBlockId\n";

// Description block
$descBlockInsert = "
INSERT INTO cms_blocks (page_id, block_type_id, slug, sort_order, is_visible, published_at, created_at, updated_at)
VALUES ($pageId, $descBlockTypeId, 'test-description-block', 2, 1, NOW(), NOW(), NOW())
";
$pdo->exec($descBlockInsert);
$descBlockId = $pdo->lastInsertId();
echo "✅ Created description block with ID: $descBlockId\n";

// Insert translations for title block
echo "\n📚 Inserting title block translations...\n";
foreach ($locales as $locale) {
    $titleContentJson = json_encode(['title' => $translations[$locale]['title']]);
    $metadataJson = json_encode(['seo_keyword' => 'platform ' . $locale, 'og_title' => $translations[$locale]['title']]);
    
    $stmt = $pdo->prepare("
    INSERT INTO cms_block_translations (block_id, locale, content, metadata, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$titleBlockId, $locale, $titleContentJson, $metadataJson]);
    echo "  ✅ $locale";
}
echo "\n";

// Insert translations for description block
echo "\n📚 Inserting description block translations...\n";
foreach ($locales as $locale) {
    $descContentJson = json_encode(['description' => $translations[$locale]['description']]);
    $metadataJson = json_encode(['seo_keyword' => 'excellence ' . $locale, 'og_description' => $translations[$locale]['description']]);
    
    $stmt = $pdo->prepare("
    INSERT INTO cms_block_translations (block_id, locale, content, metadata, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$descBlockId, $locale, $descContentJson, $metadataJson]);
    echo "  ✅ $locale";
}
echo "\n";

// Verify data
echo "\n✅ Verification:\n";
$blockCount = $pdo->query("SELECT COUNT(*) as count FROM cms_blocks WHERE page_id = $pageId")->fetch(PDO::FETCH_ASSOC);
$transCount = $pdo->query("SELECT COUNT(*) as count FROM cms_block_translations WHERE block_id IN (SELECT id FROM cms_blocks WHERE page_id = $pageId)")->fetch(PDO::FETCH_ASSOC);

echo "  • Blocks created: " . $blockCount['count'] . "\n";
echo "  • Translations created: " . $transCount['count'] . "\n";
echo "  • Page ID: $pageId\n";
echo "  • Locales: " . implode(', ', $locales) . "\n";

echo "\n✅ Multilingual CMS blocks setup complete!\n";
echo "Test at: /pages/$pageId/blocks?locale=en\n";
?>
