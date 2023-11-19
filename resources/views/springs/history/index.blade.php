@extends('layouts.plain')
@section('main')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-20"
        id="photos"
        x-data="{}"
        x-init="window.initPhotoSwipe('#photos');">

        <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
                <a href="{{ route('springs.show', $spring) }}" class="block text-base font-semibold text-blue-600 hover:text-blue-700"">
                    <span class="mr-2 inline-flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-2" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
                        </svg>
                        {{ without_http(route('springs.show', $spring)) }}
                    </span>
                </a>
            </div>
        </div>

        <h1 class="mt-4 mb-2 text-3xl font-bold">Water Source History</h1>
        <div class="grid grid-cols-1 gap-1 ">
            @foreach($events as $event)
                @if (get_class($event) == 'App\Models\Report')
                    <div class="card bg-stone-50 shadow-xl">
                        <div class="card-body p-4">
                            <div class="md:flex">
                                <div class="md:w-64 shrink-0 flex flex-row md:flex-col items-stretch justify-between">
                                    <div class="flex items-center md:block">
                                        <div class="font-extrabold mr-2">{{ $event->created_at }}</div>
                                        <div class="text-sm font-semibold text-gray-600">
                                            @if ($event->user)
                                                <a class="block flex flex-wrap items-center text-sm text-blue-600 cursor-pointer hover:text-blue-700"
                                                    href="{{ route('users.show', $event->user) }}">
                                                    <div class="mr-1">{{ $event->user->name }}</div>
                                                    <div class="text-xs font-semibold text-gray-600">{{ $event->user->rating }}</div>
                                                </a>
                                            @else
                                                Anonymous
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge bg-stone-600 text-white text-xs">Report</span>
                                    </div>
                                </div>
                                <div class="">
                                    @if ($event->visited_at)
                                        Visit date: {{ $event->visited_at->format('F d, Y') }}<br>
                                    @endif
                                    @if ($event->quality)
                                        Quality: {{ $event->quality }}<br>
                                    @endif
                                    @if ($event->state)
                                        State: {{ $event->state }}<br>
                                    @endif
                                    @if ($event->comment)
                                        Comment: {{ $event->comment }}<br>
                                    @endif
                                    @if ($event->photos->count())
                                        <div class="mt-1">
                                            <ul role="list" class="pswp-gallery mt-3 flex gap-2">
                                                @foreach ($event->photos as $photo)
                                                    <li class="h-24 w-24">
                                                        <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 overflow-hidden">
                                                            <a href="{{ $photo->url }}"
                                                                data-pswp-width="{{ $photo->width }}"
                                                                data-pswp-height="{{ $photo->height }}"
                                                                data-cropped="true"
                                                                target="blank" class="photoswipeImage">
                                                                <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full">
                                                            </a>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @if  (get_class($event) == 'App\Models\SpringRevision')
                    <div class="card shadow-xl
                        {{ $event->revision_type == 'from_osm' ? 'bg-amber-50' : 'bg-indigo-50' }}
                        ">
                        <div class="card-body p-4">
                            <div class="md:flex">
                                <div class="md:w-64 shrink-0 flex flex-row md:flex-col items-stretch justify-between">
                                    <div class="flex items-center md:block">
                                        <div class="font-extrabold mr-2">{{ $event->created_at }}</div>
                                        <div class="text-sm font-semibold text-gray-600">
                                            @if ($event->revision_type == 'from_osm')

                                            @else
                                                @if ($event->user)
                                                    <a class="block flex flex-wrap items-center text-sm text-blue-600 cursor-pointer hover:text-blue-700"
                                                        href="{{ route('users.show', $event->user) }}">
                                                        <div class="mr-1">{{ $event->user->name }}</div>
                                                        <div class="text-xs font-semibold text-gray-600">{{ $event->user->rating }}</div>
                                                    </a>
                                                @else
                                                    Anonymous
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        @if ($event->revision_type == 'from_osm')
                                            <span class="badge bg-amber-600 text-white text-xs">OSM Update</span>
                                        @else
                                            <span class="badge bg-indigo-600 text-white text-xs">Edit</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    @if (!
                                            (
                                                $event->old_latitude === $event->new_latitude
                                                && $event->old_longitude === $event->old_longitude
                                            )
                                        )
                                        @include('springs.history.change', [
                                            'key' => 'Location',
                                            'old' => $event->old_latitude . ', ' . $event->old_longitude,
                                            'new' => $event->new_latitude . ', ' . $event->new_longitude,
                                        ])
                                    @endif
                                    @if ($event->old_name !== $event->new_name)
                                        @include('springs.history.change', [
                                            'key' => 'Name',
                                            'old' => $event->old_name,
                                            'new' => $event->new_name,
                                        ])
                                    @endif
                                    @if ($event->old_type !== $event->new_type)
                                        @include('springs.history.change', [
                                            'key' => 'Type',
                                            'old' => $event->old_type,
                                            'new' => $event->new_type,
                                        ])
                                    @endif
                                    @if ($event->old_intermittent !== $event->new_intermittent)
                                        @include('springs.history.change', [
                                            'key' => 'Intermittent',
                                            'old' => $event->old_intermittent,
                                            'new' => $event->new_intermittent,
                                        ])
                                    @endif
                                    {{--
                                        @if (!
                                                (
                                                    $event->old_osm_latitude === $event->new_osm_latitude
                                                    && $event->old_osm_longitude === $event->new_osm_longitude
                                                )
                                            )
                                            Coordinates: {{ $event->old_osm_longitude }}, {{ $event->old_osm_longitude }} – {{ $event->new_osm_latitude }}, {{ $event->new_osm_longitude }}<br>
                                        @endif
                                        @if (! $event->old_osm_name === $event->new_osm_name)
                                            Name: {{ $event->old_osm_name }} – {{ $event->new_osm_name }}<br>
                                        @endif
                                        @if (! $event->old_osm_type === $event->new_osm_type)
                                            Name: {{ $event->old_osm_type }} – {{ $event->new_osm_type }}<br>
                                        @endif
                                        @if (! $event->old_osm_intermittent === $event->new_osm_intermittent)
                                            Name: {{ $event->old_osm_intermittent }} – {{ $event->new_osm_intermittent }}<br>
                                        @endif
                                    --}}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
