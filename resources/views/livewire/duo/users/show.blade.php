<div x-data="{
        previousUserId: {{ intval($userId) }},
        loadUser: function() {
            if (this.userId != this.previousUserId) {
                this.previousUserId = this.userId
                $wire.setUser(this.userId)
            }
        }
    }"
    x-on:duo-load-user.window="loadUser()">
    <div wire:key="user" class="flex items-stretch" x-cloak x-show="userId && userId !== myId">
        <div
        class="bg-slate-300 text-black rounded-lg mr-1 px-3 py-1 my-1 text-sm font-medium flex items-stretch">
            <div class="mr-1">Showing Water Sources of <b>{{ $user?->name }}</b>
                <span class="bg-slate-500 rounded-full text-xs px-1.5 font-semibold text-white">{{ $user?->rating }}</span>
            </div>
        </div>
        <a href="/"
            @click.prevent="window.dispatchEvent(new CustomEvent('turbo-visit-user', {detail: { userId: 0}}))"
        class="bg-slate-500 text-white rounded-lg px-3 py-1 my-1 text-sm font-semibold flex text-center items-center">Show All</a>
    </div>
    <ul wire:loading.remove role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 gap-4 items-stretch" wire:key="reports">
        @foreach ($lastReports as $report)
            <x-last-reports.teaser :report="$report" />
        @endforeach
    </ul>
</div>
