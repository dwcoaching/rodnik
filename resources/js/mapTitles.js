import mapLinkField from './mapLinkField.js';

const normalizedSlug = slug => slug.trim().toLowerCase() || null;
const validSavedLink = (record, previousUrl) => {
    if (typeof record?.slug !== 'string' || typeof record?.url !== 'string') return false;
    if (record.slug.length < 3 || record.slug.length > 80 || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(record.slug)) return false;
    try {
        const url = new URL(record.url);
        return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password
            && url.origin === new URL(previousUrl).origin
            && url.pathname.replace(/\/$/, '').split('/').at(-1) === record.slug;
    } catch {
        return false;
    }
};

export default (config = {}) => ({
    ...mapLinkField(config),
    id: config.id,
    title: config.title ?? '',
    draftTitle: config.title ?? '',
    slug: config.slug ?? '',
    draftSlug: config.slug ?? '',
    url: config.url ?? '',
    version: config.version,
    starred: config.starred === true,
    inlineEditing: false,
    menuOpen: false,
    confirmingDelete: config.confirmDelete === true,
    busy: false,
    copying: false,
    copied: false,
    saved: false,
    manualCopyUrl: '',
    error: '',
    fieldErrors: {},
    disposed: false,
    deleted: false,
    controller: null,
    feedbackTimer: null,

    init() {
        if (config.autoEdit) this.edit();
        if (this.confirmingDelete) this.focus('cancelDelete');
    },

    focus(name, select = false) {
        this.$nextTick?.(() => {
            const apply = () => {
                if (this.disposed || (['title', 'slug'].includes(name) && !this.inlineEditing)
                    || (name === 'edit' && this.inlineEditing)
                    || (name === 'cancelDelete' && !this.confirmingDelete)
                    || (name === 'link' && !this.manualCopyUrl)) return;
                this.$refs?.[name]?.focus();
                if (select) this.$refs?.[name]?.select();
            };
            if (globalThis.requestAnimationFrame) globalThis.requestAnimationFrame(apply);
            else apply();
        });
    },

    clearFeedback() {
        this.error = '';
        this.fieldErrors = {};
        this.saved = false;
        this.copied = false;
        this.manualCopyUrl = '';
        clearTimeout(this.feedbackTimer);
    },

    receive(record) {
        if (!record || record.id !== this.id || this.disposed || this.busy || this.inlineEditing
            || this.draftTitle !== this.title || this.draftSlug !== this.slug) return;
        if (Number.isSafeInteger(this.version) && record.version < this.version) return;
        this.title = record.title;
        this.draftTitle = record.title;
        this.slug = record.slug;
        this.draftSlug = this.slug;
        this.version = record.version;
        this.starred = record.starred === true;
        if (this.url !== record.url) this.clearFeedback();
        this.url = record.url;
    },

    edit() {
        if (this.busy || this.disposed) return;
        if (!this.inlineEditing) {
            this.clearFeedback();
            this.draftTitle = this.title;
            this.resetLinkField({ slug: this.slug, id: this.id });
        }
        this.menuOpen = false;
        this.inlineEditing = true;
        this.focus('title', true);
    },

    cancelInline() {
        if (this.busy || this.disposed) return;
        this.inlineEditing = false;
        this.draftTitle = this.title;
        this.draftSlug = this.slug;
        this.clearFeedback();
        this.disposeLinkField();
        if (config.onEditClose) config.onEditClose();
        else this.focus('edit');
    },

    async saveInline(restoreFocus = false) {
        if (!this.inlineEditing || this.busy || this.disposed) return;
        await this.persistDetails();
        const active = globalThis.document?.activeElement;
        if (restoreFocus && !this.inlineEditing && (!active || active === globalThis.document?.body || this.$refs?.editor?.contains(active))) {
            this.focus('edit');
        }
    },

    async cancel() {
        if (this.busy || this.disposed) return;
        this.draftTitle = this.title;
        this.draftSlug = this.slug;
        this.clearFeedback();
        await this.close();
    },

    async close() {
        const wasBusy = this.busy;
        this.busy = true;
        try {
            await config.onClose?.();
            if (!this.disposed) {
                this.confirmingDelete = false;
            }
        } catch {
            if (!this.disposed) this.error = config.errorMessage;
        } finally {
            this.busy = wasBusy;
        }
    },

    async request(endpoint, method, payload, fallback) {
        this.controller = new AbortController();
        const response = await (config.fetch ?? globalThis.fetch)(endpoint, {
            method, credentials: 'same-origin', signal: this.controller.signal,
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken, 'X-Rodnik-Locale': config.locale ?? 'en',
            },
            ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
        });
        if (this.disposed) return null;
        if (!response.ok) {
            if ([401, 419].includes(response.status)) throw new Error(config.sessionMessage ?? fallback);
            if (response.status === 409) throw new Error(config.conflictMessage ?? fallback);
            if (response.status === 429) throw new Error(config.rateLimitMessage ?? fallback);
            if (response.status === 422) {
                const body = await response.json().catch(() => ({}));
                if (this.disposed) return null;
                const errors = Object.fromEntries(['title', 'slug'].flatMap(field => {
                    const messages = body.errors?.[field];
                    return Array.isArray(messages) && typeof messages[0] === 'string' ? [[field, messages]] : [];
                }));
                if (Object.keys(errors).length) {
                    this.fieldErrors = errors;
                    if (errors.slug) {
                        this.slugStatus = 'invalid';
                        this.slugNotice = errors.slug[0];
                    }
                    throw new Error(Object.values(errors)[0][0]);
                }
            }
            throw new Error(fallback);
        }
        return response.status === 204 ? {} : response.json();
    },

    async persistDetails() {
        const title = this.draftTitle.trim();
        if (!title) {
            this.clearFeedback();
            this.fieldErrors.title = [config.titleRequiredMessage];
            this.focus('title');
            return;
        }
        const slug = normalizedSlug(this.draftSlug);
        if (title === this.title && slug === normalizedSlug(this.slug)) {
            this.draftTitle = this.title;
            this.draftSlug = this.slug;
            this.inlineEditing = false;
            config.onEditClose?.();
            return;
        }
        if (!config.endpoint || !Number.isSafeInteger(this.version) || this.version < 1) return;
        this.busy = true;
        this.clearFeedback();
        try {
            if (!await this.validateSlug() || this.disposed) return;
            const record = await this.request(config.endpoint, 'PATCH', { title, slug, version: this.version }, config.errorMessage);
            if (this.disposed) return;
            if (typeof record?.title !== 'string' || !record.title.trim() || !Number.isSafeInteger(record.version) || record.version <= this.version
                || record.id !== this.id || !validSavedLink(record, this.url)) {
                throw new Error(config.errorMessage);
            }
            this.clearFeedback();
            this.title = record.title;
            this.draftTitle = record.title;
            this.slug = record.slug;
            this.draftSlug = record.slug;
            this.url = record.url;
            this.version = record.version;
            this.inlineEditing = false;
            config.onSaved?.({ id: this.id, title: this.title, slug: this.slug, url: this.url, version: this.version });
            config.onEditClose?.();
            this.saved = true;
            this.feedbackTimer = setTimeout(() => { this.saved = false; }, config.feedbackDuration ?? 2500);
        } catch (error) {
            if (!this.disposed) this.error = error.message || config.errorMessage;
        } finally {
            this.busy = false;
            this.controller = null;
        }
    },

    async toggleStar() {
        if (this.busy || this.disposed || !config.starEndpoint) return;
        this.busy = true;
        this.clearFeedback();
        try {
            const record = await this.request(config.starEndpoint, 'PATCH', { starred: !this.starred }, config.starMessage ?? config.errorMessage);
            if (this.disposed) return;
            if (typeof record?.starred !== 'boolean' || (record.id !== undefined && record.id !== this.id)) throw new Error(config.starMessage ?? config.errorMessage);
            this.starred = record.starred;
            config.onSaved?.({ id: this.id, starred: this.starred });
        } catch (error) {
            if (!this.disposed) this.error = error.message || config.starMessage || config.errorMessage;
        } finally {
            this.busy = false;
            this.controller = null;
        }
    },

    async remove() {
        if (this.busy || this.disposed || !this.confirmingDelete || !config.deleteEndpoint) return;
        this.busy = true;
        this.clearFeedback();
        try {
            if (!this.deleted) {
                await this.request(config.deleteEndpoint, 'DELETE', undefined, config.deleteMessage ?? config.errorMessage);
                if (this.disposed) return;
                this.deleted = true;
                config.onDeleted?.(this.id);
            }
            await this.close();
        } catch (error) {
            if (!this.disposed) this.error = error.message || config.deleteMessage || config.errorMessage;
        } finally {
            this.busy = false;
            this.controller = null;
        }
    },

    canCopyLink() {
        if (this.disposed || this.copying || !this.url) return false;
        try {
            const url = new URL(this.url);
            return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password;
        } catch {
            return false;
        }
    },

    async copy() {
        if (!this.canCopyLink()) return;
        const url = this.url;
        const current = () => !this.disposed && this.url === url;
        this.clearFeedback();
        this.copying = true;
        try {
            const clipboard = config.clipboard ?? globalThis.navigator?.clipboard;
            if (!clipboard?.writeText) throw new Error();
            await clipboard.writeText(url);
            if (!current()) return;
            this.copied = true;
            this.feedbackTimer = setTimeout(() => { this.copied = false; }, config.feedbackDuration ?? 2500);
        } catch {
            if (!current()) return;
            this.error = config.copyMessage ?? config.errorMessage;
            this.manualCopyUrl = url;
            this.focus('link', true);
        } finally {
            this.copying = false;
        }
    },

    destroy() {
        this.disposed = true;
        this.controller?.abort();
        this.disposeLinkField();
        clearTimeout(this.feedbackTimer);
    },
});
