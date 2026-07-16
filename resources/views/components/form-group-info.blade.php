@props(['id', 'title'])

<div {{ $attributes->class(['mb-2']) }} x-data="{ infoOpen: false }">
    <button
        type="button"
        class="group flex w-full cursor-pointer items-center gap-1 text-left text-sm font-bold text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        @click="infoOpen = ! infoOpen"
        :aria-expanded="infoOpen.toString()"
        aria-controls="{{ $id }}"
        id="{{ $id }}-toggle"
        data-test="{{ $id }}-toggle"
    >
        <span>{{ $title }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-400 group-hover:text-gray-500" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        id="{{ $id }}"
        x-cloak
        x-show="infoOpen"
        x-transition.opacity
        role="region"
        aria-labelledby="{{ $id }}-toggle"
        class="mt-2 max-w-lg rounded-lg bg-gray-200 px-3 py-3 text-base leading-relaxed text-gray-600"
    >
        <div class="space-y-2">
            {{ $slot }}
        </div>
    </div>
</div>
