<?php
// Seed SEO metadata for existing page blocks
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

echo "🌱 Seeding SEO metadata for page blocks...\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Determine which table to use - either cms_blocks or cms_page_blocks
$blockTable = null;
$tables = $pdo->query("SHOW TABLES LIKE 'cms_%blocks'")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('cms_blocks', $tables)) {
    $blockTable = 'cms_blocks';
} elseif (in_array('cms_page_blocks', $tables)) {
    $blockTable = 'cms_page_blocks';
}

if (!$blockTable) {
    echo "⚠️  No block table found (cms_blocks or cms_page_blocks). Skipping...\n";
    exit;
}

echo "Using table: $blockTable\n\n";

// Get all existing blocks/page blocks
$stmt = $pdo->query("SELECT id, title FROM `$blockTable` LIMIT 5");
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($blocks)) {
    echo "⚠️  No blocks found in $blockTable.\n";
    // Create sample blocks for demonstration
    echo "Creating sample blocks...\n";
    
    // Insert sample blocks
    $insertBlock = $pdo->prepare("INSERT INTO `$blockTable` (title, created_at, updated_at) VALUES (?, NOW(), NOW())");
    FOR ($i = 1; $i <= 2; $i++) {
        $insertBlock->execute(["Sample Block $i"]);
    }
    
    $blocks = $pdo->query("SELECT id, title FROM `$blockTable`")->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Created " . count($blocks) . " sample blocks\n\n";
}

$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];

$seoTitles = [
    'en' => 'SEO Optimized Content Block',
    'es' => 'Bloque de Contenido Optimizado para SEO',
    'fr' => 'Bloc de Contenu Optimisé pour le SEO',
    'de' => 'SEO-Optimierter Inhaltsblock',
    'it' => 'Blocco di Contenuto Ottimizzato per SEO',
    'pt' => 'Bloco de Conteúdo Otimizado para SEO',
    'zh' => 'SEO 优化内容块',
    'ja' => 'SEO 最適化コンテンツブロック',
];

$seoDescriptions = [
    'en' => 'Experience advanced content management with our comprehensive SEO optimization features. Enhance visibility, track performance, and manage multilingual content effortlessly.',
    'es' => 'Experimente gestión de contenido avanzada con nuestras características completas de optimización SEO. Mejore la visibilidad y administre contenido multilingüe sin esfuerzo.',
    'fr' => 'Expérience de gestion de contenu avancée avec nos fonctionnalités complètes d\'optimisation SEO. Améliorez la visibilité et gérez le contenu multilingue sans effort.',
    'de' => 'Erweiterte Content-Management-Erfahrung mit unseren umfassenden SEO-Optimierungsfunktionen. Verbessern Sie die Sichtbarkeit und verwalten Sie mehrsprachigen Inhalt mühelos.',
    'it' => 'Esperienza di gestione dei contenuti avanzata con le nostre funzionalità complete di ottimizzazione SEO. Migliora la visibilità e gestisci i contenuti multilingue senza sforzo.',
    'pt' => 'Experiência avançada de gerenciamento de conteúdo com nossos recursos abrangentes de otimização de SEO. Melhore a visibilidade e gerencie conteúdo multilíngue sem esforço.',
    'zh' => '通过我们全面的 SEO 优化功能体验高级内容管理。增强可见性、跟踪性能并轻松管理多语言内容。',
    'ja' => '包括的な SEO 最適化機能による高度なコンテンツ管理体験。可視性を高め、パフォーマンスを追跡し、多言語コンテンツを簡単に管理します。',
];

$keywords = [
    'en' => ['content management', 'SEO optimization', 'multilingual content', 'block system', 'digital content'],
    'es' => ['gestión de contenidos', 'optimización SEO', 'contenido multilingüe', 'sistema de bloques', 'contenido digital'],
    'fr' => ['gestion de contenu', 'optimisation SEO', 'contenu multilingue', 'système de blocs', 'contenu numérique'],
    'de' => ['Content Management', 'SEO-Optimierung', 'mehrsprachiger Inhalt', 'Blocksystem', 'digitaler Inhalt'],
    'it' => ['gestione contenuti', 'ottimizzazione SEO', 'contenuto multilingue', 'sistema di blocchi', 'contenuto digitale'],
    'pt' => ['gerenciamento de conteúdo', 'otimização SEO', 'conteúdo multilíngue', 'sistema de blocos', 'conteúdo digital'],
    'zh' => ['内容管理', 'SEO优化', '多语言内容', '块系统', '数字内容'],
    'ja' => ['コンテンツ管理', 'SEO 最適化', '多言語コンテンツ', 'ブロック システム', 'デジタル コンテンツ'],
];

$insertSeoStmt = $pdo->prepare("
    INSERT INTO cms_seo_metadata (
        seoable_type, seoable_id, locale, meta_title, meta_description, meta_keywords,
        og_title, og_description, og_image, og_type,
        twitter_card, twitter_title, twitter_description, twitter_image, twitter_creator,
        canonical_url, robots_index, robots_follow,
        schema_json, schema_type, seo_score, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )
");

$insertKeywordStmt = $pdo->prepare("
    INSERT INTO cms_seo_keywords (
        seo_metadata_id, keyword, keyword_type, density, search_volume,
        keyword_rank, search_intent_score, optimization_level, ranked_at, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )
");

$seoCount = 0;
$keywordCount = 0;

foreach ($blocks as $block) {
    $blockTitle = $block['title'] ?? 'Block ' . $block['id'];
    echo "📝 Block: $blockTitle (ID: {$block['id']})\n";

    foreach ($locales as $locale) {
        $schema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $seoTitles[$locale],
            'description' => $seoDescriptions[$locale],
            'image' => 'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
            'author' => [
                '@type' => 'Organization',
                'name' => 'Aero CMS',
            ],
            'datePublished' => date('c'),
            'dateModified' => date('c'),
        ]);

        try {
            $insertSeoStmt->execute([
                'Aero\\Cms\\Models\\CmsBlock',
                $block['id'],
                $locale,
                $seoTitles[$locale] . ' - ' . $blockTitle,
                $seoDescriptions[$locale],
                implode(', ', $keywords[$locale]),
                $seoTitles[$locale],
                $seoDescriptions[$locale],
                'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
                'article',
                'summary_large_image',
                $seoTitles[$locale],
                $seoDescriptions[$locale],
                'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
                '@aerocms',
                "https://example.com/blocks/{$block['id']}",
                'index',
                'follow',
                $schema,
                'BlogPosting',
                85,
            ]);

            $seoId = $pdo->lastInsertId();
            $seoCount++;

            // Insert keywords
            foreach ($keywords[$locale] as $index => $keyword) {
                $insertKeywordStmt->execute([
                    $seoId,
                    $keyword,
                    $index === 0 ? 'primary' : 'secondary',
                    rand(1, 5),
                    rand(500, 50000),
                    rand(1, 100),
                    rand(60, 100),
                    rand(70, 100),
                    date('c', strtotime('-' . rand(1, 30) . ' days')),
                ]);
                $keywordCount++;
            }
        } catch (PDOException $e) {
            echo "  ⚠️  $locale - " . $e->getMessage() . "\n";
            continue;
        }

        echo "  ✅ $locale\n";
    }

    echo "\n";
}

// Verify
$seoVerify = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_metadata")->fetch(PDO::FETCH_ASSOC);
$keywordVerify = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_keywords")->fetch(PDO::FETCH_ASSOC);

echo "📊 Verification:\n";
echo "   SEO Metadata: {$seoVerify['count']} records\n";
echo "   Keywords: {$keywordVerify['count']} records\n\n";
echo "✅ SEO metadata seeding complete!\n";
