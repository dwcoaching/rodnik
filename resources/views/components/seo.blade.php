@props([
    'title' => null,
    'description' => null,
    'indexable' => null,
])

@php
    $title = $title ?: __('seo.default_title');
    $description = $description ?: __('seo.default_description');
    $indexable ??= app(\App\Support\LocalizedUrl::class)->isIndexable(request());
    $canonical = localized_url(app()->getLocale(), true);
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">

@if ($indexable)
    @foreach (array_keys(config('localization.supported')) as $locale)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ localized_url($locale, true) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ localized_url(config('localization.default'), true) }}">
@else
    <meta name="robots" content="noindex, nofollow">
@endif

<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ app()->isLocale('ru') ? 'ru_RU' : 'en_US' }}">
