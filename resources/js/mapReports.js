const defaultFilters = {
    spring: true,
    water_well: true,
    water_tap: true,
    drinking_water: true,
    fountain: true,
    other: true,
    with_reports: false,
    along: false,
};

const normalizeFilters = (filters) => Object.fromEntries(
    Object.entries(defaultFilters).map(([key, value]) => [key, Boolean(filters?.[key] ?? value)]),
);

export default () => ({
    inMapArea: true,
    busy: false,
    waitingForPolygon: false,
    failed: false,
    retryMore: false,
    pendingState: null,
    activeState: null,
    loaderTop: null,
    mapReady: false,

    init() {
        this.$nextTick(async () => {
            if (window.rodnikMap?.initialSharedState) await window.rodnikMap.ready;
            if (!this.$el.isConnected) return;
            this.mapReady = true;
            this.positionLoader();
            return this.refresh();
        });
    },

    restoreScope() {
        const url = new URL(window.location.href);
        const value = url.searchParams.get('w') ?? url.searchParams.get('whole_world');
        if (value === '0' || value === '1') {
            this.inMapArea = value !== '1';
        } else {
            try {
                this.inMapArea = window.localStorage.getItem('rodnik.reports.inMapArea') !== '0';
            } catch { /* Keep the default when storage is unavailable. */ }
        }
        this.persistScope();
    },

    persistScope() {
        const value = this.inMapArea ? '1' : '0';
        try {
            window.localStorage.setItem('rodnik.reports.inMapArea', value);
        } catch { /* The URL remains usable when storage is unavailable. */ }
        const url = new URL(window.location.href);
        url.searchParams.delete('report_area');
        url.searchParams.delete('whole_world');
        if (this.inMapArea) {
            url.searchParams.delete('w');
        } else {
            url.searchParams.set('w', '1');
        }
        if (url.href !== window.location.href) {
            window.history.replaceState(window.history.state, '', url);
            window.rodnikNavigation?.capture?.();
        }
    },

    toggleScope() {
        this.inMapArea = !this.inMapArea;
        this.persistScope();
        return this.refresh();
    },

    positionLoader() {
        if (!this.$el.isConnected || this.$wire.userId) return;

        this.loaderTop = Math.max(0, this.$refs.reportsList.getBoundingClientRect().top);
    },

    readMapState() {
        if (window.rodnikNavigation?.restoring) return null;
        this.restoreScope();
        const map = window.rodnikMap;
        if (map?.initialSharedState && !this.mapReady) return null;
        const bounds = this.inMapArea ? map?.getViewportBounds() : { west: -180, south: -90, east: 180, north: 90 };
        if (!bounds) return null;

        const filters = normalizeFilters(this.inMapArea ? map.filters : defaultFilters);
        const hasPolygon = filters.along && Boolean(map.buffer?.buffer?.geometry);
        const polygonStatus = hasPolygon ? map.buffer.trackPolygon.status : null;
        const state = {
            bounds,
            filters,
            trackRevision: filters.along ? map.buffer?.revision ?? 0 : null,
            hasPolygon,
            polygonStatus,
            trackPolygonHash: polygonStatus === 'saved' ? map.buffer.trackPolygon.hash : null,
            trackLoading: filters.along && map.sharedTrack?.status === 'loading',
            trackFailed: filters.along && map.sharedTrack?.status === 'failed' && Boolean(map.sharedTrack.token),
        };

        return { ...state, key: JSON.stringify(state) };
    },

    loadedState() {
        return JSON.stringify({
            bounds: this.$wire.bounds,
            filters: normalizeFilters(this.$wire.filters),
            trackPolygonHash: this.$wire.trackPolygonHash ?? null,
        });
    },

    needsPolygon(state) {
        return state.trackLoading || state.trackFailed
            || (state.hasPolygon && (state.polygonStatus !== 'saved' || !state.trackPolygonHash));
    },

    updatePolygonState(state) {
        const needsPolygon = this.needsPolygon(state);
        this.waitingForPolygon = state.trackLoading || (needsPolygon && state.polygonStatus === 'saving');
        this.failed = needsPolygon && !this.waitingForPolygon;
        if (needsPolygon) this.retryMore = false;
    },

    retry() {
        if (!this.$el.isConnected || this.$wire.userId) return;

        const state = this.readMapState();
        if (state?.trackFailed) {
            window.rodnikMap.retrySharedTrack();
        } else if (state && this.needsPolygon(state) && !state.trackLoading && state.polygonStatus !== 'saving') {
            window.rodnikMap.buffer.saveTrackPolygon();
        }

        return this.refresh(this.retryMore);
    },

    async refresh(more = false) {
        if (!this.$el.isConnected || this.$wire.userId) return;

        const latest = this.readMapState();
        if (!latest) return;

        this.pendingState = latest;
        this.updatePolygonState(latest);
        if (this.busy) {
            if (latest.key !== this.activeState?.key) {
                this.retryMore = false;
            }
            return;
        }

        this.retryMore = false;
        try {
            while (this.pendingState && this.$el.isConnected) {
                const state = this.pendingState;
                this.pendingState = null;
                this.activeState = state;
                this.updatePolygonState(state);
                if (this.needsPolygon(state)) return;

                try {
                    const trackPolygonHash = state.trackPolygonHash;
                    const requested = JSON.stringify({ bounds: state.bounds, filters: state.filters, trackPolygonHash });

                    if (requested !== this.loadedState()) {
                        this.positionLoader();
                        this.busy = true;
                        this.retryMore = false;
                        await this.$wire.updateMap(state.bounds, state.filters, trackPolygonHash);
                        if (!this.$el.isConnected) return;

                        if (requested !== this.loadedState()) {
                            if (state.hasPolygon && this.readMapState()?.key === state.key) {
                                window.rodnikMap.buffer.trackPolygon.clear();
                            }
                            throw new Error('The report filters could not be applied.');
                        }

                        window.scrollTo({ top: 0, behavior: 'instant' });
                    } else if (more) {
                        this.positionLoader();
                        this.busy = true;
                        this.retryMore = true;
                        await this.$wire.showMore();
                    }

                    this.retryMore = false;
                } catch {
                    if (!this.pendingState || this.pendingState.key === state.key) {
                        this.failed = true;
                        break;
                    }
                }

                more = false;
            }
        } finally {
            this.activeState = null;
            this.busy = false;
        }
    },
});
