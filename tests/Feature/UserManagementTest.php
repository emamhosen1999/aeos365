<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.users.index'));
        $this->assertTrue(Route::has('core.users.create'));
        $this->assertTrue(Route::has('core.users.store'));
        $this->assertTrue(Route::has('core.users.show'));
        $this->assertTrue(Route::has('core.users.edit'));
        $this->assertTrue(Route::has('core.users.update'));
        $this->assertTrue(Route::has('core.users.destroy'));
        $this->assertTrue(Route::has('core.users.paginate'));
        $this->assertTrue(Route::has('core.users.stats'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.users.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.users.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Users/Index')
                ->has('title')
                ->has('users')
                ->has('roles')
                ->has('filters')
                ->has('stats')
            );
    }

    public function test_create_returns_inertia_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.users.create'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Users/Create')
                ->has('roles')
            );
    }

    public function test_show_returns_inertia_page(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('core.users.show', $target->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Users/Show')
                ->has('user')
            );
    }

    public function test_edit_returns_inertia_page(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('core.users.edit', $target->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Users/Edit')
                ->has('user')
                ->has('roles')
            );
    }

    public function test_store_creates_user(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('core.users.store'), [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('core.users.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_update_modifies_user(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)
            ->putJson(route('core.users.update', $target->id), [
                'name' => 'Updated Name',
                'email' => $target->email,
                'active' => true,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_destroy_soft_deletes_user(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson(route('core.users.destroy', $target->id));

        $response->assertOk();
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_paginate_returns_json(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson(route('core.users.paginate'));

        $response->assertOk()
            ->assertJsonStructure([
                'users' => ['data', 'current_page', 'last_page', 'total'],
            ]);
    }

    public function test_stats_returns_json(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson(route('core.users.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total_users',
                    'active_users',
                    'inactive_users',
                    'verified_users',
                    'users_with_roles',
                    'recent_users_30_days',
                ],
            ]);
    }
}
