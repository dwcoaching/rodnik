<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    private const SPRINGS_PER_SITEMAP = 20000;

    public function index(): Response
    {
        $springPages = (int) ceil($this->visibleSprings()->count() / self::SPRINGS_PER_SITEMAP);

        return $this->xml('sitemaps.index', [
            'springPages' => $springPages,
        ]);
    }

    public function staticPages(): Response
    {
        $paths = [
            '/',
            '/docs/about',
            '/docs/contact-us',
            '/docs/exports',
            '/docs/coll-de-sa-batalla',
        ];

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
                    $locale => route(localized_route_name('duo', $locale), [
                        'page' => ['spring' => $springId],
                    ]),
                ])
                ->all(),
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
