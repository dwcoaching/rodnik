<div class="w-full px-4" x-data
    x-on:turbo-location-create.window="
        $wire.$set('locationMode', true)
        window.rodnikMap.enterLocationMode()
        registerLocationCreateVisit()
    "

    x-on:turbo-location-edit.window="
        $wire.$set('locationMode', true, false)
        $wire.$call('setSpring', $event.detail.springId)
        window.rodnikMap.enterLocationMode()
        window.rodnikMap.locate($event.detail.coordinates);
        registerLocationEditVisit()
    ">
    @if ($locationMode)
        <div
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

            <div class="flex items-center justify-between">
                <div class="mt-4 flex-1 min-w-0">
                    <span class="block text-lg font-bold">
                        <span class="items-center">
                            @if ($springId)
                                <div>
                                    Edit water source
                                    <div class="text-sm mt-1 font-normal">
                                        All changes will be applied immediately.
                                        Please double-check before changing anything 😇
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center justify-between">
                                    <div>Create New Water Source</div>
                                    <button type="button" class="btn btn-sm"
                                        onclick="window.history.back()"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-6 w-6"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path
                                              stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M6 18L18 6M6 6l12 12" />
                                          </svg>
                                        Cancel
                                    </button>
                                </div>
                            @endif
                        </span>
                    </span>
                </div>
            </div>

            <div class="">

                <div class="w-full sm:max-w-xl">


                    <div class="flex items-center">
                        <div class="mt-2 w-full relative">
                            <input
                                x-ref="coordinates"
                                x-model="coordinates"
                                @change="updateCoordinates($event.target.value)"
                                type="text"
                                name="coordinates"
                                id="coordinates"
                                type="text" name="name" id="name"
                                class="h-[60px] font-bold w-full pl-3 pt-[32px] text-base input input-primary"
                                x-bind:class="{
                                    'border-red-600': coordinatesError,
                                }"
                                placeholder="">
                            <label class="pointer-events-none absolute top-2 left-3 text-sm font-medium text-gray-500">
                                <span class="">Latitude, longitude</span>
                                <svg x-cloak x-show="coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-red-600">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pb-6">
                <div class="flex justify-start">
                    <button type="button"
                        @click="if (! error()) {
                            $wire.$call('store')
                        }" class="btn btn-primary btn-block"
                        x-bind:class="{
                            'bg-blue-300': error(),
                            'cursor-not-allowed': error(),
                            'text-blue-50': error(),
                            'text-white': ! error(),
                            'bg-blue-600': ! error(),
                            'hover:bg-blue-700': ! error(),
                            'cursor-pointer': ! error(),
                            'focus:bg-blue-700': ! error(),
                        }"
                    >
                        {{ $springId ? 'Save changes' : 'Add water source' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
