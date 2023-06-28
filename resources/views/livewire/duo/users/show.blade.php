<div class="h-full" x-data="{
        previousUserId: {{ intval($userId) }},
        loadUser: function() {
            if (this.userId != this.previousUserId) {
                this.previousUserId = this.userId
                $wire.setUser(this.userId)
            }
        }
    }"
    x-on:duo-load-user.window="loadUser()">
    <div wire:loading.delay.long.flex class="grow hidden w-full h-full flex justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div wire:loading.remove>
        <div class="flex items-stretch -mt-3">
            <div
            class="rounded-lg flex items-center">
                <div class="mr-2 text-xl font-medium">{{ $user?->name }}</div>
                <span class="ml-0 text-sm font-medium px-1.5 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ $user?->rating }}</span>
            </div>
        </div>
        <ul wire:loading.remove role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
    </div>
</div>
