<div id="spring"
    x-data="{
        defaultServerQueryParameters: {
            'spring': null,
            'user': null,
            'location': null
        },
        registerVisit: function() {
            ym(90143259, 'hit', window.location.href)
        },
    }"

    x-on:duo-visit.window="
        Object.entries(defaultServerQueryParameters).forEach(([key, value]) => {
            const newValue = $event.detail.hasOwnProperty(key) ? $event.detail[key] : value;
            $wire.$set('page.' + key, newValue, false);
        });

        $wire.$refresh();

        window.rodnikMap.duoVisit({...defaultServerQueryParameters, ...$event.detail})
    "
    x-init="
        if ($wire.firstRender) {
            @if ($coordinates)
                window.rodnikMap.locate({{ json_encode($coordinates) }})
                window.rodnikMap.highlightFeatureById({{ intval($page['spring']) }})
            @endif

            window.rodnikMap.duoVisit({
                spring: {{ intval($page['spring']) }},
                user: {{ intval($page['user']) }},
                location: {{ intval($page['location']) }},
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
            spring: parseInt(parameters.get('page[spring]')) || null,
            user: parseInt(parameters.get('page[user]')) || null,
            location: parseInt(parameters.get('page[location]')) || null
        })"
    class="flex grow justify-center"
>
    <div class="grow">
        <div class="h-full">
            @if (! $page['spring'] && ! $page['location'])
                <livewire:duo.reports.index :userId="$page['user']" />
            @endif
            @if ($page['spring'] && ! $page['location'])
                <livewire:duo.springs.show :springId="$page['spring']" :userId="$page['user']" />
            @endif
            @if ($page['location'])
                <livewire:duo.springs.create :springId="$page['spring']" :location="$page['location']" />
            @endif
        </div>
    </div>
</div>
