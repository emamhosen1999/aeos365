<?php

namespace Aero\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeoMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('cms.blocks.update');
    }

    public function rules(): array
    {
        return [
            'locale' => 'string|max:5',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:255',
            'og_image' => 'nullable|url|max:500',
            'og_type' => 'nullable|string|in:website,article,product,business,book,profile',
            'twitter_card' => 'nullable|string|in:summary,summary_large_image,app,player',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:255',
            'twitter_image' => 'nullable|url|max:500',
            'twitter_creator' => 'nullable|string|max:100',
            'canonical_url' => 'nullable|url|max:500',
            'robots_index' => 'nullable|string|in:index,noindex',
            'robots_follow' => 'nullable|string|in:follow,nofollow',
            'schema_json' => 'nullable|json',
            'schema_type' => 'nullable|string|in:Article,BlogPosting,Product,Organization,BreadcrumbList,LocalBusiness,Event',
            'keywords' => 'nullable|array',
            'keywords.*.keyword' => 'required_with:keywords|string|max:255',
            'keywords.*.type' => 'nullable|string|in:primary,secondary,related,lsi',
            'keywords.*.search_volume' => 'nullable|integer|min:0',
            'keywords.*.search_intent_score' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'meta_title.max' => 'Meta title should not exceed 60 characters',
            'meta_description.max' => 'Meta description should not exceed 160 characters',
            'og_title.max' => 'Open Graph title should not exceed 255 characters',
            'og_description.max' => 'Open Graph description should not exceed 255 characters',
            'twitter_title.max' => 'Twitter title should not exceed 255 characters',
            'twitter_description.max' => 'Twitter description should not exceed 255 characters',
            'schema_json.json' => 'Schema JSON must be valid JSON format',
        ];
    }
}
