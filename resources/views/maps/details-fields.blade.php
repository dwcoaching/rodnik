<div class="grid gap-4">
    <div class="grid gap-1.5">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <label for="{{ $fieldPrefix }}-title" class="text-sm font-semibold text-zinc-800">{{ __('ui.maps.title') }}</label>
            <span id="{{ $fieldPrefix }}-title-example" class="text-sm font-normal text-zinc-500">{{ __('ui.maps.title_example') }}</span>
        </div>
        <input id="{{ $fieldPrefix }}-title" x-ref="title" x-model="draftTitle" @input="delete fieldErrors.title" :disabled="busy" type="text" required maxlength="160" placeholder="{{ __('ui.maps.title_placeholder') }}"
            :aria-invalid="Boolean(fieldErrors.title)" aria-describedby="{{ $fieldPrefix }}-title-example {{ $fieldPrefix }}-title-error" :class="fieldErrors.title ? 'border-[#dc3545] focus:border-[#dc3545] focus:ring-[#dc3545]' : 'border-zinc-200 focus:border-blue-600 focus:ring-blue-600'" class="min-h-11 w-full rounded-lg bg-white px-3 py-2 text-base text-zinc-900 placeholder:text-zinc-400 disabled:opacity-60 sm:text-sm" />
        <p id="{{ $fieldPrefix }}-title-error" x-cloak x-show="fieldErrors.title" x-text="fieldErrors.title?.[0]" role="alert" class="text-sm text-[#dc3545]"></p>
    </div>
    <div class="grid gap-1.5">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <label for="{{ $fieldPrefix }}-slug" class="text-sm font-semibold text-zinc-800">{{ __('ui.maps.link_label') }}</label>
            <span id="{{ $fieldPrefix }}-slug-example" class="text-sm font-normal text-zinc-500">{{ __('ui.maps.link_example') }}</span>
        </div>
        <div :class="slugStatus === 'invalid' || slugStatus === 'failed' || fieldErrors.slug ? 'border-[#dc3545] ring-1 ring-[#dc3545] focus-within:border-[#dc3545] focus-within:ring-[#dc3545]' : slugStatus === 'valid' ? 'border-[#198754] ring-1 ring-[#198754] focus-within:border-[#198754] focus-within:ring-[#198754]' : 'border-zinc-200 focus-within:border-blue-600 focus-within:ring-blue-600'" class="flex min-w-0 items-center rounded-lg border bg-white focus-within:ring-1">
            <span class="shrink-0 py-2 pl-3 text-sm text-zinc-500">/maps/</span>
            <input id="{{ $fieldPrefix }}-slug" x-ref="slug" x-model="draftSlug" @input="slugInput()" type="text" minlength="3" maxlength="80" required pattern="[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*" placeholder="{{ __('ui.maps.link_placeholder') }}"
                autocapitalize="none" autocomplete="off" spellcheck="false" :disabled="busy" :aria-invalid="slugStatus === 'invalid' || Boolean(fieldErrors.slug)" aria-describedby="{{ $fieldPrefix }}-slug-example {{ $fieldPrefix }}-slug-status {{ $fieldPrefix }}-slug-error"
                class="min-h-11 w-full min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-base text-zinc-900 placeholder:text-zinc-400 focus:ring-0 focus:outline-none disabled:opacity-60 sm:text-sm" />
            <span class="flex size-10 shrink-0 items-center justify-center" aria-hidden="true">
                <span x-cloak x-show="slugStatus === 'checking'" class="size-4 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600"></span>
                <svg x-cloak x-show="slugStatus === 'valid'" class="size-4 text-[#198754]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                <svg x-cloak x-show="slugStatus === 'invalid' || slugStatus === 'failed'" class="size-4 text-[#dc3545]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
            </span>
        </div>
        <p id="{{ $fieldPrefix }}-slug-status" x-cloak x-show="(slugStatus === 'invalid' || slugStatus === 'failed') && slugNotice && !fieldErrors.slug" x-text="slugNotice" role="status" aria-live="polite" class="text-xs leading-5 text-[#dc3545]"></p>
        <button type="button" x-cloak x-show="slugStatus === 'failed'" @click="validateSlug()" :disabled="busy" class="min-h-8 justify-self-start rounded-sm text-xs font-semibold text-blue-700 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.retry') }}</button>
        <p id="{{ $fieldPrefix }}-slug-error" x-cloak x-show="fieldErrors.slug" x-text="fieldErrors.slug?.[0]" role="alert" class="text-sm text-[#dc3545]"></p>
        <p x-cloak x-show="slugToken && draftSlug.trim() !== slugOriginal" class="text-xs leading-5 text-zinc-500">{{ __('ui.maps.custom_link_change_help') }}</p>
    </div>
</div>
