<div>
    <div
        x-data="{
            dragover: false,
            filesInProgress: [],
            addFileInProgress: function(data) {
                this.filesInProgress = [...this.filesInProgress, data];
            },
            removeFileInProgress: function(id) {
                this.filesInProgress = this.filesInProgress.filter(item => item.id !== id);
            },
            updateFileInProgress: function(id, event) {
                this.filesInProgress.forEach(item => {
                    if (item.id === id) {
                        item.progress = event.detail.progress;
                    }
                });
            },
            uploadFile: function (file) {
                let id = uuidv1();

                window.ImageBlobReduce.toBlob(file, {max: 1280})
                    .then(newFile => {
                        this.addFileInProgress({
                            id: id,
                            name: file.name,
                            oldSize: file.size,
                            newSize: newFile.size,
                            progress: 0,
                        });

                        @this.upload('file', newFile,
                            (uploadedFilename) => {this.removeFileInProgress(id)}, {{-- success callback --}}
                            () => {this.removeFileInProgress(id)}, {{-- error callback --}}
                            (event) => {this.updateFileInProgress(id, event)} {{-- progress callback --}}
                        );
                    })
            },
            handleFileDrop: function (event) {
                if (event.dataTransfer.files.length > 0) {
                    for (let i = 0; i < event.dataTransfer.files.length; i++) {
                        this.uploadFile(event.dataTransfer.files.item(i));
                    }
                }
            },
            handleFileSelect: function (event) {
                if (event.target.files.length > 0) {
                    for (let i = 0; i < event.target.files.length; i++) {
                        this.uploadFile(event.target.files.item(i));
                    }
                }

                event.target.value = '';
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
            <div class="mt-5 text-center">
                <div class="text-sm">
                    Или выберите файл на диске:
                </div>
                <div>
                    <input
                        x-on:change="handleFileSelect($event)"
                    type="file" multiple placeholder="file" class="bg-gray-100 px-4 py-2 rounded-lg">
                </div>
            </div>
            <div class="mt-8 text-lg">
                GPS-координаты определятся сами
            </div>
            @error('files.*')
                <div class="mt-4 text-red-600">
                    Загружать можно фотографии размером до 10 мегабайт
                </div>
            @enderror
            <div x-show="filesInProgress.length" class="mt-6">
                <template x-for="file in filesInProgress">
                    <div class="mt-2 mb-2">
                        <b>Файл <span x-text="file.name"></span> загружается</b><br>
                        исходный размер <span x-text="file.oldSize"></span> байт<br>
                        загружаемый размер <span x-text="file.newSize"></span> байт<br>
                        загружено <span x-text="file.progress"></span>%
                    </div>
                </template>
            </div>
        </div>
    </div>
    <div class="pb-12"
        x-data
        id="photos"
        x-init="window.initPhotoSwipe('#photos');"
    >
        @foreach ($photos as $photo)
            <div class="bg-white m-4 rounded-lg shadow-xl overflow-hidden md:flex md:items-top"
                wire:key="photo-{{ $photo->id }}"
                x-data="{
                    coordinates: [{{ $photo->longitude }}, {{ $photo->latitude }}],
                }"
            >
                <div class="w-full md:w-1/3 relative group" style="">
                    <a href="{{ $photo->url }}"
                        data-pswp-width="{{ $photo->width }}"
                        data-pswp-height="{{ $photo->height }}"
                        data-cropped="true"
                        target="blank"
                        class="photoswipeImage block w-full h-0 rounded-lg bg-gray-100 overflow-hidden">
                        <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full z-10">
                    </a>
                </div>
                <div class="w-full md:w-1/3" wire:ignore>
                    <div class="w-full relative"
                        style="padding-bottom: 75%;"

                    >
                        <div class="absolute h-full w-full"
                            x-init="initOpenHelper($el, coordinates);">

                        </div>
                    </div>
                </div>
                <div class="w-full md:w-1/3 p-4">
                    @if ($photo->latitude && $photo->longitude)
                        {{ $photo->latitude }}, {{ $photo->longitude }}
                    @else
                        Фотография не содержит координат
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
