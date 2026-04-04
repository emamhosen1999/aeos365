<?php

use Aero\Cms\Http\Controllers\Api\CmsBlockTypeController;
use Aero\Cms\Http\Controllers\Api\CmsBlockController;
use Aero\Cms\Http\Controllers\Api\CmsSeoMetadataController;
use Aero\Cms\Http\Controllers\Api\CmsPageBlockController;
use Aero\Cms\Http\Controllers\Api\CmsBlockPublishingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:landlord', 'verified'])
    ->prefix('block-types')
    ->name('block-types.')
    ->group(function () {
        Route::get('/', [CmsBlockTypeController::class, 'index'])->name('index');
        Route::get('/advanced', [CmsBlockTypeController::class, 'advanced'])->name('advanced');
        Route::get('/{slug}', [CmsBlockTypeController::class, 'show'])->name('show');
        Route::get('/{slug}/schema', [CmsBlockTypeController::class, 'schema'])->name('schema');
        Route::post('/', [CmsBlockTypeController::class, 'store'])->name('store');
        Route::put('/{slug}', [CmsBlockTypeController::class, 'update'])->name('update');
        Route::delete('/{slug}', [CmsBlockTypeController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth:landlord', 'verified'])
    ->prefix('pages/{page}/blocks')
    ->name('blocks.')
    ->group(function () {
        Route::get('/', [CmsBlockController::class, 'index'])->name('index');
        Route::post('/', [CmsBlockController::class, 'store'])->name('store');
        Route::get('/{block}', [CmsBlockController::class, 'show'])->name('show');
        Route::put('/{block}/translations', [CmsBlockController::class, 'updateTranslations'])->name('translations.update');
        Route::get('/{block}/translate/{locale}', [CmsBlockController::class, 'translate'])->name('translate');
        Route::delete('/{block}', [CmsBlockController::class, 'destroy'])->name('destroy');

        // SEO Metadata endpoints
        Route::get('/{block}/seo-metadata', [CmsSeoMetadataController::class, 'index'])->name('seo.index');
        Route::post('/{block}/seo-metadata', [CmsSeoMetadataController::class, 'store'])->name('seo.store');
        Route::get('/{block}/seo-metadata/{locale}', [CmsSeoMetadataController::class, 'show'])->name('seo.show');
        Route::put('/{block}/seo-metadata/{locale}', [CmsSeoMetadataController::class, 'update'])->name('seo.update');
        Route::delete('/{block}/seo-metadata/{locale}', [CmsSeoMetadataController::class, 'destroy'])->name('seo.destroy');
        Route::get('/{block}/seo-audit/{locale?}', [CmsSeoMetadataController::class, 'audit'])->name('seo.audit');
        Route::get('/{block}/seo-metadata/{locale}/robots', [CmsSeoMetadataController::class, 'getRobotsTag'])->name('seo.robots');
    });
// Page Blocks Management API - CRUD operations
Route::middleware(['auth:landlord', 'verified'])
    ->prefix('page-blocks')
    ->name('blocks.')
    ->group(function () {
        // List all page blocks with pagination
        Route::get('/', [CmsPageBlockController::class, 'index'])->name('index');

        // Get page block statistics
        Route::get('/stats', [CmsPageBlockController::class, 'stats'])->name('stats');

        // Create new page block
        Route::post('/', [CmsPageBlockController::class, 'store'])
            ->middleware('hrmac:cms.blocks.create')
            ->name('store');

        // Get single page block
        Route::get('/{block}', [CmsPageBlockController::class, 'show'])->name('show');

        // Update page block
        Route::put('/{block}', [CmsPageBlockController::class, 'update'])
            ->middleware('hrmac:cms.blocks.update')
            ->name('update');

        // Delete page block
        Route::delete('/{block}', [CmsPageBlockController::class, 'destroy'])
            ->middleware('hrmac:cms.blocks.delete')
            ->name('destroy');
    });
