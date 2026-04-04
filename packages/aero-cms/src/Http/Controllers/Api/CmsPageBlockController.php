<?php

namespace Aero\Cms\Http\Controllers\Api;

use Aero\Cms\Models\CmsPageBlock;
use Aero\Cms\Http\Requests\StorePageBlockRequest;
use Aero\Cms\Http\Requests\UpdatePageBlockRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CmsPageBlockController extends Controller
{
    /**
     * Get paginated list of page blocks
     */
    public function index(Request $request)
    {
        $query = CmsPageBlock::query();

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%");
        }

        // Filter by locale
        if ($request->has('locale') && $request->locale) {
            $query->where('locale', $request->locale);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $status = $request->status;
            $query->whereHas('currentPublish', function ($q) use ($status) {
                $q->where('status', $status);
            })->orWhereDoesntHave('currentPublish');
        }

        // Eager load relationships
        $query->with([
            'currentPublish:id,cms_page_block_id,status,visibility,published_at,scheduled_publish_at,archived_at,view_count,interaction_count',
            'currentPublish.publishedByUser:id,name,email',
            'createdByUser:id,name,email',
        ])
        ->orderByDesc('updated_at');

        $perPage = $request->input('per_page', 25);
        $blocks = $query->paginate($perPage);

        // Format response
        return response()->json([
            'data' => $blocks->items(),
            'meta' => [
                'total' => $blocks->total(),
                'per_page' => $blocks->perPage(),
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
            ],
        ]);
    }

    /**
     * Get single page block
     */
    public function show(CmsPageBlock $block)
    {
        $block->load([
            'currentPublish',
            'versions',
            'revisions',
            'createdByUser:id,name,email',
            'updatedByUser:id,name,email',
        ]);

        return response()->json([
            'data' => $block,
        ]);
    }

    /**
     * Create new page block
     */
    public function store(StorePageBlockRequest $request)
    {
        try {
            $block = CmsPageBlock::create([
                'title' => $request->title,
                'slug' => $request->slug,
                'short_description' => $request->short_description,
                'locale' => $request->locale,
                'block_data' => $request->block_data ?? [],
                'metadata' => $request->metadata ?? [],
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            $block->load('currentPublish');

            return response()->json([
                'data' => $block,
                'message' => 'Block created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create block',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update page block
     */
    public function update(CmsPageBlock $block, UpdatePageBlockRequest $request)
    {
        try {
            $block->update([
                'title' => $request->title,
                'slug' => $request->slug,
                'short_description' => $request->short_description,
                'locale' => $request->locale,
                'block_data' => $request->block_data ?? $block->block_data,
                'metadata' => $request->metadata ?? $block->metadata,
                'updated_by_user_id' => auth()->id(),
            ]);

            $block->load('currentPublish');

            return response()->json([
                'data' => $block,
                'message' => 'Block updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update block',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete page block
     */
    public function destroy(CmsPageBlock $block)
    {
        try {
            // Delete related records
            $block->versions()->delete();
            $block->publishes()->delete();
            $block->revisions()->delete();

            $block->delete();

            return response()->json([
                'message' => 'Block deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete block',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get block statistics
     */
    public function stats()
    {
        return response()->json([
            'data' => [
                'total' => CmsPageBlock::count(),
                'published' => CmsPageBlock::whereHas('currentPublish', function ($q) {
                    $q->where('status', 'published');
                })->count(),
                'scheduled' => CmsPageBlock::whereHas('currentPublish', function ($q) {
                    $q->where('status', 'scheduled');
                })->count(),
                'drafts' => CmsPageBlock::whereDoesntHave('currentPublish')
                    ->orWhereHas('currentPublish', function ($q) {
                        $q->where('status', 'draft');
                    })
                    ->count(),
            ],
        ]);
    }
}
