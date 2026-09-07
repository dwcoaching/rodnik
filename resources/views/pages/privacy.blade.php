<?php

use function Laravel\Folio\name;

name('docs.privacy');

?>

@extends('folio.index')

@section('title', __('privacy.title').' — Rodnik.today')
@section('description', __('privacy.description'))

@section('content')
    <article class="prose max-w-prose">
        <h1>{{ __('privacy.title') }}</h1>
        <p class="text-sm text-gray-500">{{ __('privacy.updated') }}</p>
        <p>
            {{ __('privacy.operator') }}
            <a href="mailto:kolpakov@hey.com">kolpakov@hey.com</a>.
        </p>

        @foreach (__('privacy.sections') as $key => $section)
            <section aria-labelledby="privacy-{{ $key }}">
                <h2 id="privacy-{{ $key }}">{{ $section['title'] }}</h2>
                <p>{{ $section['body'] }}</p>
                @if ($key === 'public')
                    <p>
                        <a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0 1.0</a>
                        · <a href="https://www.openstreetmap.org/copyright">OpenStreetMap / ODbL</a>
                    </p>
                @endif
                @if ($key === 'deletion')
                    <p><a href="{{ route(app()->isLocale('ru') ? 'ru.docs.delete-account' : 'docs.delete-account') }}">{{ __('privacy.deletion.title') }}</a></p>
                @endif
            </section>
        @endforeach
    </article>
@endsection
