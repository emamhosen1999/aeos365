<?php

try {
	$pdo = new PDO(
		'mysql:host=127.0.0.1;port=3306;dbname=aeos365;charset=utf8mb4',
		'root',
		'',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);

	echo "📋 Database Contents:\n\n";

	// Page blocks
	$stmt = $pdo->query("SELECT COUNT(*) as count FROM cms_page_blocks");
	$pbcount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
	echo "Page Blocks: $pbcount\n";

	if ($pbcount > 0) {
		$stmt = $pdo->query("SELECT id, page_id, type FROM cms_page_blocks LIMIT 5");
		$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($blocks as $b) {
			echo "   - ID: " . $b['id'] . ", Type: " . $b['type'] . "\n";
		}
	}

	// SEO metadata
	$stmt = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_metadata");
	$seocount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
	echo "\nSEO Metadata: $seocount\n";

	if ($seocount > 0) {
		$stmt = $pdo->query("SELECT id, seoable_type, seoable_id, locale FROM cms_seo_metadata LIMIT 5");
		$meta = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($meta as $m) {
			echo "   - ID: " . $m['id'] . ", Type: " . substr($m['seoable_type'], -15) . ", ID: " . $m['seoable_id'] . ", Locale: " . $m['locale'] . "\n";
		}
	}

	// Keywords
	$stmt = $pdo->query("SELECT COUNT(*) as count FROM cms_seo_keywords");
	$kwcount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
	echo "\nSEO Keywords: $kwcount\n";

	echo "\n✅ Done\n";

} catch (PDOException $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	exit(1);
}
