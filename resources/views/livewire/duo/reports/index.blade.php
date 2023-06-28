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
    <div wire:loading.remove>
        @if ($loaded)
            <div class="-mt-4 mb-3">
                <div class="lg:flex items-center justify-between text-sm font-medium mb-2">
                    <div class="mt-2">
                        <span class="px-1.5 py-0 rounded-full bg-[#33A9FF]/[0.1] border border-[#33A9FF]">{{ number_format($springsCount, 0, ',', ' ') }}</span>
                        {{ \Str::plural('water source', $springsCount) }} with
                        <span class="ml-0 px-1.5 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ number_format($reportsCount, 0, ',', ' ') }}</span>
                        {{ \Str::plural('report', $reportsCount) }}
                    </div>
                </div>
            </div>
        @endif
        <ul role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
    </div>
</div>
