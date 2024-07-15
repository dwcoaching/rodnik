<div class="w-full px-4 h-full"
    x-data="{
        saving: $wire.$entangle('saving'),
        locationModeJustExited: false,
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
    "

    x-on:location-mode-exited.window="
        locationModeJustExited = true
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
                x-show="! saving && ! locationModeJustExited"
                x-cloak
                x-data="{
                        latitude: $wire.$entangle('latitude'),
                        longitude: $wire.$entangle('longitude'),
                        coordinates: $wire.$entangle('coordinates'),
                        mapAndInputSynced: function() {
                            let parsedCoordinates = window.parseCoordinates(this.coordinates);
                            return this.latitude == parsedCoordinates.getLatitude()
                                && this.longitude == parsedCoordinates.getLongitude()
                        },
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
                    x-init="
                        updateCoordinates(window.rodnikMap.getCoordinates())
                        locationModeJustExited = false
                    "
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
                                        @if ($springId)
                                            x-on:click="
                                            window.dispatchEvent(
                                                new CustomEvent('spring-selected-on-map', {detail: {
                                                    id: {{ intval($springId) }},
                                                }}))
                                            "
                                        @else
                                            x-on:click="
                                                window.dispatchEvent(
                                                    new CustomEvent('spring-deselected-on-map'))
                                                "
                                        @endif
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
                            <div class="mt-1 flex items-center gap-x-2">
                                <input
                                    x-ref="coordinates"
                                    x-model="coordinates"
                                    @change="updateCoordinates($event.target.value)"
                                    @blur="updateCoordinates($event.target.value)"
                                    type="text" name="coordinates" id="coordintates" class="focus:ring-inset focus:ring-2 ring-inset font-medium block w-full rounded-lg border-0 py-2.5 text-gray-900 h-12 ring-1 ring-inset ring-gray-300  placeholder:text-gray-400 sm:leading-6"
                                    x-bind:class="{
                                        'focus:ring-blue-600': ! coordinatesError,
                                        'focus:ring-red-600': coordinatesError,
                                        'ring-gray-300 ': ! coordinatesError,
                                        'ring-1': ! coordinatesError,
                                        'ring-red-600': coordinatesError,
                                        'ring-2': coordinatesError,
                                    }">
                                <button type="button" class="btn btn-primary"
                                    :class="{
                                        'btn-disabled': mapAndInputSynced(),
                                    }"
                                    x-bind:disabled="mapAndInputSynced()"
                                    >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 256 256"><path d="M232,120h-8.34A96.14,96.14,0,0,0,136,32.34V24a8,8,0,0,0-16,0v8.34A96.14,96.14,0,0,0,32.34,120H24a8,8,0,0,0,0,16h8.34A96.14,96.14,0,0,0,120,223.66V232a8,8,0,0,0,16,0v-8.34A96.14,96.14,0,0,0,223.66,136H232a8,8,0,0,0,0-16Zm-96,87.6V200a8,8,0,0,0-16,0v7.6A80.15,80.15,0,0,1,48.4,136H56a8,8,0,0,0,0-16H48.4A80.15,80.15,0,0,1,120,48.4V56a8,8,0,0,0,16,0V48.4A80.15,80.15,0,0,1,207.6,120H200a8,8,0,0,0,0,16h7.6A80.15,80.15,0,0,1,136,207.6ZM128,88a40,40,0,1,0,40,40A40,40,0,0,0,128,88Zm0,64a24,24,0,1,1,24-24A24,24,0,0,1,128,152Z"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center flex-wrap gap-y-2 gap-x-2 mt-2">
                    <div
                        class="btn btn-sm"
                        @click="
                            const text = await navigator.clipboard.readText()
                            coordinates = text
                            updateCoordinates(coordinates)
                        ">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M10.986 3H12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1.014A2.25 2.25 0 0 1 7.25 1h1.5a2.25 2.25 0 0 1 2.236 2ZM9.5 4v-.75a.75.75 0 0 0-.75-.75h-1.5a.75.75 0 0 0-.75.75V4h3Z" clip-rule="evenodd" />
                        </svg>
                        Paste from Clipboard
                    </div>
                    {{--
                        <div
                            class="btn btn-sm"
                            @click="
                                const text = await navigator.clipboard.readText();
                                coordinates = text;
                            ">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4">
                                <path d="M9.5 8.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                <path fill-rule="evenodd" d="M2.5 5A1.5 1.5 0 0 0 1 6.5v5A1.5 1.5 0 0 0 2.5 13h11a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 13.5 5h-.879a1.5 1.5 0 0 1-1.06-.44l-1.122-1.12A1.5 1.5 0 0 0 9.38 3H6.62a1.5 1.5 0 0 0-1.06.44L4.439 4.56A1.5 1.5 0 0 1 3.38 5H2.5ZM11 8.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" clip-rule="evenodd" />
                            </svg>
                            Get Location from Photo
                        </div>
                    --}}
                </div>
                <div class="mt-4 pb-6">
                    <div class="flex justify-start">
                        <button type="button"
                            @click="if (! error() && ! saving) {
                                saving = true
                                $wire.$call('store')
                            }" class="btn btn-primary btn-block"
                            x-bind:class="{
                                'btn-disabled': error() || saving,
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
