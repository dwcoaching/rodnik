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
            this.springId = springId
            if (this.previousSpringId !== springId) {
                // $wire.render()
            }

            this.previousSpringId = null

            window.dispatchEvent(new CustomEvent('duo-load-spring'))
        },
        unsetSpringId: function() {
            this.previousSpringId = this.springId
            this.springId = null

            if (this.userId) {
                window.dispatchEvent(new CustomEvent('duo-load-user'))
            } else {
                window.dispatchEvent(new CustomEvent('duo-load-all-reports'))
            }
        },
        springId: {{ intval($springId) }},
        previousSpringId: null,
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
        }
    }"
    x-on:spring-selected-on-map.window="
        setSpringId($event.detail.id)
        registerSpringVisit($event.detail.id)
    "
    x-on:spring-deselected-on-map.window="
        unsetSpringId()
        registerHomeVisit()
    "
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
        window.rodnikMap.dehighlightFeature();
        unsetSpringId()
        unsetUserId()
        registerHomeVisit()
    "
    x-on:turbo-visit-user.window="
        if ($event.detail.userId == myId) {
            personal = true
        }

        setUserId($event.detail.userId)
        unsetSpringId()
        registerHomeVisit()
    "
    x-on:popstate.window="
        if (window.openedPhotoswipe) {
            window.openedPhotoswipe.destroy()
        }

        if ($event.state && $event.state.springId) {
            setSpringId($event.state.springId)
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
        }"
    class="flex grow justify-center"
>
    <div class="grow">
        <div x-show="! springId" class="h-full">
            <div x-show="! userId" class="h-full">
                <livewire:duo.reports.index loaded="{{ ! $userId && ! $springId }}" />
            </div>
            <div x-show="userId" class="h-full">
                <livewire:duo.users.show :userId="$userId" />
            </div>
        </div>
        <div x-show="springId" class="h-full">
            <livewire:duo.springs.show :springId="$springId" />
        </div>
    </div>
</div>
