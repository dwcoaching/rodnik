export const styleColors = ['blue', 'yellow', 'green', 'red'];

const clone = value => JSON.parse(JSON.stringify(value));
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/** Whether a value is a complete style: an order and four [size, opacity] pairs for zooms 0-10. */
export function isStyle(value) {
    return Boolean(value && Array.isArray(value.order) && value.order.length === 4
        && styleColors.every(name => value.order.includes(name))
        && value.zooms && Array.from({ length: 11 }, (_, zoom) => value.zooms[zoom]).every(row => row
            && styleColors.every(name => Array.isArray(row[name]) && row[name].length === 2
                && row[name].every(number => typeof number === 'number' && Number.isFinite(number)))));
}

/** Preview tiles: the server draws each one with the draft values for its zoom. */
export function previewUrl(draft, z, x, y) {
    const values = styleColors.flatMap(name => draft.zooms[z][name]).join(',');
    const order = draft.order.map(name => styleColors.indexOf(name)).join('');
    return `/admin/source-raster/preview/${z}/${x}/${y}.png?s=${values}&o=${order}`;
}

/**
 * State and actions of the raster lab's controls. The page supplies `host`:
 *   zoom()            the raster zoom currently on screen (0-10)
 *   onMove(callback)  call back whenever the map stops moving
 *   preview(draft)    draw the overview with a draft style, or null for the published tiles
 *   published()       a new revision was published; reload its tiles
 */
export default host => ({
    error: '',
    colors: styleColors,
    zoom: 0,
    follow: true,
    style: null,
    published: null,
    status: { revision: null, generated_at: null, enabled: false, pending: false, busy: false },
    maxDiameter: 14,
    transfer: false,
    transferText: '',

    get dirty() { return Boolean(this.style) && !same(this.style, this.published); },
    get working() { return this.status.pending || this.status.busy; },

    async init() {
        await this.load();
        host.onMove(() => { if (this.follow) this.zoom = host.zoom(); });
        this.zoom = host.zoom();
        if (this.dirty) this.changed();
        if (this.working) this.watch();
    },

    hostZoom() { return host.zoom(); },

    async request(options = {}) {
        const response = await fetch('/admin/source-raster/style', {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || `Request failed (${response.status}).`);
        return body;
    },

    async load() {
        try {
            const body = await this.request();
            this.published = body.style;
            // Keep unsaved edits when only the status is being refreshed.
            if (!this.style || !this.dirty) this.style = clone(body.pending_style ?? body.style);
            this.maxDiameter = body.max_diameter;
            this.setStatus(body);
            this.error = '';
        } catch (error) {
            this.error = error.message;
        }
    },

    setStatus({ revision, generated_at, enabled, pending, busy }) {
        this.status = { revision, generated_at, enabled, pending, busy };
    },

    /** Called after any edit: redraw the preview shortly after the last change. */
    changed() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => host.preview(this.dirty ? clone(this.style) : null), 200);
    },

    set(name, index, value) {
        const limit = index === 0 ? this.maxDiameter : 1;
        const number = Math.max(0, Math.min(limit, Number(value)));
        this.style.zooms[this.zoom][name][index] = Number.isFinite(number) ? number : 0;
        this.changed();
    },

    raise(name) {
        const index = this.style.order.indexOf(name);
        if (index < 0 || index === this.style.order.length - 1) return;
        this.style.order.splice(index, 1);
        this.style.order.splice(index + 1, 0, name);
        this.changed();
    },

    copyToNextZoom() {
        if (this.zoom >= 10) return;
        this.style.zooms[this.zoom + 1] = clone(this.style.zooms[this.zoom]);
        this.follow = false;
        this.zoom += 1;
        this.changed();
    },

    reset() {
        this.style = clone(this.published);
        this.changed();
    },

    openTransfer() {
        this.transfer = !this.transfer;
        this.transferText = JSON.stringify(this.style);
    },

    applyTransfer() {
        try {
            const value = JSON.parse(this.transferText);
            if (!isStyle(value)) throw new Error('This is not a complete style.');
            this.style = { order: value.order, zooms: Object.fromEntries(Array.from({ length: 11 }, (_, zoom) => [zoom, value.zooms[zoom]])) };
            this.transfer = false;
            this.error = '';
            this.changed();
        } catch (error) {
            this.error = error.message;
        }
    },

    async generate() {
        try {
            this.setStatus(await this.request({ method: 'POST', body: JSON.stringify({ style: this.style }) }));
            this.error = '';
            this.watch();
        } catch (error) {
            this.error = error.message;
        }
    },

    /** Poll until the scheduled refresh has drawn and published the new tiles. */
    watch() {
        clearTimeout(this.watchTimer);
        this.watchTimer = setTimeout(async () => {
            const revision = this.status.revision;
            await this.load();
            if (this.working) return this.watch();
            if (this.status.revision !== revision) {
                this.style = clone(this.published);
                host.published();
            }
        }, 5000);
    },
});
