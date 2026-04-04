<?php

namespace Aero\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSeoKeyword extends Model
{
    protected $table = 'cms_seo_keywords';

    protected $fillable = [
        'seo_metadata_id',
        'keyword',
        'keyword_type',
        'density',
        'search_volume',
        'keyword_rank',
        'search_intent_score',
        'optimization_level',
        'ranked_at',
    ];

    protected $casts = [
        'ranked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Parent SEO metadata
     */
    public function seoMetadata(): BelongsTo
    {
        return $this->belongsTo(CmsSeoMetadata::class);
    }

    /**
     * Scope: Filter by keyword type
     */
    public function scopeOfType(mixed $query, string $type): mixed
    {
        return $query->where('keyword_type', $type);
    }

    /**
     * Scope: Filter by primary keywords
     */
    public function scopePrimary(mixed $query): mixed
    {
        return $query->where('keyword_type', 'primary');
    }

    /**
     * Scope: Filter by ranked keywords (top positions)
     */
    public function scopeRanked(mixed $query): mixed
    {
        return $query->whereNotNull('keyword_rank')->where('keyword_rank', '<=', 100);
    }

    /**
     * Scope: Order by search volume descending
     */
    public function scopeBySearchVolume(mixed $query): mixed
    {
        return $query->orderByDesc('search_volume');
    }

    /**
     * Scope: Order by keyword rank ascending (1 = best)
     */
    public function scopeByRank(mixed $query): mixed
    {
        return $query->whereNotNull('keyword_rank')->orderBy('keyword_rank');
    }

    /**
     * Check if keyword is ranking in top 10
     */
    public function isInTopTen(): bool
    {
        return $this->keyword_rank && $this->keyword_rank <= 10;
    }

    /**
     * Check if keyword has high search volume
     */
    public function hasHighVolume(int $threshold = 1000): bool
    {
        return $this->search_volume >= $threshold;
    }

    /**
     * Get optimization status
     */
    public function getOptimizationStatus(): string
    {
        if ($this->optimization_level >= 80) {
            return 'optimized';
        } elseif ($this->optimization_level >= 50) {
            return 'partially_optimized';
        }

        return 'needs_optimization';
    }

    /**
     * Calculate keyword difficulty (potential ranking difficulty)
     */
    public function getKeywordDifficulty(): int
    {
        // Simplified calculation: higher rank = lower difficulty
        if (! $this->keyword_rank) {
            return 75; // Not ranked yet = assume high difficulty
        }

        // Scale: rank 1 = difficulty 90, rank 100 = difficulty 40
        return max(40, 90 - ($this->keyword_rank / 100) * 50);
    }

    /**
     * Get keyword assessment
     */
    public function assessKeyword(): array
    {
        return [
            'keyword' => $this->keyword,
            'type' => $this->keyword_type,
            'rank' => $this->keyword_rank,
            'top_ten' => $this->isInTopTen(),
            'search_volume' => $this->search_volume,
            'high_volume' => $this->hasHighVolume(),
            'difficulty' => $this->getKeywordDifficulty(),
            'optimization_status' => $this->getOptimizationStatus(),
            'search_intent_match' => $this->search_intent_score,
        ];
    }
}
