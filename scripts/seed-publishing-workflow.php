<?php

/**
 * Seed Publishing Workflow Tables
 * Direct PDO + basic PHP execution - test data creation
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Database connection details
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'aeos365';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    
    $pdo->exec("USE `$db_name`");
    echo "✅ Connected to database: $db_name\n\n";

    // Get 4 existing page blocks
    $stmt = $pdo->query("SELECT id FROM cms_page_blocks LIMIT 4");
    $blocks = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($blocks)) {
        echo "⚠️  No CMS page blocks found. Cannot seed publishing data.\n";
        exit(0);
    }

    echo "📦 Found " . count($blocks) . " page blocks\n";
    $userId = '00000000-0000-0000-0000-000000000001';

    foreach ($blocks as $blockId) {
        echo "\n📦 Seeding block {$blockId}...\n";

        // Create 3 versions per block
        for ($v = 1; $v <= 3; $v++) {
            $blockData = json_encode([
                'heading' => "Version {$v} Content",
                'content' => "This is version {$v} of the block",
            ]);

            $metadata = json_encode([
                'theme' => ['light', 'dark', 'gradient'][$v - 1],
                'layout' => 'single-column',
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO cms_block_versions 
                (cms_page_block_id, version_number, version_label, block_data, metadata, change_summary, created_by_user_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $blockId,
                $v,
                "v{$v} - Update {$v}",
                $blockData,
                $metadata,
                'Updated content and styling',
                $userId,
            ]);

            $versionId = $pdo->lastInsertId();
            echo "  ✓ Created version {$v} (ID: {$versionId})\n";

            // Create publishing record
            $status = ($v === 1) ? 'published' : ($v === 2 ? 'draft' : 'scheduled');
            $visibility = ($status === 'published') ? 'public' : ($status === 'draft' ? 'draft_only' : 'public');
            $publishedAt = ($status === 'published') ? 'NOW()' : 'NULL';
            $scheduledAt = ($status === 'scheduled') ? 'DATE_ADD(NOW(), INTERVAL 3 DAY)' : 'NULL';

            $stmt = $pdo->prepare("
                INSERT INTO cms_block_publishes 
                (cms_page_block_id, cms_block_version_id, status, visibility, published_at, scheduled_publish_at, view_count, interaction_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, " . ($publishedAt === 'NOW()' ? 'NOW()' : 'NULL') . ", " . ($scheduledAt === 'DATE_ADD(NOW(), INTERVAL 3 DAY)' ? 'DATE_ADD(NOW(), INTERVAL 3 DAY)' : 'NULL') . ", ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $blockId,
                $versionId,
                $status,
                $visibility,
                rand(10, 500),
                rand(0, 100),
            ]);

            $publishId = $pdo->lastInsertId();
            echo "  ✓ Created publish record (status: {$status})\n";

            // Create revision
            $revisionType = ($status === 'published') ? 'published' : ($status === 'draft' ? 'updated' : 'scheduled');

            $stmt = $pdo->prepare("
                INSERT INTO cms_block_revisions 
                (cms_page_block_id, cms_block_version_id, revision_type, change_details, user_id, user_name, user_email, reason, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $blockId,
                $versionId,
                $revisionType,
                "Block {$revisionType} - version {$v}",
                $userId,
                'Seeder',
                'seeder@example.com',
                'Seeded for testing',
            ]);

            echo "  ✓ Created revision (type: {$revisionType})\n";
        }
    }

    echo "\n✅ Publishing workflow seeding complete!\n";
    echo "📊 Data created:\n";
    echo "  ✓ " . (4 * 3) . " versions (3 per block × 4 blocks)\n";
    echo "  ✓ " . (4 * 3) . " publishing records\n";
    echo "  ✓ " . (4 * 3) . " revisions\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
