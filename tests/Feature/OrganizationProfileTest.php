<?php

namespace Tests\Feature;

use Aero\Core\Models\SystemSetting;
use Aero\Core\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure SystemSetting table has a default record for current() to work
        SystemSetting::firstOrCreate(
            ['slug' => SystemSetting::DEFAULT_SLUG],
            ['company_name' => 'Test Company']
        );
    }

    public function test_organization_profile_route_is_registered(): void
    {
        $this->assertTrue(Route::has('core.organization.profile.index'));
        $this->assertTrue(Route::has('core.organization.profile.update'));
    }

    public function test_organization_profile_index_requires_authentication(): void
    {
        $response = $this->get(route('core.organization.profile.index'));
        $response->assertRedirect();
    }

    public function test_organization_profile_update_requires_authentication(): void
    {
        $response = $this->putJson(route('core.organization.profile.update'), [
            'company_name' => 'Updated Company',
        ]);
        $response->assertUnauthorized();
    }

    public function test_organization_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('core.organization.profile.update'), [
            'company_name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name', 'email']);
    }

    public function test_organization_profile_update_persists_valid_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'company_name' => 'Acme Corp',
            'legal_name' => 'Acme Corporation Ltd.',
            'tax_id' => 'TAX-12345',
            'vat_number' => 'VAT-98765',
            'registration_number' => 'REG-555',
            'industry' => 'Technology',
            'website_url' => 'https://acme.example.com',
            'email' => 'contact@acme.example.com',
            'support_email' => 'support@acme.example.com',
            'phone' => '+1-555-0100',
            'mobile_number' => '+1-555-0200',
            'fax' => '+1-555-0300',
            'contact_person' => 'John Doe',
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 400',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'USA',
            'timezone' => 'America/Chicago',
            'fiscal_year_start' => '2026-01-01',
            'fiscal_year_end' => '2026-12-31',
        ];

        $response = $this->actingAs($user)->putJson(route('core.organization.profile.update'), $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Organization profile updated successfully.');

        $setting = SystemSetting::current();
        $this->assertEquals('Acme Corp', $setting->company_name);
        $this->assertEquals('Acme Corporation Ltd.', $setting->legal_name);
        $this->assertEquals('TAX-12345', $setting->tax_id);
        $this->assertEquals('VAT-98765', $setting->vat_number);
        $this->assertEquals('REG-555', $setting->registration_number);
        $this->assertEquals('Technology', $setting->industry);
        $this->assertEquals('https://acme.example.com', $setting->website_url);
        $this->assertEquals('contact@acme.example.com', $setting->email);
        $this->assertEquals('support@acme.example.com', $setting->support_email);
        $this->assertEquals('+1-555-0100', $setting->phone);
        $this->assertEquals('+1-555-0200', $setting->mobile_number);
        $this->assertEquals('+1-555-0300', $setting->fax);
        $this->assertEquals('John Doe', $setting->contact_person);
        $this->assertEquals('123 Main St', $setting->address_line1);
        $this->assertEquals('Suite 400', $setting->address_line2);
        $this->assertEquals('Springfield', $setting->city);
        $this->assertEquals('IL', $setting->state);
        $this->assertEquals('62701', $setting->postal_code);
        $this->assertEquals('USA', $setting->country);
        $this->assertEquals('America/Chicago', $setting->timezone);
        $this->assertEquals('2026-01-01', $setting->fiscal_year_start->toDateString());
        $this->assertEquals('2026-12-31', $setting->fiscal_year_end->toDateString());
    }

    public function test_organization_profile_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.organization.profile.index'));

        $response->assertOk();
    }
}
