<?php

namespace Aero\Cms\Database\Factories;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsSeoMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsSeoMetadataFactory extends Factory
{
    protected $model = CmsSeoMetadata::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        $description = $this->faker->sentence(15);
        $keywords = $this->faker->words(5, asText: true);

        return [
            'seoable_type' => CmsBlock::class,
            'seoable_id' => CmsBlock::factory(),
            'locale' => 'en',
            'meta_title' => substr($title, 0, 60),
            'meta_description' => substr($description, 0, 160),
            'meta_keywords' => $keywords,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->faker->imageUrl(1200, 630),
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $this->faker->imageUrl(1200, 630),
            'twitter_creator' => '@' . $this->faker->userName(),
            'canonical_url' => $this->faker->url(),
            'robots_index' => 'index',
            'robots_follow' => 'follow',
            'schema_json' => [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $title,
                'description' => $description,
                'image' => $this->faker->imageUrl(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $this->faker->name(),
                ],
            ],
            'schema_type' => 'BlogPosting',
            'seo_score' => $this->faker->numberBetween(60, 95),
            'view_count' => $this->faker->numberBetween(0, 10000),
            'click_count' => $this->faker->numberBetween(0, 500),
        ];
    }

    /**
     * State: With minimal SEO data
     */
    public function minimal(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'meta_title' => null,
                'meta_description' => null,
                'og_image' => null,
                'twitter_image' => null,
                'schema_json' => null,
                'seo_score' => 20,
            ];
        });
    }

    /**
     * State: With complete SEO optimization
     */
    public function optimized(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'seo_score' => 95,
                'robots_index' => 'index',
                'robots_follow' => 'follow',
            ];
        });
    }

    /**
     * State: Not indexed
     */
    public function notIndexed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'robots_index' => 'noindex',
                'robots_follow' => 'nofollow',
            ];
        });
    }

    /**
     * State: For specific locale
     */
    public function forLocale(string $locale): self
    {
        return $this->state(function (array $attributes) use ($locale) {
            return [
                'locale' => $locale,
            ];
        });
    }

    /**
     * State: With schema.org Article
     */
    public function withArticleSchema(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'schema_type' => 'Article',
                'schema_json' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $attributes['meta_title'],
                    'description' => $attributes['meta_description'],
                    'image' => $attributes['og_image'],
                    'articleBody' => 'Article content here',
                    'author' => [
                        '@type' => 'Person',
                        'name' => 'Author Name',
                    ],
                    'datePublished' => now()->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * State: With schema.org Product
     */
    public function withProductSchema(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'schema_type' => 'Product',
                'schema_json' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $attributes['meta_title'],
                    'description' => $attributes['meta_description'],
                    'image' => $attributes['og_image'],
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '9.99',
                        'priceCurrency' => 'USD',
                    ],
                ],
            ];
        });
    }

    /**
     * State: With high view count
     */
    public function highTraffic(): self
    {
        return $this->state(function (array $attributes) {
            $viewCount = $this->faker->numberBetween(50000, 1000000);
            $clickCount = (int) ($viewCount * $this->faker->randomFloat(max: 0.05));

            return [
                'view_count' => $viewCount,
                'click_count' => $clickCount,
                'avg_click_through_rate' => ($clickCount / $viewCount) * 100,
            ];
        });
    }
}
