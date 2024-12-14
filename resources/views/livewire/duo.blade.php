<div id="spring"
    x-data="{
        defaultServerQueryParameters: {
            springId: null,
            userId: null,
            location: false
        },
        initialRender: true,
        locateMap: true,
        myId: {{ intval(Auth::check() ? Auth::user()->id : 0) }},
        userId: {{ intval($userId) }},
        springId: {{ intval($springId) }},
        previousSpringId: null,
        locationMode: window.rodnikMap.queryParameters.location,
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
            window.rodnikMap.duoVisit({
                springId: {{ intval($springId) }},
                userId: {{ intval($userId) }},
                location: {{ intval($location) }},
            })

            $wire.firstRender = false
        } else {
            this.registerVisit()
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
