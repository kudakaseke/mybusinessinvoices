<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach(['home', 'features', 'pricing'] as $page)
        <url><loc>{{ route($page) }}</loc></url>
    @endforeach
</urlset>
