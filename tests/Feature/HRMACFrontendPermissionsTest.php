<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HRMACFrontendPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_wildcard_permissions_map(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('core.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.user')
                ->where('auth.user.permissions_map', ['*' => true])
            );
    }

    public function test_regular_user_permissions_map_present(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('core.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.user')
                ->has('auth.user.permissions_map')
                ->where('auth.user.is_super_admin', false)
            );
    }

    public function test_guest_has_empty_permissions_map(): void
    {
        $this->get(route('core.login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.user')
                ->where('auth.user.permissions_map', [])
                ->where('auth.user.is_super_admin', false)
            );
    }
}
