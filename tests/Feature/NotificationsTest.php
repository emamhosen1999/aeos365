<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.notifications.index'));
        $this->assertTrue(Route::has('core.notifications.list'));
        $this->assertTrue(Route::has('core.notifications.read'));
        $this->assertTrue(Route::has('core.notifications.read-all'));
        $this->assertTrue(Route::has('core.notifications.destroy'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.notifications.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.notifications.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Notifications/Index')
                ->has('title')
            );
    }

    public function test_list_returns_paginated_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.notifications.list'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'unread_count',
            ]);
    }

    public function test_list_filters_unread_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.notifications.list', ['filter' => 'unread']));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'unread_count',
            ]);
    }

    public function test_list_filters_read_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.notifications.list', ['filter' => 'read']));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'unread_count',
            ]);
    }

    public function test_mark_as_read_requires_authentication(): void
    {
        $response = $this->postJson(route('core.notifications.read', 'fake-id'));
        $response->assertUnauthorized();
    }

    public function test_mark_all_as_read_requires_authentication(): void
    {
        $response = $this->postJson(route('core.notifications.read-all'));
        $response->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->deleteJson(route('core.notifications.destroy', 'fake-id'));
        $response->assertUnauthorized();
    }
}
