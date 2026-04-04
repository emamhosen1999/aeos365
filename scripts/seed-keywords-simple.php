<?php

try {
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);

	$now = date('Y-m-d H:i:s');

	echo "🔑 Seeding keywords for all SEO metadata...\n";

	// Get all SEO metadata IDs
	$metaIds = $pdo->query("SELECT id FROM cms_seo_metadata ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

	echo "📦 Found " . count($metaIds) . " SEO metadata records\n";

	$keywordData = [
		['keyword' => 'aero', 'type' => 'primary'],
		['keyword' => 'blocks', 'type' => 'primary'],
		['keyword' => 'cms', 'type' => 'secondary'],
		['keyword' => 'content', 'type' => 'secondary'],
		['keyword' => 'seo', 'type' => 'secondary']
	];

	$inserted = 0;

	foreach ($metaIds as $metaId) {
		foreach ($keywordData as $idx => $kw) {
			$density = rand(8, 25) / 10;
			$volume = 1000 * (5 - $idx);
			$rank = rand(5, 100);
			$score = rand(60, 95);
			$opt = rand(70, 95);

			try {
				$stmt = $pdo->prepare(
					"INSERT INTO cms_seo_keywords 
					(seo_metadata_id, keyword, keyword_type, density, search_volume, keyword_rank, search_intent_score, optimization_level, ranked_at, created_at, updated_at) 
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
				);

				$stmt->execute([
					$metaId,
					$kw['keyword'],
					$kw['type'],
					$density,
					$volume,
					$rank,
					$score,
					$opt,
					$now,
					$now,
					$now
				]);

				$inserted++;
			} catch (Exception $e) {
				echo "Error on meta $metaId, keyword {$kw['keyword']}: " . $e->getMessage() . "\n";
			}
		}
	}

	echo "✅ Inserted $inserted keywords\n";

	$total = $pdo->query("SELECT COUNT(*) FROM cms_seo_keywords")->fetchColumn();
	echo "\n📊 Total keywords in DB: $total\n";

	if ($total > 0) {
		echo "✅ Seeding successful!\n";
	} else {
		echo "⚠️  No keywords inserted\n";
	}

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	exit(1);
}
