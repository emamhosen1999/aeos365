<?php

namespace Aero\Cms\Http\Controllers\Api;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class CmsBlockController extends \Illuminate\Routing\Controller
{
    /**
     * Get all blocks for a page in a specific locale
     */
    public function index(CmsPage $page, ?string $locale = null): JsonResponse
    {
        try {
            $locale = $locale ?? app()->getLocale();

            $blocks = $page->blocks()
                ->visible()
                ->published()
                ->ordered()
                ->with(['blockType', 'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                }])
                ->get()
                ->map(function ($block) use ($locale) {
                    return [
                        'id' => $block->id,
                        'type' => $block->blockType->slug,
                        'type_name' => $block->blockType->name,
                        'config' => $block->config,
                        'content' => $block->getTranslatedContent($locale),
                        'metadata' => $block->getTranslation($locale)?->metadata,
                        'available_locales' => $block->getAvailableLocales(),
                        'sort_order' => $block->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $blocks,
                'locale' => $locale,
                'count' => $blocks->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch page blocks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single block with all translations
     */
    public function show(CmsBlock $block): JsonResponse
    {
        try {
            $block->load(['blockType', 'translations']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $block->id,
                    'slug' => $block->slug,
                    'type' => $block->blockType->slug,
                    'type_name' => $block->blockType->name,
                    'config' => $block->config,
                    'is_visible' => $block->is_visible,
                    'published_at' => $block->published_at,
                    'translations' => $block->translations->mapWithKeys(function ($trans) {
                        return [
                            $trans->locale => [
                                'content' => $trans->content,
                                'metadata' => $trans->metadata,
                            ]
                        ];
                    }),
                    'available_locales' => $block->getAvailableLocales(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Block not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get block translation for a specific locale
     */
    public function translate(CmsBlock $block, string $locale): JsonResponse
    {
        try {
            if (!$block->hasTranslation($locale)) {
                return response()->json([
                    'success' => false,
                    'message' => "Translation not available for locale: $locale",
                ], 404);
            }

            $translation = $block->getTranslation($locale);

            return response()->json([
                'success' => true,
                'data' => [
                    'block_id' => $block->id,
                    'locale' => $locale,
                    'content' => $translation->content,
                    'metadata' => $translation->metadata,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch translation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new block for a page
     */
    public function store(CmsPage $page): JsonResponse
    {
        try {
            $validated = request()->validate([
                'block_type_id' => 'required|exists:cms_block_types,id',
                'slug' => 'required|string|unique:cms_blocks',
                'config' => 'nullable|array',
                'is_visible' => 'boolean',
                'translations' => 'required|array',
                'translations.*.locale' => 'required|string',
                'translations.*.content' => 'required|array',
            ]);

            $block = CmsBlock::create([
                'page_id' => $page->id,
                'block_type_id' => $validated['block_type_id'],
                'slug' => $validated['slug'],
                'config' => $validated['config'] ?? [],
                'is_visible' => $validated['is_visible'] ?? true,
                'sort_order' => ($page->blocks()->max('sort_order') ?? 0) + 1,
            ]);

            // Create translations
            foreach ($validated['translations'] as $translation) {
                $block->translations()->create([
                    'locale' => $translation['locale'],
                    'content' => $translation['content'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Block created successfully',
                'data' => $block->load('translations'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create block: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update block translations
     */
    public function updateTranslations(CmsBlock $block): JsonResponse
    {
        try {
            $validated = request()->validate([
                'translations' => 'required|array',
                'translations.*.locale' => 'required|string',
                'translations.*.content' => 'required|array',
                'translations.*.metadata' => 'nullable|array',
            ]);

            foreach ($validated['translations'] as $transData) {
                $block->translations()->updateOrCreate(
                    ['locale' => $transData['locale']],
                    [
                        'content' => $transData['content'],
                        'metadata' => $transData['metadata'] ?? null,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Translations updated successfully',
                'data' => $block->load('translations'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update translations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a block
     */
    public function destroy(CmsBlock $block): JsonResponse
    {
        try {
            $block->delete();

            return response()->json([
                'success' => true,
                'message' => 'Block deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete block: ' . $e->getMessage(),
            ], 500);
        }
    }
}
