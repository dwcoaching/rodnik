<div id="spring"
    x-data="{
        defaultServerQueryParameters: {
            spring: null,
            user: null,
            location: false
        },
        registerVisit: function() {
            ym(90143259, 'hit', window.location.href)
        },
    }"

    x-on:duo-visit.window="
        Object.entries(defaultServerQueryParameters).forEach(([key, value]) => {

            const newValue = $event.detail.hasOwnProperty(key) ? $event.detail[key] : value;
            
            if (key === 'spring') {
                $wire.$set('view.spring', newValue, false);
            } else if (key === 'user') {
                $wire.$set('view.user', newValue, false);
            } else if (key === 'location') {
                $wire.$set('view.location', newValue, false);
            } else {
                $wire.$set(key, newValue, false);
            }
        });
        $wire.$refresh();

        window.rodnikMap.duoVisit({...defaultServerQueryParameters, ...$event.detail})
    "
    x-init="
        if ($wire.firstRender) {
            @if ($coordinates)
                window.rodnikMap.locate({{ json_encode($coordinates) }})
                window.rodnikMap.highlightFeatureById({{ intval($view['spring']) }})
            @endif

            window.rodnikMap.duoVisit({
                spring: {{ intval($view['spring']) }},
                user: {{ intval($view['user']) }},
                location: {{ intval($view['location']) }},
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
            spring: parameters.get('spring'),
            user: parameters.get('user'),
            location: parameters.get('location')
        })"
    class="flex grow justify-center"
>
    <div class="grow">
        <div class="h-full">
            @if (! $view['spring'] && ! $view['location'])
                <livewire:duo.reports.index :userId="$view['user']" />
            @endif
            @if ($view['spring'] && ! $view['location'])
                <livewire:duo.springs.show :springId="$view['spring']" :userId="$view['user']" />
            @endif
            @if ($view['location'])
                <livewire:duo.springs.create :springId="$view['spring']" :location="$view['location']" />
            @endif
        </div>
    </div>
</div>
