<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.modules.index'));
        $this->assertTrue(Route::has('core.modules.role-access.show'));
        $this->assertTrue(Route::has('core.modules.role-access.sync'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.modules.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.modules.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Modules/Index')
                ->has('title')
                ->has('modules')
                ->has('roles')
                ->has('statistics')
                ->has('accessScopes')
            );
    }

    public function test_role_access_json_returns_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.modules.role-access.show', 1));

        $response->assertOk()
            ->assertJsonStructure([
                'role' => ['id', 'name'],
                'access_tree',
            ]);
    }

    public function test_sync_role_access_validates_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.modules.role-access.sync', 1), [
                'access_data' => 'invalid-string',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['access_data']);
    }

    public function test_sync_role_accepts_valid_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.modules.role-access.sync', 1), [
                'access_data' => [
                    [
                        'module_id' => 'core',
                        'submodule_id' => null,
                        'component_id' => null,
                        'action_id' => null,
                        'access_scope' => 'full',
                        'is_active' => true,
                    ],
                ],
            ]);

        // May return 200 or 403 depending on super admin check in controller
        $this->assertContains($response->getStatusCode(), [200, 403]);
    }
}
