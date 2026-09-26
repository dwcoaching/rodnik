import { routePreview, libraryDate, mapCoordinates } from './libraryPreview.js';

const fields = ['id', 'title', 'slug', 'url', 'version', 'starred', 'created_at', 'edit_url', 'details_url', 'star_url', 'delete_url', 'track_name', 'preview', 'center', 'updated_at'];
const normalize = record => record && Number.isSafeInteger(record.id) && typeof record.title === 'string'
    && typeof record.url === 'string' && Number.isSafeInteger(record.version) && record.version > 0
    ? Object.fromEntries(fields.filter(key => key in record).map(key => [key, record[key]])) : null;
const initialItems = config => {
    const items = (config.items ?? []).map(normalize).filter(Boolean);
    const target = normalize(config.editingRecord);
    return target ? [target, ...items.filter(item => item.id !== target.id)] : items;
};

export default (config = {}) => ({
    preview: routePreview,
    coordinates: mapCoordinates,
    date(value) { return libraryDate(value, config.locale); },
    items: initialItems(config),
    query: config.query ?? '',
    favorites: config.favorites === true,
    nextPage: config.nextPage ?? null,
    loadedThroughPage: config.currentPage ?? 1,
    total: config.total ?? 0,
    loading: false,
    error: '',
    editingRecord: normalize(config.editingRecord),
    deletingRecord: null,
    dialogTrigger: null,
    disposed: false,
    controller: null,
    searchTimer: null,
    revision: 0,
    retryAppend: false,
    retryPreserveWindow: false,

    invalidate() {
        clearTimeout(this.searchTimer);
        this.controller?.abort();
        this.controller = null;
        this.revision += 1;
    },

    scheduleSearch() {
        if (this.disposed) return;
        this.invalidate();
        this.loading = true;
        this.error = '';
        this.searchTimer = setTimeout(() => this.load(), config.debounce ?? 300);
    },

    filterChanged() {
        if (this.disposed) return;
        this.invalidate();
        return this.load();
    },

    optionsUrl(append, nextPage = this.nextPage) {
        const base = new URL(config.endpoint, config.indexUrl);
        if (append) {
            const next = new URL(nextPage, base);
            if (next.origin !== base.origin || next.pathname !== base.pathname) throw new Error();
            return next.href;
        }
        base.search = '';
        if (this.query.trim()) base.searchParams.set('q', this.query.trim());
        if (this.favorites) base.searchParams.set('favorites', '1');
        return base.href;
    },

    async load(append = false, preserveWindow = false) {
        if (this.disposed || (append && (!this.nextPage || this.loading))) return;
        this.invalidate();
        const revision = this.revision;
        const targetPage = preserveWindow ? this.loadedThroughPage : 1;
        this.controller = new AbortController();
        this.loading = true;
        this.error = '';
        this.retryAppend = append;
        this.retryPreserveWindow = preserveWindow;
        try {
            let url = this.optionsUrl(append);
            if (!append) this.nextPage = null;
            const incoming = [];
            let body;
            let currentPage;
            do {
                const response = await (config.fetch ?? globalThis.fetch)(url, {
                    credentials: 'same-origin', signal: this.controller.signal,
                    headers: { Accept: 'application/json', 'X-Rodnik-Locale': config.locale ?? 'en' },
                });
                if (this.disposed || revision !== this.revision) return;
                if (!response.ok) {
                    if ([401, 419].includes(response.status)) throw new Error(config.sessionMessage ?? config.loadMessage);
                    if (response.status === 429) throw new Error(config.rateLimitMessage ?? config.loadMessage);
                    throw new Error(config.loadMessage);
                }
                body = await response.json();
                if (this.disposed || revision !== this.revision) return;
                if (!Array.isArray(body.data) || !Number.isSafeInteger(body.total) || body.total < 0) throw new Error(config.loadMessage);
                const records = body.data.map(normalize);
                if (records.some(record => !record)) throw new Error(config.loadMessage);
                incoming.push(...records);
                currentPage = Number(new URL(url).searchParams.get('page') || 1);
                if (!Number.isSafeInteger(currentPage) || currentPage < 1) throw new Error(config.loadMessage);
                if (append || !preserveWindow || currentPage >= targetPage || !body.next_page_url) break;
                const next = this.optionsUrl(true, body.next_page_url);
                if (Number(new URL(next).searchParams.get('page')) !== currentPage + 1) throw new Error(config.loadMessage);
                url = next;
            } while (true);
            const records = incoming.map(record => {
                const existing = this.items.find(item => item.id === record.id);
                return existing?.version > record.version ? existing : record;
            }).filter(record => record && (!this.favorites || record.starred));
            this.items = [...new Map([...(append ? this.items : []), ...records].map(item => [item.id, item])).values()];
            this.nextPage = typeof body.next_page_url === 'string' ? body.next_page_url : null;
            this.loadedThroughPage = currentPage;
            this.total = body.total;
        } catch (error) {
            if (!this.disposed && revision === this.revision && error.name !== 'AbortError') this.error = error.message || config.loadMessage;
        } finally {
            if (revision === this.revision) {
                this.loading = false;
                this.controller = null;
            }
        }
    },

    rowConfig(record) {
        return {
            ...config.messages, ...record, locale: config.locale, csrfToken: config.csrfToken,
            autoEdit: false, slugEndpoint: config.slugEndpoint,
            fetch: config.fetch, clipboard: config.clipboard,
            endpoint: record.details_url, starEndpoint: record.star_url, deleteEndpoint: record.delete_url,
            onSaved: change => this.applyRecord(change), onDeleted: id => this.removeRecord(id),
        };
    },

    editConfig(record) {
        return { ...this.rowConfig(record), autoEdit: true, onEditClose: () => this.closeEdit() };
    },

    openEdit(record, trigger) {
        if (this.disposed || this.editingRecord || this.deletingRecord) return;
        this.dialogTrigger = trigger;
        this.editingRecord = { ...record };
    },

    closeEdit() {
        this.editingRecord = null;
        this.restoreFocus();
    },

    deleteConfig(record) {
        return { ...this.rowConfig(record), confirmDelete: true, onClose: () => this.closeDelete() };
    },

    openDelete(record, trigger) {
        if (this.disposed || this.deletingRecord) return;
        this.dialogTrigger = trigger;
        this.deletingRecord = { ...record };
    },

    closeDelete() {
        this.deletingRecord = null;
        this.restoreFocus();
    },

    restoreFocus() {
        const trigger = this.dialogTrigger;
        this.dialogTrigger = null;
        this.$nextTick?.(() => {
            if (this.disposed) return;
            if (trigger?.isConnected) trigger.focus();
            else this.$refs?.search?.focus();
        });
    },

    applyRecord(change) {
        if (this.disposed) return;
        const original = this.items.find(record => record.id === change.id);
        if (!original || original.id !== change.id) return;
        const record = normalize({ ...original, ...change });
        if (!record) return;
        if (this.favorites && original.starred && !record.starred) this.total = Math.max(0, this.total - 1);
        this.items = this.items.map(item => item.id === record.id ? record : item).filter(item => !this.favorites || item.starred);
        // Mutations can change both filter membership and offset pagination order.
        void this.load(false, true);
    },

    removeRecord(id) {
        if (this.disposed) return;
        this.items = this.items.filter(record => record.id !== id);
        this.total = Math.max(0, this.total - 1);
        void this.load(false, true);
    },

    destroy() {
        this.disposed = true;
        this.invalidate();
    },
});
