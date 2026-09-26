@persist('rodnik-map')
<div class="fixed bottom-0 h-[50vh] sm:w-1/2 w-full sm:h-full bg-stone-100"
    :class="{
        'h-[50vh]': ! fullscreen && ! minimized,
        'h-full': fullscreen && ! minimized,

        'sm:w-1/2': ! fullscreen,
        'sm:w-full': fullscreen,

        'h-10': minimized,
        'ol-minimized': minimized,
    }"
    id="map"
    x-data="mapOwner">
    <div x-cloak x-show="window.rodnikMap.queryParameters.location" class="absolute w-full h-full flex items-center justify-center" style="pointer-events: none; z-index: 10001">
        <div class="text-black/50">
            <svg class="w-14 h-14"  viewBox="0 0 224 224" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <g id="pointer" transform="translate(4.000000, 4.000000)" fill="#000000" fill-rule="nonzero" stroke="#FFFFFF" stroke-width="4">
                        <path d="M201.803164,114 C198.74255,161.142969 161.142969,198.74255 114,201.803164 L114,212 C114,215.313708 111.313708,218 108,218 C104.686292,218 102,215.313708 102,212 L102,201.803164 C54.8570309,198.74255 17.2574498,161.142969 14.1968358,114 L4.00000024,114 C0.686291658,114 -1.99999976,111.313708 -1.99999976,108 C-1.99999976,104.686292 0.686291658,102 4.00000024,102 L14.1968358,102 C17.2574498,54.8570308 54.8570309,17.2574498 102,14.1968358 L102,4 C102,0.686291437 104.686292,-2 108,-2 C111.313709,-2 114,0.686291565 114,4 L114,14.1968358 C161.142969,17.2574498 198.74255,54.8570309 201.803164,102 L212,102 C215.313709,102 218,104.686292 218,108 C218,111.313708 215.313709,114 212,114 L201.803164,114 Z M114.000008,189.773254 C154.51262,186.765606 186.765606,154.51262 189.773254,114.000008 L180,114 C176.686292,114 174,111.313708 174,108 C174,104.686292 176.686292,102 180,102 L189.773255,102 C186.765606,61.4873801 154.51262,29.2343943 114.000008,26.2267459 L114,36 C114,39.3137085 111.313708,42 108,42 C104.686292,42 102,39.3137085 102,36 L102,26.2267454 C61.4873801,29.2343943 29.2343943,61.4873801 26.2267459,101.999992 L36,102 C39.3137085,102 42,104.686292 42,108 C42,111.313708 39.3137085,114 36,114 L26.2267454,114 C29.2343943,154.51262 61.4873801,186.765606 101.999992,189.773254 L102,180 C102,176.686292 104.686292,174 108,174 C111.313708,174 114,176.686292 114,180 L114,189.773255 Z M108,70 C128.98682,70 146,87.0131795 146,108 C146,128.98682 128.98682,146 108,146 C87.0131795,146 70,128.98682 70,108 C70,87.0131795 87.0131795,70 108,70 Z M108,134 C122.359403,134 134,122.359403 134,108 C134,93.6405965 122.359403,82 108,82 C93.6405965,82 82,93.6405965 82,108 C82,122.359403 93.6405965,134 108,134 Z" id="Shape"></path>
                    </g>
                </g>
            </svg>
            <!--
                <svg xmlns="http://www.w3.org/2000/svg"
                    fill="#000000"
                    stroke="#ffffff" stroke-width="2"
                    viewBox="0 0 256 256"
                    >
                    <path d="M232,124H219.91A92.13,92.13,0,0,0,132,36.09V24a4,4,0,0,0-8,0V36.09A92.13,92.13,0,0,0,36.09,124H24a4,4,0,0,0,0,8H36.09A92.13,92.13,0,0,0,124,219.91V232a4,4,0,0,0,8,0V219.91A92.13,92.13,0,0,0,219.91,132H232a4,4,0,0,0,0-8ZM132,211.9V200a4,4,0,0,0-8,0v11.9A84.11,84.11,0,0,1,44.1,132H56a4,4,0,0,0,0-8H44.1A84.11,84.11,0,0,1,124,44.1V56a4,4,0,0,0,8,0V44.1A84.11,84.11,0,0,1,211.9,124H200a4,4,0,0,0,0,8h11.9A84.11,84.11,0,0,1,132,211.9ZM128,92a36,36,0,1,0,36,36A36,36,0,0,0,128,92Zm0,64a28,28,0,1,1,28-28A28,28,0,0,1,128,156Z"></path>
                </svg>
            -->
        </div>
    </div>
    <div x-cloak class="absolute sm:block top-2 right-2" style="z-index: 10000;"
        :class="{
            'hidden': minimized
        }"
        >
        <div @click="toggleFullscreen" class="h-9 w-9 bg-white shadow-xs rounded-md cursor-pointer flex items-center justify-center">
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
                class="select-none border border-2 mt-2 h-9 w-9 bg-white shadow-xs rounded-md cursor-pointer flex items-center justify-center"
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
                        window.rodnikMap.updateFilters();
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
                    class="cursor-default shadow-sm absolute top-0 right-11 w-64 rounded-md bg-white pb-2"
                >
                    <fieldset>
                        <label for="filters.all" class="relative flex items-start cursor-pointer px-4 py-2 pb-1">
                            <div class="flex items-center h-5">
                                <input @change="toggleAllFilters" x-model="filters.all" id="filters.all" name="filter__all" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-bold text-gray-700"><span data-map-i18n="map.filters.all">{{ __('ui.map.filters.all') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.spring" class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.spring" id="filters.spring" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.springs">{{ __('ui.map.filters.springs') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.water_well"  class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.water_well" id="filters.water_well" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.water_wells">{{ __('ui.map.filters.water_wells') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.water_tap" class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.water_tap" id="filters.water_tap" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.water_taps">{{ __('ui.map.filters.water_taps') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.drinking_water"  class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.drinking_water" id="filters.drinking_water" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.drinking_water_sources">{{ __('ui.map.filters.drinking_water_sources') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.fountain" class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.fountain" id="filters.fountain" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.fountains">{{ __('ui.map.filters.fountains') }}</span></span>
                            </div>
                        </label>
                        <label for="filters.other"  class="relative flex items-start cursor-pointer px-4 py-1">
                            <div class="flex items-center h-5">
                                <input @change="updateFilters" x-model="filters.other" id="filters.other" name="filter__intermittent" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                            </div>
                            <div class="ml-3 text-sm">
                                <span class="font-regular text-gray-700"><span data-map-i18n="map.filters.other">{{ __('ui.map.filters.other') }}</span></span>
                            </div>
                        </label>
                        <div class="px-4 py-1 flex items-center cursor-pointer"
                            @click="filters.with_reports = ! filters.with_reports; updateFilters()"
                            x-model="filters.with_reports">
                            <button
                                type="button" class="-ml-1 mr-2 group relative inline-flex h-5 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full focus:outline-hidden" role="switch" aria-checked="false">
                                <span aria-hidden="true" class="pointer-events-none absolute h-full w-full rounded-md"></span>
                                <span
                                    :class="{
                                        'bg-orange-400': filters.with_reports,
                                        'bg-gray-300': ! filters.with_reports,
                                    }"
                                    aria-hidden="true" class="pointer-events-none absolute mx-auto h-5 w-9 rounded-full transition-colors duration-200 ease-in-out"></span>
                                <span
                                     :class="{
                                        'translate-x-5': filters.with_reports,
                                        'translate-x-1': ! filters.with_reports,
                                    }"
                                    aria-hidden="true" class="translate-x-0 pointer-events-none absolute left-0 inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition-transform duration-200 ease-in-out"></span>
                            </button>
                            <div class="font-regular text-gray-700 text-sm"><span data-map-i18n="map.filters.with_reports">{{ __('ui.map.filters.with_reports') }}</span></div>
                        </div>
                        <div class="px-4 py-1 flex items-center cursor-pointer"
                            @click="filters.along = ! filters.along; updateFilters()"
                            x-model="filters.along">
                            <button
                                type="button" class="-ml-1 mr-2 group relative inline-flex h-5 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full focus:outline-hidden" role="switch" aria-checked="false">
                                <span aria-hidden="true" class="pointer-events-none absolute h-full w-full rounded-md"></span>
                                <span
                                    :class="{
                                        'bg-orange-400': filters.along,
                                        'bg-gray-300': ! filters.along,
                                    }"
                                    aria-hidden="true" class="pointer-events-none absolute mx-auto h-5 w-9 rounded-full transition-colors duration-200 ease-in-out"></span>
                                <span
                                     :class="{
                                        'translate-x-5': filters.along,
                                        'translate-x-1': ! filters.along,
                                    }"
                                    aria-hidden="true" class="translate-x-0 pointer-events-none absolute left-0 inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition-transform duration-200 ease-in-out"></span>
                            </button>
                            <div class="font-regular text-gray-700 text-sm"><span data-map-i18n="map.filters.along_track">{{ __('ui.map.filters.along_track') }}</span></div>
                        </div>
                    </fieldset>
                </div>
            </div>
        </div>
        <div class="relative">
            <div
                x-data="{
                    layersOpen: false,
                    sourceState: window.rodnikMap.sourceState,
                    source: function(name) {
                        window.rodnikMap.source(name);
                    },
                    overlays: window.rodnikMap.overlays,
                    updateOverlays: function() {
                        window.rodnikMap.updateOverlays();
                    }
                }"
                @click="layersOpen = ! layersOpen;"
                @click.outside="layersOpen = false;"
                class="select-none border border-2 mt-2 h-9 w-9 bg-white overflow:hidden shadow-xs rounded-md cursor-pointer flex items-center justify-center"
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
                    class="absolute shadow-sm top-0 right-11 w-64 rounded-md shadow-sm bg-white px-4 py-2"
                >
                    <div>
                        <div class="space-y-1.5">
                            <button @click="source('osm')" type="button" class=" inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name != 'osm',
                                    'bg-blue-600': sourceState.name == 'osm',
                                    'text-blue-700': sourceState.name != 'osm',
                                    'text-white': sourceState.name == 'osm'
                                }"
                            >OpenStreetMap</button>
                            <button @click="source('openTopoMap')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name != 'openTopoMap',
                                    'bg-blue-600': sourceState.name == 'openTopoMap',
                                    'text-blue-700': sourceState.name != 'openTopoMap',
                                    'text-white': sourceState.name == 'openTopoMap'
                                }"
                            >OpenTopoMap</button>
                            <button @click="source('outdoors')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name != 'outdoors',
                                    'bg-blue-600': sourceState.name == 'outdoors',
                                    'text-blue-700': sourceState.name != 'outdoors',
                                    'text-white': sourceState.name == 'outdoors'
                                }"
                            >OSM Outdoors</button>
                        {{--
                            <button @click="source('outdoors')" type="button" class="mr-0.5 inline-flex items-center px-3 py-1.5 border-2 border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name == 'outdoors',
                                    'bg-blue-600': sourceState.name != 'outdoors',
                                    'text-blue-700': sourceState.name == 'outdoors',
                                    'text-white': sourceState.name != 'outdoors'
                                }"
                            >OSM Outdoors</button>
                        --}}
                            <button @click="source('terrain')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name != 'terrain',
                                    'bg-blue-600': sourceState.name == 'terrain',
                                    'text-blue-700': sourceState.name != 'terrain',
                                    'text-white': sourceState.name == 'terrain'
                                }"
                            ><span data-map-i18n="map.layers.terrain">{{ __('ui.map.layers.terrain') }}</span></button>
                            <button @click="source('satellite')" type="button" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-full shadow-xs"
                                :class="{
                                    'bg-white': sourceState.name != 'satellite',
                                    'bg-blue-600': sourceState.name == 'satellite',
                                    'text-blue-700': sourceState.name != 'satellite',
                                    'text-white': sourceState.name == 'satellite'
                                }"
                            ><span data-map-i18n="map.layers.satellite">{{ __('ui.map.layers.satellite') }}</span></button>
                        </div>

                        <div class="mt-3 mb-1 space-y-2 space-x-1">
                            <fieldset class="space-y-2">
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateOverlays" x-model="overlays.stravaPublic" id="overlays.stravaPublic" name="overlays__stravaPublic" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="overlays.stravaPublic" class="font-regular text-gray-700"><span data-map-i18n="map.layers.strava_heatmap">{{ __('ui.map.layers.strava_heatmap') }}</span></label>
                                        {{--
                                            <p class="text-gray-500">Without detailed heatmap for zoomed in maps — Strava does not permit that</p>
                                        --}}
                                    </div>
                                </div>
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input @change="updateOverlays" x-model="overlays.osmTraces" id="overlays.osmTraces" name="overlays__osmTraces" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded-sm">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="overlays.osmTraces" class="font-regular text-gray-700"><span data-map-i18n="map.layers.osm_traces">{{ __('ui.map.layers.osm_traces') }}</span></label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>


                    </div>
                </div>
            </div>
        </div>
        <div @click="window.rodnikMap.locateMe()" class="mt-2 h-9 w-9 bg-white shadow-xs rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700">
            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" class="h-5 w-5">
                <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
            </svg>
        </div>
        <div x-data="{
            gpxTrackUploaded: window.rodnikMap.trackLayer.isUploaded,
            gpxTrackMenuOpen: false,
            forceUpload: false
        }" class="relative">
            <label
                x-ref="gpxTrackUploadLabel"
                @click="if (gpxTrackUploaded.value && ! forceUpload) {
                    event.preventDefault();
                    gpxTrackMenuOpen = ! gpxTrackMenuOpen;
                }
                "
                @click.outside="gpxTrackMenuOpen = false;"
                for="gpx-track-upload" title="{{ __('ui.map.upload_track') }}" data-map-i18n-title="map.upload_track" class="mt-2 h-9 w-9 bg-white border-2 shadow-xs rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700" :class="{
                'border-blue-600': gpxTrackMenuOpen,
                'border-white': ! gpxTrackMenuOpen,
                'text-blue-700': gpxTrackMenuOpen,
                'hover:text-blue-600': ! gpxTrackMenuOpen,
                'text-black': ! gpxTrackMenuOpen,
            }">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-5 h-5">
                    <path d="M7.25 10.25a.75.75 0 0 0 1.5 0V4.56l2.22 2.22a.75.75 0 1 0 1.06-1.06l-3.5-3.5a.75.75 0 0 0-1.06 0l-3.5 3.5a.75.75 0 0 0 1.06 1.06l2.22-2.22v5.69Z" />
                    <path d="M3.5 9.75a.75.75 0 0 0-1.5 0v1.5A2.75 2.75 0 0 0 4.75 14h6.5A2.75 2.75 0 0 0 14 11.25v-1.5a.75.75 0 0 0-1.5 0v1.5c0 .69-.56 1.25-1.25 1.25h-6.5c-.69 0-1.25-.56-1.25-1.25v-1.5Z" />
                </svg>
                <input id="gpx-track-upload" type="file"
                    x-on:change="forceUpload = false; window.rodnikMap.upload($event.target.files[0]); $event.target.value = null;" class="hidden">
            </label>
            <div
                x-cloak
                x-show="gpxTrackMenuOpen"
                class="absolute shadow-sm top-0 right-11 w-64 rounded-md shadow-sm bg-white px-4 py-4 flex flex-col gap-y-2"
                >
                <button @click="forceUpload = true; $refs.gpxTrackUploadLabel.click()" class="px-3 py-1 border border-blue-500 hover:bg-blue-50 rounded-sm text-blue-500 hover:text-blue-600 text-sm font-medium transition-colors"><span data-map-i18n="map.upload_new_track_or_photo">{{ __('ui.map.upload_new_track_or_photo') }}</span></button>
                <button type="button" @click="window.rodnikMap.trackLayer.clear(); gpxTrackMenuOpen = false; forceUpload = false" class="px-3 py-1 border border-red-500 hover:bg-red-50 rounded-sm text-red-500 hover:text-red-600 text-sm font-medium transition-colors"><span data-map-i18n="map.remove_track">{{ __('ui.map.remove_track') }}</span></button>
            </div>
        </div>
        <div @click="window.rodnikMap.download()" title="{{ __('ui.map.download_waypoints') }}" data-map-i18n-title="map.download_waypoints" class="mt-2 h-9 w-9 bg-white shadow-xs rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-5 h-5">
                <path d="M8.75 2.75a.75.75 0 0 0-1.5 0v5.69L5.03 6.22a.75.75 0 0 0-1.06 1.06l3.5 3.5a.75.75 0 0 0 1.06 0l3.5-3.5a.75.75 0 0 0-1.06-1.06L8.75 8.44V2.75Z" />
                <path d="M3.5 9.75a.75.75 0 0 0-1.5 0v1.5A2.75 2.75 0 0 0 4.75 14h6.5A2.75 2.75 0 0 0 14 11.25v-1.5a.75.75 0 0 0-1.5 0v1.5c0 .69-.56 1.25-1.25 1.25h-6.5c-.69 0-1.25-.56-1.25-1.25v-1.5Z" />
            </svg>
        </div>
    </div>
    <div class="sm:hidden absolute right-2" style="z-index: 10000;"
        :class="{
            'bottom-7': ! minimized,
            'bottom-2': minimized,
        }"
    >
        <div x-show="! fullscreen" @click="toggleMinimized" class="mt-2 h-6 w-9 bg-gray-600 opacity-60 hover:opacity-100 text-white shadow-xs rounded-md cursor-pointer flex items-center justify-center">
            <svg x-cloak x-show="! minimized" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" class="h-3 w-3 mt-0.5" fill="currentColor">
                <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
            </svg>
            <svg x-cloak x-show="minimized" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" class="h-3 w-3" fill="currentColor">
                 <path d="m7.247 4.86-4.796 5.481c-.566.647-.106 1.659.753 1.659h9.592a1 1 0 0 0 .753-1.659l-4.796-5.48a1 1 0 0 0-1.506 0z"/>
            </svg>
        </div>
    </div>
    <div x-data="trackNotice(window.rodnikMap.sharedTrack)" x-cloak x-show="!minimized && visible"
        x-transition:leave="transition-opacity duration-150 ease-out motion-reduce:transition-none"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute bottom-10 left-2 z-[10000] max-w-[calc(100%-4rem)] rounded-lg border border-zinc-200 bg-white/95 px-3 py-2 text-sm shadow-sm sm:max-w-sm" role="status" aria-live="polite">
        <p x-show="track.status !== 'missing'" x-text="track.name" class="max-w-64 truncate font-semibold text-zinc-800"></p>
        <p x-show="['local', 'saving'].includes(track.status)" class="flex items-center gap-2 text-zinc-500"><span class="size-3 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600" aria-hidden="true"></span><span data-map-i18n="map.track_uploading">{{ __('ui.map.track_uploading') }}</span></p>
        <p x-show="track.status === 'saved'" class="text-xs text-zinc-500" data-map-i18n="map.track_uploaded">{{ __('ui.map.track_uploaded') }}</p>
        <div x-show="track.status === 'failed'" class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="text-red-700" data-map-i18n="map.track_upload_failed">{{ __('ui.map.track_upload_failed') }}</span>
            <button type="button" @click="window.rodnikMap.retrySharedTrack().catch(() => {})" class="min-h-8 font-semibold text-blue-600 hover:underline" data-map-i18n="map.retry_track">{{ __('ui.map.retry_track') }}</button>
        </div>
        <p x-show="track.status === 'missing'" class="text-amber-800" data-map-i18n="map.track_deleted">{{ __('ui.map.track_deleted') }}</p>
    </div>
    <div class="drop-overlay" style="z-index: 10002;">
        <div class="text-center">
            <div class="font-extrabold text-2xl lg:text-4xl"><span data-map-i18n="map.drop_track">{{ __('ui.map.drop_track') }}</span></div>
            <div class="mt-1 font-medium text-lg"><span data-map-i18n="map.display_route">{{ __('ui.map.display_route') }}</span></div>
            <div class="my-4 font-extrabold text-3xl"><span data-map-i18n="map.or">{{ __('ui.map.or') }}</span></div>
            <div class="font-extrabold text-2xl lg:text-4xl"><span data-map-i18n="map.drop_photo">{{ __('ui.map.drop_photo') }}</span></div>
            <div class="mt-1 font-medium text-lg"><span data-map-i18n="map.locate_from_photo">{{ __('ui.map.locate_from_photo') }}</span></div>
        </div>
    </div>
</div>
@endpersist
