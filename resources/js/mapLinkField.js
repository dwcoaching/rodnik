const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';

export function randomMapSlug(crypto = globalThis.crypto) {
    const bytes = new Uint8Array(16);
    let slug = '';
    while (slug.length < 8) {
        crypto.getRandomValues(bytes);
        for (const byte of bytes) {
            if (byte < 252) slug += alphabet[byte % alphabet.length];
            if (slug.length === 8) break;
        }
    }
    return slug;
}

export default (config = {}) => ({
    slugStatus: 'idle',
    slugNotice: '',
    slugOriginal: '',
    slugMapId: null,
    slugTimer: null,
    slugController: null,
    slugRevision: 0,
    slugPromise: null,
    slugCheckedValue: null,

    resetLinkField({ slug = '', id = null } = {}) {
        this.disposeLinkField();
        this.draftSlug = slug;
        this.slugOriginal = id ? slug : '';
        this.slugMapId = id;
        this.slugStatus = 'idle';
        this.slugNotice = '';
        if (id) {
            this.slugStatus = 'valid';
            this.slugNotice = config.slugAvailableMessage ?? '';
            return Promise.resolve(true);
        }
        return this.validateSlug();
    },

    slugValue() {
        return this.draftSlug.trim().toLowerCase();
    },

    slugInput() {
        this.disposeLinkField();
        if (this.fieldErrors) delete this.fieldErrors.slug;
        this.slugStatus = 'idle';
        this.slugNotice = '';
        this.slugTimer = setTimeout(() => this.validateSlug(), config.slugDebounce ?? 350);
    },

    validateSlug() {
        clearTimeout(this.slugTimer);
        const value = this.slugValue();
        if (this.disposed) return Promise.resolve(false);
        if (this.slugMapId && value === this.slugOriginal) {
            this.disposeLinkField();
            this.slugStatus = 'valid';
            this.slugNotice = config.slugAvailableMessage ?? '';
            return Promise.resolve(true);
        }
        if (value.length < 3 || value.length > 80 || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)) {
            this.disposeLinkField();
            this.slugStatus = 'invalid';
            this.slugNotice = config.slugInvalidMessage ?? '';
            return Promise.resolve(false);
        }
        if (this.slugCheckedValue === value && this.slugPromise) return this.slugPromise;
        this.disposeLinkField();
        if (!config.slugEndpoint) {
            this.slugStatus = 'failed';
            this.slugNotice = config.slugCheckFailedMessage ?? '';
            return Promise.resolve(false);
        }
        const revision = this.slugRevision;
        const controller = this.slugController = new AbortController();
        this.slugCheckedValue = value;
        this.slugStatus = 'checking';
        this.slugNotice = config.slugCheckingMessage ?? '';
        const current = () => !this.disposed && revision === this.slugRevision && value === this.slugValue();
        this.slugPromise = (async () => {
            try {
                const baseUrl = config.baseUrl ?? globalThis.window?.location?.href ?? (/^https?:\/\//.test(config.endpoint ?? '') ? config.endpoint : undefined);
                const url = new URL(config.slugEndpoint, baseUrl);
                url.searchParams.set('slug', value);
                if (this.slugMapId) url.searchParams.set('map', this.slugMapId);
                const response = await (config.fetch ?? globalThis.fetch)(url.href, {
                    credentials: 'same-origin', signal: controller.signal,
                    headers: { Accept: 'application/json', 'X-Rodnik-Locale': config.locale ?? 'en' },
                });
                if (!current()) return false;
                if (response.status === 422) {
                    this.slugStatus = 'invalid';
                    this.slugNotice = config.slugUnavailableMessage ?? config.slugInvalidMessage ?? '';
                    return false;
                }
                if (!response.ok) throw new Error();
                const result = await response.json();
                if (!current()) return false;
                if (result.available !== true) {
                    this.slugStatus = 'invalid';
                    this.slugNotice = config.slugUnavailableMessage ?? '';
                    return false;
                }
                this.slugStatus = 'valid';
                this.slugNotice = config.slugAvailableMessage ?? '';
                return true;
            } catch {
                if (current()) {
                    this.slugStatus = 'failed';
                    this.slugNotice = config.slugCheckFailedMessage ?? '';
                }
                return false;
            } finally {
                if (revision === this.slugRevision) {
                    this.slugController = null;
                    if (this.slugStatus === 'failed') {
                        this.slugPromise = null;
                        this.slugCheckedValue = null;
                    }
                }
            }
        })();
        return this.slugPromise;
    },

    disposeLinkField() {
        clearTimeout(this.slugTimer);
        this.slugController?.abort();
        this.slugController = null;
        this.slugPromise = null;
        this.slugCheckedValue = null;
        this.slugRevision += 1;
    },
});
