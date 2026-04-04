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
        Schema::create('cms_block_publishes', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to cms_page_blocks
            $table->unsignedBigInteger('cms_page_block_id');
            $table->foreign('cms_page_block_id')->references('id')->on('cms_page_blocks')->onDelete('cascade');
            
            // Foreign key to cms_block_versions
            $table->unsignedBigInteger('cms_block_version_id')->nullable();
            $table->foreign('cms_block_version_id')->references('id')->on('cms_block_versions')->onDelete('set null');
            
            // Publishing state enum: draft, scheduled, published, archived
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft')->index();
            
            // Publishing schedule
            $table->dateTime('scheduled_publish_at')->nullable()->index();
            $table->dateTime('published_at')->nullable()->index();
            $table->dateTime('archived_at')->nullable();
            $table->dateTime('scheduled_unpublish_at')->nullable(); // For auto-archiving
            
            // Publishing metadata
            $table->string('published_by_user_id')->nullable();
            $table->string('archived_by_user_id')->nullable();
            
            // Visibility and access control
            $table->enum('visibility', ['public', 'internal', 'private', 'draft_only'])->default('draft_only');
            $table->boolean('require_approval')->default(false);
            $table->boolean('is_featured')->default(false);
            
            // Scheduling options
            $table->boolean('auto_publish')->default(false);
            $table->boolean('auto_unpublish')->default(false);
            $table->integer('publish_duration_days')->nullable(); // Auto-archive after X days
            
            // Publishing notes
            $table->text('publish_notes')->nullable();
            $table->text('rejection_reason')->nullable(); // If rejected during review
            
            // Workflow state
            $table->enum('workflow_state', ['pending_review', 'approved', 'rejected', 'ready'])->default('ready');
            
            // Tracking
            $table->integer('view_count')->default(0);
            $table->integer('interaction_count')->default(0);
            
            // Indexes
            $table->index('cms_page_block_id');
            $table->index(['status', 'scheduled_publish_at']);
            $table->index(['status', 'published_at']);
            $table->index('visibility');
            $table->index('workflow_state');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_block_publishes');
    }
};
