<div class="h-full" x-data="{
    loaded: {{ intval($loaded) }},
    loadAllReports: function() {
        if (! this.loaded) {
            this.loaded = true;
            $wire.setLoaded()
        }
    }
    }"
    x-on:duo-load-all-reports.window="loadAllReports()">
    <div wire:loading.delay.long.flex class="grow hidden w-full h-full flex justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <ul wire:loading.remove role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
        @foreach ($lastReports as $report)
            <x-last-reports.teaser :report="$report" />
        @endforeach
    </ul>
</div>
