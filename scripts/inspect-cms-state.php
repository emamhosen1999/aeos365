<?php

/**
 * Inspect Database Contents
 * Check what tables have data
 */

try {
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);

	echo "📋 Database Inspection:\n\n";

	// Check all CMS tables
	$tables = [
		'cms_pages' => 'Pages',
		'cms_page_blocks' => 'Page Blocks',
		'cms_page_versions' => 'Page Versions',
		'cms_block_types' => 'Block Types',
		'cms_block_templates' => 'Block Templates',
		'cms_seo_metadata' => 'SEO Metadata',
		'cms_seo_keywords' => 'SEO Keywords'
	];

	foreach ($tables as $table => $label) {
		$stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $result['count'] ?? 0;

		$status = $count > 0 ? '✅' : '⚠️ ';
		echo "$status $label ($table): $count records\n";
	}

	echo "\n📊 Sample Data:\n";

	// Sample from each table
	echo "\n1. Block Types:\n";
	$stmt = $pdo->query("SELECT * FROM cms_block_types LIMIT 3");
	$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($types as $type) {
		echo "   - " . $type['name'] . " (id: " . $type['id'] . ")\n";
	}

	echo "\n2. Pages:\n";
	$stmt = $pdo->query("SELECT * FROM cms_pages LIMIT 3");
	$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($pages as $page) {
		echo "   - " . $page['title'] . " (id: " . $page['id'] . ")\n";
	}

	echo "\n3. Page Blocks:\n";
	$stmt = $pdo->query("SELECT * FROM cms_page_blocks LIMIT 5");
	$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($blocks)) {
		echo "   ⚠️  No page blocks found (table is empty)\n";
	} else {
		foreach ($blocks as $block) {
			echo "   - Block " . $block['id'] . " (Page: " . $block['page_id'] . ", Type: " . $block['type'] . ")\n";
		}
	}

	echo "\n4. Block Templates:\n";
	$stmt = $pdo->query("SELECT * FROM cms_block_templates LIMIT 3");
	$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($templates as $template) {
		echo "   - " . $template['name'] . " (id: " . $template['id'] . ")\n";
	}

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	exit(1);
}
