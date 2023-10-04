<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6"
    x-data="{
            type: @entangle('type').defer,
            latitude: @entangle('latitude').defer,
            longitude: @entangle('longitude').defer,
            coordinates: @entangle('coordinates').defer,
            coordinatesError: false,
            error: function() {
                if (this.coordinatesError) {
                    return true;
                }

                if (! this.type) {
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
                    window.rodnikPicker.updateCoordinates([this.longitude, this.latitude]);
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
        x-init="initOpenPicker(document.getElementById('openPicker'),
            [
                @if ($spring->id)
                    {{ $spring->longitude }}, {{ $spring->latitude }}
                @endif
            ]
        );"
    >
    <a x-data href="#" x-on:click.prevent="history.back();" class="text-3xl font-bold text-blue-600 hover:text-blue-700"">
        <span class="mr-2 inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 mb-6" width="36" height="36" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
            </svg>
        </span>
    </a>

    @guest
        <div class="bg-yellow-100 p-4 rounded-lg border border-yellow-400 mb-6  max-w-3xl">
            <div class="font-bold max-w-prose">
                You are writing anonymously
            </div>
            <div class="mt-2 max-w-prose">
                That's fine! But if you sign up, you'll grow the collection
                of your water sources, and you'll be able to edit
                and delete your reports.
            </div>
            <div class="mt-4 max-w-prose">
                <a href="{{ route('register') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Sign up</a>
                <a href="{{ route('login') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Log in</a>
            </div>
        </div>
    @endguest

    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <span class="block text-3xl font-bold">
                <span class="mr-2 inline-flex items-center">
                    @if ($spring->id)
                        <div>
                            Edit water source
                            <div class="text-sm mt-1 font-normal">
                                All changes will be applied immediately.
                                Please double-check before changing anything 😇
                            </div>
                        </div>
                    @else
                        <span class="mr-2">New</span>
                        <span x-text="type ? type.toLowerCase() : 'water source'">water source</span>
                    @endif
                </span>
            </span>
        </div>
    </div>

    <div class="mt-4">
        <div class="">
            <x-chip-radio name="💧 Spring" key="type" value="Spring" />
            <x-chip-radio name="🪣 Water well" key="type" value="Water well" />
            <x-chip-radio name="🚰 Water tap" key="type" value="Water tap" />
            <x-chip-radio name="🐳 Other" key="type" value="Water source" />
            @error('type')
                <div class="text-red-600 text-sm mb-4">{{ $message }}</div>
            @enderror
        </div>

        <div class="mt-2 max-w-xl w-full h-80 rounded-md overflow-hidden relative">
            <div class="absolute w-full h-full" wire:ignore
                id="openPicker">
            </div>
            <div class="absolute w-full h-full flex items-center justify-center" style="pointer-events: none;">
                <div class="text-red-600 text-4xl font-light">○</div>
            </div>
            <!-- COPIED -->
                <div x-cloak class="absolute sm:block top-2 right-2" style="z-index: 10000;">
                    <div @click="window.rodnikPicker.locateMe()" class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" class="h-5 w-5">
                            <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
                        </svg>
                    </div>
                </div>
            <!-- END OF COPIED -->
        </div>
        <div class="max-w-lg">
            <div class="mt-2 relative border border-gray-300 rounded-md bg-white px-3 py-2 focus-within:z-10 focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600"
                x-bind:class="{
                    'border-red-600': coordinatesError,
                }"
            >
                <label for="coordinates" class="block text-sm font-light text-gray-600 mb-1">
                    Latitude, longitude
                    <svg x-cloak x-show="! coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-green-600">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    <svg x-cloak x-show="coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-red-600">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </label>
                <input
                    x-ref="coordinates"
                    x-model="coordinates"
                    @change="updateCoordinates($event.target.value)"
                    {{--wire:model.defer="coordinates"--}}
                    type="text"
                    name="coordinates"
                    id="coordinates"
                    class="block w-full border-0 p-0 text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-sm">
            </div>

            <div class="mt-2 border border-gray-300 rounded-md bg-white px-3 py-2 focus-within:z-10 focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600">
                <label for="name" class="block text-sm font-light text-gray-600 mb-1">Water source name (if any)</label>
                <input wire:model.defer="name" type="text" name="name" id="name" class="block w-full border-0 p-0 text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-sm" placeholder="">
            </div>
        </div>
    </div>

    <div class="mt-4 pb-6">
        <div class="flex justify-start">
            <button type="button" @click="if (! error()) {$wire.store();}" class="inline-flex items-center px-12 py-3 border border-transparent text-base font-medium rounded-md"
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
                {{ $spring->id ? 'Save changes' : 'Add water source' }}
            </button>
        </div>
    </div>
</div>
