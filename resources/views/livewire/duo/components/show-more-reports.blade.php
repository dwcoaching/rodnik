<div>
    @if ($shown)
        <ul role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-4 gap-4 items-stretch" wire:key="reports">
            @foreach ($reports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
        <livewire:duo.components.show-more-reports
            skip="{{ $skip * 2 }}"
            take="{{ $take * 2 }}"
        />
    @elseif ($take < 50)
        <button wire:loading.remove
            class="w-full p-3 bg-stone-200 rounded-xl mt-4 text-sm flex items-center justify-center"
            wire:click="show" type="button">
            <div class="h-5">Show {{ $take }} more</div>
        </button>
        <div wire:loading.flex
            class="hidden w-full p-3 bg-stone-200 rounded-xl mt-4 text-sm justify-center items-center"
            wire:click="show">
            <div class="animate-spin w-5 h-5 border border-4 rounded-full border-stone-400 border-t-transparent"></div>
        </div>
    @endif
</div>
