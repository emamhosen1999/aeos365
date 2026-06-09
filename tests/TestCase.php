<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SaaS core routes are registered under Route::domain('{tenant}.<domain>'),
        // so every route('core.*') call needs a {tenant} parameter. Provide a
        // default so URL generation in feature tests resolves without each test
        // having to pass it explicitly.
        URL::defaults(['tenant' => 'test']);
    }
}
