<div id="spring" data-rodnik-page="{{ json_encode(['page' => $page, 'coordinates' => $coordinates]) }}" class="flex grow justify-center">
    <div class="grow">
        <div class="h-full">
            @if (! $page['spring'] && ! $page['location'])
                <livewire:duo.reports.index :userId="$page['user']" :key="'reports-'.$page['user'].'-visit-'.$navigationRevision" />
            @endif
            @if ($page['spring'] && ! $page['location'])
                <livewire:duo.springs.show :springId="$page['spring']" :userId="$page['user']" :key="'spring-'.$page['spring'].'-user-'.$page['user'].'-visit-'.$navigationRevision" />
            @endif
            @if ($page['location'])
                <livewire:duo.springs.create :springId="$page['spring']" :location="$page['location']" :key="'location-'.$page['spring'].'-visit-'.$navigationRevision" />
            @endif
        </div>
    </div>
</div>
