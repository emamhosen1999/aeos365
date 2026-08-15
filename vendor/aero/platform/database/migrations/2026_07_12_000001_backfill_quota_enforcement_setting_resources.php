<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill quota_enforcement_settings onto the P-3 resource shape.
 *
 * The 2026_05_21 consolidation added resource/default_limit/warning_threshold_pct/
 * hard_limit_pct/action alongside the original quota_type columns, but never
 * migrated the existing rows. The result: every row carried resource = NULL and
 * default_limit = 0, so the Enforcement Settings screen (which reads and orders
 * by `resource`) rendered a list of blank rows, and nothing could match a policy
 * to a resource.
 *
 * This copies quota_type -> resource with the canonical spelling, lifts the
 * legacy percentage columns into the P-3 threshold columns where present, and
 * de-duplicates any rows that collapse onto the same resource.
 */
return new class extends Migration
{
    /** Legacy quota_type -> canonical resource key. */
    private const MAP = [
        'users' => 'users',
        'storage_gb' => 'storage_gb',
        'api_calls_monthly' => 'api_calls',
        'api_calls' => 'api_calls',
        'ai_messages' => 'ai_messages',
        'employees' => 'employees',
        'projects' => 'projects',
    ];

    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('quota_enforcement_settings')) {
            return;
        }

        $rows = DB::connection('central')->table('quota_enforcement_settings')
            ->whereNull('resource')->get();

        $seen = DB::connection('central')->table('quota_enforcement_settings')
            ->whereNotNull('resource')->pluck('resource')->all();

        foreach ($rows as $row) {
            $resource = self::MAP[$row->quota_type] ?? null;

            // A row we can't map to a known resource is legacy noise — leaving
            // it with resource = NULL keeps it out of the console (which only
            // reads non-null resources) without destroying data.
            if ($resource === null || in_array($resource, $seen, true)) {
                continue;
            }

            DB::connection('central')->table('quota_enforcement_settings')
                ->where('id', $row->id)
                ->update([
                    'resource' => $resource,
                    'warning_threshold_pct' => $row->warning_threshold_percentage ?: 80,
                    'hard_limit_pct' => $row->block_threshold_percentage ?: 100,
                    'action' => $this->actionFor($row),
                    'updated_at' => now(),
                ]);

            $seen[] = $resource;
        }
    }

    /**
     * Derive the P-3 action from the legacy block_on_exceed flag when the
     * newer `action` column has not been set.
     */
    private function actionFor(object $row): string
    {
        if (! empty($row->action)) {
            return (string) $row->action;
        }

        return ! empty($row->block_on_exceed) ? 'block' : 'warn';
    }

    public function down(): void
    {
        // Non-destructive backfill; the legacy quota_type column is untouched
        // so there is nothing to reverse.
    }
};
