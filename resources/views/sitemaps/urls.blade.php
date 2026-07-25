<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
    @foreach ($entries as $entry)
        @foreach ($entry['urls'] as $locale => $url)
            <url>
                <loc>{{ $url }}</loc>
                @foreach ($entry['urls'] as $alternateLocale => $alternateUrl)
                    <xhtml:link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}" />
                @endforeach
                <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry['urls'][config('localization.default')] }}" />
            </url>
        @endforeach
    @endforeach
</urlset>
