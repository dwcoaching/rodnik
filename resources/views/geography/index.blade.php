@extends('folio.index')

@section('title', __('geography.countries').' — Rodnik.today')
@section('description', __('geography.index_description'))

@section('content')
    <main class="mx-auto grid max-w-5xl gap-6">
        <header class="grid gap-3">
            @include('geography.breadcrumbs')
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('geography.countries') }}</h1>
            <p class="max-w-3xl text-base leading-7 text-zinc-600">{{ __('geography.index_description') }}</p>
        </header>
        @if ($countries->isEmpty())
            <p>{{ __('geography.empty') }}</p>
        @else
            @include('geography.places', ['places' => $countries, 'grouped' => true, 'level' => 'h2', 'id' => 'countries', 'route' => 'geography.countries.show', 'parameter' => 'country'])
        @endif
    </main>
@endsection
