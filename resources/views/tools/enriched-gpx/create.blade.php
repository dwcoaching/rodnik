<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('ui.tools.enrich_gpx_title') }}</title>
    </head>
    <body>
        <h1>{{ __('ui.tools.enrich_gpx_title') }}</h1>

        <form action="{{ localized_route('tools.enriched-gpx.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <input type="file" name="gpx" id="gpx">
            <button type="submit">{{ __('ui.tools.enrich') }}</button>
        </form>
    </body>
</html>
