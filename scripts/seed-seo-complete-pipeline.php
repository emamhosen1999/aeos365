<?php

/**
 * Complete SEO Seeding Pipeline
 * 
 * 1. Creates cms_page_blocks with sample blocks
 * 2. Seeds SEO metadata for each block
 * 3. Populates keywords
 */

try {
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);

	echo "🌱 SEO Seeding Pipeline\n";
	echo "======================\n\n";

	$now = date('Y-m-d H:i:s');
	$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];
	$blockTypes = ['Hero', 'text', 'CTA', 'Gallery', 'Testimonial', 'Features'];

	// Step 1: Create sample page blocks
	echo "📍 Step 1: Creating sample page blocks...\n";

	$blockConfigs = [
		[
			'type' => 'Hero',
			'order_index' => 0,
			'data' => json_encode(['title' => 'Welcome', 'cta' => true]),
			'is_visible' => 1
		],
		[
			'type' => 'text',
			'order_index' => 1,
			'data' => json_encode(['content' => 'This is a text block']),
			'is_visible' => 1
		],
		[
			'type' => 'CTA',
			'order_index' => 2,
			'data' => json_encode(['button_text' => 'Get Started', 'link' => '/signup']),
			'is_visible' => 1
		],
		[
			'type' => 'Features',
			'order_index' => 3,
			'data' => json_encode(['num_features' => 3]),
			'is_visible' => 1
		]
	];

	$pageId = 1; // Welcome page
	$createdBlockIds = [];

	foreach ($blockConfigs as $config) {
		$stmt = $pdo->prepare(
			"INSERT INTO cms_page_blocks (page_id, type, data, is_visible, order_index, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$pageId,
			$config['type'],
			$config['data'],
			$config['is_visible'],
			$config['order_index'],
			$now,
			$now
		]);

		$blockId = $pdo->lastInsertId();
		$createdBlockIds[] = $blockId;
		echo "   ✅ Created " . $config['type'] . " block (id: $blockId)\n";
	}

	echo "\n📍 Step 2: Seeding SEO metadata...\n";

	$seoCount = 0;

	foreach ($createdBlockIds as $blockId) {
		foreach ($locales as $locale) {
			$metaTitle = 'Aero Block - ' . ucfirst($locale);
			$metaDescription = 'Optimized page block content for better search visibility in ' . $locale . '. SEO-friendly block with proper metadata.';
			$keywords = json_encode(['aero', 'blocks', 'cms', 'seo', $locale]);

			$ogTitle = 'Shared - ' . $metaTitle;
			$ogImage = 'https://www.placeholder.com/1200x630';

			$schema = json_encode([
				'@context' => 'https://schema.org',
				'@type' => 'WebPage',
				'headline' => $metaTitle,
				'description' => $metaDescription,
				'publisher' => ['@type' => 'Organization', 'name' => 'Aero Enterprise']
			]);

			$stmt = $pdo->prepare(
				"INSERT INTO cms_seo_metadata (
					seoable_type, seoable_id, locale,
					meta_title, meta_description, meta_keywords,
					og_title, og_description, og_image, og_type,
					twitter_card, twitter_title, twitter_description, twitter_image, twitter_creator,
					canonical_url, robots_index, robots_follow,
					schema_json, schema_type, seo_score, seo_issues,
					view_count, click_count, avg_click_through_rate,
					last_seo_audit_at, created_at, updated_at
				) VALUES (
					?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
					?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
				)"
			);

			$stmt->execute([
				'Aero\Cms\Models\CmsPageBlock',
				$blockId,
				$locale,
				$metaTitle,
				$metaDescription,
				$keywords,
				$ogTitle,
				$metaDescription,
				$ogImage,
				'article',
				'summary_large_image',
				$metaTitle,
				substr($metaDescription, 0, 200),
				$ogImage,
				'@aeroenterprise',
				'https://aeroenterprise.com/blocks/' . $blockId,
				'index',
				'follow',
				$schema,
				'WebPage',
				75 + rand(-10, 20),
				json_encode([['level' => 'info', 'message' => 'Optimized']]),
				rand(100, 10000),
				rand(10, 1000),
				rand(1, 50) / 100,
				$now,
				$now,
				$now
			]);

			$seoCount++;
		}
	}

	echo "   ✅ Seeded $seoCount SEO metadata records\n";

	echo "\n📍 Step 3: Seeding keywords...\n";

	$keywordCount = 0;

	// Get all SEO metadata we just created
	$stmt = $pdo->query(
		"SELECT id FROM cms_seo_metadata WHERE seoable_type = 'Aero\Cms\Models\CmsPageBlock'"
	);
	$seoMetadata = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$keywordList = [
		['keyword' => 'aero', 'type' => 'primary'],
		['keyword' => 'blocks', 'type' => 'primary'],
		['keyword' => 'cms', 'type' => 'secondary'],
		['keyword' => 'content', 'type' => 'secondary'],
		['keyword' => 'management', 'type' => 'secondary']
	];

	$kwStmt = $pdo->prepare(
		"INSERT INTO cms_seo_keywords (
			seo_metadata_id, keyword, keyword_type,
			density, search_volume, keyword_rank,
			search_intent_score, optimization_level,
			ranked_at, created_at, updated_at
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
	);

	foreach ($seoMetadata as $meta) {
		foreach ($keywordList as $kw) {
			$kwStmt->execute([
				$meta['id'],
				$kw['keyword'],
				$kw['type'],
				rand(5, 30) / 10,
				rand(1000, 50000),
				rand(5, 100),
				rand(60, 95),
				rand(70, 95),
				$now,
				$now,
				$now
			]);
			$keywordCount++;
		}
	}

	echo "   ✅ Seeded $keywordCount keywords\n";

	echo "\n✅ Done!\n";
	echo "\n📊 Summary:\n";
	echo "   Page blocks created: " . count($createdBlockIds) . "\n";
	echo "   SEO metadata: $seoCount\n";
	echo "   Keywords: $keywordCount\n";

	// Verify
	$pageBlockCount = $pdo->query("SELECT COUNT(*) as count FROM cms_page_blocks")->fetch(PDO::FETCH_ASSOC)['count'];
	$seoCount = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_metadata")->fetch(PDO::FETCH_ASSOC)['count'];
	$keywordCount = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_keywords")->fetch(PDO::FETCH_ASSOC)['count'];

	echo "\n📈 Final DB State:\n";
	echo "   Page blocks: $pageBlockCount\n";
	echo "   SEO metadata: $seoCount\n";
	echo "   Keywords: $keywordCount\n";
	echo "\n🎉 SEO seeding completed successfully!\n";

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . " (Line: " . $e->getLine() . ")\n";
	exit(1);
}
