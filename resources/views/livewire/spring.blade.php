<div
    x-data="{
        active: 'osm',
        source: function(name) {
            this.active = name;
            window.rodnikMap.source(name);
        }
    }"
    x-on:spring-selected.window="$wire.setSpring($event.detail.id)"
    x-on:spring-unselected.window="$wire.unselectSpring()"
    >
    @if (! $spring)
        <div>
            <button @click="window.rodnikMap.locateMe()" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Определить мои координаты</button>
        </div>
        <div>
            <div class="mt-5 text-lg font-semibold">Включить карту:</div>
            <div class="mt-2 space-y-2" x-data="{

            }">
                <div>
                    <button @click="source('osm')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-indigo-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        :class="{
                            'bg-white': active == 'osm',
                            'bg-indigo-600': active != 'osm',
                            'text-indigo-700': active == 'osm',
                            'text-white': active != 'osm'
                        }"
                    >OpenStreetMap</button>
                </div>
                <div>
                    <button @click="source('mapy')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-indigo-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        :class="{
                            'bg-white': active == 'mapy',
                            'bg-indigo-600': active != 'mapy',
                            'text-indigo-700': active == 'mapy',
                            'text-white': active != 'mapy'
                        }"
                    >Mapy.cz</button>
                </div>
                <div>
                    <button @click="source('outdoors')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-indigo-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        :class="{
                            'bg-white': active == 'outdoors',
                            'bg-indigo-600': active != 'outdoors',
                            'text-indigo-700': active == 'outdoors',
                            'text-white': active != 'outdoors'
                        }"
                    >OSM Outdoors</button>
                </div>
                <div>
                    <button @click="source('terrain')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-indigo-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        :class="{
                            'bg-white': active == 'terrain',
                            'bg-indigo-600': active != 'terrain',
                            'text-indigo-700': active == 'terrain',
                            'text-white': active != 'terrain'
                        }"
                    >Google Terrain</button>
                </div>
            </div>
        </div>

    @endif

    @if ($spring)
        <div class="">
            <div class="text-3xl font-bold">
                {{ $spring->name }}
            </div>
            <div class="mt-3 text-gray-500 text-sm flex">
                <span class="mr-1">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                  <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                </svg>
            </div>
        </div>

        @if ($spring->osm_tags->count())
            <div class="mt-3">
                <div class="text-gray-900 text-sm">
                    <span class="font-semibold mr-3">
                        Теги OSM
                    </span>
                    <span class="text-xs text-gray-500">
                        (node id: {{ $spring->osm_node_id }})
                    </span>
                </div>
                @foreach ($spring->osm_tags as $tag)
                    <div class="text-gray-900 mt-1 text-sm">{{ $tag->key }}: {{ $tag->value }}</div>
                @endforeach
            </div>
        @endif




        <a href="{{ route('reviews.create', ['spring_id' => $spring]) }}"
            class="
                mt-3
                inline-flex
                items-center
                px-4
                py-3
                border-4
                border-yellow-600
                leading-4
                font-medium
                rounded-full
                text-black
                bg-yellow-400
                hover:bg-yellow-500
                focus:outline-none
                focus:ring-2
                focus:ring-offset-2
                focus:ring-indigo-500">Добавить отзыв</a>

        <div class="mt-3">
            <ul role="list" class="divide-y divide-gray-200">
                @foreach ($reviews as $review)
                    @include('reviews.item')
                @endforeach
            </ul>
        </div>
    @endif
</div>
