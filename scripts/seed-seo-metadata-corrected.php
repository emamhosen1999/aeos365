<?php

/**
 * Seed SEO Metadata for CMS Page Blocks
 * 
 * Maps SEO metadata to cms_page_blocks entries
 * Creates multilingual test data (8 locales per block)
 */

$basePath = dirname(__FILE__, 2);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

$locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];

try {
	echo "🌱 Starting SEO metadata seeding...\n";

	// Get all cms_page_blocks records
	$pageBlocks = DB::table('cms_page_blocks')->whereNull('deleted_at')->get();

	if ($pageBlocks->isEmpty()) {
		echo "⚠️  No page blocks found in cms_page_blocks table\n";
		exit(1);
	}

	echo "📦 Found " . $pageBlocks->count() . " page blocks\n";
	$totalSeeded = 0;

	foreach ($pageBlocks as $pageBlock) {
		foreach ($locales as $locale) {
			// Generate SEO data
			$metaTitle = 'Page Block Content - ' . ucfirst($locale);
			$metaDescription = 'Optimized description for page block with rich SEO metadata support in ' . $locale . ' language. Improve search rankings with proper meta tags.';
			$keywords = json_encode([
				'page blocks',
				'content management',
				'seo ' . $locale,
				'meta tags',
				'optimization'
			]);

			// Generate Open Graph data
			$ogTitle = 'Shared Content - ' . $metaTitle;
			$ogDescription = 'Share this optimized page block on social media';
			$ogImage = 'https://via.placeholder.com/1200x630?text=' . urlencode($metaTitle);
			$ogType = 'article';

			// Generate Twitter Card data
			$twitterCard = 'summary_large_image';
			$twitterTitle = $metaTitle;
			$twitterDescription = substr($metaDescription, 0, 200);
			$twitterImage = $ogImage;
			$twitterCreator = '@aeroenterprise';

			// Generate Schema.org data
			$schema = [
				'@context' => 'https://schema.org',
				'@type' => 'Article',
				'headline' => $metaTitle,
				'description' => $metaDescription,
				'author' => [
					'@type' => 'Organization',
					'name' => 'Aero Enterprise'
				],
				'publisher' => [
					'@type' => 'Organization',
					'name' => 'Aero Enterprise',
					'logo' => [
						'@type' => 'ImageObject',
						'url' => 'https://aeroenterprise.com/logo.png',
						'width' => 250,
						'height' => 60
					]
				],
				'image' => [$ogImage],
				'datePublished' => now()->toIso8601String(),
				'dateModified' => now()->toIso8601String()
			];

			// Calculate initial SEO score
			$seoScore = 75;
			$seoIssues = json_encode([
				[
					'level' => 'info',
					'message' => 'Meta title is well-optimized'
				],
				[
					'level' => 'info',
					'message' => 'Meta description length is appropriate'
				],
				[
					'level' => 'warning',
					'message' => 'Consider adding more keywords'
				]
			]);

			// Insert SEO metadata
			$result = DB::table('cms_seo_metadata')->insertOrIgnore([
				'seoable_type' => 'Aero\Cms\Models\CmsPageBlock',
				'seoable_id' => $pageBlock->id,
				'locale' => $locale,
				'meta_title' => $metaTitle,
				'meta_description' => $metaDescription,
				'meta_keywords' => $keywords,
				'og_title' => $ogTitle,
				'og_description' => $ogDescription,
				'og_image' => $ogImage,
				'og_type' => $ogType,
				'twitter_card' => $twitterCard,
				'twitter_title' => $twitterTitle,
				'twitter_description' => $twitterDescription,
				'twitter_image' => $twitterImage,
				'twitter_creator' => $twitterCreator,
				'canonical_url' => 'https://aeosenterprise.com/pages/' . $pageBlock->page_id . '/blocks/' . $pageBlock->id,
				'robots_index' => 'index',
				'robots_follow' => 'follow',
				'schema_json' => json_encode($schema),
				'schema_type' => 'Article',
				'seo_score' => $seoScore,
				'seo_issues' => $seoIssues,
				'view_count' => rand(0, 5000),
				'click_count' => rand(0, 500),
				'avg_click_through_rate' => rand(1, 50) / 100,
				'last_seo_audit_at' => now(),
				'created_at' => now(),
				'updated_at' => now()
			]);

			if ($result) {
				$metadataId = DB::table('cms_seo_metadata')
					->where('seoable_type', '=', 'Aero\Cms\Models\CmsPageBlock')
					->where('seoable_id', '=', $pageBlock->id)
					->where('locale', '=', $locale)
					->value('id');

				if ($metadataId) {
					// Insert keywords
					$keywordsList = [
						[
							'keyword' => 'page blocks',
							'keyword_type' => 'primary',
							'density' => 2.5,
							'search_volume' => 5000,
							'keyword_rank' => rand(5, 20),
							'search_intent_score' => 85,
							'optimization_level' => 90
						],
						[
							'keyword' => 'content management',
							'keyword_type' => 'secondary',
							'density' => 1.8,
							'search_volume' => 3500,
							'keyword_rank' => rand(20, 50),
							'search_intent_score' => 80,
							'optimization_level' => 85
						],
						[
							'keyword' => 'seo ' . $locale,
							'keyword_type' => 'secondary',
							'density' => 1.2,
							'search_volume' => 2000,
							'keyword_rank' => rand(15, 40),
							'search_intent_score' => 75,
							'optimization_level' => 80
						],
						[
							'keyword' => 'meta tags',
							'keyword_type' => 'related',
							'density' => 0.8,
							'search_volume' => 1000,
							'keyword_rank' => rand(30, 100),
							'search_intent_score' => 70,
							'optimization_level' => 75
						],
						[
							'keyword' => 'schema markup',
							'keyword_type' => 'lsi',
							'density' => 0.6,
							'search_volume' => 800,
							'keyword_rank' => rand(50, 150),
							'search_intent_score' => 65,
							'optimization_level' => 70
						]
					];

					foreach ($keywordsList as $kw) {
						DB::table('cms_seo_keywords')->insertOrIgnore(
							array_merge($kw, [
								'seo_metadata_id' => $metadataId,
								'ranked_at' => now(),
								'created_at' => now(),
								'updated_at' => now()
							])
						);
					}

					$totalSeeded++;
				}
			}
		}
	}

	echo "✅ Seeded $totalSeeded locale variants\n";
	echo "🎉 SEO metadata seeding completed!\n";
	echo "\n📊 Summary:\n";
	echo "   Page Blocks: " . $pageBlocks->count() . "\n";
	echo "   Locales: " . count($locales) . "\n";
	echo "   Total SEO Records: " . ($pageBlocks->count() * count($locales)) . "\n";
	echo "   Total Keywords: " . ($pageBlocks->count() * count($locales) * 5) . "\n";

} catch (Exception $e) {
	echo "❌ Error: " . $e->getMessage() . "\n";
	echo "Line: " . $e->getLine() . "\n";
	exit(1);
}
