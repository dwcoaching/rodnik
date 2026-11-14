<x-filament-widgets::widget>
    <x-filament::section>
        <div class="font-semibold">Map overview at far zooms</div>
        <div class="mt-2 text-sm">
            @if ($status['revision'] === null)
                Visitors see the <b>vector</b> overview. Raster tiles have not been built on this server yet:
                run <code>php artisan sources:raster-build</code> first.
            @elseif ($status['enabled'])
                Visitors see the <b>raster</b> overview (tiles from {{ $status['generated_at'] }}).
            @else
                Visitors see the <b>vector</b> overview. Raster tiles are built ({{ $status['generated_at'] }}) and ready to turn on.
            @endif
        </div>
        @if ($status['revision'] !== null)
            <div class="mt-3 flex items-center gap-3">
                <button wire:click="setEnabled({{ $status['enabled'] ? 'false' : 'true' }})" type="button"
                    class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                    {{ $status['enabled'] ? 'Switch to vector' : 'Switch to raster' }}
                </button>
                <a href="{{ route('raster-lab') }}" class="text-sm text-blue-700 hover:underline">Open the raster lab</a>
            </div>
            <div class="mt-2 text-xs text-gray-500">Open maps follow the switch within about a minute.</div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
