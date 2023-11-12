<div x-cloak>
    @if ($shown)
        <ul role="list" class="
            grid grid-cols-2 lg:grid-cols-3 md:mt-4 md:px-4
            bg-stone-200
            border-b
            border-stone-200
            gap-[1px]
            md:bg-inherit
            md:border-0
            md:gap-4 items-stretch
        " wire:key="reports">
            @foreach ($reports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
        @if (count($reports) == $take)
            <livewire:duo.components.show-more-reports
                skip="{{ $skip * 2 }}"
                take="{{ $take * 2 }}"
                userId="{{ $userId }}"
            />
        @endif
    @elseif ($take > 0 && $take < 50)
        <div wire:loading.remove class="px-4 pb-6">
            <button
                class="w-full p-3 bg-stone-200 rounded-xl mt-4 text-sm flex items-center justify-center"
                wire:click="show" type="button">
                <div class="h-5">Show {{ $take }} more</div>
            </button>
        </div>
        <div wire:loading.flex class="hidden px-4 pb-6">
            <div class="w-full flex p-3 bg-stone-200 rounded-xl mt-4 text-sm justify-center items-center"
            wire:click="show">
                <div class="animate-spin w-5 h-5 border border-4 rounded-full border-stone-400 border-t-transparent"></div>
            </div>
        </div>
    @endif
</div>
