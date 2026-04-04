<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_block_versions', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to cms_page_blocks
            $table->unsignedBigInteger('cms_page_block_id');
            $table->foreign('cms_page_block_id')->references('id')->on('cms_page_blocks')->onDelete('cascade');
            
            // Version tracking
            $table->integer('version_number')->default(1);
            $table->string('version_label')->nullable(); // e.g., "v1.0 - Hero Banner Update"
            
            // Content versioning
            $table->longText('block_data')->nullable(); // JSON of block content at this version
            $table->longText('metadata')->nullable(); // JSON of meta tags at this version
            
            // Revision info
            $table->text('change_summary')->nullable(); // What changed in this version
            $table->text('change_description')->nullable(); // Detailed description
            
            // Creator/editor tracking
            $table->string('created_by_user_id')->nullable();
            $table->string('edited_by_user_id')->nullable();
            
            // Unique constraints
            $table->unique(['cms_page_block_id', 'version_number']);
            
            // Indexes
            $table->index('cms_page_block_id');
            $table->index('version_number');
            $table->index('created_at');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_block_versions');
    }
};
