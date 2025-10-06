<?php
 
use function Laravel\Folio\name;
 
name('docs.exports');

?>

@extends('folio.index')

@php
    use App\Library\Export\FileParser;
    $files = FileParser::getExportFiles();
@endphp

@section('content')
    <div class="prose">
        <div class="font-black text-2xl">
            Admin
        </div>
        <div class="mt-3 max-w-prose">
            <p>
                Admin actions are explained on this page.
            </p>
            <h3>
                Reports
            </h3>
            <p>
                Any report can be hidden by the author or by a moderator. 
                Hidden reports can be unhidden immediately after the action
                or through the database. No data is lost.
            </p>
            <h3>
                Water sources
            </h3>
            <h4>
                Hide
            </h4>
            <p>
                Any water source can be hidden by a moderator. Hidden water sources
                can be restored, no information is lost.
            </p>
            <h4>
                Annihilate
            </h4>
            <p>
                Some water sources were created by mistake, they can be annihilated
                forever without leaving a trace. They can not be restored.
            </p>
            <p>
                It is only possible to annihilate water sources that were created first
                on Rodnik.today (without a connection to OSM) and have no visible reports.
                (You can annihilate a water source with hidden reports.)
            </p>
            <h4>
                Invalidate tiles
            </h4>
            <p>
                Usually, there is no need to invalidate and regenerate tiles manually,
                because it is done automatically. Is something goes wrong, this action
                gives you an alternative way to fix it. Tiles of all zoom levels containing
                this water source will be regenerated.
            </p>
        </div>
    </div>
@endsection
