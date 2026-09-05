export default () => ({
    busy: false,
    failed: false,
    retryMore: false,
    pendingBounds: null,
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

    async refresh(more = false) {
        if (!this.$el.isConnected || this.$wire.userId) return;

        this.pendingBounds = window.rodnikMap.getViewportBounds();
        if (!this.pendingBounds || this.busy) return;

        this.positionLoader();
        this.busy = true;
        this.failed = false;
        this.retryMore = more;

        try {
            do {
                const bounds = this.pendingBounds;
                this.pendingBounds = null;

                if (JSON.stringify(bounds) !== JSON.stringify(this.$wire.bounds)) {
                    await this.$wire.updateBounds(bounds);
                    if (!this.$el.isConnected) return;

                    window.scrollTo({ top: 0, behavior: 'instant' });
                } else if (more) {
                    await this.$wire.showMore();
                }

                more = false;
                this.retryMore = false;
            } while (this.pendingBounds && this.$el.isConnected);
        } catch {
            this.failed = true;
        } finally {
            this.busy = false;
        }
    },
});
