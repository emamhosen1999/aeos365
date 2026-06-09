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
        if (Schema::hasTable('installation_progress')) {
            return;
        }

        Schema::create('installation_progress', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('latest_error')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('session_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_progress');
    }
};
