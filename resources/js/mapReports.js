const defaultFilters = {
    spring: true,
    water_well: true,
    water_tap: true,
    drinking_water: true,
    fountain: true,
    other: true,
    confirmed: false,
    along: false,
};

const normalizeFilters = (filters) => Object.fromEntries(
    Object.entries(defaultFilters).map(([key, value]) => [key, Boolean(filters?.[key] ?? value)]),
);
const mapChanged = Symbol('mapChanged');

export default () => ({
    busy: false,
    failed: false,
    retryMore: false,
    pendingState: null,
    activeState: null,
    interruptPolygonWait: null,
    loaderTop: null,

    init() {
        this.$nextTick(() => {
            this.positionLoader();
            return this.refresh();
        });
    },

    positionLoader() {
        if (!this.$el.isConnected || this.$wire.userId) return;

        this.loaderTop = Math.max(0, this.$refs.reportsList.getBoundingClientRect().top);
    },

    readMapState() {
        const map = window.rodnikMap;
        const bounds = map?.getViewportBounds();
        if (!bounds) return null;

        const filters = normalizeFilters(map.filters);
        const state = {
            bounds,
            filters,
            trackRevision: filters.along ? map.buffer?.revision ?? 0 : null,
            hasPolygon: filters.along && Boolean(map.buffer?.buffer?.geometry),
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

    async refresh(more = false) {
        if (!this.$el.isConnected || this.$wire.userId) return;

        const latest = this.readMapState();
        if (!latest) return;

        this.pendingState = latest;
        if (this.busy) {
            if (latest.key !== this.activeState?.key) {
                this.retryMore = false;
                this.interruptPolygonWait?.();
            }
            return;
        }

        this.positionLoader();
        this.busy = true;
        this.failed = false;
        this.retryMore = false;
        try {
            while (this.pendingState && this.$el.isConnected) {
                const state = this.pendingState;
                this.pendingState = null;
                this.activeState = state;

                try {
                    let trackPolygonHash = null;

                    if (state.hasPolygon) {
                        const changed = new Promise((resolve) => {
                            this.interruptPolygonWait = () => resolve(mapChanged);
                        });
                        const record = await Promise.race([
                            window.rodnikMap.buffer.saveTrackPolygon(),
                            changed,
                        ]);
                        this.interruptPolygonWait = null;
                        if (!this.$el.isConnected) return;

                        const current = this.readMapState();
                        if (record === mapChanged || current?.key !== state.key) {
                            this.pendingState = current;
                            more = false;
                            this.retryMore = false;
                            continue;
                        }

                        if (!record?.hash) throw new Error('The track polygon could not be saved.');
                        trackPolygonHash = record.hash;
                    }

                    const requested = JSON.stringify({ bounds: state.bounds, filters: state.filters, trackPolygonHash });

                    if (requested !== this.loadedState()) {
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
            this.interruptPolygonWait = null;
            this.activeState = null;
            this.busy = false;
        }
    },
});
