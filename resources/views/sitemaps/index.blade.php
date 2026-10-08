<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>{{ route('sitemap.static') }}</loc>
    </sitemap>
    @for ($page = 1; $page <= $springPages; $page++)
        <sitemap>
            <loc>{{ route('sitemap.springs', ['page' => $page]) }}</loc>
        </sitemap>
    @endfor
    @foreach ($locationPages as $type => $pageCount)
        @for ($page = 1; $page <= $pageCount; $page++)
            <sitemap>
                <loc>{{ route('sitemap.locations', ['type' => $type, 'page' => $page]) }}</loc>
            </sitemap>
        @endfor
    @endforeach
    @for ($page = 1; $page <= $mapPages; $page++)
        <sitemap>
            <loc>{{ route('sitemap.maps', ['page' => $page]) }}</loc>
        </sitemap>
    @endfor
</sitemapindex>
