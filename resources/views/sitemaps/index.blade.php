<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>{{ route('sitemap.static') }}</loc>
    </sitemap>
    @for ($page = 1; $page <= $springPages; $page++)
        <sitemap>
            <loc>{{ route('sitemap.springs', ['page' => $page]) }}</loc>
        </sitemap>
    @endfor
</sitemapindex>
