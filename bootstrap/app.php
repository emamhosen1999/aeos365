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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
