<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Moscow Stats</title>
        <style>
            table { border-collapse: collapse; }
            th { text-align: left; }
            .value { text-align: right; }
            td, th { padding: 4px 8px; }
        </style>
    </head>
    <body>
        <h1>Статистика по Москве</h1>
        <ul>
            <li><a href="{{ route('moscow-stats', ['area' => 'mkad']) }}">МКАД</a></li>
            <li><a href="{{ route('moscow-stats', ['area' => 'moscow']) }}">Москва</a></li>
            <li><a href="{{ route('moscow-stats', ['area' => 'mo']) }}">Московская область</a></li>
            <li><a href="{{ route('moscow-stats', ['area' => 'moscow-200-km']) }}">Москва 200 км</a></li>
            <li><a href="{{ route('moscow-stats', ['area' => 'all']) }}">Всё вместе</a> (осторожно: не укладывается в таймаут 30 секунд)</li>
        </ul>
    </body>
</html>
