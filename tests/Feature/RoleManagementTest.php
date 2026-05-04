<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.roles.index'));
        $this->assertTrue(Route::has('core.roles.store'));
        $this->assertTrue(Route::has('core.roles.update'));
        $this->assertTrue(Route::has('core.roles.delete'));
        $this->assertTrue(Route::has('core.roles.toggle-status'));
        $this->assertTrue(Route::has('core.roles.assign-user'));
        $this->assertTrue(Route::has('core.roles.export'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.roles.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.roles.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Roles/Index')
                ->has('title')
                ->has('roles')
                ->has('users')
                ->has('can_manage_super_admin')
            );
    }

    public function test_store_creates_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.roles.store'), [
                'name' => 'Test Role',
                'description' => 'A test role',
                'priority' => 5,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'role' => ['id', 'name'],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.roles.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_modifies_role(): void
    {
        $user = User::factory()->create();

        // Create a role first via the API
        $create = $this->actingAs($user)
            ->postJson(route('core.roles.store'), [
                'name' => 'Old Role Name',
                'description' => 'Old desc',
            ]);
        $roleId = $create->json('role.id');

        $response = $this->actingAs($user)
            ->putJson(route('core.roles.update', $roleId), [
                'name' => 'Updated Role Name',
                'description' => 'Updated desc',
                'priority' => 10,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'role',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'name' => 'Updated Role Name',
        ]);
    }

    public function test_delete_removes_role(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)
            ->postJson(route('core.roles.store'), [
                'name' => 'Role To Delete',
            ]);
        $roleId = $create->json('role.id');

        $response = $this->actingAs($user)
            ->deleteJson(route('core.roles.delete', $roleId));

        $response->assertOk();
        $this->assertDatabaseMissing('roles', [
            'id' => $roleId,
        ]);
    }

    public function test_assign_user_to_role(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $create = $this->actingAs($admin)
            ->postJson(route('core.roles.store'), [
                'name' => 'Assignable Role',
            ]);
        $roleId = $create->json('role.id');

        $response = $this->actingAs($admin)
            ->postJson(route('core.roles.assign-user'), [
                'user_id' => $target->id,
                'roles' => [$roleId],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'roles'],
            ]);
    }

    public function test_export_returns_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.roles.export'));

        $response->assertOk();
    }
}
