<?php

namespace Aero\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CmsSeoMetadata extends Model
{
    protected $table = 'cms_seo_metadata';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'locale',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_creator',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'schema_json',
        'schema_type',
        'seo_score',
        'seo_issues',
        'view_count',
        'click_count',
        'avg_click_through_rate',
        'last_seo_audit_at',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'seo_issues' => 'array',
        'avg_click_through_rate' => 'float',
        'last_seo_audit_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Polymorphic relationship to the SEO target (CmsBlock, CmsPage, etc.)
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Keywords associated with this SEO metadata
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(CmsSeoKeyword::class, 'seo_metadata_id');
    }

    /**
     * Get primary keywords
     */
    public function primaryKeywords(): HasMany
    {
        return $this->keywords()->where('keyword_type', 'primary');
    }

    /**
     * Scope: Filter by locale
     */
    public function scopeForLocale(mixed $query, string $locale): mixed
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope: Filter by index status
     */
    public function scopeIndexed(mixed $query): mixed
    {
        return $query->where('robots_index', 'index');
    }

    /**
     * Scope: Filter by follow status
     */
    public function scopeFollowed(mixed $query): mixed
    {
        return $query->where('robots_follow', 'follow');
    }

    /**
     * Scope: Filter by SEO score minimum
     */
    public function scopeMinimumScore(mixed $query, int $score): mixed
    {
        return $query->where('seo_score', '>=', $score);
    }

    /**
     * Scope: Order by SEO score descending
     */
    public function scopeOrderByScore(mixed $query): mixed
    {
        return $query->orderByDesc('seo_score');
    }

    /**
     * Generate meta robots tag
     */
    public function getMetaRobotsAttribute(): string
    {
        $robots = [];
        if ($this->robots_index === 'noindex') {
            $robots[] = 'noindex';
        } else {
            $robots[] = 'index';
        }

        if ($this->robots_follow === 'nofollow') {
            $robots[] = 'nofollow';
        } else {
            $robots[] = 'follow';
        }

        return implode(', ', $robots);
    }

    /**
     * Calculate SEO score based on filled fields
     */
    public function calculateSeoScore(): int
    {
        $score = 0;
        $maxScore = 100;
        $totalChecks = 0;

        // Meta title (20 points)
        if ($this->meta_title && strlen($this->meta_title) >= 30 && strlen($this->meta_title) <= 60) {
            $score += 20;
        }
        $totalChecks++;

        // Meta description (20 points)
        if ($this->meta_description && strlen($this->meta_description) >= 120 && strlen($this->meta_description) <= 160) {
            $score += 20;
        }
        $totalChecks++;

        // Keywords (15 points)
        if ($this->meta_keywords && count(explode(',', $this->meta_keywords)) >= 3) {
            $score += 15;
        }
        $totalChecks++;

        // Open Graph (15 points)
        if ($this->og_title && $this->og_description && $this->og_image) {
            $score += 15;
        }
        $totalChecks++;

        // Twitter Card (10 points)
        if ($this->twitter_title && $this->twitter_description && $this->twitter_image) {
            $score += 10;
        }
        $totalChecks++;

        // Canonical URL (10 points)
        if ($this->canonical_url) {
            $score += 10;
        }
        $totalChecks++;

        // Schema.org (10 points)
        if ($this->schema_json && $this->schema_type) {
            $score += 10;
        }
        $totalChecks++;

        return min($score, $maxScore);
    }

    /**
     * Update SEO score and save
     */
    public function updateSeoScore(): self
    {
        $this->seo_score = $this->calculateSeoScore();
        $this->last_seo_audit_at = now();
        $this->save();

        return $this;
    }

    /**
     * Generate SEO issues/recommendations
     */
    public function generateSeoIssues(): array
    {
        $issues = [];

        // Meta title checks
        if (! $this->meta_title) {
            $issues[] = ['level' => 'error', 'message' => 'Meta title is missing'];
        } elseif (strlen($this->meta_title) < 30) {
            $issues[] = ['level' => 'warning', 'message' => 'Meta title is too short (< 30 characters)'];
        } elseif (strlen($this->meta_title) > 60) {
            $issues[] = ['level' => 'warning', 'message' => 'Meta title is too long (> 60 characters)'];
        }

        // Meta description checks
        if (! $this->meta_description) {
            $issues[] = ['level' => 'error', 'message' => 'Meta description is missing'];
        } elseif (strlen($this->meta_description) < 120) {
            $issues[] = ['level' => 'warning', 'message' => 'Meta description is too short (< 120 characters)'];
        } elseif (strlen($this->meta_description) > 160) {
            $issues[] = ['level' => 'warning', 'message' => 'Meta description is too long (> 160 characters)'];
        }

        // Keywords checks
        if (! $this->meta_keywords) {
            $issues[] = ['level' => 'warning', 'message' => 'No keywords defined'];
        } elseif (count(explode(',', $this->meta_keywords)) < 3) {
            $issues[] = ['level' => 'warning', 'message' => 'Less than 3 keywords defined'];
        }

        // Open Graph checks
        if (! $this->og_image) {
            $issues[] = ['level' => 'warning', 'message' => 'No Open Graph image set'];
        }

        // Schema.org checks
        if (! $this->schema_json) {
            $issues[] = ['level' => 'info', 'message' => 'Consider adding Schema.org structured data'];
        }

        $this->seo_issues = $issues;

        return $issues;
    }

    /**
     * Track view
     */
    public function recordView(): self
    {
        $this->increment('view_count');

        return $this;
    }

    /**
     * Track click
     */
    public function recordClick(): self
    {
        $this->increment('click_count');
        $this->updateClickThroughRate();

        return $this;
    }

    /**
     * Update click-through rate
     */
    public function updateClickThroughRate(): self
    {
        if ($this->view_count > 0) {
            $ctr = ($this->click_count / $this->view_count) * 100;
            $this->avg_click_through_rate = round($ctr, 2);
            $this->save();
        }

        return $this;
    }
}
