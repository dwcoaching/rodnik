import mapLinkField, { randomMapSlug } from './mapLinkField.js';
import { fullMapUrl } from './mapLink.js';
import { normalizeSharedMapState } from './sharedMapState.js';
import { resourceNavigationUrl } from './navigationState.js';

export function sharedMapFingerprint(state, trackReference = null) {
    const normalized = normalizeSharedMapState(state);
    return JSON.stringify({
        ...normalized,
        center: normalized.center.map(value => Math.round(value * 1e8) / 1e8),
        zoom: Math.round(normalized.zoom * 1e8) / 1e8,
        trackToken: trackReference,
    });
}

const reference = track => track?.token ?? null;
const pendingStatuses = ['local', 'saving', 'loading', 'failed'];

export default (config = {}) => ({
    ...mapLinkField(config),
    opened: false,
    saving: false,
    confirmation: null,
    dirty: false,
    busy: false,
    ready: false,
    error: '',
    notice: '',
    copied: false,
    copiedSaved: false,
    copying: false,
    contextVersion: 0,
    linkUrl: '',
    draftTitle: '',
    draftSlug: '',
    fieldErrors: {},
    disposed: false,
    focusRequest: 0,
    feedbackTimer: null,
    requestController: null,
    removeListeners: null,

    map() { return config.getMap?.() ?? globalThis.window?.rodnikMap; },
    sharedMap() {
        void this.contextVersion;
        return config.getSharedMap ? config.getSharedMap() : globalThis.window?.rodnikSharedMap;
    },
    ownerId() {
        void this.contextVersion;
        return globalThis.window && Object.hasOwn(globalThis.window, 'rodnikOwnerId') ? globalThis.window.rodnikOwnerId : config.ownerId;
    },
    canSave() { return !this.disposed && Number.isSafeInteger(this.ownerId()) && this.ownerId() > 0; },
    trackPending() { return pendingStatuses.includes(this.map()?.sharedTrack?.status); },
    trackFailed() { return this.map()?.sharedTrack?.status === 'failed'; },
    trackMissing() { return this.map()?.sharedTrack?.status === 'missing'; },

    shareRecord(result) {
        if (!Number.isSafeInteger(result?.id) || result.id < 1) throw new Error(config.errorMessage);
        const parsed = new URL(result.url);
        const origin = new URL(config.endpoint, config.baseUrl ?? globalThis.window?.location?.href ?? parsed.href).origin;
        if (!['http:', 'https:'].includes(parsed.protocol) || parsed.origin !== origin || parsed.username || parsed.password
            || !/^\/(?:ru\/)?maps\/[a-z0-9][a-z0-9-]{2,79}\/?$/.test(parsed.pathname)
            || parsed.search || parsed.hash) throw new Error(config.errorMessage);
        return result;
    },

    canUpdate() {
        const record = this.sharedMap();
        if (!this.canSave() || record?.can_update !== true || !Number.isSafeInteger(record.version)
            || record.version < 1 || record.version > 4294967294) return false;
        try { return this.shareRecord(record) === record; } catch { return false; }
    },

    savedUrl() {
        if (this.disposed) return '';
        try { return this.shareRecord(this.sharedMap()).url; } catch { return ''; }
    },

    refreshDirty() {
        const map = this.map();
        this.dirty = false;
        if (this.disposed || !this.ready || !this.canUpdate() || !map || map.restoringSharedState
            || map.restoringNavigationState || globalThis.window?.rodnikNavigation?.restoring) return false;
        try {
            const original = this.sharedMap();
            this.dirty = this.trackPending() || sharedMapFingerprint(map.captureSharedState(), reference(map.sharedTrack))
                !== sharedMapFingerprint(original.state, reference(original.track));
        } catch {}
        return this.dirty;
    },

    refresh() {
        this.contextVersion++;
        this.refreshDirty();
        if (!this.opened || this.disposed || !this.ready) return;
        this.linkUrl = '';
        if (this.trackPending()) return;
        try {
            const map = this.map();
            const state = map.captureSharedState();
            const href = globalThis.window?.location?.href;
            const resource = href ? resourceNavigationUrl(href, state.page) : null;
            if (resource && resource.pathname.replace(/\/$/, '') === new URL(href).pathname.replace(/\/$/, '')
                && new URL(href).searchParams.get('redirect') === 'false') resource.searchParams.set('redirect', 'false');
            this.linkUrl = fullMapUrl({ state, track: map.sharedTrack?.status === 'missing' ? null : map.sharedTrack,
                resource_url: resource?.href },
                config.baseUrl ?? globalThis.window?.rodnikPublicBaseUrl ?? globalThis.window?.location?.href);
        } catch { this.error = config.errorMessage; }
    },

    async init() {
        const map = this.map();
        if (!map) return;
        await map.ready;
        if (this.disposed || this.map() !== map) return;
        this.ready = true;
        const events = ['map-state-changed', 'map-viewport-changed', 'map-filters-changed', 'map-track-changed',
            'map-track-persistence-changed', 'map-shared-state-restored', 'rodnik:navigated'];
        const refresh = () => this.refresh();
        this.removeListeners?.();
        events.forEach(event => globalThis.window?.addEventListener(event, refresh));
        this.removeListeners = () => events.forEach(event => globalThis.window?.removeEventListener(event, refresh));
        this.refreshDirty();
    },

    destroy() {
        this.disposed = true;
        this.ready = false;
        this.confirmation = null;
        this.focusRequest++;
        this.requestController?.abort();
        this.disposeLinkField();
        this.removeListeners?.();
        clearTimeout(this.feedbackTimer);
    },

    clearFeedback() {
        clearTimeout(this.feedbackTimer);
        this.error = '';
        this.fieldErrors = {};
        this.notice = '';
        this.copied = false;
        this.copiedSaved = false;
    },

    focusLink(select = false, target = 'link') {
        const revision = ++this.focusRequest;
        const focus = () => (config.afterShow ?? (callback => {
            if (typeof requestAnimationFrame === 'function') requestAnimationFrame(callback);
            else callback();
        }))(() => {
            if (this.disposed || !this.opened || revision !== this.focusRequest) return;
            const element = this.$refs?.[target];
            element?.focus();
            if (element && ['link', 'savedLink'].includes(target)) {
                if (element.setSelectionRange) {
                    element.setSelectionRange(0, select ? element.value.length : 0, select ? 'backward' : 'none');
                } else if (select) element.select?.();
                element.scrollLeft = 0;
            } else if (select) element?.select?.();
        });
        if (this.$nextTick) this.$nextTick(focus);
        else focus();
    },

    open() {
        if (this.disposed || !this.ready || this.busy) return false;
        this.opened = true;
        this.saving = false;
        this.confirmation = null;
        this.clearFeedback();
        this.refresh();
        this.focusLink();
        return true;
    },

    close() {
        if (this.disposed) return;
        const revision = ++this.focusRequest;
        this.opened = false;
        const restoreFocus = () => {
            if (!this.disposed && !this.opened && revision === this.focusRequest) this.$refs?.trigger?.focus();
        };
        if (this.$nextTick) this.$nextTick(restoreFocus);
        else restoreFocus();
    },

    defaultMapTitle() {
        const locale = (config.locale ?? globalThis.window?.rodnikLocale) === 'ru' ? 'ru-RU' : 'en-US';
        const date = new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date());
        const title = (config.defaultTitleDate ?? (locale === 'ru-RU' ? 'Карта от :date' : 'Map of :date')).replace(':date', date);
        let center;
        try { center = this.map()?.captureSharedState()?.center; } catch {}
        if (!Array.isArray(center) || center.length !== 2 || !center.every(Number.isFinite)
            || Math.abs(center[0]) > 180 || Math.abs(center[1]) > 90) return title;
        const coordinate = value => Number(value.toFixed(2)).toFixed(2);
        return `${title} (${coordinate(center[1])}, ${coordinate(center[0])})`;
    },

    beginSave() {
        if (!this.opened || !this.canSave() || this.busy || this.trackPending()) return false;
        this.confirmation = null;
        this.clearFeedback();
        this.draftTitle = [this.map()?.sharedTrack?.name, this.sharedMap()?.title]
            .find(title => typeof title === 'string' && title.trim()) ?? this.defaultMapTitle();
        this.resetLinkField({ slug: randomMapSlug() });
        this.saving = true;
        this.focusLink(false, 'title');
        return true;
    },

    cancelSave() {
        if (this.busy) return;
        this.saving = false;
        this.clearFeedback();
        this.focusLink();
    },

    async retry() {
        if (this.disposed || this.busy || !this.ready || !this.trackFailed()) return false;
        this.clearFeedback();
        try { await this.map().retrySharedTrack(); } catch (error) { this.error = error.message || config.errorMessage; }
        if (this.disposed) return false;
        this.refresh();
        return !this.trackPending();
    },

    async save() { return this.persist(false); },
    async update() { return this.persist(true); },

    async persist(update) {
        if (this.disposed || this.busy || this.confirmation || !this.ready || !this.opened || !this.canSave()
            || (update ? !this.canUpdate() : !this.saving)) return false;
        if (!update && !this.draftTitle.trim()) {
            this.clearFeedback();
            this.fieldErrors.title = [config.titleRequiredMessage];
            this.focusLink(false, 'title');
            return false;
        }
        const map = this.map();
        const original = this.sharedMap();
        const id = original?.id;
        const version = original?.version;
        const ownerId = this.ownerId();
        const current = () => !this.disposed && this.map() === map && this.sharedMap() === original
            && this.ownerId() === ownerId && (!update || (original.id === id && original.version === version && original.can_update === true));
        const message = update ? (config.updateErrorMessage ?? config.errorMessage) : config.errorMessage;
        this.busy = true;
        this.clearFeedback();
        this.requestController = new AbortController();
        const signal = AbortSignal.any([this.requestController.signal, AbortSignal.timeout(config.timeoutMs ?? 30000)]);
        try {
            if (!update && !await this.validateSlug()) return false;
            if (!current()) return false;
            const track = map.sharedTrack?.status === 'missing' ? null : await map.ensureSharedTrack();
            if (!current()) return false;
            if (this.trackPending()) throw new Error(config.trackErrorMessage ?? message);
            const state = normalizeSharedMapState(map.captureSharedState());
            const payload = { state, track_token: track?.token ?? null,
                ...(update ? { version } : { title: this.draftTitle.trim(), slug: this.draftSlug.trim() || null }) };
            const fingerprint = sharedMapFingerprint(state, reference(track));
            const response = await (config.fetch ?? globalThis.fetch)(update ? `${config.endpoint.replace(/\/$/, '')}/${id}` : config.endpoint, {
                method: update ? 'PATCH' : 'POST', credentials: 'same-origin', signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken,
                    'X-Rodnik-Locale': config.locale ?? globalThis.window?.rodnikLocale ?? 'en' },
                body: JSON.stringify(payload),
            });
            if (!current()) return false;
            if (response.status === 409) throw new Error(config.conflictMessage ?? message);
            if ([401, 419].includes(response.status)) throw new Error(config.sessionMessage ?? message);
            if (response.status === 429) throw new Error(config.rateLimitMessage ?? message);
            if (response.status === 422) {
                const result = await response.json().catch(() => ({}));
                if (!current()) return false;
                this.fieldErrors = Object.fromEntries(['title', 'slug'].flatMap(field => {
                    const messages = result.errors?.[field];
                    return Array.isArray(messages) && typeof messages[0] === 'string' ? [[field, messages]] : [];
                }));
                if (this.fieldErrors.slug) {
                    this.slugStatus = 'invalid';
                    this.slugNotice = this.fieldErrors.slug[0];
                }
                throw new Error(Object.values(result.errors ?? {}).flat()[0] ?? message);
            }
            if (!response.ok) throw new Error(message);
            const result = this.shareRecord(await response.json());
            if (!current()) return false;
            let responseFingerprint;
            try { responseFingerprint = sharedMapFingerprint(result.state, reference(result.track)); }
            catch { throw new Error(message); }
            if (result.can_update !== true || !Number.isSafeInteger(result.version) || result.version < 1
                || result.version > 4294967295 || (update && (result.id !== id || result.version !== version + 1))
                || responseFingerprint !== fingerprint) throw new Error(message);
            const record = { ...result, resource_url: result.resource_url ?? original?.resource_url };
            if (globalThis.window?.rodnikNavigation?.savedMap) globalThis.window.rodnikNavigation.savedMap(record);
            else if (globalThis.window) globalThis.window.rodnikSharedMap = record;
            this.saving = false;
            this.confirmation = update ? 'updated' : 'created';
            this.refresh();
            this.notice = update ? config.updatedMessage : config.savedMessage;
            this.focusLink(false, 'savedLink');
            return true;
        } catch (error) {
            if (current()) this.error = ['TypeError', 'SyntaxError', 'AbortError', 'TimeoutError'].includes(error.name)
                ? message : error.message || message;
            return false;
        } finally {
            this.busy = false;
            this.requestController = null;
        }
    },

    async copy(saved = false) {
        if (this.disposed || this.busy || this.copying) return false;
        if (this.confirmation) saved = true;
        if (!saved) this.refresh();
        const url = saved ? this.savedUrl() : this.linkUrl;
        if (!url) return false;
        this.clearFeedback();
        this.copying = true;
        try {
            const clipboard = config.clipboard ?? globalThis.navigator?.clipboard;
            if (!clipboard?.writeText) throw new Error('Clipboard unavailable');
            await clipboard.writeText(url);
            if (this.disposed) return false;
            this.copied = !saved;
            this.copiedSaved = saved;
            this.notice = config.copiedMessage;
            this.feedbackTimer = setTimeout(() => this.clearFeedback(), config.feedbackDuration ?? 3500);
            this.feedbackTimer?.unref?.();
            return true;
        } catch {
            if (this.disposed) return false;
            this.notice = config.copyMessage;
            this.focusLink(true, saved ? 'savedLink' : 'link');
            return false;
        } finally {
            this.copying = false;
        }
    },
});
