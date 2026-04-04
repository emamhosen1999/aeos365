<?php

/**
 * Direct PDO-based SEO Metadata Seeding
 * 
 * Bypasses Laravel bootstrap, inserts data directly via PDO
 * Maps SEO metadata to cms_page_blocks entries
 */

try {
	// Database connection
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]
	);

	echo "🔗 Connected to database: aeos365\n";

	$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];

	// Get all cms_page_blocks records
	$pageBlocksStmt = $pdo->query('SELECT id, page_id FROM cms_page_blocks WHERE deleted_at IS NULL LIMIT 10');
	$pageBlocks = $pageBlocksStmt->fetchAll(PDO::FETCH_ASSOC);

	if (empty($pageBlocks)) {
		echo "⚠️  No page blocks found\n";
		exit(1);
	}

	echo "📦 Found " . count($pageBlocks) . " page blocks\n";
	$totalSeeded = 0;

	$now = date('Y-m-d H:i:s');

	foreach ($pageBlocks as $pageBlock) {
		foreach ($locales as $locale) {
			// Generate SEO data
			$metaTitle = 'Page Block - ' . ucfirst($locale);
			$metaDescription = 'Optimized description for page block with rich SEO metadata support in ' . $locale . ' language. Improve search visibility.';
			$keywords = json_encode(['blocks', 'seo', $locale, 'meta', 'content']);

			// Open Graph
			$ogTitle = 'Shared: ' . $metaTitle;
			$ogDescription = 'Share this optimized content';
			$ogImage = 'https://via.placeholder.com/1200x630?text=' . urlencode('Block');
			$ogType = 'article';

			// Twitter
			$twitterCard = 'summary_large_image';
			$twitterTitle = $metaTitle;
			$twitterDescription = substr($metaDescription, 0, 200);
			$twitterImage = $ogImage;
			$twitterCreator = '@aeroenterprise';

			// Schema.org
			$schema = json_encode([
				'@context' => 'https://schema.org',
				'@type' => 'Article',
				'headline' => $metaTitle,
				'description' => $metaDescription,
				'author' => ['@type' => 'Organization', 'name' => 'Aero'],
				'datePublished' => $now,
				'dateModified' => $now,
				'image' => [$ogImage]
			]);

			$seoScore = 75;
			$seoIssues = json_encode([
				['level' => 'info', 'message' => 'Title optimized'],
				['level' => 'info', 'message' => 'Description appropriate']
			]);

			// Insert SEO metadata
			$stmt = $pdo->prepare(
				"INSERT IGNORE INTO cms_seo_metadata (
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
				$pageBlock['id'],
				$locale,
				$metaTitle,
				$metaDescription,
				$keywords,
				$ogTitle,
				$ogDescription,
				$ogImage,
				$ogType,
				$twitterCard,
				$twitterTitle,
				$twitterDescription,
				$twitterImage,
				$twitterCreator,
				'https://aeroenterprise.com/blocks/' . $pageBlock['id'],
				'index',
				'follow',
				$schema,
				'Article',
				$seoScore,
				$seoIssues,
				rand(0, 5000),
				rand(0, 500),
				rand(1, 50) / 100,
				$now,
				$now,
				$now
			]);

			// Get inserted ID
			$metaidStmt = $pdo->prepare(
				"SELECT id FROM cms_seo_metadata 
				WHERE seoable_type = ? AND seoable_id = ? AND locale = ? 
				LIMIT 1"
			);
			$metaidStmt->execute(['Aero\Cms\Models\CmsPageBlock', $pageBlock['id'], $locale]);
			$metadata = $metaidStmt->fetch();

			if ($metadata) {
				// Insert keywords
				$keywords_data = [
					['keyword' => 'blocks', 'type' => 'primary', 'volume' => 5000, 'rank' => rand(5, 15)],
					['keyword' => 'content', 'type' => 'secondary', 'volume' => 3500, 'rank' => rand(15, 40)],
					['keyword' => 'seo', 'type' => 'secondary', 'volume' => 2000, 'rank' => rand(20, 50)],
					['keyword' => 'meta', 'type' => 'related', 'volume' => 1000, 'rank' => rand(30, 100)],
					['keyword' => 'tags', 'type' => 'lsi', 'volume' => 800, 'rank' => rand(50, 150)]
				];

				$kwStmt = $pdo->prepare(
					"INSERT IGNORE INTO cms_seo_keywords (
						seo_metadata_id, keyword, keyword_type,
						density, search_volume, keyword_rank,
						search_intent_score, optimization_level,
						ranked_at, created_at, updated_at
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
				);

				foreach ($keywords_data as $kw) {
					$kwStmt->execute([
						$metadata['id'],
						$kw['keyword'],
						$kw['type'],
						rand(5, 25) / 10,
						$kw['volume'],
						$kw['rank'],
						rand(65, 95),
						rand(70, 95),
						$now,
						$now,
						$now
					]);
				}

				$totalSeeded++;
			}
		}
	}

	echo "✅ Seeded $totalSeeded locale variants\n";
	echo "🎉 SEO metadata seeding completed!\n";
	echo "\n📊 Summary:\n";
	echo "   Page Blocks: " . count($pageBlocks) . "\n";
	echo "   Locales: " . count($locales) . "\n";
	echo "   Total Records: " . (count($pageBlocks) * count($locales)) . "\n";

	// Verify
	$countStmt = $pdo->query('SELECT COUNT(*) as count FROM cms_seo_metadata');
	$count = $countStmt->fetch();
	echo "   Total in DB: " . $count['count'] . "\n";

	$keywordStmt = $pdo->query('SELECT COUNT(*) as count FROM cms_seo_keywords');
	$kcount = $keywordStmt->fetch();
	echo "   Keywords: " . $kcount['count'] . "\n";

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	exit(1);
}
