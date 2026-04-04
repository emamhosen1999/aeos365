<?php

namespace Aero\Cms\Database\Factories;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsBlockType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsBlockFactory extends Factory
{
    protected $model = CmsBlock::class;

    public function definition(): array
    {
        return [
            'page_id' => 1,
            'block_type_id' => CmsBlockType::first()?->id ?? 1,
            'slug' => $this->faker->slug(),
            'sort_order' => 0,
            'config' => [
                'title' => $this->faker->sentence(),
                'subtitle' => $this->faker->sentence(),
            ],
            'is_visible' => true,
            'published_at' => now(),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn() => [
            'is_visible' => false,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn() => [
            'published_at' => null,
        ]);
    }

    public function forBlockType(CmsBlockType $blockType): static
    {
        return $this->state(fn() => [
            'block_type_id' => $blockType->id,
        ]);
    }
}
