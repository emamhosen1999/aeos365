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
        Schema::create('cms_block_revisions', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to cms_page_blocks
            $table->unsignedBigInteger('cms_page_block_id');
            $table->foreign('cms_page_block_id')->references('id')->on('cms_page_blocks')->onDelete('cascade');
            
            // Foreign key to cms_block_versions
            $table->unsignedBigInteger('cms_block_version_id')->nullable();
            $table->foreign('cms_block_version_id')->references('id')->on('cms_block_versions')->onDelete('set null');
            
            // Revision type: created, updated, published, archived, restored, reverted
            $table->enum('revision_type', [
                'created',      // Block created
                'updated',      // Content changed
                'published',    // Published to live
                'archived',     // Archived/unpublished
                'restored',     // Restored from archive
                'reverted',     // Reverted to previous version
                'scheduled',    // Publishing scheduled
                'approved',     // Workflow approved
                'rejected'      // Workflow rejected
            ])->index();
            
            // What changed
            $table->text('change_details')->nullable(); // Human-readable change description
            $table->longText('diff_json')->nullable(); // JSON diff of changes
            $table->longText('before_state')->nullable(); // Previous state snapshot
            $table->longText('after_state')->nullable(); // New state snapshot
            
            // Author info
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            
            // Additional context
            $table->text('reason')->nullable(); // Why this change was made
            $table->json('metadata')->nullable(); // Additional metadata (IP, user agent, etc.)
            
            // Approval trail
            $table->string('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Indexes for queries
            $table->index('cms_page_block_id');
            $table->index('revision_type');
            $table->index('user_id');
            $table->index(['cms_page_block_id', 'revision_type']);
            $table->index(['cms_page_block_id', 'created_at']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_block_revisions');
    }
};
