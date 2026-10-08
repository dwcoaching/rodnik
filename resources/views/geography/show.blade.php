@php
    $heading = __('geography.'.$type.'_title', ['name' => $place->displayName()]);
    $listing = $type === 'city' ? $springs : $settlements;
    $title = $listing->currentPage() > 1 ? __('geography.page_title', ['title' => $heading, 'page' => $listing->currentPage()]) : $heading;
    $count = fn (int $value): string => number_format($value, 0, ',', ' ');
    // A settlement can have thousands of sources, so their links and
    // distances fill templates instead of being generated row by row.
    $kilometers = __('geography.distance', ['distance' => '{distance}']);
    $decimal = app()->isLocale('ru') ? ',' : '.';
    $distance = fn (?float $km): ?string => $km === null ? null : str_replace('{distance}', number_format($km, 1, $decimal, ' '), $kilometers);
    $springLink = str_replace((string) PHP_INT_MAX, '{spring}', localized_route('springs.show', ['springId' => PHP_INT_MAX]));
    $springUrl = fn (\App\Models\Spring $spring): string => str_replace('{spring}', (string) $spring->id, $springLink);
    $types = [];
    $typeName = function (?string $type) use (&$types): string {
        return $types[$type ?? ''] ??= __('ui.spring.types.'.\Illuminate\Support\Str::snake($type ?: 'Water source'));
    };
    $springName = fn (\App\Models\Spring $spring): string => $spring->name ?: $typeName($spring->type);
@endphp

@extends('folio.index')

@section('title', $title.' — Rodnik.today')
@section('description', __('geography.'.$type.'_description', ['name' => $place->displayName(), 'count' => $count($total)]))

@section('content')
    <main class="mx-auto grid max-w-5xl gap-8">
        <header class="grid gap-3">
            @include('geography.breadcrumbs')
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $heading }}</h1>
            <p class="text-lg font-medium">{{ trans_choice('geography.sources_count', $total, ['count' => $count($total)]) }}</p>
            @if ($type === 'city')
                <p class="max-w-3xl leading-7 text-zinc-600">{{ __('geography.city_help') }}</p>
                <p class="text-sm text-zinc-600">{{ __('geography.settlement_center') }}: {{ number_format($place->latitude, 5, '.', '') }}, {{ number_format($place->longitude, 5, '.', '') }}</p>
            @endif
        </header>

        @if ($type === 'country' && $regions->isNotEmpty())
            <section aria-labelledby="geography-regions" class="grid gap-4">
                <h2 id="geography-regions" class="text-xl font-semibold">{{ __('geography.regions') }}</h2>
                @include('geography.places', ['places' => $regions, 'id' => 'regions', 'route' => 'geography.regions.show', 'parameter' => 'region'])
            </section>
        @endif

        @if ($type !== 'city' && $settlements->total() > 0)
            <section aria-labelledby="geography-settlements" class="grid gap-4">
                <h2 id="geography-settlements" class="text-xl font-semibold">{{ __($type === 'country' ? 'geography.outside_regions' : 'geography.settlements') }}</h2>
                <p class="text-sm leading-6 text-zinc-600">{{ __('geography.settlements_help') }}</p>
                @include('geography.places', ['places' => $settlements, 'id' => 'settlements', 'route' => 'geography.cities.show', 'parameter' => 'city'])
                @include('geography.pages', ['paginator' => $settlements])
            </section>
        @endif

        @if ($type === 'country' && $topSettlements->isNotEmpty())
            <section aria-labelledby="geography-top-settlements" class="grid gap-4">
                <h2 id="geography-top-settlements" class="text-xl font-semibold">{{ __('geography.top_settlements') }}</h2>
                @if ($settlements->total() === 0)
                    <p class="text-sm leading-6 text-zinc-600">{{ __('geography.settlements_help') }}</p>
                @endif
                @include('geography.places', ['places' => $topSettlements, 'grouped' => false, 'id' => 'top-settlements', 'route' => 'geography.cities.show', 'parameter' => 'city'])
            </section>
        @endif

        @if ($type !== 'city' && $mostReported->isNotEmpty())
            <section aria-labelledby="geography-most-reported" class="grid gap-3">
                <h2 id="geography-most-reported" class="text-xl font-semibold">{{ __('geography.most_reported') }}</h2>
                <ol class="max-w-3xl text-sm">
                    @foreach ($mostReported as $spring)
                        <li class="flex flex-wrap items-baseline gap-x-2 border-b border-zinc-100 py-1.5">
                            <a href="{{ $springUrl($spring) }}" data-rodnik-navigate class="font-medium text-blue-700 hover:underline">{{ $springName($spring) }}</a>
                            @if ($spring->name)
                                <span class="text-zinc-600">{{ $typeName($spring->type) }}</span>
                            @endif
                            @if ($spring->nearestCity)
                                <span aria-hidden="true" class="text-zinc-400">·</span>
                                @if ($spring->nearestCity->is_active)
                                    <a href="{{ localized_route('geography.cities.show', ['city' => $spring->nearestCity->slug]) }}" data-rodnik-navigate class="text-blue-700 hover:underline">{{ $spring->nearestCity->displayName() }}</a>
                                @else
                                    <span class="text-zinc-600">{{ $spring->nearestCity->displayName() }}</span>
                                @endif
                            @endif
                            <span class="ml-auto shrink-0 tabular-nums text-zinc-500">{{ trans_choice('geography.reports_count', (int) $spring->reports_count, ['count' => $count((int) $spring->reports_count)]) }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($type === 'city')
            <section aria-labelledby="geography-sources" class="grid gap-3">
                <div class="flex max-w-3xl flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 id="geography-sources" class="text-xl font-semibold">{{ __('geography.source_list') }}</h2>
                    <p class="text-sm text-zinc-600">{{ __('geography.source_order') }}</p>
                </div>
                <ol class="max-w-3xl text-sm *:flex *:flex-wrap *:items-baseline *:gap-x-2 *:border-b *:border-zinc-100 *:py-1.5 [&_a]:font-medium [&_a]:text-blue-700 [&_a:hover]:underline [&_span]:text-zinc-600 [&_small]:ml-auto [&_small]:text-xs [&_small]:tabular-nums [&_small]:text-zinc-500">
                    @foreach ($springs as $spring)
                        @php
                            $details = array_filter([
                                $spring->name ? $typeName($spring->type) : null,
                                $spring->geoCountry && $spring->country_id !== (int) $place->country_id ? $spring->geoCountry->displayName() : null,
                            ]);
                        @endphp
                        <li><a href="{{ $springUrl($spring) }}">{{ $springName($spring) }}</a>@if ($details)<span>{{ implode(' · ', $details) }}</span>@endif<small>{{ implode(' · ', array_filter([$distance($spring->nearest_city_distance_km), number_format($spring->latitude, 5, '.', '').', '.number_format($spring->longitude, 5, '.', '')])) }}</small></li>
                    @endforeach
                </ol>
                @include('geography.pages', ['paginator' => $springs])
            </section>
        @endif
    </main>
@endsection
