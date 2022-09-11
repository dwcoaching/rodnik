<div id="spring"
    x-data="{
        active: 'osm',
        source: function(name) {
            this.active = name;
            window.rodnikMap.source(name);
        },
        locateMap: true,
        filters: window.rodnikMap.filters,
        overlays: window.rodnikMap.overlays,
        updateFilters: function() {
            this.checkAllFilters();
            window.rodnikMap.springsFinalLayer.updateStyle();
        },
        checkAllFilters: function() {
            if (this.filters.spring == true
                && this.filters.water_well == true
                && this.filters.water_tap == true
                && this.filters.drinking_water == true
                && this.filters.fountain == true
                && this.filters.other == true) {
                this.filters.all = true;
            } else {
                this.filters.all = false;
            }
        },
        toggleAllFilters: function() {
            if (this.filters.all) {
                this.filters.spring = true;
                this.filters.water_well = true;
                this.filters.water_tap = true;
                this.filters.drinking_water = true;
                this.filters.fountain = true;
                this.filters.other = true;
            } else {
                this.filters.spring = false;
                this.filters.water_well = false;
                this.filters.water_tap = false;
                this.filters.drinking_water = false;
                this.filters.fountain = false;
                this.filters.other = false;
            }

            this.updateFilters();
        },
        updateOverlays: function() {
            window.rodnikMap.updateOverlays();
        },
        springsSource: function(userId = 0) {
            window.rodnikMap.springsSource(userId);
            this.userId = userId;
        },
        userId: @entangle('userId'),
    }"
    x-on:spring-selected.window="
        $wire.setSpring($event.detail.id);
        ym(90143259, 'hit', window.location.origin + '/' + $event.detail.id);
    "
    x-on:spring-unselected.window="
        $wire.unselectSpring();
        ym(90143259, 'hit', window.location.origin + '/');
    "
    x-on:popstate.window="
        if ($event.state && $event.state.springId) {
            locateMap = true;
            const event = new CustomEvent('spring-selected', {detail: {id: $event.state.springId}});
            window.dispatchEvent(event);
        } else if ($event.state && $event.state.userId) {
            springsSource($event.state.userId);
            window.rodnikMap.unselectPreviousFeature();
            const event = new CustomEvent('spring-unselected');
            window.dispatchEvent(event);
        } else {
            springsSource(0);
            const event = new CustomEvent('spring-unselected');
            window.dispatchEvent(event);
        }"
    x-init="
        if ({{ intval($springId)}} && locateMap) {
            window.rodnikMap.locate({{ json_encode($coordinates) }});
            window.rodnikMap.showFeature({{ $springId }});
        }

        if ({{ intval($user instanceof \App\Models\User) }}) {
            window.rodnikMap.locateWorld();
        }

        locateMap = false;

        window.initPhotoSwipe('#spring');
    ">
    @if (! $spring)
        <div>
            {{--
                <div>
                    @if (Auth::check() && Auth::user()->springs->count() > 0)
                        <div class="mb-4">
                            <button @click="springsSource()" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="{
                                    'bg-white': userId != 0,
                                    'bg-blue-600': userId == 0,
                                    'text-blue-700': userId != 0,
                                    'text-white': userId == 0
                                }"
                            >Все родники</button>
                            <button @click="springsSource({{ Auth::user()->id }})" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="{
                                    'bg-white': userId == 0,
                                    'bg-blue-600': userId != 0,
                                    'text-blue-700': userId == 0,
                                    'text-white': userId != 0
                                }"
                            >Мои родники</button>
                        </div>
                    @endif
                </div>
            --}}

            <div class="bg-white rounded-xl shadow p-4">
                <div>
                    <div @click="window.rodnikMap.locateMe()" type="button" class="-ml-1 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24">
                            <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
                        </svg>
                        <span class="ml-2">
                            Определить мои координаты
                        </span>
                    </div>
                </div>

                <div class="mt-6 space-y-2 space-x-1">
                    <fieldset class="space-y-2">
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="toggleAllFilters" x-model="filters.all" id="filters.all" name="filter__all" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.all" class="font-bold text-gray-700">Показывать все источники воды</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.spring" id="filters.spring" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.spring" class="font-regular text-gray-700">Родники</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.water_well" id="filters.water_well" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.water_well" class="font-regular text-gray-700">Колодцы</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.water_tap" id="filters.water_tap" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.water_tap" class="font-regular text-gray-700">Краны и колонки</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.drinking_water" id="filters.drinking_water" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.drinking_water" class="font-regular text-gray-700">Источники питьевой воды</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.fountain" id="filters.fountain" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.fountain" class="font-regular text-gray-700">Фонтаны</label>
                            </div>
                        </div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.other" id="filters.other" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="filters.other" class="font-regular text-gray-700">Прочие источники воды</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div>
                    <div class="mt-6 space-y-2">
                        <button @click="source('osm')" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            :class="{
                                'bg-white': active != 'osm',
                                'bg-blue-600': active == 'osm',
                                'text-blue-700': active != 'osm',
                                'text-white': active == 'osm'
                            }"
                        >OpenStreetMap</button>
                        <button @click="source('mapy')" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            :class="{
                                'bg-white': active != 'mapy',
                                'bg-blue-600': active == 'mapy',
                                'text-blue-700': active != 'mapy',
                                'text-white': active == 'mapy'
                            }"
                        >Mapy.cz</button>
                    {{--
                        <button @click="source('outdoors')" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
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
                                'bg-white': active != 'terrain',
                                'bg-blue-600': active == 'terrain',
                                'text-blue-700': active != 'terrain',
                                'text-white': active == 'terrain'
                            }"
                        >Google Terrain</button>
                    </div>

                    <div class="mt-3 space-y-2 space-x-1">
                        <fieldset class="space-y-2">
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input @change="updateOverlays" x-model="overlays.stravaPublic" id="overlays.stravaPublic" name="overlays__stravaPublic" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="overlays.stravaPublic" class="font-regular text-gray-700">Strava Heatmap</label>
                                    {{--
                                        <p class="text-gray-500">Без подробной Heatmap для крупного масштаба — Strava это не разрешает</p>
                                    --}}
                                </div>
                            </div>
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input @change="updateOverlays" x-model="overlays.osmTraces" id="overlays.osmTraces" name="overlays__osmTraces" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="overlays.osmTraces" class="font-regular text-gray-700">OpenStreetMap Traces</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>


                </div>
            </div>

            <div class="mt-3 bg-blue-100 rounded-xl px-4 py-2 text-sm leading-relaxed">
                <a href="https://t.me/rodnik_today" target="_blank" class="font-medium text-blue-600 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                    </svg>
                    rodnik_today
                </a> — открытая группа в Telegram для обсуждения проблем, ошибок, идей
                и общения для развития проекта.
            </div>


            <div>
                @if ($user)
                    <div class="mt-16 text-3xl font-bold">{{ $user->name }}, отчеты</div>
                @else
                    <div class="mt-16 text-3xl font-bold">Последние отчеты</div>
                @endif

                <ul role="list" class="pt-4" wire:key="reports">
                    @foreach ($lastReports as $report)
                        <livewire:reports.show has-name="true" :report="$report" wire:key="reports.latest.show.{{ $report->id }}" />
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($spring)
        <div class="" wire:key="spring.{{ $spring->id }}">
            <div class="text-3xl font-bold">
                <span class="mr-2">{{ $spring->name }}</span>
                <span class="text-gray-600 text-2xl font-thin">#{{ $spring->id }}</span>
                <a class="text-sm text-blue-600 font-normal" href="{{ route('springs.edit', $spring) }}">редактировать</a>
            </div>
            <div class="mt-3 text-gray-500 text-sm cursor-pointer"
                x-data="{
                    copied: false,
                    timeout: null
                }"
                @click="
                    $clipboard('{{ $spring->latitude }}, {{ $spring->longitude }}');
                    copied = true;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        copied = false;
                    }, 1000)
                "
            >
                <span class="mr-1">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline -mt-0.5 -mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                    <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                </svg>
                <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">cкопировано</span>
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
                        <livewire:reports.show has-name="false" :report="$report" wire:key="spring.reports.show.{{ $report->id }}" />
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
