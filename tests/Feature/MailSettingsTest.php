<?php

namespace Tests\Feature;

use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MailSettingsTest extends TestCase
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

    public function test_mail_settings_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.settings.mail.index'));
        $this->assertTrue(Route::has('core.settings.mail.update'));
        $this->assertTrue(Route::has('core.settings.mail.test'));
    }

    public function test_mail_index_requires_authentication(): void
    {
        $response = $this->get(route('core.settings.mail.index'));
        $response->assertRedirect();
    }

    public function test_mail_update_requires_authentication(): void
    {
        $response = $this->post(route('core.settings.mail.update'), [
            'host' => 'smtp.example.com',
        ]);
        $response->assertUnauthorized();
    }

    public function test_mail_update_validates_smtp_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('core.settings.mail.update'), [
            'port' => 'not-a-number',
            'from_address' => 'invalid-email',
            'encryption' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['port', 'from_address', 'encryption']);
    }

    public function test_mail_update_persists_valid_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster@example.com',
            'password' => 'secret123',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test Company',
        ];

        $response = $this->actingAs($user)->postJson(route('core.settings.mail.update'), $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Mail settings updated successfully.')
            ->assertJsonPath('email_settings.host', 'smtp.mailgun.org')
            ->assertJsonPath('email_settings.port', 587)
            ->assertJsonPath('email_settings.encryption', 'tls')
            ->assertJsonPath('email_settings.username', 'postmaster@example.com')
            ->assertJsonPath('email_settings.from_address', 'noreply@example.com')
            ->assertJsonPath('email_settings.from_name', 'Test Company')
            ->assertJsonPath('email_settings.password_set', true);

        $setting = SystemSetting::current();
        $email = $setting->email_settings ?? [];
        $this->assertEquals('smtp.mailgun.org', data_get($email, 'host'));
        $this->assertEquals(587, data_get($email, 'port'));
        $this->assertEquals('tls', data_get($email, 'encryption'));
        $this->assertNotEmpty(data_get($email, 'password'));
    }

    public function test_mail_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.settings.mail.index'));

        $response->assertOk();
    }

    public function test_mail_test_endpoint_requires_authentication(): void
    {
        $response = $this->postJson(route('core.settings.mail.test'), [
            'email' => 'test@example.com',
        ]);
        $response->assertUnauthorized();
    }

    public function test_mail_test_endpoint_validates_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('core.settings.mail.test'), [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
