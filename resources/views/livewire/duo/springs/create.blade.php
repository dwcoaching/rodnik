<div class="w-full px-4 h-full"
    x-data="{
        saving: $wire.$entangle('saving'),
    }"
    x-on:turbo-location-create.window="
        $wire.$call('initializeCreating')
        window.rodnikMap.enterLocationMode()
        registerLocationCreateVisit()
    "

    x-on:turbo-location-edit.window="
        $wire.$call('initializeEditing', $event.detail.springId)
        window.rodnikMap.enterLocationMode()
        window.rodnikMap.highlightFeatureById($event.detail.springId)
        registerLocationEditVisit()
    ">
    <div wire:loading.delay.long.flex class="h-full w-full hidden justify-center items-center">
        <div class="-top-6 relative animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div x-show="saving" class="h-full w-full flex justify-center items-center">
        <div class="-top-6 relative animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div wire:loading.remove>
        @if ($locationMode)
            <div
                x-show="! saving"
                x-cloak
                x-data="{
                        latitude: $wire.$entangle('latitude'),
                        longitude: $wire.$entangle('longitude'),
                        coordinates: $wire.$entangle('coordinates'),
                        coordinatesError: false,
                        error: function() {
                            if (this.coordinatesError) {
                                return true;
                            }

                            return false;
                        },
                        updateCoordinates: function(coordinates) {
                            try {
                                let parsedCoordinates = window.parseCoordinates(coordinates);
                                this.latitude = parsedCoordinates.getLatitude();
                                this.longitude = parsedCoordinates.getLongitude();
                                this.coordinates = this.latitude.toFixed(6) + ', ' + this.longitude.toFixed(6);
                                window.rodnikMap.locateWithZoom([this.longitude, this.latitude]);
                                this.coordinatesError = false;
                            } catch (error) {
                                this.coordinatesError = true;
                            }
                        },
                        mapMoved: function(coordinates) {
                            try {
                                let parsedCoordinates = window.parseCoordinates(coordinates);
                                this.latitude = parsedCoordinates.getLatitude();
                                this.longitude = parsedCoordinates.getLongitude();
                                this.coordinates = this.latitude.toFixed(6) + ', ' + this.longitude.toFixed(6);
                                this.coordinatesError = false;
                            } catch (error) {
                                this.coordinatesError = true;
                            }
                        }
                    }"
                    x-on:map-moved.window="mapMoved($event.detail.coordinates)"
                >
                <div class="hidden" wire:key="{{ uniqid() }}"
                    x-data
                    x-init="updateCoordinates(window.rodnikMap.getCoordinates())"
                ></div>
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <span class="block">
                            <span class="items-center">
                                <div class="flex items-center justify-between ">
                                    <div class="text-lg font-extrabold">
                                        @if ($springId)
                                            Update Location
                                        @else
                                            New Water Source
                                        @endif
                                    </div>
                                    <button type="button" class="rounded-md bg-stone-200 px-2.5 py-1.5 text-sm font-semibold text-stone-600 hover:bg-stone-300
                                                    outline-blue-700 outline-2 outline-offset-[3px] gap-x-1 flex items-center"
                                        onclick="window.history.back()"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-5 h-5">
                                            <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                        </svg>
                                        Cancel
                                    </button>
                                </div>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="">
                    <div class="w-full">
                        <div class="mt-2 w-full">
                            <label for="coordinates" class="text-sm font-semibold leading-6 text-gray-500 leading-6">
                                <span>Latitude, longitude</span>
                                <svg x-cloak x-show="coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-red-600">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </label>
                            <div class="mt-1">
                                <input
                                    x-ref="coordinates"
                                    x-model="coordinates"
                                    @change="updateCoordinates($event.target.value)"
                                    type="text" name="coordinates" id="coordintates" class="focus:ring-inset focus:ring-2 ring-inset font-medium block w-full rounded-lg border-0 py-2.5 text-gray-900ring-1 ring-inset ring-gray-300  placeholder:text-gray-400 sm:leading-6"
                                    x-bind:class="{
                                        'focus:ring-blue-600': ! coordinatesError,
                                        'focus:ring-red-600': coordinatesError,
                                        'ring-gray-300 ': ! coordinatesError,
                                        'ring-1': ! coordinatesError,
                                        'ring-red-600': coordinatesError,
                                        'ring-2': coordinatesError,





                                    }">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pb-6">
                    <div class="flex justify-start">
                        <button type="button"
                            @click="if (! error()) {
                                saving = true
                                $wire.$call('store')
                            }" class="btn btn-primary btn-block"
                            x-bind:class="{
                                'btn-disabled': error(),

                            }"
                        >
                            {{ $springId ? 'Save Location' : 'Add Water Source' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
