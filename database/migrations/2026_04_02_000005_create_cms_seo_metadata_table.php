<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable'); // Polymorphic: Block, Page, etc.
            $table->string('locale', 5)->default('en');
            
            // Meta Tags
            $table->string('meta_title', 60)->nullable();
            $table->text('meta_description', 160)->nullable();
            $table->string('meta_keywords')->nullable();
            
            // Open Graph
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('website');
            
            // Twitter Card
            $table->string('twitter_card')->default('summary_large_image');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('twitter_creator')->nullable();
            
            // Technical SEO
            $table->string('canonical_url')->nullable();
            $table->enum('robots_index', ['index', 'noindex'])->default('index');
            $table->enum('robots_follow', ['follow', 'nofollow'])->default('follow');
            
            // Structured Data
            $table->json('schema_json')->nullable()->comment('JSON-LD structured data');
            $table->string('schema_type')->nullable()->comment('Schema.org type (Article, BlogPosting, Product, etc.)');
            
            // SEO Scoring
            $table->integer('seo_score')->default(0)->comment('0-100 SEO quality score');
            $table->json('seo_issues')->nullable()->comment('Array of SEO issues/recommendations');
            
            // Tracking
            $table->integer('view_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->decimal('avg_click_through_rate', 5, 2)->default(0)->comment('CTR percentage');
            $table->timestamp('last_seo_audit_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['seoable_type', 'seoable_id', 'locale']);
            $table->index(['seoable_type', 'seoable_id']);
            $table->index(['locale']);
            $table->index(['robots_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_seo_metadata');
    }
};
