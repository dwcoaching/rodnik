@php
    use App\Library\PossibleDuplicateSprings;
    use Illuminate\Support\Str;

    $springLabel = function ($name, $type) {
        return $name ?: ($type ?: 'Unnamed source');
    };

    $coordinates = function ($latitude, $longitude) {
        return number_format((float) $latitude, 6, '.', '') . ', ' . number_format((float) $longitude, 6, '.', '');
    };

@endphp

<div wire:init="load">
    @if (! $loaded)
        <div class="mt-6 w-full h-[300px] rounded-lg bg-gray-100 flex flex-col items-center justify-center">
            <div class="animate-spin w-8 h-8 border-4 rounded-full border-gray-400 border-t-transparent"></div>
            <div class="mt-4 text-sm text-gray-500">Looking for possible duplicates...</div>
        </div>
    @else
        <div class="mt-6 overflow-x-auto" style="width: min(100%, calc(100vw - 4rem)); max-width: min(100%, calc(100vw - 4rem));">
            <table class="table table-zebra min-w-[620px] w-full">
                <thead>
                    <tr>
                        <th>Distance</th>
                        <th>Rodnik source</th>
                        <th>Closest OSM candidate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($duplicates as $duplicate)
                        <tr>
                            <td class="font-mono whitespace-nowrap">
                                {{ number_format((float) $duplicate['distance_meters'], 1) }} m
                            </td>
                            <td>
                                <a href="{{ route('springs.show', $duplicate['rodnik_id']) }}" class="font-semibold text-blue-600 no-underline hover:underline">
                                    #{{ $duplicate['rodnik_id'] }} {{ $springLabel($duplicate['rodnik_name'], $duplicate['rodnik_type']) }}
                                </a>
                                <div class="text-xs text-gray-500 font-mono mt-1">
                                    {{ $coordinates($duplicate['rodnik_latitude'], $duplicate['rodnik_longitude']) }}
                                </div>
                                @if ($duplicate['rodnik_type'])
                                    <div class="text-xs text-gray-500 mt-1">{{ $duplicate['rodnik_type'] }}</div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('springs.show', $duplicate['osm_id']) }}" class="font-semibold text-blue-600 no-underline hover:underline">
                                    #{{ $duplicate['osm_id'] }} {{ $springLabel($duplicate['osm_name'], $duplicate['osm_type']) }}
                                </a>
                                <div class="text-xs text-gray-500 font-mono mt-1">
                                    {{ $coordinates($duplicate['osm_latitude'], $duplicate['osm_longitude']) }}
                                </div>
                                @if ($duplicate['osm_type'])
                                    <div class="text-xs text-gray-500 mt-1">{{ $duplicate['osm_type'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-10 text-gray-500">
                                No possible duplicates within {{ $radius }} m.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-sm text-gray-500">
            @if ($timedOut)
                Found {{ count($duplicates) }} candidate {{ Str::plural('pair', count($duplicates)) }} in this batch,
                result returned after {{ number_format($elapsedSeconds, 1) }} seconds.
                There are more possible duplicates.
            @else
                Found {{ count($duplicates) }} candidate {{ Str::plural('pair', count($duplicates)) }} in this batch
                in {{ number_format($elapsedSeconds, 1) }} seconds.
            @endif
        </div>
    @endif
</div>
