<x-app-layout>
    <div class="flex flex-col-reverse sm:flex-row w-screen h-full">
        <div class="relative flex-none sm:w-1/2 h-1/2 sm:h-full" id="map"
            x-data="{}"
            x-init="
                initOpenLayers($el.id);
                window.rodnikMap.springsSource({{ intval($userId) }});
            ">
            <div class="absolute top-16 right-2" style="z-index: 10000;">
                {{--
                    <div class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center">
                        ←→
                    </div>
                --}}
                <div @click="window.rodnikMap.locateMe()" class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center text-gray-500 hover:text-gray-900 ">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" class="h-6 w-6">
                        <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
                    </svg>
                </div>
                {{--
                    <div class="relative mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center"
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
                        <div @click="filtersOpen = ! filtersOpen;">
                            F
                        </div>
                        <div x-cloak x-show="filtersOpen" class="absolute top-0 right-11 bg-white w-96 rounded-md bg-white px-4 py-2">
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
                    </div>
                    <div class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center">
                        L
                    </div>
                --}}
            </div>
        </div>
        <div class="w-full sm:w-1/2 sm:h-full h-1/2 overflow-y-scroll px-6">
             @include('navbar')
            <div class="">
                <livewire:spring spring_id="{{ $springId }}" user_id="{{ $userId }}" />
            </div>
        </div>
    </div>
</x-app-layout>
