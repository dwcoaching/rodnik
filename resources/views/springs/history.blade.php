@extends('layouts.plain')
@section('main')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-20"
        x-data="{}">

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

        <div>
            @foreach($events as $event)
                @if (get_class($event) == 'App\Models\Report')
                    <b>{{ $event->created_at }}</b> Report:
                    @if ($event->user)
                        {{ $event->user->name }} {{ $event->user->cached_rating }}
                    @endif
                    <br>
                    Visit date: {{ $event->visited_at }}<br>
                    Quality: {{ $event->quality }}<br>
                    State: {{ $event->state }}<br>
                    Comment: {{ $event->comment }}<br>



                @endif
                @if  (get_class($event) == 'App\Models\SpringRevision')
                    <b>{{ $event->created_at }}</b>
                    Edit:
                    @if ($event->user)
                        {{ $event->user->name }} {{ $event->user->cached_rating }}
                    @endif
                    <br>
                    @if (!
                            (
                                $event->old_latitude === $event->new_latitude
                                && $event->old_longitude === $event->old_longitude
                            )
                        )
                        Coordinates: {{ $event->old_latitude }}, {{ $event->old_longitude }} – {{ $event->new_latitude }}, {{ $event->old_longitude }}<br>
                    @endif
                    @if (! $event->old_name === $event->new_name)
                        Name: {{ $event->old_name }} – {{ $event->new_name }}<br>
                    @endif
                    @if (! $event->old_type === $event->new_type)
                        Name: {{ $event->old_type }} – {{ $event->new_type }}<br>
                    @endif
                    @if (! $event->old_intermittent === $event->new_intermittent)
                        Name: {{ $event->old_intermittent }} – {{ $event->new_intermittent }}<br>
                    @endif



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
                @endif
                <hr>
            @endforeach
        </div>
    </div>
@endsection
