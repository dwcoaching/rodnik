<div id="spring"
    x-data="{
        locateMap: true,
        myId: {{ intval(Auth::check() ? Auth::user()->id : 0) }},
        userId: {{ intval($userId) }},
        personal: {{ intval($userId && (Auth::check() && intval($userId) === Auth::user()->id)) }},
        setPersonal: function(value) {
            this.personal = value
            this.setUserId(value ? this.myId : 0)
            this.registerHomeVisit()
        },
        setUserId: function(userId) {
            this.userId = userId
            window.rodnikMap.springsSource(userId);

            if (this.userId) {
                window.dispatchEvent(new CustomEvent('duo-load-user'))
            } else {
                window.dispatchEvent(new CustomEvent('duo-load-all-reports'))
            }
        },
        unsetUserId: function() {
            this.setUserId(0)
        },
        setSpringId: function(springId) {
            $wire.springId = springId;
            //this.springId = springId
            if (this.previousSpringId !== springId) {
                // $wire.render()
            }

            this.previousSpringId = null
            window.rodnikMap.exitLocationMode()
            window.dispatchEvent(new CustomEvent('duo-load-spring'))
        },
        unsetSpringId: function() {
            this.previousSpringId = this.springId
            this.springId = null

            window.rodnikMap.exitLocationMode()
            if (this.userId) {
                window.dispatchEvent(new CustomEvent('duo-load-user'))
            } else {
                window.dispatchEvent(new CustomEvent('duo-load-all-reports'))
            }
        },
        springId: {{ intval($springId) }},
        previousSpringId: null,
        locationMode: window.rodnikMap.locationMode,
        registerVisit: function(details, location) {
            window.history.pushState(details, 'Rodnik.today', location);
            ym(90143259, 'hit', location)
        },
        registerSpringVisit: function(springId) {
            this.registerVisit({springId: springId}, window.location.origin + '/' + springId)
        },
        registerHomeVisit: function() {
            const location = this.userId ? window.location.origin + '/users/' + this.userId : window.location.origin
            this.registerVisit({userId: this.userId ? this.userId : 0}, location)
        },
        registerLocationCreateVisit: function() {
            const location = window.location.origin + '/create'
            this.registerVisit({}, location)
        },
        registerLocationEditVisit: function() {
            const location = window.location.origin + '/' + this.springId + '/location/edit/'
            this.registerVisit({springId: this.springId}, location)
        },
    }"

    x-on:spring-selected-on-map.window="
        $wire.$set('springId', $event.detail.id, true)
        //setSpringId()
        //registerSpringVisit($event.detail.id)
    "
    x-on:spring-deselected-on-map.window="
        $wire.$set('springId', null, true)
        //unsetSpringId()
        //registerHomeVisit()
    "

    // spring requested
    // spring cleared
    // user requested
    // user cleared
    // location requested
    // location cleared
    // tags requested
    // tags cleared


    x-on:spring-turbo-visit.window="
        setSpringId($event.detail.id)
        registerSpringVisit($event.detail.id)

        window.rodnikMap.locate($event.detail.coordinates);
        window.rodnikMap.highlightFeatureById($event.detail.id);
    "
    x-on:spring-turbo-visit-home.window="
        window.rodnikMap.dehighlightFeature();
        unsetSpringId()
        registerHomeVisit()
    "
    x-on:spring-turbo-visit-index.window="
        $wire.$set('userId', null, false)
        $wire.$set('springId', null, true)
        window.rodnikMap.dehighlightFeature()
        window.rodnikMap.springsSource(0)
        {{--
            unsetSpringId()
            unsetUserId()
            registerHomeVisit()
        --}}
    "
    x-init="window.rodnikMap.springsSource({{ $this->userId }});"
    x-on:turbo-visit-user.window="
        $wire.$set('userId', $event.detail.userId, true)
        window.rodnikMap.springsSource($event.detail.userId)
        {{--
            if ($event.detail.userId == myId) {
                personal = true
            }

            setUserId($event.detail.userId)
            unsetSpringId()
            registerHomeVisit()
        --}}
    "



    x-on:popstate.window="
        if (window.openedPhotoswipe) {
            window.openedPhotoswipe.destroy()
        }

        {{--
            if ($event.state && $event.state.springId) {
                setSpringId($event.state.springId)
                unsetUserId();
                window.rodnikMap.highlightFeatureById($event.state.springId);
            } else if ($event.state) {
                if ($event.state.userId == myId) {
                    personal = true
                } else {
                    personal = false
                }

                setUserId($event.state.userId);
                unsetSpringId()
                window.rodnikMap.dehighlightPreviousFeature();
            }

            if (window.location.pathname == '/create') {
                window.dispatchEvent(
                    new CustomEvent('turbo-location-create')
                )
            } else if (window.location.pathname.includes('/location/edit')) {
                window.dispatchEvent(
                    new CustomEvent('turbo-location-edit',
                        {
                            detail: {
                                springId: $event.state.springId,
                            }
                        }
                    )
                )
            } else {
                window.rodnikMap.exitLocationMode()
            }
        --}}"
    class="flex grow justify-center"
>
    <div class="grow">
        <div class="h-full">
            @if (! $springId)
                <livewire:duo.reports.index :userId="$userId" />
            @elseif ($springId)
                <livewire:duo.springs.show :springId="$springId" />
            @elseif ($locationMode)
                <livewire:duo.springs.create :springId="$springId" :locationMode="$locationMode" />
            @endif
        </div>
    </div>
</div>
