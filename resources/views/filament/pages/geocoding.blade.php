<x-filament-panels::page>
    <div wire:poll.3s>
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Attach a country and the nearest city from the local GeoJSON dataset to water sources.
                Runs continue in the background, and progress is saved when you leave this page.
            </p>
            @forelse ($runs as $run)
                <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <strong>Run #{{ $run->id }} · {{ ucfirst($run->mode) }}</strong>
                        <span>{{ ucfirst($run->status) }}</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        @if ($run->status === 'completed')
                            {{ number_format($run->processed) }} water sources processed
                        @else
                            {{ number_format($run->processed) }} of {{ number_format($run->total) }} processed
                        @endif
                        · {{ number_format($run->matched) }} matched
                        · {{ number_format($run->unmatched) }} unmatched
                        · {{ number_format($run->failed_count) }} failed
                    </div>
                    <progress class="mt-3 w-full" max="100" value="{{ $run->progressPercentage() }}" aria-label="Run {{ $run->id }} progress">
                        {{ $run->progressPercentage() }}%
                    </progress>
                    @if ($run->status === 'queued')
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Waiting for a queue worker.</p>
                    @elseif ($run->status === 'running' && $run->canRecover())
                        <p class="mt-2 text-sm text-amber-600">The worker has stopped reporting progress. Resume this run, or stop it to start another.</p>
                    @endif
                    @if ($run->error)
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $run->error }}</p>
                    @endif
                    <div class="mt-2 text-xs text-gray-500">
                        Started {{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                        @if ($run->finished_at) · Finished {{ $run->finished_at->format('Y-m-d H:i:s') }} @endif
                    </div>
                </div>
            @empty
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">No reverse geocoding runs yet.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
