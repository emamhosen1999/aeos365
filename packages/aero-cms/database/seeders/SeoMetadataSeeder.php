<?php

namespace Aero\Cms\Database\Seeders;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsSeoMetadata;
use Aero\Cms\Models\CmsSeoKeyword;
use Illuminate\Database\Seeder;

class SeoMetadataSeeder extends Seeder
{
    public function run(): void
    {
        // Get all existing blocks
        $blocks = CmsBlock::all();

        if ($blocks->isEmpty()) {
            $this->command->info('No blocks found. Run MultilingualBlocksSeeder first.');
            return;
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

        $this->command->info('🚀 Seeding SEO metadata for blocks...');

        foreach ($blocks as $block) {
            $this->command->info("📝 Creating SEO metadata for block: {$block->slug}");

            foreach ($locales as $locale) {
                $seo = CmsSeoMetadata::create([
                    'seoable_type' => CmsBlock::class,
                    'seoable_id' => $block->id,
                    'locale' => $locale,
                    'meta_title' => $seoTitles[$locale] . ' - ' . $block->slug,
                    'meta_description' => $seoDescriptions[$locale],
                    'meta_keywords' => implode(', ', $keywords[$locale]),
                    'og_title' => $seoTitles[$locale],
                    'og_description' => $seoDescriptions[$locale],
                    'og_image' => 'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
                    'og_type' => 'article',
                    'twitter_card' => 'summary_large_image',
                    'twitter_title' => $seoTitles[$locale],
                    'twitter_description' => $seoDescriptions[$locale],
                    'twitter_image' => 'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
                    'twitter_creator' => '@aerocms',
                    'canonical_url' => "https://example.com/blocks/{$block->slug}",
                    'robots_index' => 'index',
                    'robots_follow' => 'follow',
                    'schema_json' => [
                        '@context' => 'https://schema.org',
                        '@type' => 'BlogPosting',
                        'headline' => $seoTitles[$locale],
                        'description' => $seoDescriptions[$locale],
                        'image' => 'https://via.placeholder.com/1200x630?text=' . urlencode($seoTitles[$locale]),
                        'author' => [
                            '@type' => 'Organization',
                            'name' => 'Aero CMS',
                        ],
                        'datePublished' => now()->toIso8601String(),
                        'dateModified' => now()->toIso8601String(),
                    ],
                    'schema_type' => 'BlogPosting',
                ]);

                // Calculate and set SEO score
                $seo->generateSeoIssues();
                $seo->updateSeoScore();

                // Add keywords
                foreach ($keywords[$locale] as $index => $keyword) {
                    CmsSeoKeyword::create([
                        'seo_metadata_id' => $seo->id,
                        'keyword' => $keyword,
                        'keyword_type' => $index === 0 ? 'primary' : 'secondary',
                        'density' => rand(1, 5),
                        'search_volume' => rand(500, 50000),
                        'keyword_rank' => rand(1, 100),
                        'search_intent_score' => rand(60, 100),
                        'optimization_level' => rand(70, 100),
                        'ranked_at' => now()->subDays(rand(1, 30)),
                    ]);
                }

                $this->command->info("✅ $locale");
            }

            $this->command->info("📊 SEO metadata created for {$block->slug} (8 locales)\n");
        }

        $this->command->info('✅ SEO metadata seeding complete!');
    }
}
