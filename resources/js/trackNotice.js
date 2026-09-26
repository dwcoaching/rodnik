const visibleStatuses = ['local', 'saving', 'saved', 'failed', 'missing'];

export default track => ({
    track,
    visible: false,
    hideTimer: null,
    revision: 0,
    disposed: false,

    init() {
        this.refresh();
        this.$watch('track', () => this.refresh());
    },

    refresh() {
        this.clearTimer();
        if (this.disposed) return;
        this.visible = visibleStatuses.includes(this.track.status)
            && (this.track.status !== 'saved' || this.track.uploaded === true);
        if (!this.visible || this.track.status !== 'saved') return;
        const revision = this.revision;
        this.hideTimer = setTimeout(() => {
            if (this.disposed || revision !== this.revision || this.track.status !== 'saved') return;
            this.visible = false;
            this.hideTimer = null;
        }, 3000);
    },

    clearTimer() {
        this.revision++;
        if (this.hideTimer !== null) clearTimeout(this.hideTimer);
        this.hideTimer = null;
    },

    destroy() {
        this.disposed = true;
        this.clearTimer();
        this.visible = false;
    },
});
