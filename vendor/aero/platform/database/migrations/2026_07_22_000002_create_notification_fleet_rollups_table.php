<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) rollup of tenant notification deliverability.
 *
 * The platform has no cross-tenant view of notification_logs / email_suppression_list
 * — those live per tenant DB. Fanning out a live query to every tenant on each page
 * load does not scale, so RollUpNotificationDeliverabilityJob writes ONE row per
 * (tenant, date, channel) here on a schedule, and FleetDeliverabilityService reads
 * only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_fleet_rollups')) {
            return;
        }

        Schema::create('notification_fleet_rollups', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->date('date')->index();
            $table->string('channel', 20);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('bounced')->default(0);
            $table->unsignedInteger('suppressed')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_fleet_rollups');
    }
};
