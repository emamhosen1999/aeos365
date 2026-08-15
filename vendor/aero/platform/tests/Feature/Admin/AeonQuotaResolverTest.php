<?php

declare(strict_types=1);

namespace Aero\Platform\Tests\Feature\Admin;

use Aero\Platform\Models\AeonTenantUsage;
use Aero\Platform\Models\Tenant;
use Aero\Platform\Models\TenantQuotaOverride;
use Aero\Platform\Services\Quotas\QuotaEnforcementService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * Locks the AI quota resolver's subtle conventions (discovered live):
 *  - AI OFF = the max_ai_messages key is ABSENT (0 means UNLIMITED, per the
 *    platform quota convention), and
 *  - a per-tenant Quotas-page override (TenantQuotaOverride resource
 *    'ai_messages') wins over the plan allowance.
 */
class AeonQuotaResolverTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as baseRunDatabaseMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
    }

    private function shareSqliteAcrossConnections(): void
    {
        $cfg = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        config(['database.connections.mysql' => $cfg, 'database.connections.central' => $cfg, 'tenancy.database.central_connection' => 'sqlite']);
        $this->app['db']->purge('mysql');
        $this->app['db']->purge('central');
        $pdo = $this->app['db']->connection('sqlite')->getPdo();
        $this->app['db']->connection('mysql')->setPdo($pdo);
        $this->app['db']->connection('central')->setPdo($pdo);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->shareSqliteAcrossConnections();
    }

    private function svc(): QuotaEnforcementService
    {
        return $this->app->make(QuotaEnforcementService::class);
    }

    public function test_ai_is_off_when_no_allowance_is_configured(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertFalse($this->svc()->aiEnabled($tenant));
        $this->assertSame(0, $this->svc()->getAiMessageLimit($tenant));
        $this->assertSame('flash', $this->svc()->getAiModelTier($tenant));
    }

    public function test_per_tenant_override_grants_a_message_allowance(): void
    {
        $tenant = Tenant::factory()->create();
        TenantQuotaOverride::create([
            'tenant_id' => $tenant->id,
            'resource' => 'ai_messages',
            'limit_value' => 500,
            'set_by' => null,
        ]);

        $this->assertTrue($this->svc()->aiEnabled($tenant));
        $this->assertSame(500, $this->svc()->getAiMessageLimit($tenant));
    }

    public function test_override_of_zero_means_unlimited_not_off(): void
    {
        $tenant = Tenant::factory()->create();
        TenantQuotaOverride::create([
            'tenant_id' => $tenant->id,
            'resource' => 'ai_messages',
            'limit_value' => 0,
            'set_by' => null,
        ]);

        // 0 = unlimited in the platform quota convention.
        $this->assertSame(-1, $this->svc()->getAiMessageLimit($tenant));
        $this->assertTrue($this->svc()->aiEnabled($tenant));
    }

    public function test_can_send_until_the_allowance_is_reached(): void
    {
        $tenant = Tenant::factory()->create();
        TenantQuotaOverride::create([
            'tenant_id' => $tenant->id, 'resource' => 'ai_messages', 'limit_value' => 2, 'set_by' => null,
        ]);
        $svc = $this->svc();

        $this->assertTrue($svc->canSendAiMessage($tenant));      // 0/2
        $svc->incrementAiMessages($tenant);
        $svc->incrementAiMessages($tenant);
        $this->assertSame(2, $svc->getAiMessagesUsed($tenant));  // 2/2
        $this->assertFalse($svc->canSendAiMessage($tenant));     // exhausted
    }

    public function test_ai_summary_shape(): void
    {
        $tenant = Tenant::factory()->create();
        TenantQuotaOverride::create([
            'tenant_id' => $tenant->id, 'resource' => 'ai_messages', 'limit_value' => 100, 'set_by' => null,
        ]);

        $s = $this->svc()->getAiSummary($tenant);

        $this->assertTrue($s['enabled']);
        $this->assertSame(100, $s['limit']);
        $this->assertSame(100, $s['remaining']);
        $this->assertFalse($s['unlimited']);
        $this->assertArrayHasKey('model', $s);
    }

    public function test_rollup_usage_row_persists(): void
    {
        // The fleet summary table accepts an upsert per tenant/period.
        $tenant = Tenant::factory()->create();
        AeonTenantUsage::updateOrCreate(
            ['tenant_id' => $tenant->id, 'period' => '2026-07'],
            ['enabled' => true, 'message_limit' => 200, 'model' => 'pro', 'messages_used' => 24, 'feedback_up' => 2, 'feedback_down' => 0],
        );

        $this->assertDatabaseHas('aeon_tenant_usage', [
            'tenant_id' => $tenant->id, 'period' => '2026-07', 'messages_used' => 24, 'feedback_up' => 2,
        ]);
    }
}
