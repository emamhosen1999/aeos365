<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_route_is_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.security.index'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.security.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.security.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Settings/Security')
                ->has('title')
                ->has('policy')
                ->has('config')
            );
    }

    public function test_password_policy_endpoints_remain_reachable(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.settings.password-policy.index'));

        $response->assertOk()
            ->assertJsonStructure(['policy']);
    }

    public function test_ip_whitelist_endpoints_remain_reachable(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.settings.ip-whitelist.index'));

        $response->assertOk()
            ->assertJsonStructure(['config']);
    }
}
