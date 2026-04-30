<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Aero\Auth\Contracts\AuthContext;
use Aero\Auth\Services\ModernAuthenticationService;
use Aero\Auth\Services\IpGeolocationService;

class AeroAuthPackageWiringTest extends TestCase
{
    public function test_auth_context_is_resolvable(): void
    {
        $this->assertInstanceOf(
            AuthContext::class,
            app(AuthContext::class)
        );
    }

    public function test_auth_context_has_correct_methods(): void
    {
        $authContext = app(AuthContext::class);

        $this->assertIsString($authContext->guard());
        $this->assertIsBool($authContext->isLandlordContext());
    }

    public function test_auth_routes_are_registered(): void
    {
        // Check that auth routes exist with their actual names
        $this->assertTrue(Route::has('admin.login'));
        $this->assertTrue(Route::has('auth.saml.redirect'));
        $this->assertTrue(Route::has('auth.two-factor.index'));
    }

    public function test_modern_authentication_service_is_resolvable(): void
    {
        $this->assertInstanceOf(
            ModernAuthenticationService::class,
            app(ModernAuthenticationService::class)
        );
    }

    public function test_ip_geolocation_service_is_resolvable(): void
    {
        $this->assertInstanceOf(
            IpGeolocationService::class,
            app(IpGeolocationService::class)
        );
    }

    public function test_auth_config_is_loaded(): void
    {
        $this->assertIsArray(config('auth'));
        $this->assertArrayHasKey('defaults', config('auth'));
    }
}
