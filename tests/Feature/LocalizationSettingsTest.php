<?php

namespace Tests\Feature;

use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationSettingsTest extends TestCase
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

    public function test_localization_settings_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.localization.index'));
        $this->assertTrue(Route::has('core.settings.localization.update'));
    }

    public function test_localization_index_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.localization.index'));
        $response->assertRedirect();
    }

    public function test_localization_update_requires_authentication(): void
    {
        $response = $this->putJson(route('core.settings.localization.update'), [
            'timezone' => 'America/New_York',
        ]);
        $response->assertUnauthorized();
    }

    public function test_localization_update_validates_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('core.settings.localization.update'), [
            'timezone' => 'invalid-timezone',
            'currency' => 'US Dollar',
            'time_format' => 'AMPM',
            'first_day_of_week' => 7,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone', 'time_format', 'first_day_of_week']);
    }

    public function test_localization_update_persists_valid_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'locale' => 'en_GB',
            'date_format' => 'd/m/Y',
            'time_format' => '24',
            'first_day_of_week' => 1,
        ];

        $response = $this->actingAs($user)->putJson(route('core.settings.localization.update'), $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Localization settings updated successfully.')
            ->assertJsonPath('localization.timezone', 'Europe/London')
            ->assertJsonPath('localization.currency', 'GBP')
            ->assertJsonPath('localization.locale', 'en_GB')
            ->assertJsonPath('localization.date_format', 'd/m/Y')
            ->assertJsonPath('localization.time_format', '24')
            ->assertJsonPath('localization.first_day_of_week', 1);

        $setting = SystemSetting::current();
        $this->assertEquals('Europe/London', $setting->timezone);
        $this->assertEquals('GBP', $setting->currency);
        $this->assertEquals('en_GB', $setting->locale);
        $this->assertEquals('d/m/Y', $setting->date_format);
        $this->assertEquals('24', $setting->time_format);
        $this->assertEquals(1, $setting->first_day_of_week);
    }

    public function test_localization_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.localization.index'));

        $response->assertOk();
    }

    public function test_localization_payload_returns_defaults_when_null(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.localization.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('localization.timezone')
                ->has('localization.currency')
                ->has('localization.locale')
                ->has('localization.date_format')
                ->has('localization.time_format')
                ->has('localization.first_day_of_week')
            );
    }
}
