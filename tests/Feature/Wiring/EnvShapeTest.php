<?php

namespace Tests\Feature\Wiring;

use Tests\TestCase;

class EnvShapeTest extends TestCase
{
    public function test_env_example_is_production_shaped(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_NAME="AEOS365"', $env, '.env.example must brand to AEOS365');
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $env);
        $this->assertStringContainsString('CACHE_STORE=redis', $env);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $env);
        $this->assertStringContainsString('LOG_CHANNEL=stack', $env);
        $this->assertStringContainsString('LOG_STACK=daily,stderr', $env);
        $this->assertStringContainsString('BROADCAST_CONNECTION=null', $env);
        $this->assertStringContainsString('FILESYSTEM_DISK=s3', $env);
        $this->assertStringContainsString('SENTRY_LARAVEL_DSN=', $env);
        $this->assertStringContainsString('AERO_MODE=saas', $env);
        $this->assertStringContainsString('APP_BASE_DOMAIN=', $env);
        $this->assertStringNotContainsString('APP_NAME=Laravel', $env);
        $this->assertStringNotContainsString('DB_CONNECTION=sqlite', $env);
    }
}
