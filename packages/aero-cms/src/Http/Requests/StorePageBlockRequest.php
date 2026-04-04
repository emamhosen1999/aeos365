<?php

namespace Aero\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageBlockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('cms.blocks.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:cms_page_blocks,slug',
            'short_description' => 'nullable|string|max:500',
            'locale' => 'required|string|max:10',
            'block_data' => 'nullable|array',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Block title is required',
            'title.max' => 'Block title cannot exceed 255 characters',
            'slug.required' => 'Block slug is required',
            'slug.unique' => 'This slug already exists. Please use a different one',
            'locale.required' => 'Locale is required',
            'short_description.max' => 'Short description cannot exceed 500 characters',
        ];
    }
}
