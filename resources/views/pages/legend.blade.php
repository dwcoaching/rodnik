<?php

use function Laravel\Folio\name;

name('docs.legend');

?>

@extends('folio.index')

@section('title', __('ui.home.map_legend.title').' — Rodnik.today')
@section('description', __('ui.home.map_legend.seo_description'))

@section('content')
    <section id="map-legend" aria-labelledby="map-legend-heading" class="max-w-prose">
        <h1 id="map-legend-heading" class="font-black text-2xl">{{ __('ui.home.map_legend.title') }}</h1>
        <div class="mt-3 space-y-4">
            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 rounded-full border border-[#33A9FF] bg-[#33A9FF]/10 shrink-0"></div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ __('ui.home.map_legend.no_reports') }}</div>
                    <div class="text-sm text-gray-600">{{ __('ui.home.map_legend.no_reports_description') }}</div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 rounded-full border border-[#006600] bg-[#009900]/50 shrink-0"></div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ __('ui.home.map_legend.good_water') }}</div>
                    <div class="text-sm text-gray-600">{{ __('ui.home.map_legend.good_water_description') }}</div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 rounded-full border border-[#FF0000] bg-[#FF0000]/50 shrink-0"></div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ __('ui.home.map_legend.poor_water') }}</div>
                    <div class="text-sm text-gray-600">{{ __('ui.home.map_legend.poor_water_description') }}</div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 rounded-full border border-[#ff9900] bg-[#FFB400]/80 shrink-0"></div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ __('ui.home.map_legend.unsure') }}</div>
                    <div class="text-sm text-gray-600">{{ __('ui.home.map_legend.unsure_description') }}</div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 rounded-full border border-red-500 shrink-0 flex items-center justify-center">
                    <span class="text-red-500 font-bold text-sm">✕</span>
                </div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ __('ui.home.map_legend.not_found') }}</div>
                    <div class="text-sm text-gray-600">{{ __('ui.home.map_legend.not_found_description') }}</div>
                </div>
            </div>
        </div>
        <div class="mt-4 p-4 bg-gray-100 rounded-lg text-sm text-gray-800">
            {!! __('ui.home.map_legend.marker_numbers') !!}
        </div>
    </section>
@endsection
