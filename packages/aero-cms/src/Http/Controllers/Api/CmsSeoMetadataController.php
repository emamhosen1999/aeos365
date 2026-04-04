<?php

namespace Aero\Cms\Http\Controllers\Api;

use Aero\Cms\Models\CmsBlock;
use Aero\Cms\Models\CmsSeoMetadata;
use Aero\Cms\Models\CmsSeoKeyword;
use Aero\Cms\Http\Requests\StoreSeoMetadataRequest;
use Aero\Cms\Http\Requests\UpdateSeoMetadataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsSeoMetadataController
{
    /**
     * Get SEO metadata for a block
     * GET /api/cms/blocks/{block}/seo-metadata/{locale?}
     */
    public function show(CmsBlock $block, ?string $locale = null): JsonResponse
    {
        $locale = $locale ?? app()->getLocale();

        $seo = $block->seoMetadata()->forLocale($locale)->first();

        if (! $seo) {
            return response()->json([
                'message' => 'SEO metadata not found for this locale',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => $this->formatSeoResponse($seo),
        ]);
    }

    /**
     * Get all SEO metadata variants for a block
     * GET /api/cms/blocks/{block}/seo-metadata
     */
    public function index(CmsBlock $block): JsonResponse
    {
        $seoMetadata = $block->seoMetadata()->get();

        return response()->json([
            'data' => $seoMetadata->map(fn ($seo) => $this->formatSeoResponse($seo))->toArray(),
            'count' => $seoMetadata->count(),
        ]);
    }

    /**
     * Create or update SEO metadata for a block
     * POST /api/cms/blocks/{block}/seo-metadata
     */
    public function store(CmsBlock $block, StoreSeoMetadataRequest $request): JsonResponse
    {
        try {
            $locale = $request->input('locale', app()->getLocale());

            // Find or create SEO metadata for this locale
            $seo = $block->seoMetadata()
                ->where('locale', $locale)
                ->first() ?? new CmsSeoMetadata([
                    'locale' => $locale,
                ]);

            // Update SEO fields
            $seo->fill($request->validated());
            $seo->seoable_type = CmsBlock::class;
            $seo->seoable_id = $block->id;

            // Generate SEO issues and score
            $seo->generateSeoIssues();
            $seo->updateSeoScore();

            $seo->save();

            // Handle keywords if provided
            if ($request->has('keywords')) {
                $this->updateKeywords($seo, $request->input('keywords'));
            }

            return response()->json([
                'message' => 'SEO metadata saved successfully',
                'data' => $this->formatSeoResponse($seo),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save SEO metadata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SEO metadata for a block
     * PUT /api/cms/blocks/{block}/seo-metadata/{locale}
     */
    public function update(CmsBlock $block, string $locale, UpdateSeoMetadataRequest $request): JsonResponse
    {
        try {
            $seo = $block->seoMetadata()->forLocale($locale)->first();

            if (! $seo) {
                return response()->json([
                    'message' => 'SEO metadata not found for this locale',
                ], 404);
            }

            $seo->fill($request->validated());

            // Regenerate issues and score
            $seo->generateSeoIssues();
            $seo->updateSeoScore();

            $seo->save();

            // Update keywords if provided
            if ($request->has('keywords')) {
                $this->updateKeywords($seo, $request->input('keywords'));
            }

            return response()->json([
                'message' => 'SEO metadata updated successfully',
                'data' => $this->formatSeoResponse($seo),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update SEO metadata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete SEO metadata for a block locale
     * DELETE /api/cms/blocks/{block}/seo-metadata/{locale}
     */
    public function destroy(CmsBlock $block, string $locale): JsonResponse
    {
        try {
            $seo = $block->seoMetadata()->forLocale($locale)->first();

            if (! $seo) {
                return response()->json([
                    'message' => 'SEO metadata not found',
                ], 404);
            }

            // Delete associated keywords
            $seo->keywords()->delete();
            $seo->delete();

            return response()->json([
                'message' => 'SEO metadata deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete SEO metadata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get SEO score and audit report
     * GET /api/cms/blocks/{block}/seo-audit/{locale?}
     */
    public function audit(CmsBlock $block, ?string $locale = null): JsonResponse
    {
        try {
            $locale = $locale ?? app()->getLocale();
            $seo = $block->seoMetadata()->forLocale($locale)->with('keywords')->first();

            if (! $seo) {
                return response()->json([
                    'message' => 'SEO metadata not found',
                ], 404);
            }

            $issues = $seo->seo_issues ?? [];
            $seoScore = $seo->seo_score ?? 0;

            // Categorize issues
            $errors = array_filter($issues, fn ($i) => $i['level'] === 'error');
            $warnings = array_filter($issues, fn ($i) => $i['level'] === 'warning');
            $info = array_filter($issues, fn ($i) => $i['level'] === 'info');

            return response()->json([
                'data' => [
                    'seo_score' => $seoScore,
                    'score_status' => match (true) {
                        $seoScore >= 80 => 'excellent',
                        $seoScore >= 60 => 'good',
                        $seoScore >= 40 => 'fair',
                        default => 'poor'
                    },
                    'issues' => [
                        'errors' => array_values($errors),
                        'warnings' => array_values($warnings),
                        'info' => array_values($info),
                        'total' => count($issues),
                    ],
                    'keywords' => [
                        'total' => $seo->keywords->count(),
                        'primary' => $seo->keywords()->ofType('primary')->count(),
                        'ranking' => $seo->keywords()->ranked()->count(),
                    ],
                    'metrics' => [
                        'views' => $seo->view_count,
                        'clicks' => $seo->click_count,
                        'ctr' => $seo->avg_click_through_rate . '%',
                    ],
                    'last_audit' => $seo->last_seo_audit_at?->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate SEO audit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate meta robots tag value
     * GET /api/cms/blocks/{block}/seo-metadata/{locale}/robots
     */
    public function getRobotsTag(CmsBlock $block, string $locale): JsonResponse
    {
        try {
            $seo = $block->seoMetadata()->forLocale($locale)->first();

            if (! $seo) {
                return response()->json([
                    'message' => 'SEO metadata not found',
                ], 404);
            }

            return response()->json([
                'data' => [
                    'robots' => $seo->meta_robots,
                    'index' => $seo->robots_index,
                    'follow' => $seo->robots_follow,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve robots tag',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SEO keywords
     */
    protected function updateKeywords(CmsSeoMetadata $seo, array $keywords): void
    {
        // Clear existing keywords for this metadata (keep primary only if updating from UI)
        $seo->keywords()->where('keyword_type', '!=', 'primary')->delete();

        // Add new keywords
        foreach ($keywords as $keyword) {
            $type = $keyword['type'] ?? 'secondary';

            // Check if keyword already exists and is primary, skip it
            if ($type !== 'primary') {
                CmsSeoKeyword::create([
                    'seo_metadata_id' => $seo->id,
                    'keyword' => $keyword['keyword'],
                    'keyword_type' => $type,
                    'search_volume' => $keyword['search_volume'] ?? 0,
                    'search_intent_score' => $keyword['search_intent_score'] ?? 0,
                ]);
            }
        }
    }

    /**
     * Format SEO response data
     */
    protected function formatSeoResponse(CmsSeoMetadata $seo): array
    {
        return [
            'id' => $seo->id,
            'locale' => $seo->locale,
            'meta_title' => $seo->meta_title,
            'meta_description' => $seo->meta_description,
            'meta_keywords' => $seo->meta_keywords,
            'og_title' => $seo->og_title,
            'og_description' => $seo->og_description,
            'og_image' => $seo->og_image,
            'og_type' => $seo->og_type,
            'twitter_card' => $seo->twitter_card,
            'twitter_title' => $seo->twitter_title,
            'twitter_description' => $seo->twitter_description,
            'twitter_image' => $seo->twitter_image,
            'twitter_creator' => $seo->twitter_creator,
            'canonical_url' => $seo->canonical_url,
            'robots_index' => $seo->robots_index,
            'robots_follow' => $seo->robots_follow,
            'robots_tag' => $seo->meta_robots,
            'schema_json' => $seo->schema_json,
            'schema_type' => $seo->schema_type,
            'seo_score' => $seo->seo_score,
            'seo_issues' => $seo->seo_issues,
            'view_count' => $seo->view_count,
            'click_count' => $seo->click_count,
            'avg_click_through_rate' => $seo->avg_click_through_rate,
            'last_seo_audit_at' => $seo->last_seo_audit_at?->toIso8601String(),
            'keywords' => $seo->keywords()->get()->map(fn ($k) => [
                'id' => $k->id,
                'keyword' => $k->keyword,
                'type' => $k->keyword_type,
                'rank' => $k->keyword_rank,
                'search_volume' => $k->search_volume,
                'optimization_level' => $k->optimization_level,
                'ranking_difficulty' => $k->getKeywordDifficulty(),
                'top_ten' => $k->isInTopTen(),
                'assessment' => $k->assessKeyword(),
            ])->toArray(),
        ];
    }
}
