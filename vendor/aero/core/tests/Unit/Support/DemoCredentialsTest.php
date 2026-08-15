<?php

namespace Aero\Core\Tests\Unit\Support;

use Aero\Core\Support\DemoCredentials;
use Orchestra\Testbench\TestCase;

class DemoCredentialsTest extends TestCase
{
    public function test_defaults(): void
    {
        $admin = DemoCredentials::admin();
        $this->assertSame('admin@democorp.com', $admin['email']);
        $this->assertSame('Aeos365!Admin', $admin['password']);
        $this->assertSame('Super Administrator', $admin['role']);

        $employee = DemoCredentials::employee();
        $this->assertSame('employee@democorp.com', $employee['email']);
        $this->assertSame('Employee', $employee['role']);
    }

    public function test_config_overrides(): void
    {
        config(['aero.demo.email' => 'x@y.test', 'aero.demo.employee_email' => 'e@y.test']);
        $this->assertSame('x@y.test', DemoCredentials::admin()['email']);
        $this->assertSame(['x@y.test', 'e@y.test'], DemoCredentials::emails());
    }

    public function test_personas_order(): void
    {
        $personas = DemoCredentials::personas();
        $this->assertCount(2, $personas);
        $this->assertSame('Admin', $personas[0]['label']);
        $this->assertSame('Employee', $personas[1]['label']);
    }
}
