<div class="mx-auto max-w-lg">
  <form wire:submit.prevent="store">
    <div class="lg:flex lg:items-center lg:justify-between">
      <div class="flex-1 min-w-0">
        <nav class="flex" aria-label="Breadcrumb">
          <ol role="list" class="flex items-center space-x-4">
            <li>
              <div class="flex">
                <a href="#" class="text-sm font-medium text-gray-500 hover:text-gray-700">{{ $spring->name }}</a>
              </div>
            </li>
          </ol>
        </nav>
        <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Добавить отзыв</h2>
        <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
          <div class="mt-2 flex items-center text-sm text-gray-500">
            Все поля необязательные
          </div>
        </div>
      </div>
      <div class="mt-5 flex lg:mt-0 lg:ml-4">
      </div>
    </div>

    <div class="mt-8">
      <label for="visited_at" class="block text-sm font-medium text-gray-700">Дата посещения</label>
      <div class="mt-1">
        <input wire:model.defer="report.visited_at" type="date" name="visited_at" id="visited_at" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md max-w-xs" />
      </div>
    </div>
    <div class="mt-6">
      <label class="block text-sm font-medium text-gray-700">Вода есть?</label>
      <fieldset class="mt-1">
        <div class="space-y-2">
          <div class="flex items-center">
            <input wire:model="report.state" id="dry" name="state" value="dry" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="dry" class="ml-3 block text-sm font-regular text-gray-700"> Воды нет </label>
          </div>

          <div class="flex items-center">
            <input wire:model="report.state" id="dripping" name="state" value="dripping" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="dripping" class="ml-3 block text-sm font-regular text-gray-700"> Есть, но мало </label>
          </div>

          <div class="flex items-center">
            <input wire:model="report.state" id="running" name="state" value="running" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="running" class="ml-3 block text-sm font-regular text-gray-700"> Вода есть </label>
          </div>
        </div>
      </fieldset>
    </div>
    <div class="mt-6">
      <label class="block text-sm font-medium text-gray-700">Субъективная оценка качества воды</label>
      <fieldset class="mt-1">
        <div class="space-y-2">
          <div class="flex items-center">
            <input wire:model="report.quality" id="bad" name="quality" value="bad" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="bad" class="ml-3 block text-sm font-regular text-gray-700"> Вода плохая </label>
          </div>

          <div class="flex items-center">
            <input wire:model="report.quality" id="uncertain" name="quality" value="uncertain" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="uncertain" class="ml-3 block text-sm font-regular text-gray-700"> Сложно сказать, не понятно </label>
          </div>

          <div class="flex items-center">
            <input wire:model="report.quality" id="good" name="quality" value="good" type="radio" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
            <label for="good" class="ml-3 block text-sm font-regular text-gray-700"> Вода отличная </label>
          </div>
        </div>
      </fieldset>
    </div>

    <div class="mt-6">
      <label for="comment" class="block text-sm font-medium text-gray-700">Комментарий</label>
      <div class="mt-1 relative">
        <textarea wire:model.defer="report.comment" rows="4" name="comment" id="comment"
          @class([
            'shadow-sm',
            'block',
            'w-full',
            'sm:text-sm',
            'rounded-md',
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

    <div class="sm:col-span-6 mt-6"
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
                let id = window.uuidv1();

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
    >
        <label for="cover-photo" class="block text-sm font-medium text-gray-700"> Фото </label>
        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"
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
            <div class="space-y-1 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="flex text-sm text-gray-600">
                    <label for="file-upload" class="relative cursor-pointer  rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                        <span>Выберите фото</span>
                        <input x-on:change="handleFileSelect($event)" multiple id="file-upload" name="file-upload" type="file" class="sr-only">
                    </label>
                    <p class="pl-1">или перетащите сюда</p>
                </div>
                <p class="text-xs text-gray-500">PNG, JPG, GIF не более 10 Мб</p>
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
    </div>

    <ul role="list" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
        @foreach ($photos as $photo)
            <li class="">
                <div style="padding-bottom: 100%;" class="relative group block w-full h-0 rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-gray-100 focus-within:ring-indigo-500 overflow-hidden">
                    <img style="" src="{{ $photo->url }}" alt="" class="object-cover absolute h-full w-full">
                </div>
            </li>
        @endforeach
    </ul>

    <div class="pt-5">
        <div class="flex space-x-3 items-center">
            @auth
                <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
            @endauth
            @guest
                <img class="h-8 w-8 rounded-full" src="https://images.unsplash.com/photo-1517841905240-472988babdf9?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=3&w=256&h=256&q=80" alt="">
            @endguest
            <div class="text-sm font-regular mr-3">
                @auth
                    {{ Auth::user()->name }}
                @endauth
                @guest
                    Анонимно
                @endguest
            </div>
            @guest
                <div class="text-sm text-gray-600">
                    можете
                    <a href="{{ route('login') }}"
                    class="text-blue-600 hover:text-blue-900">
                        войти
                    </a>
                    или
                    <a href="{{ route('register') }}"
                        class="text-blue-600 hover:text-blue-900">
                        зарегистрироваться
                    </a>
                </div>
            @endguest
        </div>
    </div>
    <div class="pt-5">
        <div class="flex justify-start">
          <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Добавить отзыв</button>
        </div>
    </div>
  </form>
</div>
