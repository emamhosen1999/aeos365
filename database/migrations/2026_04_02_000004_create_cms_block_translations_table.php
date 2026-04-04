<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_block_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('cms_blocks')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->json('content')->comment('Translated block content (title, description, etc.)');
            $table->json('metadata')->nullable()->comment('Locale-specific metadata (SEO, etc.)');
            $table->timestamps();
            
            $table->unique(['block_id', 'locale']);
            $table->index(['locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_block_translations');
    }
};
