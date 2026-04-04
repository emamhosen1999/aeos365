<?php

namespace Aero\Cms\Database\Seeders;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsBlockType;
use Aero\Cms\Models\CmsBlockTranslation;
use Aero\Cms\Models\CmsPage;
use Illuminate\Database\Seeder;

class MultilingualBlocksSeeder extends Seeder
{
    private $locales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];

    private $translations = [
        'en' => ['title' => 'Welcome to Our Platform', 'description' => 'Join us today and experience excellence'],
        'es' => ['title' => 'Bienvenido a Nuestra Plataforma', 'description' => 'Únete a nosotros hoy y experimenta la excelencia'],
        'fr' => ['title' => 'Bienvenue sur Notre Plateforme', 'description' => 'Rejoignez-nous aujourd\'hui et vivez l\'excellence'],
        'de' => ['title' => 'Willkommen auf Unserer Plattform', 'description' => 'Treten Sie uns heute bei und erleben Sie Exzellenz'],
        'it' => ['title' => 'Benvenuto sulla Nostra Piattaforma', 'description' => 'Unisciti a noi oggi e sperimenta l\'eccellenza'],
        'pt' => ['title' => 'Bem-vindo à Nossa Plataforma', 'description' => 'Junte-se a nós hoje e experimente excelência'],
        'zh' => ['title' => '欢迎来到我们的平台', 'description' => '立即加入我们并体验卓越'],
        'ja' => ['title' => '私たちのプラットフォームへようこそ', 'description' => '今日から参加して卓越さを体験してください'],
    ];

    public function run(): void
    {
        // Get or create a CMS page
        $page = CmsPage::firstOrCreate(
            ['slug' => 'multilingual-test-page'],
            [
                'title' => 'Multilingual Test Page',
                'meta_title' => 'Multilingual Test',
                'meta_description' => 'Testing multilingual block functionality',
                'is_published' => true,
            ]
        );

        // Get block types
        $titleBlockType = CmsBlockType::where('slug', 'title-block')->first();
        $descriptionBlockType = CmsBlockType::where('slug', 'description-block')->first();

        if (!$titleBlockType || !$descriptionBlockType) {
            $this->command->error('Required block types not found. Please seed cms_block_types first.');
            return;
        }

        // Create title block
        $titleBlock = CmsBlock::create([
            'page_id' => $page->id,
            'block_type_id' => $titleBlockType->id,
            'slug' => 'test-title-block',
            'sort_order' => 1,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        // Create description block
        $descriptionBlock = CmsBlock::create([
            'page_id' => $page->id,
            'block_type_id' => $descriptionBlockType->id,
            'slug' => 'test-description-block',
            'sort_order' => 2,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        // Add translations for title block
        foreach ($this->locales as $locale) {
            CmsBlockTranslation::create([
                'block_id' => $titleBlock->id,
                'locale' => $locale,
                'content' => [
                    'title' => $this->translations[$locale]['title'],
                ],
                'metadata' => [
                    'seo_keyword' => 'platform ' . $locale,
                    'og_title' => $this->translations[$locale]['title'],
                ],
            ]);
        }

        // Add translations for description block
        foreach ($this->locales as $locale) {
            CmsBlockTranslation::create([
                'block_id' => $descriptionBlock->id,
                'locale' => $locale,
                'content' => [
                    'description' => $this->translations[$locale]['description'],
                ],
                'metadata' => [
                    'seo_keyword' => 'excellence ' . $locale,
                    'og_description' => $this->translations[$locale]['description'],
                ],
            ]);
        }

        $this->command->info("Multilingual test blocks created for {$page->slug}");
        $this->command->info("Created " . count($this->locales) . " translations for each block");
    }
}
