<?php

declare(strict_types=1);

namespace Aero\Core\Support;

/**
 * Canonical demo-tenant persona credentials. Config keys keep the v1
 * defaults (aero.demo.email/password) so existing deployments are unaffected.
 */
class DemoCredentials
{
    public static function admin(): array
    {
        return [
            'label' => 'Admin',
            'name' => 'Demo Admin',
            'email' => config('aero.demo.email', 'admin@democorp.com'),
            'password' => config('aero.demo.password', 'Aeos365!Admin'),
            'role' => 'Super Administrator',
        ];
    }

    public static function employee(): array
    {
        return [
            'label' => 'Employee',
            'name' => 'Maya Rahman',
            'email' => config('aero.demo.employee_email', 'employee@democorp.com'),
            'password' => config('aero.demo.employee_password', 'Aeos365!Employee'),
            'role' => 'Employee',
        ];
    }

    /** @return array<int, array> Admin first — the login page renders in this order. */
    public static function personas(): array
    {
        return [self::admin(), self::employee()];
    }

    /** @return string[] Emails of protected demo accounts. */
    public static function emails(): array
    {
        return [self::admin()['email'], self::employee()['email']];
    }
}
