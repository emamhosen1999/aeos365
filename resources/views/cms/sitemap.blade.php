<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
    @php
        // Group pages by translation_key to create multilingual entries
        $groupedByTranslation = $pages->groupBy('translation_key');
    @endphp

    @foreach($groupedByTranslation as $translationKey => $pageGroup)
        @php
            // Get the primary (first) page for the main URL
            $primaryPage = $pageGroup->first();
        @endphp
        <url>
            <loc>{{ route('cms.page.show', ['slug' => $primaryPage->slug], false) }}</loc>
            <lastmod>{{ $primaryPage->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>

            {{-- Add hreflang alternate links for all language versions --}}
            @foreach($pageGroup as $page)
                <xhtml:link rel="alternate" hreflang="{{ $page->language }}" href="{{ route('cms.page.show.localized', ['locale' => $page->language, 'slug' => $page->slug], false) }}" />
            @endforeach

            {{-- Add x-default hreflang for the default language --}}
            @php
                $defaultPage = $pageGroup->where('language', config('app.locale'))->first() ?? $primaryPage;
            @endphp
            <xhtml:link rel="alternate" hreflang="x-default" href="{{ route('cms.page.show', ['slug' => $defaultPage->slug], false) }}" />
        </url>
    @endforeach
</urlset>
