<?php

use App\Library\PossibleDuplicateSprings;
use function Laravel\Folio\name;

name('docs.admin.duplicates');

?>

@extends('folio.index')

@php
    $radius = PossibleDuplicateSprings::normalizeRadius(request('radius'));
    $limit = PossibleDuplicateSprings::normalizeLimit(request('limit'));
@endphp

@section('content')
    <div class="min-w-0 max-w-full">
        <div class="font-black text-2xl">
            Possible Duplicates
        </div>

        <div class="mt-3 max-w-prose prose">
            <p>
                Rodnik-only water sources without OpenStreetMap links that have OSM-linked sources nearby.
                Each Rodnik source is checked with a latitude/longitude bounding box first, then exact Haversine distance.
                Showing up to {{ $limit }} candidate pairs.
            </p>
        </div>

        <div class="mt-6 flex flex-wrap gap-4">
            <div class="flex-[1_1_100%] sm:flex-[0_1_auto] w-[min(100%,calc(100vw-4rem))] sm:w-auto min-w-0 max-w-[min(100%,calc(100vw-4rem))] sm:max-w-full overflow-x-auto pb-1">
                <div class="inline-flex min-w-max gap-1 rounded-lg bg-gray-200 p-1">
                    @foreach (PossibleDuplicateSprings::RADII as $tabRadius)
                        <a
                            href="{{ route('docs.admin.duplicates', ['radius' => $tabRadius, 'limit' => $limit]) }}"
                            class="flex h-10 items-center justify-center rounded-lg px-4 text-center text-sm font-medium whitespace-nowrap {{ $radius === $tabRadius ? 'bg-blue-600 text-white' : 'text-gray-900 hover:bg-gray-100' }}"
                        >
                            {{ $tabRadius }} m
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex-[1_1_100%] sm:flex-[0_1_auto] w-[min(100%,calc(100vw-4rem))] sm:w-auto min-w-0 max-w-[min(100%,calc(100vw-4rem))] sm:max-w-full overflow-x-auto pb-1">
                <div class="inline-flex min-w-max gap-1 rounded-lg bg-gray-200 p-1">
                    @foreach (PossibleDuplicateSprings::LIMITS as $tabLimit)
                        <a
                            href="{{ route('docs.admin.duplicates', ['radius' => $radius, 'limit' => $tabLimit]) }}"
                            class="flex h-10 items-center justify-center rounded-lg px-4 text-center text-sm font-medium whitespace-nowrap {{ $limit === $tabLimit ? 'bg-blue-600 text-white' : 'text-gray-900 hover:bg-gray-100' }}"
                        >
                            {{ number_format($tabLimit, 0, '.', ' ') }} sources
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <livewire:admin.possible-duplicates-table
            :radius="$radius"
            :limit="$limit"
            :key="'possible-duplicates-' . $radius . '-' . $limit"
        />
    </div>
@endsection
