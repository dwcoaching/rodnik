<?php

use App\Support\Docs\DocsOverview;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('docs.index');

render(fn (View $view, DocsOverview $overview): View => $view->with($overview->data()));

?>

@extends('folio.index')

@section('title', __('pages.docs.seo_title'))
@section('description', __('pages.docs.seo_description'))

@section('content')
    @php
        $docsPath = fn (string $path): string => localized_public_path('/docs/'.$path);
        $count = fn (int|string $value): string => number_format((int) $value, 0, ',', ' ');
        $stats = [
            'sources' => $sourcesCount,
            'reports' => $reportsCount,
            'contributors' => $contributorsCount,
            'maps' => $mapsCount,
            'countries' => $countriesCount,
        ];
        $exportFormats = $exports
            ->map(fn (array $file): array => [
                'label' => match (strtolower($file['extension'] ?? '')) {
                    'zip', 'csv' => 'CSV',
                    default => strtoupper($file['extension'] ?? ''),
                },
                'size' => $file['size_human'],
            ])
            ->sortBy('label')
            ->values();
        $showApi = auth()->check() && app()->isLocale('en');
        $cardClass = 'group relative rounded-xl border border-zinc-200 bg-white transition-colors hover:border-blue-300 hover:bg-blue-50/40';
        $cardLinkClass = 'text-zinc-900 transition-colors group-hover:text-blue-700 after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-blue-600';
    @endphp

    <main class="@container mx-auto grid max-w-5xl grid-cols-1 gap-12 pb-8">
        <header class="grid gap-6">
            <div class="grid gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __('pages.docs.title') }}</h1>
                <p class="max-w-3xl text-base leading-7 text-zinc-600">{{ __('pages.docs.lead') }}</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <a href="{{ localized_public_path() }}" data-rodnik-navigate class="map-button-primary">{{ __('pages.docs.open_map') }}</a>
                    <a href="{{ $docsPath('about') }}" data-rodnik-navigate class="map-button-secondary">{{ __('pages.about.title') }}</a>
                </div>
            </div>
            <section aria-labelledby="docs-stats">
                <h2 id="docs-stats" class="sr-only">{{ __('pages.docs.stats.title') }}</h2>
                <dl class="grid grid-cols-2 gap-3 @xl:grid-cols-3 @3xl:grid-cols-5">
                    @foreach ($stats as $key => $value)
                        <div @class(['flex flex-col-reverse justify-end gap-0.5 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3', 'col-span-2 @xl:col-span-1' => $loop->first])>
                            <dt class="text-sm leading-5 text-zinc-600">{{ trans_choice('pages.docs.stats.'.$key, (int) $value) }}</dt>
                            <dd class="text-2xl font-bold tracking-tight whitespace-nowrap text-zinc-900 tabular-nums">{{ $count($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </header>

        <section aria-labelledby="docs-contributors" class="grid grid-cols-1 gap-4">
            @include('docs.section-heading', [
                'id' => 'docs-contributors',
                'title' => __('pages.users.title'),
                'description' => __('pages.users.description'),
                'href' => route(app()->isLocale('ru') ? 'ru.docs.users' : 'docs.users'),
                'link' => __('pages.docs.contributors.all', ['count' => $count($contributorsCount)]),
            ])
            @if ($contributors === [])
                <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">{{ __('pages.users.empty') }}</p>
            @else
                <ol class="grid grid-cols-1 gap-2 @2xl:grid-flow-col @2xl:grid-cols-2 @2xl:grid-rows-(--contributor-rows)" style="--contributor-rows: repeat({{ (int) ceil(count($contributors) / 2) }}, auto)">
                    @foreach ($contributors as $user)
                        <li class="flex min-w-0 items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2">
                            <span class="w-5 shrink-0 text-right text-sm font-semibold text-zinc-400 tabular-nums">{{ $loop->iteration }}</span>
                            <div class="grid min-w-0 flex-1 grid-cols-1">
                                <a href="{{ duo_route(['user' => $user['id']]) }}" data-rodnik-navigate class="truncate rounded-sm font-semibold text-blue-700 hover:text-blue-800 hover:underline focus-visible:outline-2 focus-visible:outline-blue-600">{{ $user['name'] }}</a>
                                <span class="text-xs leading-5 text-zinc-500">{{ trans_choice('pages.docs.contributors.sources', $user['springs_count'], ['count' => $count($user['springs_count'])]) }}</span>
                            </div>
                            <span class="shrink-0 text-sm font-semibold whitespace-nowrap text-zinc-900 tabular-nums">{{ trans_choice('pages.docs.contributors.reports', $user['reports_count'], ['count' => $count($user['reports_count'])]) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section aria-labelledby="docs-maps" class="grid grid-cols-1 gap-4">
            @include('docs.section-heading', [
                'id' => 'docs-maps',
                'title' => __('pages.maps.title'),
                'description' => __('pages.docs.maps.description'),
                'href' => $docsPath('maps'),
                'link' => __('pages.docs.maps.all', ['count' => $count($mapsCount)]),
            ])
            @if ($maps->isEmpty())
                <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">{{ __('pages.maps.empty') }}</p>
            @else
                <div class="grid grid-cols-1 gap-3 @2xl:grid-cols-2">
                    @foreach ($maps as $map)
                        <article class="{{ $cardClass }} flex items-center gap-3 p-3">
                            @include('docs.map-item', ['map' => $map, 'heading' => 'h3'])
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="docs-open-data" class="grid grid-cols-1 gap-4">
            @include('docs.section-heading', [
                'id' => 'docs-open-data',
                'title' => __('pages.docs.open_data.title'),
                'description' => __('pages.docs.open_data.description'),
            ])
            <div @class(['grid gap-3', '@2xl:grid-cols-3' => $showApi, '@2xl:grid-cols-2' => ! $showApi])>
                <article class="{{ $cardClass }} grid content-start gap-2 p-4">
                    <h3 class="font-semibold"><a href="{{ $docsPath('exports') }}" data-rodnik-navigate class="{{ $cardLinkClass }}">{{ __('pages.exports.title') }}</a></h3>
                    <p class="text-sm leading-6 text-zinc-600">{{ __('pages.docs.open_data.exports') }}</p>
                    @if ($exportFormats->isEmpty())
                        <p class="text-sm text-zinc-500">{{ __('pages.exports.empty.title') }}</p>
                    @else
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($exportFormats as $format)
                                <li class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-700">{{ $format['label'] }} <span class="font-normal text-zinc-500">{{ $format['size'] }}</span></li>
                            @endforeach
                        </ul>
                        <p class="text-xs leading-5 text-zinc-500">{{ __('pages.exports.as_of', ['date' => $exports->min('timestamp')?->format('Y-m-d H:i')]) }}</p>
                    @endif
                </article>
                <article class="{{ $cardClass }} grid content-start gap-2 p-4">
                    <h3 class="font-semibold"><a href="{{ $docsPath('legend') }}" data-rodnik-navigate class="{{ $cardLinkClass }}">{{ __('ui.home.map_legend.title') }}</a></h3>
                    <p class="text-sm leading-6 text-zinc-600">{{ __('ui.home.map_legend.seo_description') }}</p>
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span class="size-4 rounded-full border border-[#33A9FF] bg-[#33A9FF]/10"></span>
                        <span class="size-4 rounded-full border border-[#006600] bg-[#009900]/50"></span>
                        <span class="size-4 rounded-full border border-[#ff9900] bg-[#FFB400]/80"></span>
                        <span class="size-4 rounded-full border border-[#FF0000] bg-[#FF0000]/50"></span>
                    </div>
                </article>
                @if ($showApi)
                    <article class="{{ $cardClass }} grid content-start gap-2 p-4">
                        <h3 class="font-semibold"><a href="{{ route('docs.api') }}" data-rodnik-navigate class="{{ $cardLinkClass }}">{{ __('pages.docs.open_data.api') }}</a></h3>
                        <p class="text-sm leading-6 text-zinc-600">{{ __('pages.docs.open_data.api_description') }}</p>
                    </article>
                @endif
            </div>
        </section>

        <section aria-labelledby="docs-locations" class="grid grid-cols-1 gap-4">
            @include('docs.section-heading', [
                'id' => 'docs-locations',
                'title' => __('pages.docs.locations.title'),
                'description' => __('pages.docs.locations.description'),
                'href' => localized_route('geography.countries.index'),
                'link' => __('pages.docs.locations.all', ['count' => $count($countriesCount)]),
            ])
            @if (array_filter($places) === [])
                <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">{{ __('pages.docs.locations.empty') }}</p>
            @else
                <div class="grid grid-cols-1 gap-6 @3xl:grid-cols-3 @3xl:gap-4">
                    @foreach (array_filter($places) as $type => $list)
                        <section aria-labelledby="docs-locations-{{ $type }}" class="grid min-w-0 grid-cols-1 content-start gap-1.5">
                            <h3 id="docs-locations-{{ $type }}" class="px-2 text-sm font-semibold text-zinc-500">{{ __('pages.docs.locations.'.$type) }}</h3>
                            <ol class="grid grid-cols-1 gap-0.5">
                                @foreach ($list as $place)
                                    <li>
                                        <a href="{{ $place['url'] }}" data-rodnik-navigate class="flex items-baseline gap-3 rounded-md px-2 py-1.5 transition-colors hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600">
                                            <span class="min-w-0 flex-1 truncate">
                                                <span class="font-medium text-blue-700">{{ $place['name'] }}</span>
                                                @if ($place['country'] !== null)
                                                    <span class="text-sm text-zinc-500">· {{ $place['country'] }}</span>
                                                @endif
                                            </span>
                                            <span class="shrink-0 text-sm text-zinc-600 tabular-nums">{{ $count($place['sources_count']) }}<span class="sr-only"> {{ trans_choice('pages.docs.stats.sources', $place['sources_count']) }}</span></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="docs-help" class="grid grid-cols-1 gap-4">
            @include('docs.section-heading', ['id' => 'docs-help', 'title' => __('pages.docs.help.title')])
            <ul class="grid grid-cols-1 gap-3 @xl:grid-cols-2">
                @foreach ([
                    ['😀', __('pages.about.title'), __('pages.about.seo_description'), $docsPath('about')],
                    ['💬', __('pages.contact.title'), __('pages.contact.seo_description'), $docsPath('contact-us')],
                    ['🔒', __('privacy.title'), __('privacy.description'), route(app()->isLocale('ru') ? 'ru.docs.privacy' : 'docs.privacy')],
                    ['🗑️', __('privacy.deletion.title'), __('privacy.deletion.description'), route(app()->isLocale('ru') ? 'ru.docs.delete-account' : 'docs.delete-account')],
                ] as [$icon, $title, $description, $href])
                    <li class="{{ $cardClass }} flex gap-3 p-4">
                        <span aria-hidden="true" class="text-xl leading-6">{{ $icon }}</span>
                        <div class="grid min-w-0 grid-cols-1 gap-1">
                            <a href="{{ $href }}" data-rodnik-navigate class="{{ $cardLinkClass }} font-semibold">{{ $title }}</a>
                            <p class="text-sm leading-6 text-zinc-600">{{ $description }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </main>
@endsection
