<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6"
    x-data="{
        visited_at: @entangle('visited_at').defer,
        state: @entangle('report.state').defer,
        quality: @entangle('report.quality').defer,

        withDate: true,
        previousDate: null,
        toggleDate: function() {
            if (this.withDate) {
                this.previousDate = this.visited_at;
                this.withDate = false;
                this.visited_at = null;
            } else {
                this.visited_at = this.previousDate;
                this.withDate = true;
            }
        }
    }">

    <a href="{{ route('springs.show', $spring) }}" class="block text-3xl font-bold text-blue-600 hover:text-blue-700"">
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
            <a href="{{ route('springs.show', $spring) }}" class="block text-3xl font-bold text-blue-600 hover:text-blue-700"">
                <span class="mr-2 inline-flex items-center">
                    {{--<svg xmlns="http://www.w3.org/2000/svg" class="mr-2" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
                    </svg>--}}
                    {{ $spring->name ? $spring->name : $spring->type }}
                </span>
                <span class="text-gray-600 text-2xl font-thin">#{{ $spring->id }}</span>
            </a>
            <div class="text-gray-600 mt-2 text-sm flex flex-wrap items-center">
                @if ($spring->name)
                    <div class="text-sm mr-3 mb-2">
                        {{ $spring->type }}
                    </div>
                @endif
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mb-2 mr-1 block w-5 h-5">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-1.5 0a6.5 6.5 0 11-11-4.69v.447a3.5 3.5 0 001.025 2.475L8.293 10 8 10.293a1 1 0 000 1.414l1.06 1.06a1.5 1.5 0 01.44 1.061v.363a1 1 0 00.553.894l.276.139a1 1 0 001.342-.448l1.454-2.908a1.5 1.5 0 00-.281-1.731l-.772-.772a1 1 0 00-1.023-.242l-.384.128a.5.5 0 01-.606-.25l-.296-.592a.481.481 0 01.646-.646l.262.131a1 1 0 00.447.106h.188a1 1 0 00.949-1.316l-.068-.204a.5.5 0 01.149-.538l1.44-1.234A6.492 6.492 0 0116.5 10z" clip-rule="evenodd" />
                </svg>
                <span class="mr-3 mb-2">
                    {{ $spring->latitude }}, {{ $spring->longitude }}
                </span>
            </div>
        </div>
    </div>

    <div class="relative mt-2 max-w-xs bg-white border border-gray-300 rounded-md px-3 py-2 shadow-sm focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600">
        <label for="date" class="block text-sm font-light text-gray-600 flex justify-between items-center">
            <span class="mr-3">
                Visit Date
            </span>
            <span @click="toggleDate" class="cursor-pointer text-blue-600 text-xs"
                :class="{
                    'font-bold': ! withDate
                }"
            >
                Do Not Specify
            </span>
        </label>
        <input x-show="withDate" x-model="visited_at" type="date" name="date" id="date" class="mt-1 block w-full border-0 p-0 text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-sm" placeholder="">
    </div>
{{--
    <div class="mt-4">
      <label for="visited_at" class="block text-sm font-regular text-gray-700">Дата посещения</label>
      <div class="mt-1">
        <input wire:model.defer="report.visited_at" type="date" name="visited_at" id="visited_at" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-xl max-w-xs" />
      </div>
    </div>
--}}
    <div class="mt-4">
        <div>
            <div class="mb-2">
                <x-chip-radio name="💧 Watered" key="state" value="running" />
                <x-chip-radio name="🌵 Dry" key="state" value="dry" />
                <x-chip-radio name="😡 Water source not found" key="state" value="notfound" />
            </div>
            <div x-show="state !== 'dry' && state !== 'notfound'">
                <x-chip-radio name="🚰 Good water" key="quality" value="good" />
                <x-chip-radio name="🚱 Poor water" key="quality" value="bad" />
            </div>
        </div>
    </div>

    <div class="mt-2">
        <div class="relative">
            <textarea wire:model.defer="report.comment" rows="4" name="comment" id="comment"
                placeholder="Comment"
                @class([
                    'max-w-lg' => true,
                    'shadow-sm' => true,
                    'block' => true,
                    'w-full' => true,
                    'sm:text-sm' => true,
                    'rounded-md' => true,
                    'border-gray-300' => ! $errors->has('report.comment'),
                    'focus:ring-blue-500' => ! $errors->has('report.comment'),
                    'focus:border-blue-500' => ! $errors->has('report.comment'),
                    'border-red-300' => $errors->has('report.comment'),
                    'text-red-900' => $errors->has('report.comment'),
                    'focus:ring-red-500' => $errors->has('report.comment'),
                    'focus:border-red-500' => $errors->has('report.comment'),
                ])
            ></textarea>
            @error('report.comment')
              <div class="absolute top-0 pt-3 right-0 pr-3 flex items-center pointer-events-none">
                <!-- Heroicon name: solid/exclamation-circle -->
                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
            @enderror
        </div>
        @error('report.comment') <p class="mt-2 text-sm text-red-600" id="email-error">{{ $message }}</p> @enderror
    </div>

    @if ($photos->count())
        <ul
            x-data
            x-init="window.initPhotoSwipe('#photos');"
            id="photos"
            role="list" class="max-w-3xl mt-4 mb-4 grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($photos as $photo)
                <li class="relative group">
                    <a href="{{ $photo->url }}"
                        data-pswp-width="{{ $photo->width }}"
                        data-pswp-height="{{ $photo->height }}"
                        data-cropped="true"
                        target="blank"
                        style="padding-bottom: 100%;"
                        class="photoswipeImage block w-full h-0 rounded-lg bg-gray-100 overflow-hidden">
                        <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full z-10">
                    </a>
                    <div wire:click.stop="removePhoto({{ $photo->id }}); event.preventDefault();" class="opacity-70 hover:opacity-100 cursor-pointer absolute right-0 top-0 py-1 px-2 z-20 text-white font-semibold text-2xl"
                        style="text-shadow: 0px 0px 2px #000;">×</div>
                </li>
            @endforeach
        </ul>
    @endif

    <div wire:key="reports.create.upload" class="sm:col-span-6 mt-2 max-w-3xl"
        x-data="{
            dragover: false,
            filesInProgress: [],
            addFileInProgress: function(data) {
                this.filesInProgress.push(data)
            },
            removeFileInProgress: function(id) {
                $nextTick(() => {
                    const key = this.filesInProgress.findIndex(item => item.id !== id)
                    this.filesInProgress.splice(key, 1)
                })
            },
            updateFileInProgress: function(id, event) {
                this.filesInProgress.forEach(item => {
                    if (item.id === id) {
                        item.progress = event.detail.progress;
                    }
                });
            },
            uploadFile: function (file) {
                let id = window.uuidv1();

                window.ImageBlobReduce.toBlob(file, {max: 1280})
                    .then(newFile => {
                        this.addFileInProgress({
                            id: id,
                            name: file.name,
                            oldSize: file.size,
                            newSize: newFile.size,
                            progress: 0,
                        })

                        @this.upload('file', newFile,
                            (uploadedFilename) => {this.removeFileInProgress(id)}, {{-- success callback --}}
                            () => {this.removeFileInProgress(id)}, {{-- error callback --}}
                            (event) => {this.updateFileInProgress(id, event)} {{-- progress callback --}}
                        )
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
    >
        <label for="file-upload" class="cursor-pointer group">
            <div class="mt-4 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl"
                x-bind:class="{
                    'bg-blue-100': dragover
                }"
                x-on:drop="dragover = false"
                x-on:drop.prevent="
                    handleFileDrop($event)
                "
                x-on:dragover.prevent="dragover = true"
                x-on:dragleave.prevent="dragover = false"
            >
                <div class="text-center">
                    <div x-show="filesInProgress.length" class="mt-6">
                        <template x-for="file in filesInProgress">
                            <div class="mt-2 mb-2">
                                <b>File <span x-text="file.name"></span> uploading</b><br>
                                Original size <span x-text="file.oldSize"></span> B<br>
                                Resized size <span x-text="file.newSize"></span> B<br>
                                <span x-text="file.progress"></span>% uploaded
                            </div>
                        </template>
                    </div>
                    <div class="h-12 mb-1 flex items-center" x-show="false && filesInProgress.length > 0">
                        <svg class="mx-auto flex animate-spin h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-100" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div x-show="filesInProgress.length == 0" class="h-12 mb-1">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="text-sm text-gray-600">
                        <label class="relative rounded-md font-regular text-blue-600 group-hover:text-blue-700">
                            <span class="font-bold">Choose a photo</span>
                            <input x-on:change="handleFileSelect($event)" multiple id="file-upload" name="file-upload" type="file" class="sr-only">
                        </label>
                        <p class="inline pl-1">or drag and drop here</p>
                    </div>
                    <p class="text-xs text-gray-500">PNG, JPG, GIF (10 MB max)</p>

                </div>
            </div>
        </label>
    </div>

{{--
    <div class="mt-2 overflow-x-scroll">
        <x-chip-checkbox name="Стоячая вода" key="stale" />
        <x-chip-checkbox name="Очень слабый поток" key="dripping" />
        <x-chip-checkbox name="Требуется фильтрация или кипячение" key="drinkingwaterconditional" />
        <x-chip-checkbox name="Источник заброшен" key="abandoned" />
        <x-chip-checkbox name="Табличка «Питьевая вода»" key="drinkingwaterlegal" />
        <x-chip-checkbox name="Табличка «Вода не для питья»" key="drinkingwaterlegalno" />
    </div>
--}}
{{--
    <div class="mt-6 block text-sm font-regular text-gray-700">Источник</div>
    <div class="mt-2 overflow-x-scroll">
        <x-chip-checkbox name="Стоячая вода" key="stale" />
        <x-chip-checkbox name="Очень слабый поток" key="dripping" />
        <x-chip-checkbox name="Источник заброшен" key="abandoned" />
        <x-chip-checkbox name="Источник не обнаружен" key="notfound" />
    </div>

    <div class="mt-6 block text-sm font-regular text-gray-700">Вода</div>
    <div class="mt-2 overflow-x-scroll">
        <x-chip-checkbox name="Питьевая вода" key="drinkingwater" />
        <x-chip-checkbox name="Требуется фильтрация или кипячение" key="drinkingwaterconditional" />
        <x-chip-checkbox name="Не пригодна для питья" key="drinkingwaterno" />

        <x-chip-checkbox name="Табличка «Питьевая вода»" key="drinkingwaterlegal" />
        <x-chip-checkbox name="Табличка «Вода не для питья»" key="drinkingwaterlegalno" />
    </div>
--}}

{{--
    <label for="" class="mt-6 block text-sm font-medium text-gray-700"></label>
    <div class="">
        <x-chip-checkbox name="Доступ на коляске" key="wheelchair" />
        <x-chip-checkbox name="Удобно набрать в бутылку" key="bottle" />
        <x-chip-checkbox name="Миска для животных" key="dog" />
    </div>
--}}

    <div class="mt-4 pt-5 pb-6">
        <div class="flex justify-start">
            <button type="button" class="cursor-pointer inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-sm text-white bg-blue-600  focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                x-data="{
                    storing: false,
                    buttonText: '{{ $report->id ? 'Save Changes' : 'Add Report' }}',
                    storingText: '{{ $report->id ? 'Saving...' : 'Adding...' }}',
                    text: function() {
                        return this.storing ? this.storingText : this.buttonText;
                    },
                    store: async function() {
                        if (this.storing) {
                            return;
                        }

                        this.storing = true;
                        const result = await $wire.store();
                        // this.storing = false;
                    }
                }"
                x-bind:attr="{
                    'disabled': storing
                }"
                x-bind:class="{
                    'bg-blue-600': ! storing,
                    'hover:bg-blue-700': ! storing,
                    'bg-gray-600': storing,
                }"
                @click="store"
                x-text="text()"
                >

            </button>
        </div>
    </div>
</div>
