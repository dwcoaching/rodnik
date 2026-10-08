<div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
    <div class="grid max-w-3xl gap-1">
        <h2 id="{{ $id }}" class="text-xl font-semibold tracking-tight text-zinc-900">{{ $title }}</h2>
        @isset($description)
            <p class="text-sm leading-6 text-zinc-600">{{ $description }}</p>
        @endisset
    </div>
    @isset($href)
        <a href="{{ $href }}" data-rodnik-navigate class="inline-flex shrink-0 items-center gap-1 rounded-sm text-sm font-semibold text-blue-700 hover:text-blue-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $link }}<span aria-hidden="true">→</span></a>
    @endisset
</div>
