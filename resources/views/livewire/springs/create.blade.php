<div>
        <div
        x-data="{
            dragover: false,
            handleFileDrop: function (event) {
                if (event.dataTransfer.files.length > 0) {
                    for (let i = 0; i < event.dataTransfer.files.length; i++) {
                        let file = event.dataTransfer.files.item(i);
                        @this.upload('file', file,
                            (uploadedFilename) => {}, {{-- success callback --}}
                            () => {}, {{-- error callback --}}
                            (event) => {} {{-- progress callback --}}
                        );
                    }
                }
            }
        }"
        class="bg-white m-4 rounded-lg shadow-xl overflow-hidden border-dashed border border-4 border-purple-700"
        x-bind:class="{
            'bg-purple-100': dragover
        }"
    >
        <div
            x-on:drop="dragover = false"
            x-on:drop.prevent="
                handleFileDrop($event)
            "
            x-on:dragover.prevent="dragover = true"
            x-on:dragleave.prevent="dragover = false"
            class="text-center py-16"
        >
            <div class="text-2xl font-semibold">
                Перетащите сюда фото родника
            </div>
            <div class="mt-4">
                GPS-координаты определятся сами
            </div>
            @error('files.*')
                <div class="mt-4 text-red-600">
                    Загружать можно фотографии размером до 10 мегабайт
                </div>
            @enderror
        </div>
    </div>
    <div>
        @foreach ($photos as $photo)
            <div class="bg-white m-4 rounded-lg shadow-xl overflow-hidden flex items-top">
                <div class="w-1/3">
                    <img src="{{ $photo->url }}" />
                </div>
                <div class="w-1/3">
                    <div class="w-full h-full"
                        id="leaflet-map-{{ $photo->id }}"
                        x-data="{
                            coordinates: [{{ $photo->latitude }}, {{ $photo->longitude }}],
                        }"
                        x-init="initUploadMap('leaflet-map-{{ $photo->id }}', coordinates)"
                    >
                    </div>
                </div>
                <div class="w-1/3 p-4">
                    {{ $photo->latitude }}, {{ $photo->longitude }}
                </div>
            </div>
        @endforeach
    </div>
</div>
