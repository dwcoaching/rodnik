<div>
    <div class="-mt-3">
        @if ($user)
            <div class="mt-0 text-2xl font-bold flex items-center">
                <div class="mr-1">{{ $user->name }}</div>
                <div class="-mt-2 text-sm font-semibold text-gray-900">{{ $user->rating }}</div>
            </div>
        @else
            <div class="mt-0 text font-normal">New Reports</div>
        @endif

        <ul role="list" class="mt-2 space-y-4" wire:key="reports">
            @foreach ($lastReports as $report)
                <livewire:reports.show has-name="true" :report="$report" wire:key="reports.latest.show.{{ $report->id }}" />
            @endforeach
        </ul>
    </div>
</div>
