<?php
// Seed SEO metadata for existing blocks
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

echo "🌱 Seeding SEO metadata...\n";

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

// Get all existing blocks
$stmt = $pdo->query("SELECT id, slug FROM cms_blocks ORDER BY id");
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($blocks)) {
    echo "⚠️  No blocks found. Run MultilingualBlocksSeeder first.\n";
    exit;
}

$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];

$seoTitles = [
    'en' => 'Advanced Block System for CMS',
    'es' => 'Sistema de Bloques Avanzado para CMS',
    'fr' => 'Système de Blocs Avancé pour CMS',
    'de' => 'Erweitertes Blocksystem für CMS',
    'it' => 'Sistema di Blocchi Avanzato per CMS',
    'pt' => 'Sistema de Blocos Avançado para CMS',
    'zh' => 'CMS高级块系统',
    'ja' => 'CMS向けの高度なブロックシステム',
];

$seoDescriptions = [
    'en' => 'Explore our flexible and powerful block system for creating dynamic content. Manage multiple locales, track SEO metrics, and optimize your content.',
    'es' => 'Explore nuestro sistema de bloques flexible y poderoso para crear contenido dinámico. Gestione múltiples locales y optimice su contenido.',
    'fr' => 'Explorez notre système de blocs flexible et puissant pour créer du contenu dynamique. Gérez plusieurs paramètres régionaux et optimisez votre contenu.',
    'de' => 'Erkunden Sie unser flexibles und leistungsstarkes Blocksystem zur Erstellung dynamischer Inhalte. Verwalten Sie mehrere Gebietsschemas und optimieren Sie Ihren Inhalt.',
    'it' => 'Esplora il nostro flessibile e potente sistema di blocchi per la creazione di contenuti dinamici. Gestisci più locale e ottimizza i tuoi contenuti.',
    'pt' => 'Explore nosso sistema de blocos flexível e poderoso para criar conteúdo dinâmico. Gerencie múltiplas localidades e otimize seu conteúdo.',
    'zh' => '探索我们灵活而强大的块系统以创建动态内容。管理多个区域并优化您的内容。',
    'ja' => '動的コンテンツを作成するための柔軟で強力なブロックシステムを探索します。複数のロケールを管理し、コンテンツを最適化します。',
];

$keywords = [
    'en' => ['CMS blocks', 'content management', 'SEO optimization', 'block system', 'dynamic content'],
    'es' => ['bloques CMS', 'gestión de contenidos', 'optimización SEO', 'sistema de bloques', 'contenido dinámico'],
    'fr' => ['blocs CMS', 'gestion de contenu', 'optimisation SEO', 'système de blocs', 'contenu dynamique'],
    'de' => ['CMS-Blöcke', 'Content-Management', 'SEO-Optimierung', 'Blocksystem', 'dynamische Inhalte'],
    'it' => ['blocchi CMS', 'gestione dei contenuti', 'ottimizzazione SEO', 'sistema di blocchi', 'contenuti dinamici'],
    'pt' => ['blocos CMS', 'gerenciamento de conteúdo', 'otimização SEO', 'sistema de blocos', 'conteúdo dinâmico'],
    'zh' => ['CMS块', '内容管理', 'SEO优化', '块系统', '动态内容'],
    'ja' => ['CMS ブロック', 'コンテンツ管理', 'SEO 最適化', 'ブロック システム', 'ダイナミック コンテンツ'],
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
    echo "📝 Block: {$block['slug']}\n";

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

        $insertSeoStmt->execute([
            'Aero\\Cms\\Models\\CmsBlock',
            $block['id'],
            $locale,
            $seoTitles[$locale] . ' - ' . $block['slug'],
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
            "https://example.com/blocks/{$block['slug']}",
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
