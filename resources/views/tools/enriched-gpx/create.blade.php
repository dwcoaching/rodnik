<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Enrich GPX</title>
    </head>
    <body>
        <h1>Enrich GPX</h1>

        <form action="{{ route('tools.enriched-gpx.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <input type="file" name="gpx" id="gpx">
            <button type="submit">Enrich</button>
        </form>
    </body>
</html>
