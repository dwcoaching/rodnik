<div class="w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6"
    x-data="reportCreateForm({
        wire: $wire,
        submitReport: () => $wire.store(),
        reportId: @js($reportId),
        uploadUrl: @js(route('photos.uploads.store')),
        csrfToken: @js(csrf_token()),
        initialPhotos: @js($photos->map(fn ($photo) => [
            'id' => $photo->id,
            'url' => $photo->url,
            'width' => $photo->width,
            'height' => $photo->height,
            'order' => $photo->order,
        ])->values()->all()),
    })"
    >

    @guest
        <div class="bg-yellow-100 p-4 rounded-lg border border-yellow-400 mb-6  max-w-3xl">
            <div class="font-bold max-w-prose">
                <span class="text-2xl">💧</span> You can publish reports anonymously
            </div>
            <div class="mt-2 max-w-prose">
                However, we encourage you to register and log in.
            </div>
            <div class="mt-2 max-w-prose">
                The main reason we ask
                you to register is to establish a reputation
                for each piece of knowledge. Nobody knows whether to trust an anonymous
                reporter on the web.
            </div>
            <div class="mt-2 max-w-prose">
                By having a history of your reports, we will know that the information
                from you is reliable, and we will make necessary changes to the
                OpenStreetMap database.
            </div>
            <div class="mt-2 max-w-prose">
                Besides, you will have your personal page and a collection
                of reports, and you'll be able to update or delete your reports!
            </div>
            <div class="mt-4 max-w-prose">
                <a href="{{ route('login') }}" type="button" class="mr-2 inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Login</a>
                <a href="{{ route('register') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Register</a>
            </div>
        </div>
    @endguest
    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <a href="{{ duo_route(['spring' => $spring->id]) }}" class="block text-base font-semibold text-blue-600 hover:text-blue-700"">
                <span class="mr-2 inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-2" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
                    </svg>
                </span>
            </a>
        </div>
    </div>
    <div class="font-black mt-2 text-lg">
        New Report
    </div>
    <div class="relative mt-2 max-w-xs bg-white border border-gray-300 rounded-md px-3 py-2 shadow-sm focus-within:ring-1 focus-within:ring-blue-600 focus-within:border-blue-600">
        <label for="date" class="block text-sm font-bold text-gray-500 flex justify-between items-center">
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
      <label for="visited_at" class="block text-sm font-bold text-gray-500">Visit date</label>
      <div class="mt-1">
        <input wire:model="visited_at" type="date" name="visited_at" id="visited_at" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-xl max-w-xs" />
      </div>
    </div>
--}}

    <div class="mt-4">
        <div>
            <div class="mb-2">
                <div class="text-sm font-bold text-gray-500 mt-4 mb-2">Condition</div>
                <x-chip-radio name="💧 There is water" key="state" value="running" />
                <x-chip-radio name="🌵 No water" key="state" value="dry" />
                <x-chip-radio name="😡 Water source not found" key="state" value="notfound" />
            </div>
            <div x-show="state !== 'dry' && state !== 'notfound'">
                <div class="text-sm font-bold text-gray-500 mt-2 mb-2">Water quality</div>
                <x-chip-radio name="🚰 Good Water" key="quality" value="good" />
                <x-chip-radio name="🚱 Poor Water" key="quality" value="bad" />
            </div>
        </div>
    </div>

    {{--
    <div class="mt-4">
        <div>
            <div class="mb-2">
                <div class="text-sm font-bold text-gray-500 mt-4 mb-2">Condition</div>
                <x-chip-radio name="💧 There is water" key="state" value="running" />
                <x-chip-radio name="🌵 Little water" key="state" value="dry" />
                <x-chip-radio name="🚫 No water" key="state" value="dry" />
            </div>
            <div x-show="state !== 'dry' && state !== 'notfound'">
                <div class="text-sm font-bold text-gray-500 mt-2 mb-2">Water quality</div>
                <x-chip-radio name="🚰 Good Water" key="quality" value="good" />
                <x-chip-radio name="🚱 Poor Water" key="quality" value="bad" />
            </div>
        </div>
    </div>
    --}}

    <div class="mt-2">
        <div class="relative">
            <textarea wire:model="comment" rows="4" name="comment" id="comment"
                placeholder="Comment"
                @class([
                    'w-full' => true,
                    'sm:max-w-lg' => true,
                    'shadow-sm' => true,
                    'block' => true,
                    'w-full' => true,
                    'sm:text-sm' => true,
                    'rounded-md' => true,
                    'border-gray-300' => ! $errors->has('comment'),
                    'focus:ring-blue-500' => ! $errors->has('comment'),
                    'focus:border-blue-500' => ! $errors->has('comment'),
                    'border-red-300' => $errors->has('comment'),
                    'text-red-900' => $errors->has('comment'),
                    'focus:ring-red-500' => $errors->has('comment'),
                    'focus:border-red-500' => $errors->has('comment'),
                ])
            ></textarea>
            @error('comment')
              <div class="absolute top-0 pt-3 right-0 pr-3 flex items-center pointer-events-none">
                <!-- Heroicon name: solid/exclamation-circle -->
                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
            @enderror
        </div>
        @error('comment') <p class="mt-2 text-sm text-red-600" id="email-error">{{ $message }}</p> @enderror
    </div>

    <ul
        x-cloak
        x-show="photoItems.length"
        x-sort="sortPhotos($item, $position)"
        x-sort:config="{ handle: '.photo-sort-handle' }"
        x-init="window.initPhotoSwipe('#photos');"
        id="photos"
        role="list"
        class="max-w-3xl mt-4 mb-4 grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
        <template x-for="item in photoItems" :key="item.key">
            <li class="relative group" x-sort:item="item.key">
                <div class="photo-sort-card relative w-full aspect-square">
                    <a
                        x-show="item.status === 'uploaded'"
                        x-bind:href="item.url"
                        x-bind:data-pswp-width="item.width"
                        x-bind:data-pswp-height="item.height"
                        data-cropped="true"
                        draggable="false"
                        target="blank"
                        class="photoswipeImage relative block w-full aspect-square rounded-lg bg-gray-100 overflow-hidden">
                        <img x-bind:src="item.url" alt="" draggable="false" class="object-cover absolute h-full w-full z-10">
                    </a>
                    <div
                        x-show="item.status !== 'uploaded'"
                        class="relative block w-full aspect-square rounded-lg bg-gray-100 overflow-hidden">
                        <img x-bind:src="item.url" alt="" draggable="false" class="object-cover absolute h-full w-full z-10"
                            x-bind:class="{
                                'grayscale opacity-50': isPending(item),
                                'opacity-80': item.status === 'failed'
                            }">
                    </div>
                    <div x-show="isPending(item)" class="absolute inset-0 z-20 flex flex-col items-center justify-center rounded-lg bg-black/20 text-white">
                        <div class="animate-spin w-8 h-8 flex border-4 rounded-full border-white/80 border-t-transparent"></div>
                        <div class="mt-2 rounded bg-black/30 px-2 py-1 text-xs font-bold" x-text="item.status === 'uploading' ? `${item.progress}%` : 'Preparing'"></div>
                    </div>
                    <div x-show="item.status === 'failed'" class="absolute inset-x-2 bottom-2 z-20 rounded bg-red-600/90 px-2 py-1 text-xs font-bold text-white">
                        <div x-text="item.error"></div>
                        <button type="button" class="mt-1 underline" x-on:click.stop.prevent="retryPhoto(item)">Retry</button>
                    </div>
                    <button type="button" x-sort:handle title="Move photo" class="photo-sort-handle rounded-md bg-black/20 cursor-move opacity-100 absolute left-0 top-0 py-2 px-2 z-30 text-white font-semibold text-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#fff" viewBox="0 0 256 256"><path d="M87.51,64.49a12,12,0,0,1,0-17l32-32a12,12,0,0,1,17,0l32,32a12,12,0,0,1-17,17L140,53V96a12,12,0,0,1-24,0V53L104.49,64.49A12,12,0,0,1,87.51,64.49Zm64,127L140,203V160a12,12,0,0,0-24,0v43l-11.51-11.52a12,12,0,0,0-17,17l32,32a12,12,0,0,0,17,0l32-32a12,12,0,0,0-17-17Zm89-72-32-32a12,12,0,0,0-17,17L203,116H160a12,12,0,0,0,0,24h43l-11.52,11.51a12,12,0,0,0,17,17l32-32A12,12,0,0,0,240.49,119.51ZM53,140H96a12,12,0,0,0,0-24H53l11.52-11.51a12,12,0,1,0-17-17l-32,32a12,12,0,0,0,0,17l32,32a12,12,0,1,0,17-17Z"></path></svg>
                    </button>
                    <button type="button" title="Remove photo" x-on:click.stop.prevent="removePhotoItem(item)" class="removePhotoHandle rounded-md bg-black/20 opacity-100 cursor-pointer absolute right-0 top-0 py-2 px-2 z-30 text-white font-semibold text-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#fff" viewBox="0 0 256 256"><path d="M216,48H180V36A28,28,0,0,0,152,8H104A28,28,0,0,0,76,36V48H40a12,12,0,0,0,0,24h4V208a20,20,0,0,0,20,20H192a20,20,0,0,0,20-20V72h4a12,12,0,0,0,0-24ZM100,36a4,4,0,0,1,4-4h48a4,4,0,0,1,4,4V48H100Zm88,168H68V72H188ZM116,104v64a12,12,0,0,1-24,0V104a12,12,0,0,1,24,0Zm48,0v64a12,12,0,0,1-24,0V104a12,12,0,0,1,24,0Z"></path></svg>
                    </button>
                </div>
            </li>
        </template>
    </ul>

    <div wire:key="reports.create.upload" class="sm:col-span-6 mt-2 max-w-3xl">
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
                    <div class="h-12 mb-1">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="text-sm text-gray-600">
                        <label class="relative rounded-md font-regular text-blue-600 group-hover:text-blue-700">
                            <span class="font-bold">Choose a photo</span>
                            <input x-on:change="handleFileSelect($event)" multiple accept="image/*,.heic,.heif" id="file-upload" name="file-upload" type="file" class="sr-only">
                        </label>
                        <p class="inline pl-1">or drag and drop here</p>
                    </div>
                    <p class="text-xs text-gray-500">PNG, JPG, GIF, HEIC</p>
                </div>
            </div>
        </label>
    </div>
    {{--
    <div class="collapse mt-4 p-4 bg-gray-200 rounded-xl max-w-3xl">
        <div>
            <div class="font-black text-lg">Extra parameters</div>
            <div class="mt-4">
                <x-chip-checkbox name="Very little water" key="not_found" value="notfound" />
                <x-chip-checkbox name="No access" key="no_access" value="notfound" />
                <x-chip-checkbox name="Difficult access" key="difficult_access" value="notfound" />
                <x-chip-checkbox name="Broken" key="difficult_access" value="notfound" />
                <x-chip-checkbox name="Duplicate" key="difficult_access" value="notfound" />
                <x-chip-checkbox name="Decorative fountain" key="difficult_access" value="notfound" />
            </div>


            <div class="mt-2">
                <div class="text-sm font-bold text-gray-500 mb-2">Coordinates</div>
                <x-chip-radio name="Accurate" key="not_found" value="notfound" />
                <x-chip-radio name="Inaccurate" key="no_access" value="notfound" />
                <x-chip-radio name="Water source not found" key="difficult_access" value="notfound" />
            </div>
            <div class="mt-2">
                <div class="text-sm font-bold text-gray-500 mb-2">Access to the object</div>
                <x-chip-radio name="Free" key="not_found" value="notfound" />
                <x-chip-radio name="Restricted" key="no_access" value="notfound" />
                <x-chip-radio name="No access" key="difficult_access" value="notfound" />
            </div>
            <div class="mt-4">
                <div class="text-sm font-bold text-gray-500 mb-2">Access to the water</div>
                <x-chip-radio name="Easy" key="not_found" value="notfound" />
                <x-chip-radio name="Difficult" key="no_access" value="notfound" />
                <x-chip-radio name="No access" key="difficult_access" value="notfound" />
            </div>
            <div class="mt-4">
                <div class="text-sm font-bold text-gray-500 mb-2">State of repair</div>
                <x-chip-radio name="Good" key="not_found" value="notfound" />
                <x-chip-radio name="Needs repair" key="no_access" value="notfound" />
                <x-chip-radio name="Ruined" key="difficult_access" value="notfound" />
            </div>
            <div class="mt-4">
                <div class="text-sm font-bold text-gray-500 mb-2">Additional information</div>
                <x-chip-checkbox name="Aggressive vegetation" key="not_found" value="notfound" />
                <x-chip-checkbox name="Littered" key="no_access" value="notfound" />
                <x-chip-checkbox name="Decorative" key="difficult_access" value="notfound" />
            </div>
        </div>
    </div>
    --}}
    {{--
        <div class="mt-2 overflow-x-scroll">
            <x-chip-checkbox name="Stale water" key="stale" />
            <x-chip-checkbox name="Dripping" key="dripping" />
            <x-chip-checkbox name="Boiling or filtering required" key="drinkingwaterconditional" />
            <x-chip-checkbox name="Abandoned" key="abandoned" />
            <x-chip-checkbox name="Sign: potable water" key="drinkingwaterlegal" />
            <x-chip-checkbox name="Sign: not potable water" key="drinkingwaterlegalno" />
        </div>
    --}}
    {{--
        <div class="mt-6 block text-sm font-regular text-gray-700">Water source</div>
        <div class="mt-2 overflow-x-scroll">
            <x-chip-checkbox name="Stale water" key="stale" />
            <x-chip-checkbox name="Dripping" key="dripping" />
            <x-chip-checkbox name="Abandoned" key="abandoned" />
            <x-chip-checkbox name="Not found" key="notfound" />
        </div>

        <div class="mt-6 block text-sm font-regular text-gray-700">Water</div>
        <div class="mt-2 overflow-x-scroll">
            <x-chip-checkbox name="Drinking water" key="drinkingwater" />
            <x-chip-checkbox name="Boiling or filtering required" key="drinkingwaterconditional" />
            <x-chip-checkbox name="Not drinkable" key="drinkingwaterno" />

            <x-chip-checkbox name="Sign: potable water" key="drinkingwaterlegal" />
            <x-chip-checkbox name="Sign: not potable water" key="drinkingwaterlegalno" />
        </div>
    --}}

    {{--
        <label for="" class="mt-6 block text-sm font-medium text-gray-700"></label>
        <div class="">
            <x-chip-checkbox name="Wheelchair access" key="wheelchair" />
            <x-chip-checkbox name="Easy to fill up a bottle" key="bottle" />
            <x-chip-checkbox name="Access for dogs" key="dog" />
        </div>
    --}}

    <div class="mt-0 pt-4 pb-6">
        <div x-cloak class="flex justify-start">
            <div wire:loading.remove class="w-full">
                <template x-if="! isUploadBusy()">
                    <button x-on:click.prevent="submitReport()" type="button" class="no-animation btn font-bold btn-primary btn-block max-w-3xl">
                        {{ $reportId ? 'Save Changes' : 'Add Report' }}
                    </button>
                </template>
                <template x-if="isUploadBusy()">
                    <button type="button" class="no-animation  justify-center items-center btn font-bold btn-disabled btn-primary btn-block max-w-3xl" disabled>
                        <div class="animate-spin w-5 h-5 mx-auto flex border border-4 rounded-full border-stone-400 border-t-transparent"></div>
                    </button>
                </template>
            </div>
            <button wire:loading type="button" class="no-animation flex justify-center items-center btn font-bold btn-disabled btn-primary btn-block max-w-3xl" disabled>
                <div class="animate-spin w-5 h-5 mx-auto flex border border-4 rounded-full border-stone-400 border-t-transparent"></div>
            </button>
        </div>
    </div>
</div>
