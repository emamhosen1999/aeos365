<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecuritySplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_policy_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.password-policy.index'));
        $this->assertTrue(Route::has('core.settings.password-policy.update'));
        $this->assertTrue(Route::has('core.settings.password-policy.test'));
    }

    public function test_ip_whitelist_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.ip-whitelist.index'));
        $this->assertTrue(Route::has('core.settings.ip-whitelist.update'));
        $this->assertTrue(Route::has('core.settings.ip-whitelist.add-ip'));
        $this->assertTrue(Route::has('core.settings.ip-whitelist.remove-ip'));
        $this->assertTrue(Route::has('core.settings.ip-whitelist.test-ip'));
    }

    public function test_security_settings_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.security.index'));
        $response->assertRedirect();
    }

    public function test_password_policy_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.password-policy.index'));
        $response->assertRedirect();
    }

    public function test_ip_whitelist_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.ip-whitelist.index'));
        $response->assertRedirect();
    }

    public function test_security_settings_renders_inertia_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.security.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Settings/Security')
                ->has('title')
            );
    }

    public function test_password_policy_renders_inertia_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.password-policy.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Settings/PasswordPolicy')
                ->has('title')
                ->has('policy')
            );
    }

    public function test_ip_whitelist_renders_inertia_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.ip-whitelist.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Settings/IpWhitelist')
                ->has('title')
                ->has('config')
            );
    }

    public function test_password_policy_update_validates_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson(route('core.settings.password-policy.update'), [
                'min_length' => 4,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['min_length']);
    }

    public function test_ip_whitelist_update_validates_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson(route('core.settings.ip-whitelist.update'), [
                'mode' => 'invalid_mode',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    }

    public function test_password_policy_routes_have_hrmac_middleware(): void
    {
        $route = Route::getRoutes()->getByName('core.settings.password-policy.index');
        $this->assertNotNull($route);
        $this->assertContains('hrmac:core.settings.password_policy.view', $route->gatherMiddleware());
    }

    public function test_ip_whitelist_routes_have_hrmac_middleware(): void
    {
        $route = Route::getRoutes()->getByName('core.settings.ip-whitelist.index');
        $this->assertNotNull($route);
        $this->assertContains('hrmac:core.settings.ip_whitelist.view', $route->gatherMiddleware());
    }
}
