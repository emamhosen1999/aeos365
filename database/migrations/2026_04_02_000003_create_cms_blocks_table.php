<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('block_type_id')->constrained('cms_block_types')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->json('config')->nullable()->comment('Block-specific configuration');
            $table->boolean('is_visible')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index(['page_id', 'sort_order']);
            $table->index(['block_type_id']);
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_blocks');
    }
};
