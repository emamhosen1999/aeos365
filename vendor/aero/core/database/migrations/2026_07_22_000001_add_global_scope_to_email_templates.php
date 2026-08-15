<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive, guarded columns for the Global Template Library:
     *   - is_global:       a platform-curated template visible to every tenant.
     *   - owner_tenant_id: null = global (platform-owned); set = a tenant's own
     *                      copy (either authored locally or cloned from a global
     *                      via TemplateResolverService::cloneGlobalToTenant()).
     *
     * Schema::hasColumn-guarded so this is safe to run more than once and safe
     * on any host (SaaS platform, SaaS tenant, standalone) that already carries
     * the base email_templates table from 2026_05_22_000003.
     */
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('email_templates', 'is_global')) {
                $table->boolean('is_global')->default(false)->after('is_locked');
            }

            if (! Schema::hasColumn('email_templates', 'owner_tenant_id')) {
                $table->string('owner_tenant_id')->nullable()->after('is_global');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('email_templates', 'owner_tenant_id')) {
                $table->dropColumn('owner_tenant_id');
            }

            if (Schema::hasColumn('email_templates', 'is_global')) {
                $table->dropColumn('is_global');
            }
        });
    }
};
