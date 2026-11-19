@props(['title', 'subtitle' => null])

{{-- The navbar above already carries the logo, so the card opens with what the page is for. --}}
<div class="flex grow flex-col items-center px-4 pt-4 pb-12 sm:pt-12">
    <div class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-6 shadow-xs sm:p-8">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-2 text-sm text-gray-600">{{ $subtitle }}</p>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="mt-6 border-t border-stone-200 pt-5 text-center text-sm text-gray-600">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
