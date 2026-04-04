<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('parent_id')->references('id')->on('cms_categories')->onDelete('cascade');
        });

        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('layout')->comment('Template structure/schema');
            $table->string('preview_image')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable()->comment('Display location on frontend');
            $table->timestamps();
        });

        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('order')->default(0);
            $table->string('icon')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('menu_id')->references('id')->on('cms_menus')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('cms_menu_items')->onDelete('cascade');
        });

        Schema::create('cms_block_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('schema')->comment('Block configuration schema (JSON)');
            $table->string('frontend_component')->comment('React component name');
            $table->string('icon')->nullable();
            $table->json('preview_data')->nullable()->comment('Sample data for preview');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_block_types');
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_templates');
        Schema::dropIfExists('cms_categories');
    }
};
