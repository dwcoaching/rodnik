<?php

use App\Support\Docs\PublicMaps;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('docs.maps');

render(fn (View $view, PublicMaps $maps): View => $view->with('maps', $maps->paginate()));

?>

@extends('folio.index')

@section('title', __('pages.maps.seo_title').' — Rodnik.today')
@section('description', __('pages.maps.seo_description'))

@section('content')
    <main class="mx-auto grid max-w-5xl grid-cols-1 gap-6 pb-8">
        <header class="grid gap-3">
            <nav aria-label="{{ __('geography.breadcrumbs') }}" class="text-sm">
                <a href="{{ localized_public_path('/docs') }}" data-rodnik-navigate class="rounded-sm text-blue-700 hover:underline focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('pages.docs.overview') }}</a>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __('pages.maps.title') }}</h1>
            <p class="max-w-3xl text-base leading-7 text-zinc-600">{{ __('pages.maps.description') }}</p>
            @if ($maps->total() > 0)
                <p class="text-sm font-medium text-zinc-500">{{ trans_choice('pages.maps.count', $maps->total(), ['count' => number_format($maps->total(), 0, ',', ' ')]) }}</p>
            @endif
        </header>

        @if ($maps->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-500">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Zm0 0V3m6 18V6" /></svg>
                </div>
                <h2 class="text-base font-semibold text-zinc-900">{{ __('pages.maps.empty') }}</h2>
                <p class="max-w-sm text-sm leading-6 text-zinc-500">{{ __('pages.maps.empty_help') }}</p>
            </div>
        @else
            <ol class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                @foreach ($maps as $map)
                    <li class="group relative flex items-center gap-3 px-3 py-3 transition-colors [contain-intrinsic-size:auto_6rem] [content-visibility:auto] hover:bg-blue-50/40 sm:gap-4 sm:px-4">
                        @include('docs.map-item', ['map' => $map, 'heading' => 'h2'])
                    </li>
                @endforeach
            </ol>

            @if ($maps->hasPages())
                <div data-rodnik-pagination>{{ $maps->links() }}</div>
            @endif
        @endif
    </main>
@endsection
