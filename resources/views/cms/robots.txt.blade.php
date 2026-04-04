User-agent: *
Allow: /

Sitemap: {{ route('sitemap', [], false) }}

# Disallow admin and internal routes
Disallow: /admin/
Disallow: /cms/
Disallow: /api/
Disallow: /login
Disallow: /register
Disallow: /password-reset

# Search engines crawl delay (in seconds)
Crawl-delay: 1
