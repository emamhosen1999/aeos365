<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$pages = \Aero\Cms\Models\CmsPage::all();
echo "Total pages: " . $pages->count() . "\n";

if ($pages->count() > 0) {
    foreach ($pages as $page) {
        echo "- " . $page->slug . " | status: " . $page->status . " | language: " . $page->language . " | translation_key: " . $page->translation_key . "\n";
    }
} else {
    echo "No CMS pages found in database.\n";
}
