<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Checking CMS Block Types Table...\n";

$tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();

if (in_array('cms_block_types', $tables)) {
    echo "✓ cms_block_types table EXISTS\n";
    $count = DB::table('cms_block_types')->count();
    echo "  Records: $count\n";
    if ($count === 0) {
        echo "  Table is empty, seeding data...\n";
        seedBlockTypes();
    }
} else {
    echo "✗ cms_block_types table NOT FOUND\n";
    echo "  Creating table and seeding data...\n";
    createAndSeedTable();
}

function createAndSeedTable() {
    Schema::create('cms_block_types', function ($table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->json('schema')->nullable();
        $table->string('category')->default('advanced');
        $table->string('icon')->nullable();
        $table->string('preview_image')->nullable();
        $table->integer('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    echo "✓ Table created\n";
    seedBlockTypes();
}

function seedBlockTypes() {
    $blockTypes = [
        ['name' => 'Testimonial', 'slug' => 'testimonial', 'description' => 'Customer testimonials with avatar, name, title'],
        ['name' => 'Pricing Table', 'slug' => 'pricing-table', 'description' => 'Pricing plans with features and CTAs'],
        ['name' => 'Feature List', 'slug' => 'feature-list', 'description' => 'Features with icons and descriptions'],
        ['name' => 'Contact Form', 'slug' => 'contact-form', 'description' => 'Dynamic form with validation'],
        ['name' => 'FAQ Section', 'slug' => 'faq-section', 'description' => 'Collapsible FAQ items'],
        ['name' => 'Stats Counter', 'slug' => 'stats-counter', 'description' => 'Animated statistics counters'],
        ['name' => 'Team Members', 'slug' => 'team-members', 'description' => 'Team member cards with photos'],
        ['name' => 'Call to Action', 'slug' => 'call-to-action', 'description' => 'Full-width CTA banner'],
    ];

    foreach ($blockTypes as $index => $blockType) {
        DB::table('cms_block_types')->insertOrIgnore([
            'name' => $blockType['name'],
            'slug' => $blockType['slug'],
            'description' => $blockType['description'],
            'category' => 'advanced',
            'icon' => match($blockType['slug']) {
                'testimonial' => 'StarIcon',
                'pricing-table' => 'CreditCardIcon',
                'feature-list' => 'CheckCircleIcon',
                'contact-form' => 'EnvelopeIcon',
                'faq-section' => 'QuestionMarkCircleIcon',
                'stats-counter' => 'ChartBarIcon',
                'team-members' => 'UserGroupIcon',
                'call-to-action' => 'BullhornIcon',
                default => 'SparklesIcon'
            },
            'sort_order' => $index,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "  ✓ " . $blockType['name'] . "\n";
    }

    echo "✓ Block types seeded successfully\n";
}

echo "\nDone!\n";
