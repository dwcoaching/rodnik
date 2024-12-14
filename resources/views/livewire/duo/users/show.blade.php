<div class="h-full" x-data="{}">
    <div wire:loading.delay.long.flex class="grow hidden w-full h-full flex justify-center items-center">
        <div class="animate-spin w-6 h-6 border border-4 rounded-full border-gray-400 border-t-transparent"></div>
    </div>
    <div x-cloak wire:loading.remove x-show="userId == loadedUserId">
        <div class="px-4 flex items-stretch">
            <div
            class="rounded-lg flex items-center">
                <div class="mr-2 text-xl font-medium">{{ $user?->name }}</div>
                <span class="ml-0 text-sm font-medium px-1.5 py-0 rounded-full bg-[#FFD300]/[0.25] border border-[#ff6633]">{{ $user?->rating }}</span>
            </div>
        </div>
        <ul x-cloak role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 md:px-4
            bg-stone-200
            border-t
            border-b
            border-stone-200
            gap-[1px]
            md:bg-inherit
            md:border-0
            md:gap-4 items-stretch md:items-start" wire:key="reports">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" />
            @endforeach
        </ul>
        <livewire:duo.components.show-more-reports
            key="show-more-reports-user-{{ $userId }}-skip-{{ $limit }}-take-{{ $limit }}"
            skip="{{ $limit }}"
            take="{{ $limit }}"
            :userId="$userId" />
    </div>
</div>
