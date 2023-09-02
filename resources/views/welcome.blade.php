<x-app-layout>
    <div x-data="{
        fullscreen: false,
        toggleFullscreen: function() {
            this.fullscreen = ! this.fullscreen;
            window.rodnikMap.setFullscreen(this.fullscreen);
            $nextTick(() => window.rodnikMap.map.updateSize());
        },
        enterFullscreen: function() {
            this.fullscreen = true;
            $nextTick(() => window.rodnikMap.map.updateSize());
        },
        exitFullscreen: function(event) {
            this.fullscreen = false;
            $nextTick(() => window.rodnikMap.map.updateSize());
        }
    }">
        <div class="top-0 h-1/2 w-full sm:h-full overflow-y-scroll sm:ml-[50%]"
            :class="{
                hidden: fullscreen,
                fixed: ! fullscreen,
            }"
        >
            <div class="w-full sm:w-1/2 sm:h-full  px-4 flex flex-col items-stretch">
                <x-navbar map />
                <div class="grow flex">
                    <x-duo spring_id="{{ $springId }}" user_id="{{ $userId }}" />
                </div>
            </div>
        </div>





        <div class="fixed bottom-0 w-full sm:h-full"
            :class="{
                'h-1/2': ! fullscreen,
                'sm:w-1/2': ! fullscreen,

                'h-full': fullscreen,
                'sm:w-full': fullscreen,
            }"
            id="map"
            x-on:spring-selected-on-map.window="exitFullscreen"
            x-init="
                initOpenLayers($el.id);
                window.rodnikMap.springsSource({{ intval($userId) }});
            ">
            <div class="absolute top-2 right-2" style="z-index: 10000;">
                <div @click="toggleFullscreen" class="h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center">
                    <div x-show="! fullscreen" class="">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M13.28 7.78l3.22-3.22v2.69a.75.75 0 001.5 0v-4.5a.75.75 0 00-.75-.75h-4.5a.75.75 0 000 1.5h2.69l-3.22 3.22a.75.75 0 001.06 1.06zM2 17.25v-4.5a.75.75 0 011.5 0v2.69l3.22-3.22a.75.75 0 011.06 1.06L4.56 16.5h2.69a.75.75 0 010 1.5h-4.5a.747.747 0 01-.75-.75zM12.22 13.28l3.22 3.22h-2.69a.75.75 0 000 1.5h4.5a.747.747 0 00.75-.75v-4.5a.75.75 0 00-1.5 0v2.69l-3.22-3.22a.75.75 0 10-1.06 1.06zM3.5 4.56l3.22 3.22a.75.75 0 001.06-1.06L4.56 3.5h2.69a.75.75 0 000-1.5h-4.5a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0V4.56z" />
                        </svg>
                    </div>
                    <div x-cloak x-show="fullscreen" class="select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M3.28 2.22a.75.75 0 00-1.06 1.06L5.44 6.5H2.75a.75.75 0 000 1.5h4.5A.75.75 0 008 7.25v-4.5a.75.75 0 00-1.5 0v2.69L3.28 2.22zM13.5 2.75a.75.75 0 00-1.5 0v4.5c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-2.69l3.22-3.22a.75.75 0 00-1.06-1.06L13.5 5.44V2.75zM3.28 17.78l3.22-3.22v2.69a.75.75 0 001.5 0v-4.5a.75.75 0 00-.75-.75h-4.5a.75.75 0 000 1.5h2.69l-3.22 3.22a.75.75 0 101.06 1.06zM13.5 14.56l3.22 3.22a.75.75 0 101.06-1.06l-3.22-3.22h2.69a.75.75 0 000-1.5h-4.5a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-2.69z" />
                        </svg>
                    </div>
                </div>
                <div class="relative">
                    <div @click="filtersOpen = ! filtersOpen;"
                        class="select-none border border-2 mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center"
                        :class="{
                            'border-blue-600': filtersOpen,
                            'border-white': ! filtersOpen,
                            'text-blue-700': filtersOpen,
                            'hover:text-blue-600': ! filtersOpen,
                            'text-black': ! filtersOpen,
                        }"
                        @click.outside="filtersOpen = false;"
                        x-data="{
                            filtersOpen: false,
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
                            }
                        }">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel-fill" viewBox="0 0 16 16">
                                <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                            </svg>
                        </div>
                        <div
                            x-cloak
                            x-show="filtersOpen"
                            @click.stop=""
                            class="shadow absolute top-0 right-11 w-64 rounded-md bg-white px-4 py-2"
                        >
                            <fieldset class="space-y-2">
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="toggleAllFilters" x-model="filters.all" id="filters.all" name="filter__all" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.all" class="font-bold text-gray-700">All water sources</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.spring" id="filters.spring" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.spring" class="font-regular text-gray-700">Springs</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.water_well" id="filters.water_well" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.water_well" class="font-regular text-gray-700">Water well</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.water_tap" id="filters.water_tap" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.water_tap" class="font-regular text-gray-700">Water taps</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.drinking_water" id="filters.drinking_water" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.drinking_water" class="font-regular text-gray-700">Drinking water sources</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.fountain" id="filters.fountain" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.fountain" class="font-regular text-gray-700">Fountains</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateFilters" x-model="filters.other" id="filters.other" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="filters.other" class="font-regular text-gray-700">Other</label>
                                    </div>
                                </div>
                                <div class="mt-2 mb-4 flex items-start"
                                    @click="filters.confirmed = ! filters.confirmed; updateFilters()"
                                    x-model="filters.confirmed">
                                    <button
                                        type="button" class="mt-2 -ml-1 mr-2 group relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer items-center justify-center rounded-full focus:outline-none" role="switch" aria-checked="false">
                                        <span aria-hidden="true" class="pointer-events-none absolute h-full w-full rounded-md"></span>
                                        <span
                                            :class="{
                                                'bg-orange-400': filters.confirmed,
                                                'bg-gray-300': ! filters.confirmed,
                                            }"
                                            aria-hidden="true" class="pointer-events-none absolute mx-auto h-5 w-9 rounded-full transition-colors duration-200 ease-in-out"></span>
                                        <span
                                             :class="{
                                                'translate-x-5': filters.confirmed,
                                                'translate-x-1': ! filters.confirmed,
                                            }"
                                            aria-hidden="true" class="translate-x-0 pointer-events-none absolute left-0 inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform duration-200 ease-in-out"></span>
                                    </button>
                                    <div class="mt-2 font-regular text-gray-700 text-sm">Only confirmed with good water</div>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div
                        x-data="{
                            layersOpen: false,
                            active: 'osm',
                            source: function(name) {
                                this.active = name;
                                window.rodnikMap.source(name);
                            },
                            overlays: window.rodnikMap.overlays,
                            updateOverlays: function() {
                                window.rodnikMap.updateOverlays();
                            }
                        }"
                        @click="layersOpen = ! layersOpen;"
                        @click.outside="layersOpen = false;"
                        class="select-none border border-2 mt-2 h-9 w-9 bg-white overflow:hidden shadow-sm rounded-md cursor-pointer flex items-center justify-center"
                        :class="{
                                'border-blue-600': layersOpen,
                                'border-white': ! layersOpen,
                                'text-blue-700': layersOpen,
                                'hover:text-blue-600': ! layersOpen,
                                'text-black': ! layersOpen,
                            }"
                        >
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-layers-fill" viewBox="0 0 16 16">
                                <path d="M7.765 1.559a.5.5 0 0 1 .47 0l7.5 4a.5.5 0 0 1 0 .882l-7.5 4a.5.5 0 0 1-.47 0l-7.5-4a.5.5 0 0 1 0-.882l7.5-4z"/>
                                <path d="m2.125 8.567-1.86.992a.5.5 0 0 0 0 .882l7.5 4a.5.5 0 0 0 .47 0l7.5-4a.5.5 0 0 0 0-.882l-1.86-.992-5.17 2.756a1.5 1.5 0 0 1-1.41 0l-5.17-2.756z"/>
                            </svg>
                        </div>
                        <div
                            x-cloak
                            x-show="layersOpen"
                            @click.stop=""
                            class="absolute shadow top-0 right-11 w-64 rounded-md shadow bg-white px-4 py-2"
                        >
                            <div>
                                <div class="space-y-1.5">
                                    <button @click="source('osm')" type="button" class=" inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-sm"
                                        :class="{
                                            'bg-white': active != 'osm',
                                            'bg-blue-600': active == 'osm',
                                            'text-blue-700': active != 'osm',
                                            'text-white': active == 'osm'
                                        }"
                                    >OpenStreetMap</button>
                                    <button @click="source('outdoors')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-sm"
                                        :class="{
                                            'bg-white': active != 'outdoors',
                                            'bg-blue-600': active == 'outdoors',
                                            'text-blue-700': active != 'outdoors',
                                            'text-white': active == 'outdoors'
                                        }"
                                    >OSM Outdoors</button>
                                {{--
                                    <button @click="source('outdoors')" type="button" class="mr-0.5 inline-flex items-center px-3 py-1.5 border-2 border-blue-600 text-xs font-medium rounded-full shadow-sm"
                                        :class="{
                                            'bg-white': active == 'outdoors',
                                            'bg-blue-600': active != 'outdoors',
                                            'text-blue-700': active == 'outdoors',
                                            'text-white': active != 'outdoors'
                                        }"
                                    >OSM Outdoors</button>
                                --}}
                                    <button @click="source('terrain')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-sm"
                                        :class="{
                                            'bg-white': active != 'terrain',
                                            'bg-blue-600': active == 'terrain',
                                            'text-blue-700': active != 'terrain',
                                            'text-white': active == 'terrain'
                                        }"
                                    >Terrain</button>
                                    <button @click="source('satellite')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-sm"
                                        :class="{
                                            'bg-white': active != 'satellite',
                                            'bg-blue-600': active == 'satellite',
                                            'text-blue-700': active != 'satellite',
                                            'text-white': active == 'satellite'
                                        }"
                                    >Satellite</button>
                                </div>

                                <div class="mt-3 mb-1 space-y-2 space-x-1">
                                    <fieldset class="space-y-2">
                                        <div class="relative flex items-start">
                                            <div class="flex items-center h-5">
                                                <input @change="updateOverlays" x-model="overlays.stravaPublic" id="overlays.stravaPublic" name="overlays__stravaPublic" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="overlays.stravaPublic" class="font-regular text-gray-700">Strava Heatmap</label>
                                                {{--
                                                    <p class="text-gray-500">Without detailed heatmap for zoomed in maps — Strava does not permit that</p>
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
                    </div>
                </div>
                <div @click="window.rodnikMap.locateMe()" class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" class="h-5 w-5">
                        <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
                    </svg>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
