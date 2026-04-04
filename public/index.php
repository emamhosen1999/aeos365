<?php

declare(strict_types=1);

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so we don't have to worry about the loading
| of our classes "manually". Feels great to relieve that tension.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Bootstrap Laravel And Handle The Request
|--------------------------------------------------------------------------
|
| Without going into too much detail, Laravel "bootstraps" itself by
| creating the application instance. It then routes the request to
| the application's router for dispatch to the proper controller.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
