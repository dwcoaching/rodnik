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
            handleFileDrop: function (event) {
                if (event.dataTransfer.files.length > 0) {
                    for (let i = 0; i < event.dataTransfer.files.length; i++) {
                        let file = event.dataTransfer.files.item(i);
                        let id = window.uuid.v1();

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
    <div>
        @foreach ($photos as $photo)
            <div class="bg-white m-4 rounded-lg shadow-xl overflow-hidden flex items-top"
                x-data="{
                    coordinates: [{{ $photo->latitude }}, {{ $photo->longitude }}],
                }"
            >
                <div class="w-1/3" style="
                    background-image: url('{{ $photo->url }}');
                    background-size: cover;
                    background-repeat: no-repeat;
                    background-position: center;
                ">
                </div>
                <div class="w-1/3" wire:ignore>
                    <div class="w-full"
                        style="padding-bottom: 75%"
                        x-init="initUploadMap($el, coordinates)"
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
