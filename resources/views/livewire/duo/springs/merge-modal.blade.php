<div>
    @if ($open && $source)
        <div
            role="dialog"
            aria-modal="true"
            x-data="{ target: @js($targetSpringId ? (string) $targetSpringId : '') }"
            x-trap.noscroll="true"
            @keydown.escape.window="$wire.close()"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
            wire:click="close"
        >
            <div
                class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto"
                @click.stop
            >
                <div class="flex items-center justify-between p-5 border-b border-stone-200">
                    <h3 class="text-lg font-semibold text-gray-900">Merge into another water source</h3>
                    <button
                        type="button"
                        wire:click="close"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="Close"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-sm text-gray-600">
                        This will mark
                        <span class="font-semibold">#{{ $source->id }} {{ $source->name ?: $source->type }}</span>
                        as a duplicate. Visiting its page will redirect to the target unless
                        <code class="bg-stone-100 rounded-sm px-1">?redirect=false</code> is in the URL.
                    </p>

                    <div>
                        <label for="targetSpringId" class="block text-sm font-medium text-gray-700 mb-1">
                            Target water source
                        </label>

                        @if ($candidates->isEmpty())
                            <p class="text-sm text-gray-500 italic">
                                No other water sources within {{ $radiusMeters }} meters.
                            </p>
                        @else
                            <select
                                id="targetSpringId"
                                wire:model="targetSpringId"
                                x-model="target"
                                class="block w-full rounded-md border-stone-300 shadow-xs focus:border-blue-500 focus:ring-blue-500 text-sm"
                            >
                                <option value="" disabled @selected(! $targetSpringId)>Select a water source</option>
                                @foreach ($candidates as $candidate)
                                    <option value="{{ $candidate->id }}">
                                        #{{ $candidate->id }}
                                        — {{ $candidate->name ?: $candidate->type }}
                                        @if ($candidate->isOsmTracked()) (OSM) @endif
                                        @if ($candidate->redirect_to_spring_id) (already redirects) @endif
                                        @if ($candidateDistanceLabels->get($candidate->id)) — {{ $candidateDistanceLabels->get($candidate->id) }} @endif
                                    </option>
                                @endforeach
                            </select>
                        @endif

                        @error('redirect_to_spring_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-x-2 p-5 border-t border-stone-200">
                    <button
                        type="button"
                        wire:click="merge"
                        wire:loading.attr="disabled"
                        x-bind:disabled="! target"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Merge
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
