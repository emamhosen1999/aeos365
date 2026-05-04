<?php

namespace Tests\Feature;

use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.system.index'));
        $this->assertTrue(Route::has('core.settings.system.update'));
        $this->assertTrue(Route::has('core.settings.system.test-email'));
        $this->assertTrue(Route::has('core.settings.system.test-sms'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.system.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.system.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/Settings/SystemSettings')
                ->has('title')
                ->has('systemSettings')
            );
    }

    public function test_update_persists_system_settings(): void
    {
        $user = User::factory()->create();
        SystemSetting::current();

        $response = $this->actingAs($user)
            ->putJson(route('core.settings.system.update'), [
                'company_name' => 'Updated Corp',
                'support_email' => 'support@updated.test',
                'metadata' => [
                    'seo_title' => 'Updated Title',
                    'seo_description' => 'Updated description',
                    'default_locale' => 'en',
                    'show_help_center' => true,
                    'enable_public_pages' => false,
                ],
                'advanced' => [
                    'maintenance_mode' => false,
                    'session_timeout' => 60,
                ],
                'integrations' => [
                    'slack_webhook' => 'https://hooks.slack.com/test',
                    'teams_webhook' => null,
                    'statuspage_url' => null,
                ],
                'notification_channels' => [
                    'email' => true,
                    'sms' => false,
                    'slack' => true,
                ],
            ]);

        $response->assertOk();

        $setting = SystemSetting::current();
        $this->assertEquals('Updated Corp', $setting->company_name);
        $this->assertEquals('support@updated.test', $setting->support_email);
        $this->assertEquals('Updated Title', $setting->metadata['seo_title']);
        $this->assertEquals(60, $setting->advanced['session_timeout']);
        $this->assertEquals('https://hooks.slack.com/test', $setting->integrations['slack_webhook']);
        $this->assertTrue($setting->notification_channels['email']);
    }

    public function test_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson(route('core.settings.system.update'), [
                'company_name' => '',
                'support_email' => 'not-an-email',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name', 'support_email']);
    }

    public function test_test_email_requires_valid_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.settings.system.test-email'), [
                'email' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_test_sms_requires_phone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.settings.system.test-sms'), [
                'phone' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }
}
