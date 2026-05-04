<?php

namespace Tests\Feature;

use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::firstOrCreate(
            ['slug' => SystemSetting::DEFAULT_SLUG],
            ['company_name' => 'Test Company']
        );
    }

    public function test_branding_settings_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.branding.index'));
        $this->assertTrue(Route::has('core.settings.branding.update'));
    }

    public function test_branding_index_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.branding.index'));
        $response->assertRedirect();
    }

    public function test_branding_update_requires_authentication(): void
    {
        $response = $this->post(route('core.settings.branding.update'), [
            'primary_color' => '#ffffff',
        ]);
        $response->assertUnauthorized();
    }

    public function test_branding_update_validates_colors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('core.settings.branding.update'), [
            'primary_color' => 'not-a-color',
            'accent_color' => '#zzzzzz',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);
    }

    public function test_branding_update_persists_colors_and_preferences(): void
    {
        $user = User::factory()->create();

        $payload = [
            'primary_color' => '#1a1a2e',
            'accent_color' => '#e94560',
            'branding' => [
                'font_family' => 'Inter, sans-serif',
                'button_radius' => 'lg',
                'show_company_name_header' => true,
                'show_logo_on_login' => false,
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('core.settings.branding.update'), $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Branding settings updated successfully.')
            ->assertJsonPath('branding.primary_color', '#1a1a2e')
            ->assertJsonPath('branding.accent_color', '#e94560')
            ->assertJsonPath('branding.font_family', 'Inter, sans-serif')
            ->assertJsonPath('branding.button_radius', 'lg')
            ->assertJsonPath('branding.show_company_name_header', true)
            ->assertJsonPath('branding.show_logo_on_login', false);

        $setting = SystemSetting::current();
        $branding = $setting->branding ?? [];
        $this->assertEquals('#1a1a2e', data_get($branding, 'primary_color'));
        $this->assertEquals('#e94560', data_get($branding, 'accent_color'));
        $this->assertEquals('Inter, sans-serif', data_get($branding, 'font_family'));
        $this->assertEquals('lg', data_get($branding, 'button_radius'));
    }

    public function test_branding_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.branding.index'));

        $response->assertOk();
    }
}
