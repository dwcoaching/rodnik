<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Spring;
use App\Services\Geography\PublicGeography;
use App\Support\DuoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    private const SPRINGS_PER_SITEMAP = 20000;

    // Each place is emitted in two languages, staying below the 50,000 URL limit.
    private const PLACES_PER_SITEMAP = 10000;

    public function __construct(private readonly PublicGeography $geography) {}

    public function index(): Response
    {
        $springPages = (int) ceil($this->visibleSprings()->count() / self::SPRINGS_PER_SITEMAP);
        $locationPages = [];
        foreach (['countries', 'regions', 'cities'] as $type) {
            $locationPages[$type] = (int) ceil($this->geography->places($type)->count() / self::PLACES_PER_SITEMAP);
        }

        return $this->xml('sitemaps.index', [
            'springPages' => $springPages,
            'locationPages' => $locationPages,
        ]);
    }

    public function staticPages(): Response
    {
        $paths = [
            '/',
            '/docs/about',
            '/docs/legend',
            '/docs/contact-us',
            '/docs/exports',
            '/docs/coll-de-sa-batalla',
        ];

        if ($this->geography->countries()->exists()) {
            $paths[] = '/countries';
        }

        $entries = collect($paths)->map(fn (string $path): array => [
            'urls' => collect(array_keys(config('localization.supported')))
                ->mapWithKeys(fn (string $locale): array => [
                    $locale => url(localized_public_path($path, $locale)),
                ])
                ->all(),
        ]);

        return $this->xml('sitemaps.urls', ['entries' => $entries]);
    }

    public function springs(int $page): Response
    {
        abort_if($page < 1, 404);

        $springIds = $this->visibleSprings()
            ->forPage($page, self::SPRINGS_PER_SITEMAP)
            ->pluck('id');

        abort_if($springIds->isEmpty(), 404);

        $entries = $springIds->map(fn (int $springId): array => [
            'urls' => collect(array_keys(config('localization.supported')))
                ->mapWithKeys(fn (string $locale): array => [
                    $locale => app(DuoUrl::class)->absolute(['spring' => $springId], $locale, canonical: true),
                ])
                ->all(),
        ]);

        return $this->xml('sitemaps.urls', ['entries' => $entries]);
    }

    public function locations(string $type, int $page): Response
    {
        abort_if($page < 1 || ! in_array($type, ['countries', 'regions', 'cities'], true), 404);
        $places = $this->geography->places($type)->reorder()->orderBy($type.'.id')
            ->select($type.'.slug')->forPage($page, self::PLACES_PER_SITEMAP)->pluck($type.'.slug');
        abort_if($places->isEmpty(), 404);

        $entries = $places->map(fn (string $slug): array => [
            'urls' => collect(array_keys(config('localization.supported')))
                ->mapWithKeys(fn (string $locale): array => [
                    $locale => url(localized_public_path('/'.$type.'/'.$slug, $locale)),
                ])->all(),
        ]);

        return $this->xml('sitemaps.urls', ['entries' => $entries]);
    }

    private function visibleSprings(): Builder
    {
        return Spring::query()
            ->whereNull('hidden_at')
            ->whereNull('redirect_to_spring_id')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function xml(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
