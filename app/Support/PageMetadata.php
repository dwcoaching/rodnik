<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PageMetadata
{
    public function __construct(private LocalizedUrl $localizedUrl) {}

    /**
     * @return array{title: string, description: string, canonical: string, alternates: array<string, string>, robots: ?string, locale: string}
     */
    public function forRequest(Request $request, ?string $title = null, ?string $description = null, ?bool $indexable = null): array
    {
        $spring = $request->attributes->get('duo.spring');
        $user = $request->attributes->get('duo.user');
        $resourceTitle = $spring
            ? ($spring->name ?: ($spring->type ? __('ui.spring.types.'.Str::snake($spring->type)) : __('ui.spring.no_name')))
            : $user?->name;
        $title ??= $resourceTitle ? $resourceTitle.' — Rodnik.today' : null;
        $title ??= $request->routeIs('maps.index', 'ru.maps.index') ? __('ui.maps.my_maps').' — Rodnik.today' : null;
        $title ??= $request->routeIs('tracks.index', 'ru.tracks.index') ? __('ui.tracks.my_tracks').' — Rodnik.today' : null;
        $title ??= $request->routeIs('maps.edit', 'ru.maps.edit') ? __('ui.maps.edit_map').' — Rodnik.today' : null;
        $indexable ??= $this->localizedUrl->isIndexable($request);
        $alternates = [];

        if ($indexable) {
            foreach (array_keys(config('localization.supported')) as $locale) {
                $alternates[$locale] = $this->localizedUrl->absolute($request, $locale, true);
            }

            $alternates['x-default'] = $this->localizedUrl->absolute($request, config('localization.default'), true);
        }

        return [
            'title' => $title ?: __('seo.default_title'),
            'description' => $description ?: __('seo.default_description'),
            'canonical' => $this->localizedUrl->absolute($request, app()->getLocale(), true),
            'alternates' => $alternates,
            'robots' => $indexable ? null : 'noindex, nofollow',
            'locale' => app()->getLocale(),
        ];
    }
}
