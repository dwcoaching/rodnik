<div id="spring"
    x-data="{
        defaultServerQueryParameters: {
            springId: null,
            userId: null,
            location: false
        },
        registerVisit: function() {
            ym(90143259, 'hit', window.location.href)
        },
    }"

    x-on:duo-visit.window="
        Object.entries(defaultServerQueryParameters).forEach(([key, value]) => {
            const newValue = $event.detail.hasOwnProperty(key) ? $event.detail[key] : value;
            $wire.$set(key, newValue, false);
        });
        $wire.$refresh();

        window.rodnikMap.duoVisit({...defaultServerQueryParameters, ...$event.detail})
    "
    x-init="
        if ($wire.firstRender) {
            @if ($coordinates)
                window.rodnikMap.locate({{ json_encode($coordinates) }})
                window.rodnikMap.highlightFeatureById({{ intval($springId) }})
            @endif

            window.rodnikMap.duoVisit({
                springId: {{ intval($springId) }},
                userId: {{ intval($userId) }},
                location: {{ intval($location) }},
            })

            $wire.firstRender = false
        } else {
            registerVisit()
        }
    "

    x-on:popstate.window="
        if (window.openedPhotoswipe) {
            window.openedPhotoswipe.destroy()
        }

        const parameters = new URLSearchParams(window.location.search);

        window.rodnikMap.duoVisit({
            springId: parameters.get('s'),
            userId: parameters.get('u'),
            location: parameters.get('location')
        })"
    class="flex grow justify-center"
>
    <div class="grow">
        <div class="h-full">
            @if (! $springId && ! $location)
                <livewire:duo.reports.index :userId="$userId" />
            @endif
            @if ($springId && ! $location)
                <livewire:duo.springs.show :springId="$springId" :userId="$userId" />
            @endif
            @if ($location)
                <livewire:duo.springs.create :springId="$springId" :location="$location" />
            @endif
        </div>
    </div>
</div>
