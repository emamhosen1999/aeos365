<?php

// Temp bridge: run package tests (test files live in packages/) using the host
// app's installed toolchain (phpunit + orchestra/testbench + symlinked aero/*).
$loader = require __DIR__.'/vendor/autoload.php';

$pkgs = __DIR__.'/../Aero-Enterprise-Suite-Saas/packages';

$loader->addPsr4('Aero\\Core\\Tests\\', $pkgs.'/aero-core/tests/');
$loader->addPsr4('Aero\\Contracts\\Tests\\', $pkgs.'/aero-contracts/tests/');
$loader->addPsr4('Aero\\Platform\\Tests\\', $pkgs.'/aero-platform/tests/');
$loader->addPsr4('Aero\\HRMAC\\Tests\\', $pkgs.'/aero-hrmac/tests/');
$loader->addPsr4('Aero\\Auth\\Tests\\', $pkgs.'/aero-auth/tests/');
