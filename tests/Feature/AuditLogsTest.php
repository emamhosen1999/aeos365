<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.audit-logs.index'));
        $this->assertTrue(Route::has('core.audit-logs.activity'));
        $this->assertTrue(Route::has('core.audit-logs.security'));
        $this->assertTrue(Route::has('core.audit-logs.stats'));
        $this->assertTrue(Route::has('core.audit-logs.activity.export'));
        $this->assertTrue(Route::has('core.audit-logs.security.export'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.audit-logs.index'));
        $response->assertRedirect();
    }

    public function test_activity_endpoint_requires_authentication(): void
    {
        $response = $this->getJson(route('core.audit-logs.activity'));
        $response->assertUnauthorized();
    }

    public function test_security_endpoint_requires_authentication(): void
    {
        $response = $this->getJson(route('core.audit-logs.security'));
        $response->assertUnauthorized();
    }

    public function test_stats_endpoint_returns_expected_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('core.audit-logs.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'total_activities',
                'today_activities',
                'security_events',
                'active_users_today',
            ]);
    }

    public function test_activity_endpoint_returns_paginated_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('core.audit-logs.activity'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_security_endpoint_returns_paginated_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('core.audit-logs.security'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.audit-logs.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/AuditLogs/Index')
                ->has('title')
                ->has('stats')
                ->has('tab')
                ->has('logs')
                ->has('meta')
                ->has('filters')
            );
    }

    public function test_index_with_tab_param_returns_security_logs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.audit-logs.index', ['tab' => 'security']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/AuditLogs/Index')
                ->where('tab', 'security')
                ->has('logs')
                ->has('meta')
            );
    }
}
