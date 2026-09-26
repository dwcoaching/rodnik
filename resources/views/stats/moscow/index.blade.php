<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @livewireStyles
        <script defer src="/js/@alpinejs/ui@3.14.1-beta.0.dist.cdn.min.js"></script>
        @vite(['resources/js/app.js'])
        @livewireScriptConfig
        <title>{{ __('ui.moscow_stats.title') }}</title>
        <style>
            table { border-collapse: collapse; }
            th { text-align: left; }
            .value { text-align: right; }
            td, th { padding: 4px 8px; }
        </style>
    </head>
    <body>
        <h1>{{ __('ui.moscow_stats.title') }}</h1>
        <ul>
            <li><a data-rodnik-navigate href="{{ localized_route('moscow-stats', ['area' => 'mkad']) }}">{{ __('ui.moscow_stats.areas.mkad') }}</a></li>
            <li><a data-rodnik-navigate href="{{ localized_route('moscow-stats', ['area' => 'moscow']) }}">{{ __('ui.moscow_stats.areas.moscow') }}</a></li>
            <li><a data-rodnik-navigate href="{{ localized_route('moscow-stats', ['area' => 'mo']) }}">{{ __('ui.moscow_stats.areas.mo') }}</a></li>
            <li><a data-rodnik-navigate href="{{ localized_route('moscow-stats', ['area' => 'moscow-200-km']) }}">{{ __('ui.moscow_stats.areas.moscow_200_km') }}</a></li>
            <li><a data-rodnik-navigate href="{{ localized_route('moscow-stats', ['area' => 'all']) }}">{{ __('ui.moscow_stats.areas.all') }}</a> ({{ __('ui.moscow_stats.timeout_warning') }})</li>
        </ul>
        <x-js-translations />
    </body>
</html>
