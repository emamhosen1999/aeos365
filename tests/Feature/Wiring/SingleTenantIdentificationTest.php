<?php

namespace Tests\Feature\Wiring;

use Tests\TestCase;

class SingleTenantIdentificationTest extends TestCase
{
    public function test_bootstrap_app_does_not_alias_custom_identify_tenant(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringNotContainsString('identify.tenant', $bootstrap,
            "bootstrap/app.php must NOT alias 'identify.tenant'; Stancl InitializeTenancyByDomain is canonical.");
        $this->assertStringNotContainsString('IdentifyTenant', $bootstrap,
            "bootstrap/app.php must NOT reference IdentifyTenant; use Stancl middleware in route groups instead.");
    }

    public function test_custom_identify_tenant_middleware_class_is_removed(): void
    {
        $path = base_path('vendor/aero/platform/src/Http/Middleware/IdentifyTenant.php');
        $this->assertFileDoesNotExist($path,
            "Custom IdentifyTenant must be removed. Stancl InitializeTenancyByDomain handles subdomain identification ".
            "without the config-mutation persistence bug present in the custom version.");
    }
}
