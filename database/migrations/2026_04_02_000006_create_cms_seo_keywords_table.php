<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_metadata_id')->constrained('cms_seo_metadata')->cascadeOnDelete();
            $table->string('keyword', 255)->index();
            $table->enum('keyword_type', ['primary', 'secondary', 'related', 'lsi'])->default('secondary');
            $table->integer('density')->default(0)->comment('Keyword density percentage * 100');
            $table->integer('search_volume')->default(0)->comment('Monthly search volume estimate');
            $table->integer('keyword_rank')->nullable()->comment('Current SERP ranking position');
            $table->decimal('search_intent_score', 5, 2)->default(0)->comment('0-100 match to search intent');
            $table->integer('optimization_level')->default(0)->comment('0-100 how well optimized');
            $table->timestamp('ranked_at')->nullable();
            $table->timestamps();
            
            $table->unique(['seo_metadata_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_seo_keywords');
    }
};
