<?php

namespace Aero\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CmsBlock extends Model
{
    use HasFactory;

    protected $table = 'cms_blocks';

    protected $fillable = [
        'page_id',
        'block_type_id',
        'slug',
        'sort_order',
        'config',
        'is_visible',
        'published_at',
    ];

    protected $casts = [
        'config' => 'json',
        'is_visible' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Get the CMS page this block belongs to
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class);
    }

    /**
     * Get the block type
     */
    public function blockType(): BelongsTo
    {
        return $this->belongsTo(CmsBlockType::class);
    }

    /**
     * Get all translations for this block
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CmsBlockTranslation::class, 'block_id');
    }

    /**
     * Get SEO metadata for this block
     */
    public function seoMetadata(): MorphMany
    {
        return $this->morphMany(CmsSeoMetadata::class, 'seoable');
    }

    /**
     * Get SEO metadata for a specific locale
     */
    public function getSeoMetadata(string $locale = null): ?CmsSeoMetadata
    {
        $locale = $locale ?? app()->getLocale();
        return $this->seoMetadata()->forLocale($locale)->first();
    }

    /**
     * Get translation for a specific locale
     */
    public function getTranslation(string $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        return $this->translations()->where('locale', $locale)->first();
    }

    /**
     * Get translated content for a locale
     */
    public function getTranslatedContent(string $locale = null): array
    {
        $translation = $this->getTranslation($locale);
        return $translation?->content ?? [];
    }

    /**
     * Scope: visible blocks only
     */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope: published blocks
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Scope: order by sort_order
     */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get available locales for this block
     */
    public function getAvailableLocales(): array
    {
        return $this->translations()->pluck('locale')->toArray();
    }

    /**
     * Check if block has translation for locale
     */
    public function hasTranslation(string $locale): bool
    {
        return $this->translations()->where('locale', $locale)->exists();
    }
}
