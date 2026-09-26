import { routePreview, libraryDate, mapCoordinates } from './libraryPreview.js';

const validRecord = record => record && typeof record.token === 'string' && /^[A-Za-z0-9]{10}$/.test(record.token)
    && typeof record.name === 'string' && typeof record.url === 'string'
    && Number.isSafeInteger(record.map_count) && record.map_count >= 0;

export default (config = {}) => ({
    items: [], query: '', nextPage: null, loading: true, loaded: false, error: '',
    menuToken: null, editingToken: null, draftName: '', fieldError: '',
    deletingRecord: null, dialogTrigger: null, busy: false, mutationError: '',
    controller: null, mutationController: null, searchTimer: null, revision: 0, disposed: false,
    retryAppend: false,
    preview: routePreview,
    coordinates: mapCoordinates,
    date(value) { return libraryDate(value, config.locale); },
    distance(value) {
        return Number.isFinite(value) ? new Intl.NumberFormat(config.locale, { maximumFractionDigits: 1 }).format(value) : '';
    },
    used(record) {
        const message = record.map_count === 0 ? config.unusedMessage : record.map_count === 1 ? config.usedOnceMessage : config.usedCountMessage;
        return (message ?? '').replace(':count', record.map_count);
    },
    init() { return this.load(); },
    invalidate() {
        clearTimeout(this.searchTimer);
        this.controller?.abort();
        this.revision += 1;
    },
    scheduleSearch() {
        if (this.disposed) return;
        this.invalidate();
        this.loading = true;
        this.error = '';
        this.searchTimer = setTimeout(() => this.load(), config.debounce ?? 300);
    },
    async load(append = false) {
        if (this.disposed || (append && (!this.nextPage || this.loading))) return;
        this.invalidate();
        const revision = this.revision;
        this.controller = new AbortController();
        this.loading = true;
        this.error = '';
        this.retryAppend = append;
        try {
            const endpoint = new URL(config.endpoint, config.indexUrl);
            const url = append ? new URL(this.nextPage, endpoint) : endpoint;
            if (url.origin !== endpoint.origin || url.pathname !== endpoint.pathname) throw new Error(config.loadMessage);
            if (!append) {
                url.search = '';
                if (this.query.trim()) url.searchParams.set('q', this.query.trim());
                this.nextPage = null;
            }
            const response = await (config.fetch ?? globalThis.fetch)(url.href, {
                credentials: 'same-origin', signal: this.controller.signal,
                headers: { Accept: 'application/json', 'X-Rodnik-Locale': config.locale ?? 'en' },
            });
            if (this.disposed || revision !== this.revision) return;
            if (!response.ok) throw new Error(this.responseMessage(response, config.loadMessage));
            const body = await response.json();
            if (this.disposed || revision !== this.revision) return;
            if (!Array.isArray(body.data) || !body.data.every(validRecord)) throw new Error(config.loadMessage);
            this.items = [...new Map([...(append ? this.items : []), ...body.data].map(record => [record.token, record])).values()];
            this.nextPage = typeof body.next_page_url === 'string' ? body.next_page_url : null;
            this.loaded = true;
        } catch (error) {
            if (!this.disposed && revision === this.revision && error.name !== 'AbortError') this.error = error.message || config.loadMessage;
        } finally {
            if (revision === this.revision) {
                this.loading = false;
                this.controller = null;
            }
        }
    },
    responseMessage(response, fallback) {
        if ([401, 419].includes(response.status)) return config.sessionMessage ?? fallback;
        if (response.status === 429) return config.rateLimitMessage ?? fallback;
        return fallback;
    },
    focus(name, select = false) {
        this.$nextTick?.(() => {
            if (this.disposed) return;
            const element = name === 'rename' ? this.$el?.querySelector('[data-track-rename]') : this.$refs?.[name];
            element?.focus();
            if (select) element?.select();
        });
    },
    rename(record, trigger) {
        if (this.busy) return;
        this.dialogTrigger = trigger;
        this.editingToken = record.token;
        this.draftName = record.name;
        this.fieldError = '';
        this.mutationError = '';
        this.menuToken = null;
        this.focus('rename', true);
    },
    cancelRename() {
        if (this.busy) return;
        this.editingToken = null;
        this.mutationError = '';
        this.fieldError = '';
        this.restoreFocus();
    },
    async request(endpoint, method, payload, fallback) {
        this.mutationController = new AbortController();
        const response = await (config.fetch ?? globalThis.fetch)(endpoint, {
            method, credentials: 'same-origin', signal: this.mutationController.signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken, 'X-Rodnik-Locale': config.locale ?? 'en' },
            ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
        });
        if (this.disposed) return null;
        if (!response.ok) {
            if (response.status === 422) {
                const body = await response.json().catch(() => ({}));
                if (this.disposed) return null;
                this.fieldError = typeof body.errors?.name?.[0] === 'string' ? body.errors.name[0] : '';
            }
            throw new Error(this.fieldError || this.responseMessage(response, fallback));
        }
        return response.status === 204 ? {} : response.json();
    },
    async saveName(record) {
        if (this.busy || this.disposed || this.editingToken !== record.token) return;
        if (this.draftName.trim() === record.name) { this.cancelRename(); return; }
        this.busy = true;
        this.mutationError = '';
        this.fieldError = '';
        try {
            const updated = await this.request(record.rename_url, 'PATCH', { name: this.draftName.trim() }, config.renameMessage);
            if (this.disposed) return;
            if (!validRecord(updated) || updated.token !== record.token) throw new Error(config.renameMessage);
            this.invalidate();
            this.loading = false;
            this.items = this.items.map(item => item.token === record.token ? updated : item);
            this.editingToken = null;
            this.restoreFocus();
            if (this.query.trim()) void this.load();
        } catch (error) {
            if (!this.disposed) this.mutationError = error.message || config.renameMessage;
        } finally {
            this.busy = false;
            this.mutationController = null;
        }
    },
    openDelete(record, trigger) {
        if (this.busy) return;
        this.menuToken = null;
        this.dialogTrigger = trigger;
        this.deletingRecord = record;
        this.mutationError = '';
        this.fieldError = '';
        this.focus('cancelDelete');
    },
    closeDelete() {
        if (this.busy) return;
        this.deletingRecord = null;
        this.mutationError = '';
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
    async remove() {
        if (this.busy || this.disposed || !this.deletingRecord) return;
        const record = this.deletingRecord;
        this.busy = true;
        this.mutationError = '';
        try {
            await this.request(record.delete_url, 'DELETE', undefined, config.deleteMessage);
            if (this.disposed) return;
            this.items = this.items.filter(item => item.token !== record.token);
            this.deletingRecord = null;
            this.restoreFocus();
            void this.load();
        } catch (error) {
            if (!this.disposed) this.mutationError = error.message || config.deleteMessage;
        } finally {
            this.busy = false;
            this.mutationController = null;
        }
    },
    destroy() {
        this.disposed = true;
        this.invalidate();
        this.mutationController?.abort();
    },
});
