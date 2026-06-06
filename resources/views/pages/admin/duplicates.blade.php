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
    <div>
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
            <div class="tabs tabs-boxed inline-flex">
                @foreach (PossibleDuplicateSprings::RADII as $tabRadius)
                    <a
                        href="{{ route('docs.admin.duplicates', ['radius' => $tabRadius, 'limit' => $limit]) }}"
                        class="tab {{ $radius === $tabRadius ? 'tab-active' : '' }}"
                    >
                        {{ $tabRadius }} m
                    </a>
                @endforeach
            </div>

            <div class="tabs tabs-boxed inline-flex">
                @foreach (PossibleDuplicateSprings::LIMITS as $tabLimit)
                    <a
                        href="{{ route('docs.admin.duplicates', ['radius' => $radius, 'limit' => $tabLimit]) }}"
                        class="tab {{ $limit === $tabLimit ? 'tab-active' : '' }}"
                    >
                        {{ number_format($tabLimit, 0, '.', ' ') }} sources
                    </a>
                @endforeach
            </div>
        </div>

        <livewire:admin.possible-duplicates-table
            :radius="$radius"
            :limit="$limit"
            :key="'possible-duplicates-' . $radius . '-' . $limit"
        />
    </div>
@endsection
