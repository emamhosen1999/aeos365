<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: null,
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stancl InitializeTenancyByDomain is the canonical subdomain identifier
        // and is wired in tenancy.php bootstrappers + Stancl-aware route groups.

        // Shared security response headers (aero-auth). Safe subset always on;
        // CSP is opt-in via config('aero-auth.security_headers.csp') after the
        // policy is browser-validated against the deployed SPA.
        $middleware->append(\Aero\Auth\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
