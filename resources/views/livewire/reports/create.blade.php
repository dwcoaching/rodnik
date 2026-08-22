<div
    data-test="report-create-form"
    class="w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 text-[18px]/[28px]
        [&_.text-xs]:text-[14px]/[20px]
        [&_.text-sm]:text-[16px]/[24px]
        [&_.text-lg]:text-[20px]/[30px]
        [&_.text-xl]:text-[22px]/[30px]
        [&_.text-2xl]:text-[26px]/[34px]
        [&_.btn]:text-[16px]/[24px]"
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
                <span class="text-2xl">💧</span> {{ __('ui.report.guest.title') }}
            </div>
            <div class="mt-2 max-w-prose">
                {{ __('ui.report.guest.encourage_account') }}
            </div>
            <div class="mt-2 max-w-prose">
                {{ __('ui.report.guest.reputation') }}
            </div>
            <div class="mt-2 max-w-prose">
                {{ __('ui.report.guest.reliability') }}
            </div>
            <div class="mt-2 max-w-prose">
                {{ __('ui.report.guest.personal_page') }}
            </div>
            <div class="mt-4 max-w-prose">
                <a href="{{ route('login') }}" type="button" class="mr-2 inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">{{ __('ui.common.log_in') }}</a>
                <a href="{{ route('register') }}" type="button" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">{{ __('ui.common.register') }}</a>
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
        {{ $reportId ? __('ui.report.edit') : __('ui.report.new') }}
    </div>
    <div
        @class([
            'relative mt-2 max-w-xs rounded-md border border-gray-300 bg-white px-3 py-2 shadow-xs',
            'focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600' => ! $errors->has('visited_at'),
            'outline-2 outline-offset-[-1px] outline-[#c00]' => $errors->has('visited_at'),
        ])
    >
        <label for="date" class="block text-sm font-bold text-gray-500 flex justify-between items-center">
            <span class="mr-3">
                {{ __('ui.report.visit_date') }}
            </span>
            <span @click="toggleDate" class="cursor-pointer text-blue-600 text-xs"
                :class="{
                    'font-bold': ! withDate
                }"
            >
                {{ __('ui.report.do_not_specify') }}
            </span>
        </label>
        <input
            x-show="withDate"
            x-model="visited_at"
            x-bind:max="today"
            x-on:focus="syncDateContext()"
            x-on:change.capture="syncDateContext()"
            wire:model.change.live="visited_at"
            type="date"
            name="date"
            id="date"
            class="mt-1 block w-full border-0 p-0 text-base text-gray-900 placeholder-gray-500 focus:ring-0 sm:text-base"
            @if ($errors->has('visited_at')) aria-invalid="true" aria-describedby="visit-date-error" @endif
        >
    </div>
    @error('visited_at')
        <p id="visit-date-error" class="mt-1 max-w-xs text-xs font-medium text-[#c00]" role="alert">{{ $message }}</p>
    @enderror
    <div class="mt-4">
        <div>
            <div class="mb-2">
                <x-form-group-info id="condition-info" title="{{ __('ui.report.condition') }}" class="mt-4">
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.condition_help.has_water_title') }}</span> {{ __('ui.report.condition_help.has_water') }}</p>
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.condition_help.no_water_title') }}</span> {{ __('ui.report.condition_help.no_water') }}</p>
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.condition_help.not_found_title') }}</span> {{ __('ui.report.condition_help.not_found') }}</p>
                </x-form-group-info>
                <x-chip :indicator="false" :name="'💧 ' . __('ui.report.form_conditions.running')" key="state" :value="\App\Enums\ReportState::Running->value" />
                <x-chip :indicator="false" :name="'🌵 ' . __('ui.report.form_conditions.dry')" key="state" :value="\App\Enums\ReportState::Dry->value" />
                <x-chip :indicator="false" :name="'😡 ' . __('ui.report.form_conditions.notfound')" key="state" :value="\App\Enums\ReportState::NotFound->value" />
            </div>
            <div x-show="state !== @js(\App\Enums\ReportState::Dry->value) && state !== @js(\App\Enums\ReportState::NotFound->value)">
                <x-form-group-info id="water-quality-info" title="{{ __('ui.report.water_quality') }}" class="mt-2">
                    <p>
                        <span class="font-semibold text-gray-700">{{ __('ui.report.quality_help.title') }}</span>
                        {{ __('ui.report.quality_help.text') }}
                    </p>
                </x-form-group-info>
                <x-chip :indicator="false" :name="'🚰 ' . __('ui.report.conditions.good')" key="quality" :value="\App\Enums\ReportQuality::Good->value" />
                <x-chip :indicator="false" :name="'🚱 ' . __('ui.report.conditions.bad')" key="quality" :value="\App\Enums\ReportQuality::Bad->value" />
            </div>
            <div x-show="state !== @js(\App\Enums\ReportState::NotFound->value)">
                <x-form-group-info id="problems-info" title="{{ __('ui.report.details') }}" class="mt-2">
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.problem_help.access_limited_title') }}</span> {{ __('ui.report.problem_help.access_limited') }}</p>
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.problem_help.littered_title') }}</span> {{ __('ui.report.problem_help.littered') }}</p>
                    <p><span class="font-semibold text-gray-700">{{ __('ui.report.problem_help.broken_title') }}</span> {{ __('ui.report.problem_help.broken') }}</p>
                </x-form-group-info>
                <x-chip mode="checkbox" name="{{ __('ui.report.badges.access_limited') }}" key="access_limited" />
                <x-chip mode="checkbox" name="{{ __('ui.report.badges.littered') }}" key="littered" />
                <x-chip mode="checkbox" name="{{ __('ui.report.badges.broken') }}" key="broken" />
            </div>
        </div>
    </div>

    <div class="mt-2">
        <div class="text-sm font-bold text-gray-500 mb-2">{{ __('ui.report.additional_details') }}</div>
        <div class="relative">
            <textarea wire:model="comment" rows="4" name="comment" id="comment"
                placeholder="{{ __('ui.report.comment_placeholder') }}"
                @class([
                    'w-full' => true,
                    'sm:max-w-lg' => true,
                    'shadow-xs' => true,
                    'block' => true,
                    'w-full' => true,
                    'text-base' => true,
                    'sm:text-base' => true,
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
                        <div class="mt-2 rounded-sm bg-black/30 px-2 py-1 text-xs font-bold" x-text="item.status === 'uploading' ? `${item.progress}%` : @js(__('ui.report.photo.preparing'))"></div>
                    </div>
                    <div x-show="item.status === 'failed'" class="absolute inset-x-2 bottom-2 z-20 rounded-sm bg-red-600/90 px-2 py-1 text-xs font-bold text-white">
                        <div x-text="item.error"></div>
                        <button type="button" class="mt-1 underline" x-on:click.stop.prevent="retryPhoto(item)">{{ __('ui.report.photo.retry') }}</button>
                    </div>
                    <button type="button" x-sort:handle title="{{ __('ui.report.photo.move') }}" class="photo-sort-handle rounded-md bg-black/20 cursor-move opacity-100 absolute left-0 top-0 py-2 px-2 z-30 text-white font-semibold text-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#fff" viewBox="0 0 256 256"><path d="M87.51,64.49a12,12,0,0,1,0-17l32-32a12,12,0,0,1,17,0l32,32a12,12,0,0,1-17,17L140,53V96a12,12,0,0,1-24,0V53L104.49,64.49A12,12,0,0,1,87.51,64.49Zm64,127L140,203V160a12,12,0,0,0-24,0v43l-11.51-11.52a12,12,0,0,0-17,17l32,32a12,12,0,0,0,17,0l32-32a12,12,0,0,0-17-17Zm89-72-32-32a12,12,0,0,0-17,17L203,116H160a12,12,0,0,0,0,24h43l-11.52,11.51a12,12,0,0,0,17,17l32-32A12,12,0,0,0,240.49,119.51ZM53,140H96a12,12,0,0,0,0-24H53l11.52-11.51a12,12,0,1,0-17-17l-32,32a12,12,0,0,0,0,17l32,32a12,12,0,1,0,17-17Z"></path></svg>
                    </button>
                    <button type="button" title="{{ __('ui.report.photo.remove') }}" x-on:click.stop.prevent="removePhotoItem(item)" class="removePhotoHandle rounded-md bg-black/20 opacity-100 cursor-pointer absolute right-0 top-0 py-2 px-2 z-30 text-white font-semibold text-2xl">
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
                            <span class="font-bold">{{ __('ui.report.photo.choose') }}</span>
                            <input x-on:change="handleFileSelect($event)" multiple id="file-upload" name="file-upload" type="file" class="sr-only">
                        </label>
                        <p class="inline pl-1">{{ __('ui.report.photo.drag_and_drop') }}</p>
                    </div>
                    <p class="text-xs text-gray-500">PNG, JPG, GIF, HEIC</p>
                </div>
            </div>
        </label>
    </div>
    <div class="mt-0 pt-4 pb-6">
        <div x-cloak class="flex justify-start">
            <div wire:loading.remove class="w-full">
                <template x-if="! isUploadBusy()">
                    <button x-on:click.prevent="submitReport()" type="button" class="no-animation btn h-11 font-bold btn-primary btn-block max-w-3xl">
                        {{ $reportId ? __('ui.common.save_changes') : __('ui.report.add') }}
                    </button>
                </template>
                <template x-if="isUploadBusy()">
                    <button type="button" class="no-animation h-11 justify-center items-center btn font-bold btn-disabled btn-primary btn-block max-w-3xl" disabled>
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
