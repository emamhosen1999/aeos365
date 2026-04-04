<?php

define('LARAVEL_START', microtime(true));

// Point to your Laravel installation
$laravelPath = dirname(__DIR__, 2) . '/laravel';

require $laravelPath . '/vendor/autoload.php';

$app = require_once $laravelPath . '/bootstrap/app.php';

// Override public path to this directory
$app->bind('path.public', function () {
    return __DIR__;
});

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);