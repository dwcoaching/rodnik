@extends('folio.index')

@section('title', __('geography.'.$type.'_title', ['name' => $place->displayName()]).' — Rodnik.today')
@section('description', __($type === 'city' ? 'geography.city_description' : 'geography.description', ['name' => $place->displayName(), 'count' => number_format($springs->total(), 0, ',', ' ')]))

@section('content')
    <main class="mx-auto grid max-w-5xl gap-8">
        <header class="grid gap-3">
            <nav aria-label="{{ __('ui.common.navigation') }}" class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-600">
                <a href="{{ localized_route('geography.countries.index') }}" data-rodnik-navigate class="text-blue-700 hover:underline">{{ __('geography.countries') }}</a>
                @if ($country)
                    <span aria-hidden="true">/</span>
                    @if ($countryHasSources)
                        <a href="{{ localized_route('geography.countries.show', ['country' => $country->slug]) }}" data-rodnik-navigate class="text-blue-700 hover:underline">{{ $country->displayName() }}</a>
                    @else
                        <span>{{ $country->displayName() }}</span>
                    @endif
                @endif
                @if ($region)
                    <span aria-hidden="true">/</span>
                    @if ($regionHasSources)
                        <a href="{{ localized_route('geography.regions.show', ['region' => $region->slug]) }}" data-rodnik-navigate class="text-blue-700 hover:underline">{{ $region->displayName() }}</a>
                    @else
                        <span>{{ $region->displayName() }}</span>
                    @endif
                @endif
            </nav>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('geography.'.$type.'_title', ['name' => $place->displayName()]) }}</h1>
            <p class="text-lg font-medium">{{ trans_choice('geography.sources_count', $springs->total(), ['count' => number_format($springs->total(), 0, ',', ' ')]) }}</p>
            @if ($type === 'city')
                <p class="max-w-3xl leading-7 text-zinc-600">{{ __('geography.city_help') }}</p>
                <p class="text-sm text-zinc-600">{{ __('geography.settlement_center') }}: {{ number_format($place->latitude, 5, '.', '') }}, {{ number_format($place->longitude, 5, '.', '') }}</p>
            @endif
        </header>

        @if ($regions->isNotEmpty())
            <section aria-labelledby="geography-regions">
                <h2 id="geography-regions" class="mb-4 text-xl font-semibold">{{ __('geography.regions') }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($regions as $listedRegion)
                        <li><a href="{{ localized_route('geography.regions.show', ['region' => $listedRegion->slug]) }}" data-rodnik-navigate class="flex flex-wrap justify-between gap-2 rounded-lg border border-zinc-200 p-3 text-blue-700 hover:bg-blue-50"><span>{{ $listedRegion->displayName() }}</span><span class="text-sm text-zinc-600">{{ number_format($listedRegion->sources_count, 0, ',', ' ') }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($cities && $cities->isNotEmpty())
            <section aria-labelledby="geography-settlements" class="grid gap-4">
                <h2 id="geography-settlements" class="text-xl font-semibold">{{ __('geography.settlements') }}</h2>
                <p class="text-sm leading-6 text-zinc-600">{{ __('geography.settlements_help') }}</p>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($cities as $listedCity)
                        <li><a href="{{ localized_route('geography.cities.show', ['city' => $listedCity->slug]) }}" data-rodnik-navigate class="flex flex-wrap justify-between gap-2 rounded-lg border border-zinc-200 p-3 text-blue-700 hover:bg-blue-50"><span>{{ $listedCity->displayName() }}</span><span class="text-sm text-zinc-600">{{ number_format($listedCity->sources_count, 0, ',', ' ') }}</span></a></li>
                    @endforeach
                </ul>
                {{ $cities->links() }}
            </section>
        @endif

        <section aria-labelledby="geography-sources" class="grid gap-4">
            <h2 id="geography-sources" class="text-xl font-semibold">{{ __('geography.source_list') }}</h2>
            <ol class="grid gap-3">
                @foreach ($springs as $spring)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4">
                        <div class="grid gap-1">
                            <a href="{{ localized_route('springs.show', ['springId' => $spring->id]) }}" data-rodnik-navigate class="font-semibold text-blue-700 hover:underline">{{ $spring->name ?: __('ui.spring.no_name') }}</a>
                            <span class="text-sm text-zinc-600">{{ __('ui.spring.types.'.\Illuminate\Support\Str::snake($spring->type ?: 'Water source')) }}</span>
                            <span class="text-sm text-zinc-600">{{ __('geography.coordinates') }}: {{ number_format($spring->latitude, 5, '.', '') }}, {{ number_format($spring->longitude, 5, '.', '') }}</span>
                            @if ($type === 'city' && $spring->nearest_city_distance_km !== null)
                                <span class="text-sm text-zinc-600">{{ __('geography.distance', ['distance' => number_format($spring->nearest_city_distance_km, 1, app()->isLocale('ru') ? ',' : '.', ' ')]) }}</span>
                            @endif
                            @if ($type === 'city' && $spring->geoCountry)
                                <span class="text-sm text-zinc-600">{{ __('geography.country') }}: {{ $spring->geoCountry->displayName() }}</span>
                            @endif
                        </div>
                        <a href="{{ localized_route('springs.show', ['springId' => $spring->id]) }}" data-rodnik-navigate class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">{{ __('geography.view_source') }}</a>
                    </li>
                @endforeach
            </ol>
            {{ $springs->links() }}
        </section>
    </main>
@endsection
