<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6"
    x-data="{
            type: $wire.$entangle('type'),
            latitude: $wire.$entangle('latitude'),
            longitude: $wire.$entangle('longitude'),
            coordinates: $wire.$entangle('coordinates'),
            coordinatesError: false,
            mapLocked: true,
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
                @if ($springId)
                    {{ $longitude }}, {{ $latitude }}
                @endif
            ]
        );"
    >
    <a href="{{
        $springId ? route('springs.show', $springId) : '/'
    }}" class="text-3xl font-bold text-blue-600 hover:text-blue-700"">
        <span class="mr-2 inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 mb-6" width="36" height="36" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
            </svg>
        </span>
    </a>

    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <span class="block text-3xl font-extrabold">
                <span class="mr-2 inline-flex items-center">
                    @if ($springId)
                        <div>
                            Edit water source
                            <div class="text-sm mt-1 font-normal">
                                All changes will be applied immediately.
                                Please double-check before changing anything 😇
                            </div>
                        </div>
                    @else
                        New water source
                    @endif
                </span>
            </span>
        </div>
    </div>

    <div class="mt-4">
        <div class="w-full max-w-xs relative">
            <select name="type" class="h-[60px] font-bold w-full pl-3 pt-[30px] text-base select select-primary" x-model="type">
                <option value="" x-bind:disabled="true">Choose water source type</option>
                @foreach ($waterSourceTypes as $waterSourceType)
                    <option value="{{ $waterSourceType }}">
                        @if ($waterSourceType == 'Water source')
                            Other
                        @elseif ($waterSourceType == 'Fountain')
                            Decorative fountain
                        @else
                            {{ $waterSourceType }}
                        @endif
                    </option>
                @endforeach
            </select>
            <label class="pointer-events-none absolute top-2 left-3 text-sm font-medium text-gray-500">
                <span class="">Type</span>
            </label>

            @error('type')
                {{--<div class="text-red-600 text-sm mb-4"></div>--}}
                <label class="label">
                    <span class="label-text-alt">{{ $message }}</span>
                    <span class="label-text-alt">Bottom Right label</span>
                </label>
            @enderror
        </div>
        <div class="w-full sm:max-w-xl">
            <div class="mt-2 w-full relative">
                <input wire:model="name" type="text" name="name" id="name"
                    class="h-[60px] font-bold w-full pl-3 pt-[32px] text-base input input-primary" placeholder="">
                <label class="pointer-events-none absolute top-2 left-3 text-sm font-medium text-gray-500">
                    <span class="">Water source name (if any)</span>
                </label>
            </div>

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
                        {{--
                            <svg x-cloak x-show="! coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-green-600">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        --}}
                        <svg x-cloak x-show="coordinatesError" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="inline w-4 h-4 text-red-600">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    </label>
                </div>
                <div class="flex items-center px-4">
                    <svg x-show="mapLocked" @click="mapLocked = false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-blue-600 cursor-pointer">
                        <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                    </svg>
                    <svg x-cloak x-show="! mapLocked" @click="mapLocked = true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-green-600 cursor-pointer">
                        <path d="M18 1.5c2.9 0 5.25 2.35 5.25 5.25v3.75a.75.75 0 01-1.5 0V6.75a3.75 3.75 0 10-7.5 0v3a3 3 0 013 3v6.75a3 3 0 01-3 3H3.75a3 3 0 01-3-3v-6.75a3 3 0 013-3h9v-3c0-2.9 2.35-5.25 5.25-5.25z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="mt-2 sm:max-w-xl w-full h-80 rounded-md overflow-hidden relative"
        >
            <div x-show="mapLocked" class="z-10 absolute w-full h-full bg-gray-100 opacity-50">

            </div>
            <div class="absolute w-full h-full" wire:ignore
                id="openPicker">
            </div>
            <div class="absolute w-full h-full flex items-center justify-center" style="pointer-events: none;">
                <div class="text-red-600 text-4xl font-light">○</div>
            </div>
            <!-- COPIED -->
                <div x-cloak class="absolute sm:block top-2 right-2 z-5">
                    <div @click="window.rodnikPicker.locateMe()" class="mt-2 h-9 w-9 bg-white shadow-sm rounded-md cursor-pointer flex items-center justify-center text-black hover:text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" class="h-5 w-5">
                            <path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/><
                        </svg>
                    </div>
                </div>
            <!-- END OF COPIED -->
        </div>

    </div>

    <div class="mt-4 pb-6">
        <div class="flex justify-start">
            <button type="button"
                @click="if (! error()) {
                    $wire.$call('store')
                }" class="inline-flex w-full sm:w-fit justify-center items-center px-12 py-3 border border-transparent text-base font-medium rounded-md"
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
