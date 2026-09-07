<?php

use function Laravel\Folio\name;

name('docs.delete-account');

?>

@extends('folio.index')

@section('title', __('privacy.deletion.title').' — Rodnik.today')
@section('description', __('privacy.deletion.description'))

@section('content')
    <article class="prose max-w-prose">
        <h1>{{ __('privacy.deletion.title') }}</h1>
        <p>{{ __('privacy.deletion.instructions') }}</p>
        <p>
            <a href="{{ route('profile.show') }}#delete-account" class="btn btn-primary no-underline">{{ __('privacy.deletion.button') }}</a>
        </p>
        <p>{{ __('privacy.deletion.effect') }}</p>
        <p>
            {{ __('privacy.deletion.retention') }}
            <a href="mailto:kolpakov@hey.com">kolpakov@hey.com</a>.
        </p>
        <p><a href="{{ route(app()->isLocale('ru') ? 'ru.docs.privacy' : 'docs.privacy') }}">{{ __('privacy.title') }}</a></p>
    </article>
@endsection
