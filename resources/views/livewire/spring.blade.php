<div id="spring"
    x-data="{
        active: 'osm',
        source: function(name) {
            this.active = name;
            window.rodnikMap.source(name);
        },
        locateMap: true,
        filters: window.rodnikMap.filters,
        updateFilters: function() {
            window.rodnikMap.springsFinalLayer.updateStyle();
        }
    }"
    x-on:spring-selected.window="$wire.setSpring($event.detail.id)"
    x-on:spring-unselected.window="$wire.unselectSpring()"
    x-on:popstate.window="
        if ($event.state && $event.state.springId) {
            locateMap = true;
            const event = new CustomEvent('spring-selected', {detail: {id: $event.state.springId}});
            window.dispatchEvent(event);
        }"
    x-init="
        if ({{ intval($springId)}} && locateMap) {
            window.rodnikMap.locate({{ json_encode($coordinates) }});
            window.rodnikMap.showFeature({{ $springId }});
            locateMap = false;
        }

        window.initPhotoSwipe('#spring');
    ">
    @if (! $spring)
        <div>
            <button @click="window.rodnikMap.locateMe()" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Определить мои координаты</button>
        </div>
        <div>
            <div class="mt-16 text-3xl font-bold">Карта</div>
            <div class="mt-2 space-y-2 space-x-1">
                <button @click="source('osm')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    :class="{
                        'bg-white': active == 'osm',
                        'bg-blue-600': active != 'osm',
                        'text-blue-700': active == 'osm',
                        'text-white': active != 'osm'
                    }"
                >OpenStreetMap</button>
                <button @click="source('mapy')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    :class="{
                        'bg-white': active == 'mapy',
                        'bg-blue-600': active != 'mapy',
                        'text-blue-700': active == 'mapy',
                        'text-white': active != 'mapy'
                    }"
                >Mapy.cz</button>
            {{--
                <button @click="source('outdoors')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    :class="{
                        'bg-white': active == 'outdoors',
                        'bg-blue-600': active != 'outdoors',
                        'text-blue-700': active == 'outdoors',
                        'text-white': active != 'outdoors'
                    }"
                >OSM Outdoors</button>
            --}}
                <button @click="source('terrain')" type="button" class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    :class="{
                        'bg-white': active == 'terrain',
                        'bg-blue-600': active != 'terrain',
                        'text-blue-700': active == 'terrain',
                        'text-white': active != 'terrain'
                    }"
                >Google Terrain</button>
            </div>

            <div class="mt-16 text-3xl font-bold">Показывать</div>
            <div class="mt-4 space-y-2 space-x-1">
                <fieldset class="space-y-2">
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.intermittent" id="filters.intermittent" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.intermittent" class="font-medium text-gray-700">Пересыхающие источники</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.spring" id="filters.spring" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.spring" class="font-medium text-gray-700">Родники</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.water_well" id="filters.water_well" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.water_well" class="font-medium text-gray-700">Колодцы</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.water_tap" id="filters.water_tap" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.water_tap" class="font-medium text-gray-700">Краны и колонки</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.drinking_fountain" id="filters.drinking_fountain" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.drinking_fountain" class="font-medium text-gray-700">Питьевые фонтанчики</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.drinking_water" id="filters.drinking_water" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.drinking_water" class="font-medium text-gray-700">Источники питьевой воды</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.fountain" id="filters.fountain" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.fountain" class="font-medium text-gray-700">Фонтаны</label>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input @change="updateFilters" x-model="filters.other" id="filters.other" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="filters.other" class="font-medium text-gray-700">Прочие источники воды</label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="mt-16 text-3xl font-bold">Последние отчеты</div>
            <ul role="list" class="pt-4">
                @foreach ($lastReports as $report)
                    <x-report has-name="true" :report="$report" />
                @endforeach
            </ul>
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

        @if ($reports->count())
            <div class="flex mt-16 items-center justify-between">
                <div class="text-3xl font-bold">Отчеты</div>
                <a href="{{ route('reports.create', ['spring_id' => $spring]) }}" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                    </svg>
                    Добавить&nbsp;отчет
                </a>
            </div>
            <div class="mt-3">
                <ul role="list" class="">
                    @foreach ($reports as $report)
                        <x-report has-name="false" :report="$report"/>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="mt-16">
                <a href="{{ route('reports.create', ['spring_id' => $spring]) }}" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                    </svg>
                    Добавить&nbsp;отчет
                </a>
            </div>
        @endif
    @endif
</div>
