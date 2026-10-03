@extends('folio.index')

@section('title', __('geography.countries').' — Rodnik.today')
@section('description', __('geography.index_description'))

@section('content')
    <main class="mx-auto grid max-w-5xl gap-7">
        <header class="grid gap-3">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('geography.countries') }}</h1>
            <p class="max-w-3xl text-base leading-7 text-zinc-600">{{ __('geography.index_description') }}</p>
        </header>
        @if ($countries->isEmpty())
            <p>{{ __('geography.empty') }}</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($countries as $country)
                    <li>
                        <a href="{{ localized_route('geography.countries.show', ['country' => $country->slug]) }}" data-rodnik-navigate class="grid h-full gap-2 rounded-xl border border-zinc-200 p-5 transition hover:border-blue-400 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600">
                            <span class="text-lg font-semibold text-blue-700">{{ $country->displayName() }}</span>
                            <span class="text-sm text-zinc-600">{{ trans_choice('geography.sources_count', $country->sources_count, ['count' => number_format($country->sources_count, 0, ',', ' ')]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
@endsection
