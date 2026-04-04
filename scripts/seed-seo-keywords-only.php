<?php

/**
 * Seed SEO Keywords
 * Adds keywords to existing SEO metadata records
 */

try {
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);

	echo "🔑 Seeding SEO Keywords\n";

	$now = date('Y-m-d H:i:s');

	// Get all SEO metadata
	$stmt = $pdo->query(
		"SELECT id FROM cms_seo_metadata WHERE seoable_type = 'Aero\Cms\Models\CmsPageBlock' ORDER BY id"
	);
	$seoMetadata = $stmt->fetchAll(PDO::FETCH_ASSOC);

	echo "📦 Found " . count($seoMetadata) . " SEO metadata records\n";

	if (empty($seoMetadata)) {
		echo "⚠️  No SEO metadata found\n";
		exit(1);
	}

	$keywordList = [
		['keyword' => 'aero', 'type' => 'primary', 'volume' => 5000, 'score' => 90],
		['keyword' => 'blocks', 'type' => 'primary', 'volume' => 4500, 'score' => 85],
		['keyword' => 'cms', 'type' => 'secondary', 'volume' => 3500, 'score' => 80],
		['keyword' => 'content', 'type' => 'secondary', 'volume' => 3000, 'score' => 75],
		['keyword' => 'management', 'type' => 'secondary', 'volume' => 2500, 'score' => 70]
	];

	$totalKeywords = 0;

	foreach ($seoMetadata as $i => $meta) {
		foreach ($keywordList as $idx => $kw) {
			try {
				$stmt = $pdo->prepare(
					"INSERT INTO cms_seo_keywords (
						seo_metadata_id, keyword, keyword_type,
						density, search_volume, keyword_rank,
						search_intent_score, optimization_level,
						ranked_at, created_at, updated_at
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
				);

				$result = $stmt->execute([
					$meta['id'],
					$kw['keyword'],
					$kw['type'],
					rand(8, 25) / 10,
					$kw['volume'],
					$idx + rand(5, 20),
					$kw['score'],
					rand(70, 95),
					$now,
					$now,
					$now
				]);

				if ($result) {
					$totalKeywords++;
				}
			} catch (Exception $e) {
				echo "⚠️  Error inserting keyword: " . $e->getMessage() . "\n";
			}
		}

		if (($i + 1) % 8 === 0) {
			echo "   ✅ Processed " . ($i + 1) . " SEO metadata records\n";
		}
	}

	echo "\n✅ Seeded $totalKeywords keywords\n";

	// Verify
	$keywordCount = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_keywords")->fetch(PDO::FETCH_ASSOC)['count'];
	echo "\n📊 Database state:\n";
	echo "   SEO Metadata: " . count($seoMetadata) . "\n";
	echo "   Keywords: $keywordCount\n";
	echo "\n🎉 Keyword seeding complete!\n";

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	exit(1);
}
