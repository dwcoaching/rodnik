<x-filament-widgets::widget>
    <x-filament::section>
        Create a fresh system-wide export
        <div class="mt-2">
            @if (! $started)
                <button wire:click="start" type="button" class="py-3 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                    <span wire:loading.remove>
                        Start Export
                    </span>
                    <div class="inline-flex items-center gap-x-2" wire:loading>
                        Starting...
                    </span>
                </button>
                <div>
                    <div class="mt-8 font-bold">Actual exports can be downloaded from the <a href="{{ route('docs.exports') }}" class="text-blue-600 hover:text-blue-800 underline">Exports page</a>.</div>
                </div>
            @else
                <div class="">
                    <b>The export has started in the background.</b> You can navigate away from this page,
                    and the export will complete on its own. Visit <a href="{{ route('docs.exports') }}" class="text-blue-600 hover:text-blue-800 underline">Exports page</a> to download.
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
