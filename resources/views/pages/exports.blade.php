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
            Copyright
        </div>
        <div class="mt-3 max-w-prose">
            <p>
                All data contributed by users of Rodnik.today is in the <b>public domain</b>.
            </p>
            <p>
                Rodnik.today contains data from OpenStreetMap, which is licensed under the <a href="https://www.openstreetmap.org/copyright" class="text-blue-600" target="_blank">ODbL</a>.
                In the exports, this data is marked with an <b>osm_* prefix</b>.
            </p>
            <p>
                All other data is in the public domain. You are free to include it in your own projects and databases, even without mentioning Rodnik.today.
                (Although it would be nice.)
            </p>
        </div>
        <div class="mt-9 font-black text-xl">
            Exports @if($files->count() > 0)<span class="text-gray-400">as of {{ $files->min('timestamp')?->format('Y-m-d H:i:00') ?? '' }}</span>@endif
        </div>
        <div class="mt-3">
            @if ($files->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($files as $file)
                        @php
                            $ext = strtolower($file['extension'] ?? '');
                            $label = match ($ext) {
                                'json' => 'JSON',
                                'xlsx' => 'XLSX',
                                'zip', 'csv' => 'CSV',
                                default => strtoupper($ext)
                            };
                            $formatInfo = match ($label) {
                                'JSON' => [
                                    'name' => 'JSON',
                                    'cardBg' => 'bg-gradient-to-br from-rose-500 to-red-800',
                                    'hoverBg' => 'hover:from-pink-500 hover:via-red-600 hover:to-red-900',
                                    'shadow' => 'hover:shadow-2xl hover:shadow-red-600/60',
                                ],
                                'CSV' => [
                                    'name' => 'CSV',
                                    'cardBg' => 'bg-gradient-to-br from-cyan-500 via-blue-600 to-indigo-700',
                                    'hoverBg' => 'hover:from-cyan-300 hover:via-blue-600 hover:to-purple-900',
                                    'shadow' => 'hover:shadow-2xl hover:shadow-blue-600/60',
                                ],
                                'XLSX' => [
                                    'name' => 'XLSX',
                                    'cardBg' => 'bg-gradient-to-br from-lime-500 via-emerald-700 to-teal-900',
                                    'hoverBg' => 'hover:from-lime-400 hover:via-emerald-600 hover:to-green-900',
                                    'shadow' => 'hover:shadow-2xl hover:shadow-emerald-600/60',
                                ],
                            };
                        @endphp
                        <a href="/exports/{{ $file['filename'] }}" 
                           download
                           class="group relative block rounded-2xl {{ $formatInfo['cardBg'] }} {{ $formatInfo['hoverBg'] }} p-8 transition-all duration-300 {{ $formatInfo['shadow'] }} hover:-translate-y-1 overflow-hidden no-underline">
                            {{-- Content --}}
                            <div class="relative z-10 flex flex-col items-center text-center">
                                {{-- Format Icon --}}
                                <div class="mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#ffffff" viewBox="0 0 256 256" class="opacity-100"><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-42.34-61.66a8,8,0,0,1,0,11.32l-24,24a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L120,164.69V120a8,8,0,0,1,16,0v44.69l10.34-10.35A8,8,0,0,1,157.66,154.34Z"></path></svg>
                                </div>
                                
                                {{-- Format Name & Description --}}
                                <div>
                                    <h3 class="font-bold text-white text-2xl mb-2">{{ $formatInfo['name'] }}</h3>
                                    <span class="text-base font-medium text-white/80">{{ $file['size_human'] }}@if(strtoupper($file['extension']) === 'ZIP') (zipped)@endif</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 px-4 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="mt-2 text-gray-600">No exports available at the moment.</p>
                    <p class="text-sm text-gray-500 mt-1">Please check back later.</p>
                </div>
            @endif
        </div>
        <div class="mt-9 font-black text-xl">
            Update frequency
        </div>
        <div class="mt-3">
            The exports are updated daily.
        </div>

        <div class="mt-9 font-black text-xl">
            Personal user exports
        </div>
        <div class="mt-3">
            You can export your own data from Rodnik.today.
            <a href="/exports/user/{{ auth()->user()->id }}" class="text-blue-600">
                Go to your exports
            </a>
        </div>
    </div>
@endsection
