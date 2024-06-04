<x-filament-widgets::widget>
    <x-filament::section>
        Cleanup OSM Objects
        <div class="mt-2">
            @if (! $started)
                <button wire:click="start" type="button" class="py-3 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                    <span wire:loading.remove>
                        Start Cleanup
                    </span>
                    <div class="inline-flex items-center gap-x-2" wire:loading>
                        Starting...
                    </span>
                </button>
            @else
                <div class="">
                    <b>The cleanup has started in the background.</b> You can navigate away from this page,
                    and the cleanup will complete on its own.
                </div>
            @endif
            <div>
                <div class="mt-8 font-bold">Tag combinations to be cleaned up:</div>
                @foreach ($tags as $tagsCombination)
                    @foreach ($tagsCombination as $tag)
                        {{ $tag[0] }}={{ $tag[1] }}<br>
                    @endforeach
                    <br>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
