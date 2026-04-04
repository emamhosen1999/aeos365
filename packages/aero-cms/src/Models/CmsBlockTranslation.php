<?php

namespace Aero\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsBlockTranslation extends Model
{
    protected $table = 'cms_block_translations';

    protected $fillable = [
        'block_id',
        'locale',
        'content',
        'metadata',
    ];

    protected $casts = [
        'content' => 'json',
        'metadata' => 'json',
    ];

    /**
     * Get the block this translation belongs to
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(CmsBlock::class);
    }

    /**
     * Scope: filter by locale
     */
    public function scopeForLocale(\Illuminate\Database\Eloquent\Builder $query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
