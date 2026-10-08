import { parseMapUrlState } from './mapUrlState.js';

// The server keeps each group together, best group first, and caps them the same way.
export const groupLimits = { places: 6, springs: 5, maps: 4 };
export const resultLimit = Object.values(groupLimits).reduce((total, limit) => total + limit, 0);
export const recentLimit = 6;
export const cacheLimit = 60;
// Long enough for screen readers to notice that the live region was emptied.
export const announceDelay = 100;

const typeGroups = { country: 'places', region: 'places', city: 'places', spring: 'springs', map: 'maps', coordinates: null };
const pageTypes = ['country', 'region', 'city'];
// Chosen water sources and saved maps open their own page rather than moving the map.
const visitedTypes = ['spring', 'map'];

export const defaultSearchTranslations = {
    button: 'Search places',
    shortcut_hint: '/ or :shortcut',
    or: 'or',
    placeholder: 'Search places, water sources and maps',
    placeholder_short: 'Places, water sources, maps',
    label: 'Search places',
    empty_hint: 'Find a city, region or country, a water source by its name or number like #123, or a saved map. Coordinates and pasted links work too.',
    recent: 'Recent',
    clear_recent: 'Clear',
    no_results: 'Nothing found for “:query”',
    no_results_hint: 'Check the spelling or try a larger place nearby.',
    corrected: 'Showing results for “:query”',
    error: 'Search is temporarily unavailable.',
    rate_limited: 'Too many searches. Please wait a moment.',
    retry: 'Try again',
    clear: 'Clear search',
    close: 'Close',
    cancel: 'Cancel',
    open_page: 'Open page',
    go_to_coordinates: 'Go to coordinates',
    linked_view: 'Map view from the link',
    results_count: ':count result|:count results',
    loading: 'Searching…',
    groups: {
        places: 'Places',
        springs: 'Water sources',
        maps: 'Saved maps',
    },
    types: {
        country: 'Country',
        region: 'Region',
        city: 'Settlement',
        spring: 'Water source',
        map: 'Saved map',
        coordinates: 'Coordinates',
    },
    keys: {
        navigate: 'to navigate',
        select: 'to go',
        open_page: 'open page',
        close: 'to close',
    },
};

export function searchTranslations(source) {
    const merge = (defaults, values) => Object.fromEntries(Object.entries(defaults).map(([key, fallback]) => [
        key,
        typeof fallback === 'object'
            ? merge(fallback, values?.[key])
            : (typeof values?.[key] === 'string' && values[key] !== '' ? values[key] : fallback),
    ]));

    return merge(defaultSearchTranslations, source);
}

// Laravel plural lines: "one|other" in English, "one|few|many" in Russian.
const pluralForms = { ru: ['one', 'few', 'many'] };

export function choosePlural(line, count, locale = 'en') {
    const forms = String(line).split('|');
    let index = 0;
    if (forms.length > 1) {
        index = (pluralForms[locale] ?? ['one']).indexOf(new Intl.PluralRules(locale).select(count));
        if (index === -1) index = forms.length - 1;
    }

    return forms[Math.min(index, forms.length - 1)].replaceAll(':count', String(count));
}

export function isEditableTarget(target) {
    if (!target || typeof target !== 'object') return false;
    if (target.isContentEditable) return true;
    const tag = String(target.tagName ?? '').toUpperCase();
    if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
    if (tag !== 'INPUT') return false;

    return !['button', 'checkbox', 'color', 'file', 'image', 'radio', 'range', 'reset', 'submit']
        .includes(String(target.type ?? 'text').toLowerCase());
}

export function matchSearchHotkey(event, { isMac = false } = {}) {
    if (!event || event.isComposing || event.defaultPrevented) return null;
    const key = typeof event.key === 'string' ? event.key : '';
    const primary = isMac ? event.metaKey : event.ctrlKey;
    const secondary = isMac ? event.ctrlKey : event.metaKey;

    if (primary && !secondary && !event.altKey && !event.shiftKey
        && (event.code === 'KeyK' || ['k', 'л'].includes(key.toLowerCase()))) return 'mod-k';
    if (event.ctrlKey || event.metaKey || event.altKey || isEditableTarget(event.target)) return null;

    // The physical slash key types "." on the Russian layout; Shift+Slash is "?" or ",".
    // Other layouts put "-", "!" or letters there, which must keep their own meaning.
    return key === '/' || (event.code === 'Slash' && !event.shiftKey && key === '.') ? 'slash' : null;
}

export function normalizeQuery(query) {
    return String(query ?? '').replace(/\s+/g, ' ').trim();
}

export function isSearchableQuery(query) {
    const value = normalizeQuery(query);

    // Shift+3 types "№" on the Russian layout, which is also how Russian writes "number".
    return [...value].length >= 2 || /^[#№]?\d+$/.test(value);
}

const foldedLetters = { 'ß': 'ss', 'æ': 'ae', 'œ': 'oe', 'ø': 'o', 'ł': 'l', 'đ': 'd', 'ð': 'd', 'þ': 'th', 'ı': 'i' };

function foldCharacter(character) {
    const lower = character.toLowerCase();
    // MySQL's unicode collation ignores accents and treats ё as е, but й stays distinct from и.
    if (lower === 'й') return lower;

    return foldedLetters[lower] ?? lower.normalize('NFD').replace(/[̀-ͯ]/g, '');
}

export function foldWithMap(value) {
    let text = '';
    const starts = [];
    const ends = [];
    let index = 0;
    for (const character of String(value ?? '')) {
        const folded = foldCharacter(character);
        for (let position = 0; position < folded.length; position++) {
            starts.push(index);
            ends.push(index + character.length);
        }
        text += folded;
        index += character.length;
    }

    return { text, starts, ends };
}

export function foldText(value) {
    return foldWithMap(String(value ?? '').normalize('NFC')).text;
}

const isWordCharacter = character => /[\p{L}\p{N}]/u.test(character ?? '');

function findMatch(haystack, needle, taken) {
    let fallback = null;
    for (let index = haystack.indexOf(needle); index !== -1; index = haystack.indexOf(needle, index + 1)) {
        const range = [index, index + needle.length];
        if (taken.some(([start, end]) => range[0] < end && start < range[1])) continue;
        if (index === 0 || !isWordCharacter(haystack[index - 1])) return range;
        fallback ??= needle.length >= 2 ? range : null;
    }

    return fallback;
}

export function highlightSegments(text, query) {
    const source = String(text ?? '').normalize('NFC');
    if (!source) return [];
    const needle = foldText(normalizeQuery(query));
    if (!needle) return [{ text: source, match: false }];

    const { text: haystack, starts, ends } = foldWithMap(source);
    const phrase = findMatch(haystack, needle, []);
    const ranges = phrase ? [phrase] : [];
    if (!phrase) {
        for (const token of needle.split(' ')) {
            const range = token ? findMatch(haystack, token, ranges) : null;
            if (range) ranges.push(range);
        }
    }

    const marks = ranges
        .map(([start, end]) => [starts[start], ends[end - 1]])
        .sort((left, right) => left[0] - right[0])
        .reduce((merged, range) => {
            const last = merged.at(-1);
            if (last && range[0] <= last[1]) last[1] = Math.max(last[1], range[1]);
            else merged.push([...range]);
            return merged;
        }, []);

    const segments = [];
    let cursor = 0;
    for (const [start, end] of marks) {
        if (start > cursor) segments.push({ text: source.slice(cursor, start), match: false });
        segments.push({ text: source.slice(start, end), match: true });
        cursor = end;
    }
    if (cursor < source.length) segments.push({ text: source.slice(cursor), match: false });

    return segments;
}

const coordinateCharacters = /^[\s\d.,;°'"NSEWnsew+-]+$/;

export function coordinateText(query) {
    let text = normalizeQuery(query)
        .replace(/[−–—]/g, '-')
        .replace(/[º˚]/g, '°')
        .replace(/[′‘’´`]/g, "'")
        .replace(/[″“”]/g, '"')
        .replace(/''/g, '"')
        .replace(/([сю])\.?\s?ш\.?/giu, (match, hemisphere) => (hemisphere.toLowerCase() === 'с' ? ' N ' : ' S '))
        .replace(/([вз])\.?\s?д\.?/giu, (match, hemisphere) => (hemisphere.toLowerCase() === 'в' ? ' E ' : ' W '));
    text = normalizeQuery(text);

    if (!coordinateCharacters.test(text) || (text.match(/\d+(?:[.,]\d+)?/g) ?? []).length < 2) return null;
    // "55,7522 37,6156" uses decimal commas rather than a comma separator.
    const decimalCommas = text.match(/^(-?\d+,\d+)[\s;]+(-?\d+,\d+)$/);

    return decimalCommas ? `${decimalCommas[1].replace(',', '.')} ${decimalCommas[2].replace(',', '.')}` : text;
}

export function parseCoordinateQuery(query, parse) {
    const text = coordinateText(query);
    if (!text || typeof parse !== 'function') return null;
    try {
        const parsed = parse(text);
        const lat = Number(parsed?.getLatitude?.());
        const lon = Number(parsed?.getLongitude?.());
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return null;

        return { lat, lon };
    } catch {
        return null;
    }
}

export function coordinateResult({ lat, lon }) {
    const id = `${lat.toFixed(5)},${lon.toFixed(5)}`;

    return {
        type: 'coordinates', group: null, id, name: `${lat.toFixed(5)}, ${lon.toFixed(5)}`, alt_name: null, context: null,
        sources_count: null, sources_label: null, url: null, center: [lon, lat], bounds: null, zoom: null,
    };
}

// A pasted map link such as "https://…/maps/trip/#m=12/55.75/37.6" also carries the view it showed.
export function linkedViewResult(query) {
    const text = normalizeQuery(query);
    const hash = text.indexOf('#');
    if (hash === -1) return null;
    const parsed = parseMapUrlState(`https://rodnik.invalid/${text.slice(hash)}`);
    if (!parsed) return null;
    const [lon, lat] = parsed.state.center;

    return { ...coordinateResult({ lat, lon }), zoom: parsed.state.zoom };
}

const optionalString = value => (typeof value === 'string' && value.trim() !== '' ? value : null);
const finiteNumber = value => (typeof value === 'number' && Number.isFinite(value) ? value : null);

export function sanitizeResult(value) {
    if (!value || typeof value !== 'object' || !Object.hasOwn(typeGroups, value.type)) return null;
    const group = typeGroups[value.type];
    // Recent places saved before results were grouped have no group.
    if (value.group !== undefined && value.group !== group) return null;
    const name = optionalString(value.name);
    const center = Array.isArray(value.center) && value.center.length === 2 && value.center.every(Number.isFinite)
        ? value.center.map(Number) : null;
    let url = optionalString(value.url);
    url = url && /^(?:https?:\/\/|\/(?!\/))/i.test(url) ? url : null;
    // A saved map opens by its link; everything else is shown on the map.
    if (!name || (value.type === 'map' ? !url : !center)) return null;

    return {
        type: value.type,
        group,
        id: typeof value.id === 'number' || typeof value.id === 'string' ? value.id : null,
        name,
        alt_name: optionalString(value.alt_name),
        context: optionalString(value.context),
        sources_count: finiteNumber(value.sources_count),
        sources_label: optionalString(value.sources_label),
        url,
        center,
        bounds: Array.isArray(value.bounds) && value.bounds.length === 4 && value.bounds.every(Number.isFinite)
            ? value.bounds.map(Number) : null,
        zoom: finiteNumber(value.zoom),
    };
}

export function normalizeSearchResponse(payload, query) {
    const keys = new Set();
    const groups = new Map();
    for (const result of (Array.isArray(payload?.results) ? payload.results : []).map(sanitizeResult)) {
        // Rows are keyed by type and id, so a repeated place would break the list.
        if (!result || result.type === 'coordinates' || keys.has(recentKey(result))) continue;
        keys.add(recentKey(result));
        const items = groups.get(result.group) ?? [];
        if (items.length < groupLimits[result.group]) groups.set(result.group, [...items, result]);
    }

    return {
        query: optionalString(payload?.query) ?? query,
        corrected: optionalString(payload?.corrected),
        // Each group stays in one piece under its header, in the order its first result came.
        results: [...groups.values()].flat(),
    };
}

// Rows under one header form a group; keyboard navigation still counts rows across all of them.
export function resultSections(items, labels = {}) {
    const sections = [];
    items.forEach((item, index) => {
        const group = item.group ?? typeGroups[item.type] ?? null;
        const last = sections.at(-1);
        if (last && last.group === group) last.entries.push({ item, index });
        else sections.push({ group, label: group ? labels[group] ?? null : null, entries: [{ item, index }] });
    });

    return sections.map((section, position) => ({
        ...section, position, key: `${position}:${section.group ?? 'none'}`, headerId: `map-search-group-${position}`,
    }));
}

export function searchUrl(baseUrl, query, view = '') {
    const url = `${String(baseUrl ?? '').replace(/\/+$/, '')}/search/places?q=${encodeURIComponent(query)}`;

    return view ? `${url}&bbox=${view}` : url;
}

// Only case and Unicode form are folded: the database collation keeps ł, ø, ї and similar
// letters apart from their look-alikes, so they may find different places. Links keep their
// case too, as saved map addresses differ by it.
export function cacheKey(locale, query, view = '') {
    const text = normalizeQuery(query).normalize('NFC');

    return `${locale}\u0000${text.includes('/') ? text : text.toLocaleLowerCase()}\u0000${view}`;
}

// The visible map area lets the server prefer the nearby one of many same-named villages.
// Zoomed-out views say nothing about that, so they are left out (matching the server's limit).
export function viewBias(bounds, { maxDegrees = 20 } = {}) {
    if (!bounds || ![bounds.west, bounds.south, bounds.east, bounds.north].every(Number.isFinite)) return '';
    const width = bounds.east >= bounds.west ? bounds.east - bounds.west : bounds.east - bounds.west + 360;
    if (width > maxDegrees || bounds.north - bounds.south > maxDegrees || bounds.north <= bounds.south) return '';
    // Two decimals (about a kilometre) keep URLs short and cache keys stable. Rounding outward keeps a
    // zoomed-in view (narrower than 0.01°) from collapsing to an empty box that contains nothing.
    const outward = (value, round, limit) => String(Math.max(-limit, Math.min(limit, round(Number((value * 100).toFixed(6))) / 100)));

    return [
        outward(bounds.west, Math.floor, 180),
        outward(bounds.south, Math.floor, 90),
        outward(bounds.east, Math.ceil, 180),
        outward(bounds.north, Math.ceil, 90),
    ].join(',');
}

export class LruCache {
    constructor(limit = cacheLimit) {
        this.limit = limit;
        this.entries = new Map();
    }

    get size() {
        return this.entries.size;
    }

    has(key) {
        return this.entries.has(key);
    }

    get(key) {
        if (!this.entries.has(key)) return undefined;
        const value = this.entries.get(key);
        this.entries.delete(key);
        this.entries.set(key, value);

        return value;
    }

    set(key, value) {
        this.entries.delete(key);
        this.entries.set(key, value);
        while (this.entries.size > this.limit) this.entries.delete(this.entries.keys().next().value);

        return this;
    }

    clear() {
        this.entries.clear();
    }
}

export function createDebouncer(callback, { wait = 120, maxWait = 350 } = {}) {
    let waitTimer = null;
    let maxTimer = null;
    let pendingArguments = null;

    const clearTimers = () => {
        clearTimeout(waitTimer);
        clearTimeout(maxTimer);
        waitTimer = null;
        maxTimer = null;
    };
    const invoke = () => {
        if (!pendingArguments) return;
        const args = pendingArguments;
        pendingArguments = null;
        clearTimers();
        callback(...args);
    };

    return {
        schedule(...args) {
            pendingArguments = args;
            clearTimeout(waitTimer);
            waitTimer = setTimeout(invoke, wait);
            // Continuous typing still searches at least every maxWait milliseconds.
            if (maxTimer === null && maxWait > 0) maxTimer = setTimeout(invoke, maxWait);
        },
        flush: invoke,
        cancel() {
            pendingArguments = null;
            clearTimers();
        },
        pending: () => pendingArguments !== null,
    };
}

export const recentStorageKey = locale => `rodnik.mapSearch.recent.${locale}`;

export function recentKey(item) {
    if (item?.type === 'coordinates' && Array.isArray(item.center)) {
        return `coordinates:${Number(item.center[1]).toFixed(5)},${Number(item.center[0]).toFixed(5)}`;
    }

    return `${item?.type}:${item?.id}`;
}

export function addRecent(list, item, limit = recentLimit) {
    const entry = sanitizeResult(item);
    if (!entry) return list;
    // A name is only matched against its other-language name while that query is typed.
    entry.alt_name = null;
    const key = recentKey(entry);

    return [entry, ...list.filter(recent => recentKey(recent) !== key)].slice(0, limit);
}

export function readRecents(storage, locale) {
    try {
        const stored = JSON.parse(storage?.getItem(recentStorageKey(locale)) ?? '[]');

        return Array.isArray(stored) ? stored.map(sanitizeResult).filter(Boolean).slice(0, recentLimit) : [];
    } catch {
        return [];
    }
}

export function writeRecents(storage, locale, list) {
    try {
        if (list.length) storage?.setItem(recentStorageKey(locale), JSON.stringify(list));
        else storage?.removeItem(recentStorageKey(locale));
    } catch {
        // Recent places are a convenience; private browsing may block storage.
    }
}

// Fitting point-sized bounds (one settlement, or a city-state) would zoom all the way in with
// nothing marked, so such places get a pin like a city.
const pointSpan = 0.02;

export function focusRequest(item) {
    if (item.type === 'country' || item.type === 'region') {
        if (!item.bounds) return { center: item.center, zoom: item.type === 'country' ? 5 : 7, label: item.name, marker: false };
        const [west, south, east, north] = item.bounds;
        if (east - west >= pointSpan || north - south >= pointSpan) {
            return { bounds: item.bounds, center: item.center, label: item.name, marker: false };
        }
        const longitude = (west + east) / 2;

        return { center: [longitude > 180 ? longitude - 360 : longitude, (south + north) / 2], zoom: 10, label: item.name, marker: true };
    }

    // A linked map view shows an area, not a point to mark.
    const view = item.type === 'coordinates' && Number.isFinite(item.zoom);

    return { center: item.center, zoom: item.zoom ?? (item.type === 'spring' ? 16 : null), label: item.name, marker: !view };
}

export function detectMac(navigator = globalThis.navigator) {
    return /Mac|iPhone|iPad|iPod/i.test(navigator?.userAgentData?.platform || navigator?.platform || '');
}

function anotherModalOpen(own) {
    const elements = globalThis.document?.querySelectorAll?.('dialog[open], [aria-modal="true"]') ?? [];

    return [...elements].some(element => element !== own && !own?.contains(element)
        && (element.checkVisibility ? element.checkVisibility() : element.getClientRects().length > 0));
}

export const optionId = index => `map-search-option-${index}`;

// Below Tailwind's sm breakpoint the dialog is a full-width sheet.
const narrowQuery = '(max-width: 639.98px)';

export default function mapSearch(options = {}) {
    const browser = () => globalThis.window ?? {};
    const dependencies = {
        fetch: options.fetch ?? ((...args) => globalThis.fetch(...args)),
        storage: 'storage' in options ? () => options.storage : () => {
            try {
                return globalThis.localStorage ?? null;
            } catch {
                return null;
            }
        },
        parseCoordinates: options.parseCoordinates ?? (text => browser().parseCoordinates?.(text)),
        baseUrl: options.baseUrl ?? (() => browser().rodnikPublicBaseUrl ?? ''),
        viewport: options.viewport ?? (() => {
            const map = browser().rodnikMap;

            return map?.getLayout?.()?.minimized ? null : map?.getViewportBounds?.() ?? null;
        }),
    };
    // Kept outside the reactive component: AbortController and Map lose their native methods behind a proxy.
    const internals = {
        cache: new LruCache(cacheLimit),
        debouncer: null,
        controller: null,
        sequence: 0,
        inflight: null,
        view: '',
        enterFor: null,
        moved: false,
        lastQuery: null,
        spinnerTimer: null,
        announceTimer: null,
        returnFocus: null,
        rootStyle: null,
        pointerDownOutside: false,
        pointer: null,
        listeners: [],
    };
    const listen = (target, type, handler, listenerOptions = {}) => {
        if (!target?.addEventListener) return;
        target.addEventListener(type, handler, listenerOptions);
        internals.listeners.push([target, type, handler, listenerOptions]);
    };

    return {
        isOpen: false,
        query: '',
        listKind: 'recent',
        results: [],
        resultsFor: '',
        corrected: null,
        coordinate: null,
        recents: [],
        loading: false,
        spinner: false,
        error: null,
        activeIndex: 0,
        announcement: '',
        narrow: false,
        t: searchTranslations(browser().rodnikMapTranslations?.search),
        locale: browser().rodnikLocale ?? 'en',
        isMac: options.isMac ?? detectMac(),

        init() {
            internals.debouncer = createDebouncer(query => this.request(query), { wait: 120, maxWait: 350 });
            this.recents = readRecents(dependencies.storage(), this.locale);
            // The dialog keeps its keys from the map around it, so hotkeys listen before that.
            listen(globalThis.window, 'keydown', event => this.onHotkey(event), { capture: true });
            listen(globalThis.window, 'rodnik:navigated', () => this.refreshLocale());
            listen(globalThis.document, 'livewire:navigating', () => this.close());
            const narrow = globalThis.window?.matchMedia?.(narrowQuery);
            this.narrow = Boolean(narrow?.matches);
            listen(narrow, 'change', event => { this.narrow = event.matches; });
        },

        destroy() {
            internals.listeners.forEach(([target, type, handler, listenerOptions]) => target.removeEventListener(type, handler, listenerOptions));
            internals.listeners = [];
            this.cancelSearch();
            clearTimeout(internals.announceTimer);
            if (this.$refs?.dialog?.open) this.$refs.dialog.close();
            this.onClose();
        },

        get normalizedQuery() {
            return normalizeQuery(this.query);
        },

        get items() {
            if (this.error) return [];
            if (this.listKind === 'coordinates') return this.coordinate ? [this.coordinate] : [];
            if (this.listKind === 'recent') return this.recents;

            // The view from a pasted link comes before what its address points to.
            return this.coordinate ? [this.coordinate, ...this.results] : this.results;
        },

        get sections() {
            if (this.listKind === 'results') return resultSections(this.items, this.t.groups);

            return [{
                group: null, label: null, position: 0, key: `0:${this.listKind}`, headerId: null,
                entries: this.items.map((item, index) => ({ item, index })),
            }];
        },

        get view() {
            if (this.error) return 'error';
            if (this.listKind === 'recent') return this.recents.length ? 'recent' : 'hint';

            return this.items.length ? 'list' : 'empty';
        },

        get activeId() {
            return this.isOpen && this.items.length ? optionId(this.activeIndex) : null;
        },

        get highlightQuery() {
            if (this.listKind === 'results') return this.corrected ?? this.resultsFor;

            return this.listKind === 'recent' ? this.normalizedQuery : '';
        },

        // The phone sheet has room for a shorter line only.
        get placeholder() {
            return this.narrow ? this.t.placeholder_short : this.t.placeholder;
        },

        get shortcutLabel() {
            return this.isMac ? '⌘K' : 'Ctrl K';
        },

        get buttonTitle() {
            return `${this.t.button} (${this.t.shortcut_hint.replace(':shortcut', this.shortcutLabel)})`;
        },

        open(trigger = null) {
            const dialog = this.$refs?.dialog;
            const input = this.$refs?.input;
            if (!dialog || !input) return;
            this.syncClosed();
            if (this.isOpen) {
                this.focusInput();
                return;
            }
            const active = globalThis.document?.activeElement;
            internals.returnFocus = trigger ?? (active && active !== globalThis.document.body ? active : null);
            try {
                dialog.showModal();
            } catch {
                // A dialog left open non-modally (for example after a page swap) must be reset first.
                try {
                    dialog.close();
                    dialog.showModal();
                } catch {
                    return;
                }
            }
            this.isOpen = true;
            this.activeIndex = 0;
            internals.moved = false;
            // The map cannot move while the modal is open, so its view is read once per opening.
            try {
                internals.view = viewBias(dependencies.viewport());
            } catch {
                internals.view = '';
            }
            this.lockScroll();
            this.recents = readRecents(dependencies.storage(), this.locale);
            // iOS only raises the keyboard for focus inside the same user gesture.
            this.focusInput();
            this.update();
        },

        close() {
            const dialog = this.$refs?.dialog;
            if (dialog?.open) dialog.close();
            this.onClose();
        },

        // The native close event arrives a task after Escape closes the dialog.
        syncClosed() {
            if (this.isOpen && this.$refs?.dialog && !this.$refs.dialog.open) this.onClose();
        },

        onClose() {
            // A late close event from an earlier closing must not close the reopened dialog.
            if (!this.isOpen || this.$refs?.dialog?.open) return;
            this.isOpen = false;
            internals.enterFor = null;
            this.unlockScroll();
            const target = internals.returnFocus;
            internals.returnFocus = null;
            if (target?.isConnected && globalThis.document?.activeElement !== target) target.focus({ preventScroll: true });
        },

        focusInput() {
            const input = this.$refs?.input;
            if (!input) return;
            input.focus({ preventScroll: true });
            input.select();
        },

        lockScroll() {
            const root = globalThis.document?.documentElement;
            if (!root || internals.rootStyle) return;
            internals.rootStyle = { overflow: root.style.overflow, paddingRight: root.style.paddingRight };
            const scrollbar = (globalThis.window?.innerWidth ?? 0) - root.clientWidth;
            root.style.overflow = 'hidden';
            if (scrollbar > 0) root.style.paddingRight = `${scrollbar}px`;
        },

        unlockScroll() {
            const root = globalThis.document?.documentElement;
            if (!root || !internals.rootStyle) return;
            Object.assign(root.style, internals.rootStyle);
            internals.rootStyle = null;
        },

        onHotkey(event) {
            const hotkey = matchSearchHotkey(event, { isMac: this.isMac });
            if (!hotkey) return;
            this.syncClosed();
            if (this.isOpen) {
                event.preventDefault();
                hotkey === 'mod-k' ? this.close() : this.focusInput();
                return;
            }
            if (browser().openedPhotoswipe || anotherModalOpen(this.$refs?.dialog)) return;
            event.preventDefault();
            this.open();
        },

        refreshLocale() {
            this.t = searchTranslations(browser().rodnikMapTranslations?.search);
            const locale = browser().rodnikLocale ?? this.locale;
            if (locale === this.locale) return;
            this.locale = locale;
            this.cancelSearch();
            Object.assign(internals, { enterFor: null, moved: false, lastQuery: null });
            Object.assign(this, {
                listKind: 'recent', results: [], resultsFor: '', corrected: null, coordinate: null, error: null, activeIndex: 0,
            });
            this.recents = readRecents(dependencies.storage(), locale);
            if (this.isOpen) this.update();
        },

        update() {
            const query = this.normalizedQuery;
            // Reopening over another part of the map searches the same text again.
            const searched = `${query}\u0000${internals.view}`;
            if (searched === internals.lastQuery) return;
            internals.lastQuery = searched;
            internals.enterFor = null;
            internals.moved = false;
            this.activeIndex = 0;
            this.error = null;

            const coordinates = parseCoordinateQuery(query, dependencies.parseCoordinates);
            if (coordinates) {
                this.cancelSearch();
                this.coordinate = coordinateResult(coordinates);
                this.listKind = 'coordinates';
                this.announce(this.t.go_to_coordinates);
                return;
            }
            this.coordinate = linkedViewResult(query);
            if (this.coordinate) {
                // The linked view is offered at once; the server still looks up the page the link points to.
                this.listKind = 'coordinates';
                this.announce(this.t.linked_view);
                if (query.startsWith('#')) {
                    this.cancelSearch();
                    return;
                }
            }
            if (!isSearchableQuery(query)) {
                this.cancelSearch();
                this.listKind = 'recent';
                return;
            }
            const cached = internals.cache.get(cacheKey(this.locale, query, internals.view));
            if (cached) {
                this.cancelSearch();
                this.applyResults(query, cached);
                return;
            }
            // Earlier results stay visible until the new ones arrive.
            if (this.listKind === 'coordinates' && !this.coordinate) this.listKind = this.resultsFor ? 'results' : 'recent';
            this.loading = true;
            if (internals.inflight === cacheKey(this.locale, query, internals.view)) internals.debouncer?.cancel();
            else internals.debouncer?.schedule(query);
        },

        cancelSearch() {
            internals.debouncer?.cancel();
            internals.sequence++;
            internals.controller?.abort();
            internals.controller = null;
            internals.inflight = null;
            clearTimeout(internals.spinnerTimer);
            this.loading = false;
            this.spinner = false;
        },

        async request(query) {
            const sequence = ++internals.sequence;
            internals.controller?.abort();
            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            internals.controller = controller;
            const locale = this.locale;
            const view = internals.view;
            internals.inflight = cacheKey(locale, query, view);
            this.loading = true;
            clearTimeout(internals.spinnerTimer);
            internals.spinnerTimer = setTimeout(() => {
                if (sequence === internals.sequence) this.spinner = true;
            }, 150);

            try {
                const response = await dependencies.fetch(searchUrl(dependencies.baseUrl(), query, view), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller?.signal,
                });
                if (sequence !== internals.sequence) return;
                if (!response.ok) {
                    throw Object.assign(new Error(`Place search failed with ${response.status}`), {
                        kind: response.status === 429 ? 'rate_limited' : 'error',
                    });
                }
                const payload = normalizeSearchResponse(await response.json(), query);
                if (sequence !== internals.sequence) return;
                internals.cache.set(cacheKey(locale, query, view), payload);
                this.settleRequest();
                this.applyResults(query, payload);
            } catch (error) {
                if (sequence !== internals.sequence) return;
                this.settleRequest();
                // A newer query is already scheduled; its own request decides what to show.
                if (this.loading) return;
                internals.enterFor = null;
                // The view from a pasted link works without the page it points to.
                if (this.listKind === 'coordinates' && this.coordinate) return;
                this.error = error?.kind ?? 'error';
                this.announce(this.error === 'rate_limited' ? this.t.rate_limited : this.t.error);
            }
        },

        settleRequest() {
            internals.controller = null;
            internals.inflight = null;
            clearTimeout(internals.spinnerTimer);
            this.spinner = false;
            this.loading = internals.debouncer?.pending() ?? false;
        },

        applyResults(query, payload) {
            this.results = payload.results;
            this.resultsFor = query;
            this.corrected = payload.corrected && foldText(payload.corrected) !== foldText(query) ? payload.corrected : null;
            this.listKind = 'results';
            this.error = null;
            this.activeIndex = 0;
            internals.moved = false;
            const count = this.items.length;
            this.announce(count ? choosePlural(this.t.results_count, count, this.locale) : this.t.no_results.replaceAll(':query', query));
            if (internals.enterFor === query && this.normalizedQuery === query) {
                internals.enterFor = null;
                if (this.items[0]) this.select(this.items[0]);
            }
        },

        // A live region only speaks when its text changes, and most result counts repeat.
        announce(message) {
            this.announcement = '';
            clearTimeout(internals.announceTimer);
            internals.announceTimer = setTimeout(() => {
                this.announcement = message;
            }, announceDelay);
        },

        retry() {
            const query = this.normalizedQuery;
            this.error = null;
            if (!isSearchableQuery(query)) return;
            internals.debouncer?.cancel();
            this.request(query);
        },

        isCurrent(query) {
            if (this.listKind === 'coordinates') return true;
            if (this.listKind === 'recent') return !isSearchableQuery(query);

            return this.resultsFor === query;
        },

        keydown(event) {
            if (event.isComposing || event.keyCode === 229) return;
            const emacs = this.isMac && event.ctrlKey && !event.metaKey && !event.altKey;
            if (event.key === 'ArrowDown' || (emacs && event.key === 'n')) {
                event.preventDefault();
                this.move(1);
            } else if (event.key === 'ArrowUp' || (emacs && event.key === 'p')) {
                event.preventDefault();
                this.move(-1);
            } else if (event.key === 'Enter') {
                this.enter(event);
            }
        },

        enter(event) {
            if (event.isComposing || event.keyCode === 229) return;
            event.preventDefault();
            const query = this.normalizedQuery;
            const newTab = this.isMac ? event.metaKey : event.ctrlKey;
            if (this.error) {
                if (!newTab) internals.enterFor = query;
                this.retry();
                return;
            }
            // A row picked from the list still on screen wins over the results on their way.
            if (this.isCurrent(query) || (internals.moved && this.items[this.activeIndex])) {
                const item = this.items[this.activeIndex] ?? this.items[0];
                if (item) newTab ? this.openInNewTab(item) : this.select(item);
                return;
            }
            if (newTab || !isSearchableQuery(query)) return;
            // Results for this query are on their way: go to the first one as soon as it arrives.
            internals.enterFor = query;
            if (internals.debouncer?.pending()) internals.debouncer.flush();
            else if (internals.inflight !== cacheKey(this.locale, query, internals.view)) this.request(query);
        },

        move(delta) {
            const count = this.items.length;
            if (!count) return;
            this.activeIndex = (this.activeIndex + delta + count) % count;
            internals.moved = true;
            this.$nextTick?.(() => {
                const option = globalThis.document?.getElementById(optionId(this.activeIndex));
                const group = option?.closest?.('[role="group"]');
                // The first row of a group brings its header into view too.
                if (group?.querySelector('[role="option"]') === option) group.querySelector('[role="presentation"]')?.scrollIntoView({ block: 'nearest' });
                option?.scrollIntoView({ block: 'nearest' });
            });
        },

        hover(index, event) {
            // Scrolling under a resting pointer must not steal the keyboard selection.
            const point = `${event?.clientX},${event?.clientY}`;
            if (internals.pointer === point) return;
            internals.pointer = point;
            this.activeIndex = index;
            internals.moved = true;
        },

        choose(item, event) {
            if (event?.target?.closest?.('a')) return;
            this.select(item);
        },

        select(item) {
            if (!item) return;
            this.remember(item);
            this.close();
            const browserWindow = browser();
            if (visitedTypes.includes(item.type) && item.url) {
                browserWindow.rodnikMap?.setSearchMarker?.(null);
                if (browserWindow.rodnikNavigation?.visit) browserWindow.rodnikNavigation.visit(item.url);
                else browserWindow.location?.assign(item.url);
                return;
            }
            browserWindow.rodnikMap?.focusPlace?.(focusRequest(item));
        },

        openInNewTab(item) {
            if (!item?.url) return;
            this.remember(item);
            browser().open?.(item.url, '_blank', 'noopener');
        },

        openPage(item, event) {
            this.remember(item);
            if (event?.metaKey || event?.ctrlKey || event?.shiftKey || event?.altKey) return;
            this.close();
        },

        remember(item) {
            this.recents = addRecent(this.recents, item);
            writeRecents(dependencies.storage(), this.locale, this.recents);
        },

        clearRecents() {
            this.recents = [];
            writeRecents(dependencies.storage(), this.locale, []);
            this.focusInput();
        },

        clear() {
            this.query = '';
            this.update();
            this.focusInput();
        },

        pointerDown(event) {
            internals.pointerDownOutside = !this.$refs?.panel?.contains(event.target);
        },

        backdropClick(event) {
            // A text selection dragged out of the panel must not close the dialog.
            if (internals.pointerDownOutside && !this.$refs?.panel?.contains(event.target)) this.close();
            internals.pointerDownOutside = false;
        },

        itemKey: recentKey,
        optionId,

        icon(item) {
            return this.listKind === 'recent' ? 'recent' : item.type;
        },

        hasPage(item) {
            return Boolean(item.url) && pageTypes.includes(item.type);
        },

        segments(text) {
            return highlightSegments(text, this.highlightQuery);
        },

        subtitle(item) {
            if (item.type === 'coordinates') return Number.isFinite(item.zoom) ? this.t.linked_view : this.t.go_to_coordinates;
            const context = item.context ?? (['country', 'spring', 'map'].includes(item.type) ? this.t.types[item.type] : null);

            return [context, item.sources_label].filter(Boolean).join(' · ');
        },

        parts(line) {
            const [before = '', after = ''] = String(line).split(':query');

            return { before, after };
        },
    };
}
