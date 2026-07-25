<div>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <x-section-title>
            <x-slot name="title">{{ __('contributions.export.title') }}</x-slot>
            <x-slot name="description">{{ __('contributions.export.description') }}</x-slot>
        </x-section-title>
    
        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 bg-white sm:p-6 shadow-sm sm:rounded-md">
                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-6 sm:col-span-4">
                        {{ __('contributions.export.summary', [
                            'reports' => trans_choice('contributions.export.reports', $user->reports()->visible()->count(), ['count' => $user->reports()->visible()->count()]),
                            'edits' => trans_choice('contributions.export.edits', $user->springRevisions->count(), ['count' => $user->springRevisions->count()]),
                        ]) }}
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        {{ __('contributions.export.choose_format') }}
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <x-button wire:click="exportJson">
                            <span wire:loading.remove wire:target="exportJson">JSON</span>
                            <span wire:loading wire:target="exportJson">{{ __('contributions.export.downloading') }}</span>
                        </x-button>
                        <x-button wire:click="exportCsv">
                            <span wire:loading.remove wire:target="exportCsv">CSV</span>
                            <span wire:loading wire:target="exportCsv">{{ __('contributions.export.downloading') }}</span>
                        </x-button>
                        <x-button wire:click="exportXlsx">
                            <span wire:loading.remove wire:target="exportXlsx">XLSX</span>
                            <span wire:loading wire:target="exportXlsx">{{ __('contributions.export.downloading') }}</span>
                        </x-button>
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        {{ __('contributions.export.photos_prefix') }}
                        <a class="underline text-blue-600 hover:text-blue-800" href="{{ localized_route('users.photos.index',
                            ['user' => auth()->user()]) }}" target="_blank">{{ __('contributions.export.photos_link') }}</a>
                        {{ __('contributions.export.photos_suffix') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
