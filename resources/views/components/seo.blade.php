@props([
    'title' => null,
    'description' => null,
    'indexable' => null,
])

@php
    $metadata = app(\App\Support\PageMetadata::class)->forRequest(request(), $title, $description, $indexable);
@endphp

<title>{{ $metadata['title'] }}</title>
<meta name="description" content="{{ $metadata['description'] }}">
<link rel="canonical" href="{{ $metadata['canonical'] }}">

@foreach ($metadata['alternates'] as $locale => $alternate)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternate }}">
@endforeach
@if ($metadata['robots'])
    <meta name="robots" content="{{ $metadata['robots'] }}">
@endif

<meta property="og:type" content="website">
<meta property="og:title" content="{{ $metadata['title'] }}">
<meta property="og:description" content="{{ $metadata['description'] }}">
<meta property="og:url" content="{{ $metadata['canonical'] }}">
<meta property="og:locale" content="{{ $metadata['locale'] === 'ru' ? 'ru_RU' : 'en_US' }}">
