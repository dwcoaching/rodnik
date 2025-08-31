<div>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <x-section-title>
            <x-slot name="title">Export Personal Contributions</x-slot>
            <x-slot name="description">Export your personal contributions (reports and water sources edits) to Rodnik.today
                in JSON, CSV or XLSX format. Also, you can download all of your photos in a single
                HTML file.</x-slot>
        </x-section-title>
    
        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 bg-white sm:p-6 shadow sm:rounded-md">
                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-6 sm:col-span-4">
                        You have contributed {{ $user->reports()->visible()->count() }} reports and {{  $user->springRevisions->count() }} water sources edits to Rodnik.today.
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        Pick the format to download your contributions:
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <x-button wire:click="exportJson">
                            <span wire:loading.remove wire:target="exportJson">JSON</span>
                            <span wire:loading wire:target="exportJson">Downloading...</span>
                        </x-button>
                        <x-button wire:click="exportCsv">
                            <span wire:loading.remove wire:target="exportCsv">CSV</span>
                            <span wire:loading wire:target="exportCsv">Downloading...</span>
                        </x-button>
                        <x-button wire:click="exportXlsx">
                            <span wire:loading.remove wire:target="exportXlsx">XLSX</span>
                            <span wire:loading wire:target="exportXlsx">Downloading...</span>
                        </x-button>
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        Also, you can view all of your photos 
                        <a class="underline text-blue-600 hover:text-blue-800" href="{{ route('users.photos.index', 
                            ['user' => auth()->user()]) }}" target="_blank">in a single HTML file</a>. You can
                            save the page with photos with your browser.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
