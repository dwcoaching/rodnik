<div x-data="{
        loaded: {{ intval($loaded) }},
        loadAllReports: function() {
            if (! this.loaded) {
                this.loaded = true;
                $wire.setLoaded()
            }
        }
    }"
    x-on:duo-load-all-reports.window="loadAllReports()">
    <ul wire:loading.remove role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
        @foreach ($lastReports as $report)
            <x-last-reports.teaser :report="$report" />
        @endforeach
    </ul>
</div>
