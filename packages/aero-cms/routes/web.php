<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * CMS Web Routes - Server-rendered React pages via Inertia
 * 
 * These routes handle the UI pages for CMS content management,
 * including blocks listing and editing interfaces.
 */

// CMS Blocks Management Pages
Route::middleware(['auth:web', 'verified'])
    ->prefix('cms/blocks')
    ->name('cms.blocks.')
    ->group(function () {
        // Blocks List Page
        Route::get('/', function () {
            return Inertia::render('CMS/BlocksIndex', [
                'title' => 'CMS Blocks',
            ]);
        })->name('index')
            ->middleware('hrmac:cms.blocks.view');

        // Create Block Page
        Route::get('/create', function () {
            return Inertia::render('CMS/BlockEditor', [
                'title' => 'Create Block',
                'block' => null,
            ]);
        })->name('create')
            ->middleware('hrmac:cms.blocks.create');

        // Edit Block Page
        Route::get('/{id}/edit', function ($id) {
            $block = \Aero\Cms\Models\CmsPageBlock::findOrFail($id);
            return Inertia::render('CMS/BlockEditor', [
                'title' => 'Edit Block',
                'blockId' => $block->id,
            ]);
        })->name('edit')
            ->middleware('hrmac:cms.blocks.update');
    });
