import assert from 'node:assert/strict';
import test from 'node:test';
import { installNavigation } from '../../resources/js/navigation.js';
import { normalizeSharedMapState, sharedMapPage } from '../../resources/js/sharedMapState.js';
import { initialMapConfiguration, parseMapUrlState } from '../../resources/js/mapUrlState.js';
import { resourceNavigationUrl } from '../../resources/js/navigationState.js';
import { readLocalTrackHistory } from '../../resources/js/localTrackHistory.js';
import mapReports from '../../resources/js/mapReports.js';

const tick = () => new Promise(setImmediate);
const settled = () => new Promise(resolve => setTimeout(resolve, 180));
const coordinates = id => ({ 1: [37, 55], 2: [20, 40], 3: [30, 45] }[id] ?? [id % 150, id % 70]);
const privateTrack = () => ({ type: 'FeatureCollection', features: [
    { type: 'Feature', properties: { name: 'Private route' }, geometry: { type: 'LineString', coordinates: [[37, 55], [38, 56]] } },
] });

function serverResult(href) {
    const url = new URL(href, 'https://rodnik.test');
    const path = url.pathname.replace(/^\/ru(?=\/|$)/, '') || '/';
    const spring = path.match(/^\/(\d+)\/?$/)?.[1];
    const user = path.match(/^\/users\/(\d+)\/?$/)?.[1] ?? url.searchParams.get('user');
    const page = sharedMapPage({ spring, user, location: url.searchParams.get('location') });
    const destination = resourceNavigationUrl(url.href, page);
    if (url.searchParams.get('redirect') === 'false') destination.searchParams.set('redirect', 'false');
    const canonical = destination.origin + destination.pathname;
    const title = page.spring ? `Source ${page.spring} — Rodnik.today` : page.user ? `User ${page.user} — Rodnik.today` : 'Rodnik.today';
    return {
        page, coordinates: page.spring ? coordinates(page.spring) : [],
        url: destination.pathname + destination.search,
        metadata: {
            title, description: `Description for ${title}`, canonical,
            robots: page.location ? 'noindex, nofollow' : null,
            alternates: page.location ? {} : { en: canonical, ru: canonical.replace('rodnik.test/', 'rodnik.test/ru/'), 'x-default': canonical },
        },
    };
}

function setup(t, options = {}) {
    const originals = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    const hooks = new Map();
    const requests = [], calls = [], scrolls = [], restorations = [], visits = [], writes = [], reloads = [], assignments = [], errors = [];
    const sharedData = { textContent: JSON.stringify(options.sharedRecord ?? null) };
    const window = new EventTarget();
    const document = new EventTarget();
    const href = options.href ?? 'https://rodnik.test/1/';
    let locale = href.includes('/ru/') || href.endsWith('/ru') ? 'ru' : 'en';
    let ownerId = options.ownerId ?? null;
    let data = options.page === null ? null : serverResult(href);
    if (options.sharedRecord?.state?.page) data.page = sharedMapPage(options.sharedRecord.state.page);
    if (options.page) data = { ...data, page: sharedMapPage(options.page), coordinates: options.page.spring ? coordinates(options.page.spring) : [] };
    let anchors = [], extraWires = [], sequence = 0, photoCloses = 0, activeCalls = 0, maximumActiveCalls = 0;
    let index = 0;
    const entries = [{ href, state: options.initialState === undefined ? { alpine: { snapshotIdx: 'old-cache', url: href } } : options.initialState }];
    const location = next => Object.assign(new URL(next), {
        reload() { reloads.push(this.href); },
        assign(destination) { assignments.push(destination); },
    });
    const recordHistory = (method, state, destination) => {
        const entry = { href: new URL(destination, window.location.href).href, state: structuredClone(state) };
        if (method === 'pushState') {
            entries.splice(index + 1);
            entries.push(entry);
            index++;
        } else entries[index] = entry;
        window.location = location(entry.href);
        writes.push({ method, ...entry });
    };
    Object.assign(window, {
        location: location(href),
        crypto: { randomUUID: () => `entry-${++sequence}` },
        PopStateEvent: class extends Event {
            constructor(name, { state }) { super(name); this.state = state; }
        },
        history: {
            get state() { return entries[index].state; },
            set state(state) { entries[index].state = structuredClone(state); },
            pushState: (state, title, destination) => recordHistory('pushState', state, destination),
            replaceState: (state, title, destination) => recordHistory('replaceState', state, destination),
        },
        sessionStorage: {
            getItem: key => options.storage?.get(key) ?? null,
            setItem: (key, value) => options.storage?.set(key, value),
        },
        scrollX: 0, scrollY: 100,
        scrollTo(next) { scrolls.push(next); window.scrollX = next.left ?? window.scrollX; window.scrollY = next.top ?? window.scrollY; },
        requestAnimationFrame(callback) { queueMicrotask(callback); return ++sequence; },
        Alpine: { nextTick(callback) { return Promise.resolve().then(callback); } },
        destroyPhotoSwipes() { photoCloses++; },
    });
    const head = [];
    function element(tag, attributes = {}) {
        return Object.assign({
            tagName: tag,
            setAttribute(name, value) { this[name] = value; },
            remove() { const position = head.indexOf(this); if (position >= 0) head.splice(position, 1); },
        }, attributes);
    }
    head.push(element('meta', { name: 'description' }), element('link', { rel: 'canonical' }), element('meta', { name: 'csrf-token', content: 'csrf' }));
    for (const property of ['og:title', 'og:description', 'og:url']) head.push(element('meta', { property }));
    const findHead = selector => {
        const match = selector.match(/^(meta|link)\[(name|property|rel)="([^"]+)"\]$/);
        return match ? head.find(node => node.tagName === match[1] && node[match[2]] === match[3]) : null;
    };
    const translationsElement = (translatedLocale = locale) => ({
        textContent: JSON.stringify({
            locale: translatedLocale,
            ownerId,
            publicBaseUrl: `https://rodnik.test${translatedLocale === 'ru' ? '/ru' : ''}`,
            translations: { label: translatedLocale },
            mapTranslations: { label: translatedLocale },
        }),
    });
    Object.assign(document, {
        title: 'Initial title',
        head: { append: node => head.push(node) },
        createElement: tag => element(tag),
        querySelectorAll: selector => selector === 'a[href]' ? anchors
            : selector === 'link[rel="alternate"][hreflang]' ? head.filter(node => node.rel === 'alternate' && node.hreflang) : [],
        querySelector: selector => selector === '[data-rodnik-page]'
            ? (data ? { dataset: { rodnikPage: JSON.stringify(data) } } : null)
            : selector === 'body #rodnik-translations' ? translationsElement() : findHead(selector),
        getElementById: id => id === 'rodnik-translations' ? translationsElement(options.staleHeadLocale ?? locale)
            : id === 'rodnik-shared-map' && data ? sharedData : null,
    });
    function createMap(destination = window.location.href, shared = null, overrides = {}) {
        const config = initialMapConfiguration(destination, data?.page, shared);
        let state = normalizeSharedMapState(config.state ?? { version: 1, center: [37.6, 55.7], zoom: 12, ...overrides });
        const map = {
            ready: Promise.resolve(), initialSharedState: config.state,
            springRefreshes: 0,
            refreshSpringData() { map.springRefreshes++; },
            sharedTrack: { token: typeof config.track === 'string' ? config.track : config.track?.token ?? null, status: 'saved' },
            trackGeometry: { type: 'FeatureCollection', features: ['existing track'] },
            captureSharedState: () => structuredClone(state),
            captureNavigationState: () => ({ track: map.trackGeometry }),
            async restoreNavigationState(snapshot) { restorations.push({ snapshot }); },
            async restoreSharedState(next, { track }) {
                state = normalizeSharedMapState(next);
                if (track !== undefined) {
                    map.sharedTrack.token = typeof track === 'string' ? track : track?.token ?? null;
                    map.sharedTrack.status = track ? 'saved' : 'idle';
                    if (!track) map.trackGeometry = { type: 'FeatureCollection', features: [] };
                }
                restorations.push({ state: structuredClone(state), track });
            },
            restoreLocalTrack(track) {
                map.trackGeometry = track;
                map.sharedTrack = { status: 'local', token: null, hash: null, id: null };
            },
            refreshLocale() { map.locale = window.rodnikLocale; },
            duoVisit(detail) {
                visits.push(structuredClone(detail));
                state.page = sharedMapPage(detail);
                if (!detail.preserveMapView && detail.coordinates) {
                    state.center = [...detail.coordinates];
                    state.zoom = 14;
                } else if (!detail.preserveMapView && detail.user && !detail.spring) {
                    state.center = coordinates(detail.user);
                    state.zoom = 8;
                }
            },
            change(next) {
                state = normalizeSharedMapState({ ...state, ...next });
                window.dispatchEvent(new Event('map-state-changed'));
            },
        };
        const localTrack = shared ? null : readLocalTrackHistory(window, ownerId, destination);
        if (localTrack) map.restoreLocalTrack(localTrack);
        return map;
    }
    window.rodnikMap = data ? createMap(href, options.sharedRecord, options.mapState) : null;
    const wire = {
        __instance: { name: 'duo' },
        async navigateTo(destination) {
            calls.push(destination);
            maximumActiveCalls = Math.max(maximumActiveCalls, ++activeCalls);
            try {
                const result = options.navigate ? await options.navigate(destination) : serverResult(destination);
                data = structuredClone(result);
                hooks.get('morphed')?.();
                return result;
            } finally { activeCalls--; }
        },
    };
    function pageRequest(destination, history, navigationOptions = {}) {
        const event = new CustomEvent('livewire:navigate', { cancelable: true, detail: { url: new URL(destination), history, cached: false } });
        document.dispatchEvent(event);
        if (event.defaultPrevented) return event;
        const requestOptions = { signal: new AbortController().signal };
        hooks.get('navigate.request')?.({ uri: destination, options: requestOptions });
        requests.push({ href: destination, history, options: navigationOptions, signal: requestOptions.signal });
        return event;
    }
    const Livewire = {
        getByName: name => name === 'duo' ? (data ? [wire] : []) : extraWires.filter(item => item.__instance.name === name),
        hook: (name, callback) => hooks.set(name, callback),
        navigate: (destination, next) => pageRequest(destination, false, next),
    };
    globalThis.window = window;
    globalThis.document = document;
    t.mock.method(console, 'error', (...args) => errors.push(args));
    t.after(() => {
        window.dispatchEvent(new Event('pagehide'));
        globalThis.window = originals.window;
        globalThis.document = originals.document;
        globalThis.fetch = originals.fetch;
        assert.equal(errors.length, options.expectedErrors ?? 0, errors.map(error => error.join(' ')).join('\n'));
    });
    const navigation = installNavigation(Livewire);
    window.addEventListener('popstate', event => {
        if (event.state?.alpine?.snapshotIdx) pageRequest(event.state.alpine.url, true);
    });
    async function ready() { document.dispatchEvent(new Event('livewire:navigated')); await tick(); }
    async function go(offset) {
        index += offset;
        assert.ok(index >= 0 && index < entries.length, 'history traversal is inside the stack');
        window.location = location(entries[index].href);
        window.dispatchEvent(new window.PopStateEvent('popstate', { state: window.history.state }));
        await tick();
    }
    async function swap(request = requests.at(-1), { page = undefined, nextLocale, nextOwnerId = ownerId, sharedMap = null, keepMap = true } = {}) {
        if (request.signal.aborted) return;
        const callbacks = [];
        document.dispatchEvent(new CustomEvent('livewire:navigating', { detail: { onSwap: callback => callbacks.push(callback) } }));
        const nextUrl = new URL(request.href);
        locale = nextLocale ?? (/^\/ru(?:\/|$)/.test(nextUrl.pathname) ? 'ru' : 'en');
        ownerId = nextOwnerId;
        data = page === null ? null : serverResult(request.href);
        if (page) data.page = sharedMapPage(page);
        if (sharedMap?.state?.page) data.page = sharedMapPage(sharedMap.state.page);
        sharedData.textContent = JSON.stringify(sharedMap);
        const state = { ...window.history.state, alpine: { snapshotIdx: `page-${++sequence}`, url: request.href } };
        recordHistory(request.history ? 'replaceState' : 'pushState', state, request.href);
        if (!data) window.rodnikMap = null;
        else if (!keepMap || !window.rodnikMap) window.rodnikMap = createMap(request.href, sharedMap);
        callbacks.forEach(callback => callback());
        await ready();
    }
    return {
        window, document, Livewire, navigation, calls, requests, scrolls, visits, writes, reloads, assignments, errors, restorations, sharedData,
        ready, go, swap, settled, head, hooks,
        entry: () => ({ ...structuredClone(entries[index]), index }),
        entries: () => structuredClone(entries),
        data: () => structuredClone(data),
        state: () => window.rodnikMap.captureSharedState(),
        camera: () => window.rodnikMap.captureSharedState().center,
        change: next => window.rodnikMap.change(next),
        anchors: value => { anchors = value; },
        wires: value => { extraWires = value; },
        maximumActiveCalls: () => maximumActiveCalls,
        photoCloses: () => photoCloses,
        async pasteHash(hash) {
            const destination = new URL(window.location.href);
            destination.hash = hash;
            entries.splice(index + 1);
            entries.push({ href: destination.href, state: structuredClone(window.history.state) });
            index++;
            window.location = location(destination.href);
            window.dispatchEvent(new Event('hashchange'));
            await tick();
        },
    };
}

function navigationAnchor(href, attributes = {}) {
    const dataset = { ...attributes };
    return {
        href, dataset,
        closest() { return null; },
        hasAttribute(name) {
            const key = name.replace(/^data-/, '').replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
            return Object.hasOwn(dataset, key);
        },
    };
}

function click(app, anchor, modifiers = {}) {
    app.document.dispatchEvent(new Event('pointerdown'));
    const event = Object.assign(new Event('click', { cancelable: true }), { button: 0, ...modifiers });
    Object.defineProperty(event, 'target', { value: { closest: () => anchor } });
    app.document.dispatchEvent(event);
    return event;
}

test('editing a saved view keeps the preview URL current for normal and modified clicks', async t => {
    const app = setup(t, { href: 'https://rodnik.test/user/maps/12/edit', page: null });
    const anchor = navigationAnchor('https://rodnik.test/1/#map=12/55/37', { rodnikNavigate: '', rodnikExactUrl: '' });
    anchor.target = '_blank';
    app.anchors([anchor]);
    await app.ready();
    const updated = 'https://rodnik.test/2/#map=8/40/20';
    anchor.href = updated;
    app.hooks.get('morphed')();
    assert.equal(anchor.href, updated);
    assert.equal(click(app, anchor).defaultPrevented, false);
    assert.equal(anchor.href, updated);
    assert.equal(click(app, anchor, { metaKey: true }).defaultPrevented, false);
    assert.equal(anchor.href, updated);
});

test('initialization uses the mounted Livewire proxy and live map without snapshotting reports', async t => {
    const app = setup(t);
    app.wires([{ __instance: { name: 'duo.reports.index' }, limit: 12 }]);
    await app.ready();
    app.navigation.capture();
    assert.equal(app.navigation.restoring, false);
    assert.equal(app.window.history.state.rodnik.duo, true);
    assert.equal(app.calls.length, 0);
    assert.equal(app.restorations.length, 0);
});

test('reloading uses the URL-owned map instead of old session camera and report snapshots', async t => {
    const storage = new Map([['rodnik.navigation.first', JSON.stringify({ map: { camera: [11, 22, 3] }, scroll: [0, 250], reports: { limit: 24, expanded: [12] } })]]);
    const app = setup(t, { href: 'https://rodnik.test/1/#map=12/55.7/37.6', storage });
    let expanded = 0;
    app.wires([{ __instance: { name: 'duo.components.show-more-reports' }, show: async () => expanded++ }]);
    await app.ready();
    assert.deepEqual(app.camera(), [37.6, 55.7]);
    assert.equal(app.window.scrollY, 100);
    assert.equal(expanded, 0);
    assert.equal(app.restorations.length, 0);
});

test('stale framework metadata is discarded on initialization without reloading', async t => {
    const app = setup(t, { initialState: { custom: 'keep', alpine: { snapshotIdx: 'wrong-user', url: 'https://rodnik.test/users/17/' } } });
    await app.ready();
    assert.equal(app.window.location.pathname, '/1/');
    assert.equal(app.window.history.state.alpine, undefined);
    assert.equal(app.window.history.state.custom, 'keep');
    assert.deepEqual(app.reloads, []);
});

test('source author and location navigation update Duo via component calls and retain the map instance', async t => {
    const app = setup(t);
    await app.ready();
    const map = app.window.rodnikMap;
    for (const href of ['/2/?user=17', '/users/17/', '/?location=1', '/2/?location=1']) await app.navigation.visit(href);
    assert.deepEqual(app.calls, ['/2/?user=17', '/users/17/', '/?location=1', '/2/?location=1']);
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.data().page, { spring: 2, user: null, location: 1 });
    assert.equal(app.requests.length, 0);
    assert.deepEqual(app.reloads, []);
    assert.equal(app.photoCloses(), 4);
    assert.equal(map.springRefreshes, 0);
});

test('Back and Forward request server selection and recenter while retaining current filters and track', async t => {
    const app = setup(t);
    await app.ready();
    const map = app.window.rodnikMap;
    const geometry = map.trackGeometry;
    await app.navigation.visit('/2/?user=17');
    app.change({ center: [-3, 40], sourceName: 'terrain', filters: { spring: false, with_reports: true } });
    map.sharedTrack.token = 'a'.repeat(10);
    app.navigation.capture();
    await app.go(-1);
    assert.deepEqual(app.camera(), coordinates(1));
    assert.deepEqual(app.data().page, { spring: 1, user: null, location: null });
    await app.go(1);
    assert.deepEqual(app.camera(), coordinates(2));
    assert.deepEqual(app.data().page, { spring: 2, user: 17, location: null });
    assert.equal(app.window.rodnikMap, map);
    assert.equal(map.trackGeometry, geometry);
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.state().filters.with_reports, true);
    assert.equal(app.state().sourceName, 'terrain');
    assert.equal(parseMapUrlState(app.window.location.href).trackToken, 'a'.repeat(10));
    assert.equal(app.restorations.length, 0);
    assert.deepEqual(app.calls, ['/2/?user=17', '/1/', '/2/?user=17']);
    assert.equal(app.entries().length, 2);
    assert.equal(app.requests.length, 0);
    assert.equal(map.springRefreshes, 0);
});

test('Back and Forward between author and location views retain their server selection', async t => {
    const app = setup(t);
    await app.ready();
    await app.navigation.visit('/users/17/');
    await app.navigation.visit('/2/?user=17&location=1');
    await app.go(-1);
    assert.deepEqual(app.data().page, { spring: null, user: 17, location: null });
    assert.deepEqual(app.camera(), coordinates(17));
    await app.go(1);
    assert.deepEqual(app.data().page, { spring: 2, user: 17, location: 1 });
    assert.equal(app.restorations.length, 0);
});

test('Back reaches the initial resource even when it originally had no history metadata', async t => {
    const app = setup(t, { initialState: null });
    await app.ready();
    await app.navigation.visit('/2/');
    app.change({ center: [1, 2] });
    await app.go(-1);
    assert.equal(app.window.location.pathname, '/1/');
    assert.deepEqual(app.camera(), coordinates(1));
    assert.deepEqual(app.calls, ['/2/', '/1/']);
    assert.deepEqual(app.reloads, []);
});

test('inconsistent cached history cannot swap an old page or force a document reload', async t => {
    const app = setup(t);
    await app.ready();
    await app.navigation.visit('/2/');
    app.window.location.href = 'https://rodnik.test/1/';
    app.window.dispatchEvent(new app.window.PopStateEvent('popstate', { state: { alpine: { snapshotIdx: 'cached-2', url: 'https://rodnik.test/2/' } } }));
    await tick();
    assert.equal(app.data().page.spring, 1);
    assert.equal(app.window.location.pathname, '/1/');
    assert.equal(app.requests.length, 0);
    assert.deepEqual(app.reloads, []);
});

test('resource history ignores stale expanded-report snapshots on a different author', async t => {
    const app = setup(t);
    let expanded = 0;
    app.wires([{ __instance: { name: 'duo.components.show-more-reports' }, userId: 17, skip: 12, shown: true, show: async () => expanded++ }]);
    await app.ready();
    await app.navigation.visit('/users/17/');
    await app.navigation.visit('/users/18/');
    await app.go(-1);
    assert.equal(app.data().page.user, 17);
    assert.equal(expanded, 0);
    assert.equal(app.restorations.length, 0);
});

test('rapid resource navigation is serialized and only the latest requested destination wins', async t => {
    const pending = [];
    const app = setup(t, { navigate: href => {
        const deferred = Promise.withResolvers();
        pending.push({ href, ...deferred });
        return deferred.promise;
    } });
    await app.ready();
    const originalVisits = app.visits.length;
    const first = app.navigation.visit('/2/');
    app.navigation.visit('/3/');
    app.navigation.visit('/users/17/');
    assert.deepEqual(app.calls, ['/2/']);
    pending[0].resolve(serverResult('/2/'));
    await tick();
    assert.deepEqual(app.calls, ['/2/', '/users/17/']);
    assert.equal(app.visits.length, originalVisits);
    assert.equal(app.window.location.pathname, '/1/');
    pending[1].resolve(serverResult('/users/17/'));
    await first;
    assert.equal(app.maximumActiveCalls(), 1);
    assert.equal(app.data().page.user, 17);
    assert.equal(app.window.location.pathname, '/users/17/');
    assert.equal(app.entries().length, 2);
    assert.equal(app.requests.length, 0);
});

test('returning to the current resource supersedes an outstanding component request', async t => {
    const pending = [];
    const app = setup(t, { navigate: href => { const deferred = Promise.withResolvers(); pending.push({ href, ...deferred }); return deferred.promise; } });
    await app.ready();
    const first = app.navigation.visit('/2/');
    app.navigation.visit(app.window.location.href);
    pending[0].resolve(serverResult('/2/'));
    await tick();
    pending[1].resolve(serverResult('/1/'));
    await first;
    assert.equal(app.data().page.spring, 1);
    assert.equal(app.entries().length, 1);
    assert.equal(app.navigation.restoring, false);
});

test('fragment Back during an in-flight component update restores the latest server selection and map', async t => {
    const pending = [];
    const app = setup(t, {
        href: 'https://rodnik.test/1/#map=12/55.7/37.6',
        navigate: href => { const deferred = Promise.withResolvers(); pending.push({ href, ...deferred }); return deferred.promise; },
    });
    await app.ready();
    await app.pasteHash('#map=6/45/15');
    const request = app.navigation.visit('/2/');
    await app.go(-1);
    pending[0].resolve(serverResult('/2/'));
    await tick();
    assert.deepEqual(app.calls, ['/2/', '/1/']);
    pending[1].resolve(serverResult('/1/'));
    await request;
    assert.equal(app.data().page.spring, 1);
    assert.equal(app.window.location.pathname, '/1/');
    assert.deepEqual(app.camera(), [37.6, 55.7]);
    assert.equal(app.entries().length, 2);
    assert.equal(app.requests.length, 0);
    assert.equal(app.maximumActiveCalls(), 1);
});

test('pasting a fragment supersedes an in-flight component update without accepting its stale morph', async t => {
    const pending = [];
    const app = setup(t, { navigate: href => { const deferred = Promise.withResolvers(); pending.push({ href, ...deferred }); return deferred.promise; } });
    await app.ready();
    const request = app.navigation.visit('/2/');
    await app.pasteHash('#map=6/45/15&layer=terrain');
    pending[0].resolve(serverResult('/2/'));
    await tick();
    assert.deepEqual(app.calls, ['/2/', '/1/']);
    pending[1].resolve(serverResult('/1/'));
    await request;
    assert.equal(app.data().page.spring, 1);
    assert.equal(app.window.location.pathname, '/1/');
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.state().sourceName, 'terrain');
    assert.equal(app.entries().length, 2);
    assert.equal(app.requests.length, 0);
    assert.equal(app.maximumActiveCalls(), 1);
});

test('page navigation requests are cancelled when superseded and a resource visit needs no page GET', async t => {
    const app = setup(t);
    await app.ready();
    await app.navigation.visit('/docs/about');
    await app.navigation.visit('/docs/privacy');
    assert.equal(app.requests[0].signal.aborted, true);
    assert.equal(app.requests[1].signal.aborted, false);
    await app.navigation.visit('/1/');
    assert.equal(app.requests[1].signal.aborted, true);
    assert.deepEqual(app.calls, ['/1/']);
    assert.equal(app.navigation.restoring, false);
});

test('ordinary component failures retain the visible resource and never reload the document', async t => {
    const app = setup(t, { expectedErrors: 1, navigate: async () => { throw new Error('Network unavailable'); } });
    await app.ready();
    await app.navigation.visit('/2/');
    assert.equal(app.window.location.pathname, '/1/');
    assert.equal(app.data().page.spring, 1);
    assert.equal(app.navigation.restoring, false);
    assert.equal(app.errors.length, 1);
    assert.deepEqual(app.reloads, []);
    assert.deepEqual(app.assignments, []);
});

test('a failed history request restores the last working address without another entry', async t => {
    let fail = false;
    const app = setup(t, { expectedErrors: 1, navigate: async href => { if (fail) throw new Error('Not found'); return serverResult(href); } });
    await app.ready();
    await app.navigation.visit('/2/');
    fail = true;
    await app.go(-1);
    assert.equal(app.window.location.pathname, '/2/');
    assert.equal(app.data().page.spring, 2);
    assert.equal(app.entries().length, 2);
    assert.equal(app.navigation.restoring, false);
    assert.deepEqual(app.reloads, []);
});

test('Livewire action redirects to resources are intercepted into component navigation', async t => {
    const app = setup(t);
    await app.ready();
    const event = app.Livewire.navigate('https://rodnik.test/2/?redirect=false');
    await tick();
    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(app.calls, ['/2/?redirect=false']);
    assert.equal(app.window.location.search, '?redirect=false');
    assert.equal(app.requests.length, 0);
    assert.equal(app.window.rodnikMap.springRefreshes, 1);
});

test('action redirects to the same source refresh its Livewire content without pushing history', async t => {
    const app = setup(t);
    await app.ready();
    app.Livewire.navigate(app.window.location.href);
    await tick();
    assert.deepEqual(app.calls, ['/1/']);
    assert.equal(app.entries().length, 1);
    assert.equal(app.requests.length, 0);
    assert.equal(app.window.rodnikMap.springRefreshes, 1);
});

test('source metadata and indexing links follow component navigation', async t => {
    const app = setup(t);
    await app.ready();
    await app.navigation.visit('/2/?user=17');
    assert.equal(app.document.title, 'Source 2 — Rodnik.today');
    assert.equal(app.document.querySelector('link[rel="canonical"]').href, 'https://rodnik.test/2/');
    assert.equal(app.document.querySelector('meta[property="og:url"]').content, 'https://rodnik.test/2/');
    assert.equal(app.document.querySelectorAll('link[rel="alternate"][hreflang]').length, 3);
    await app.navigation.visit('/2/?location=1');
    assert.equal(app.document.querySelector('meta[name="robots"]').content, 'noindex, nofollow');
    assert.equal(app.document.querySelectorAll('link[rel="alternate"][hreflang]').length, 0);
    await app.go(-1);
    assert.equal(app.document.querySelector('meta[name="robots"]'), undefined);
    assert.equal(app.document.querySelectorAll('link[rel="alternate"][hreflang]').length, 3);
});

test('Back from a non-map page fetches through Livewire history without adding an entry', async t => {
    const app = setup(t);
    await app.ready();
    app.change({ center: [15, 45], zoom: 6, filters: { spring: false } });
    app.navigation.capture();
    const source = app.entry();
    const originalMap = app.window.rodnikMap;
    await app.navigation.visit('/docs/about');
    await app.swap(undefined, { page: null });
    assert.equal(app.window.rodnikMap, null);
    const count = app.entries().length;
    await app.go(-1);
    assert.equal(app.requests.at(-1).history, true);
    assert.equal(app.requests.at(-1).href, source.href);
    assert.equal(app.entries().length, count);
    await app.swap();
    assert.notEqual(app.window.rodnikMap, originalMap);
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.entries().length, count);
    assert.deepEqual(app.reloads, []);
});

test('Forward to a non-Duo page uses an uncached Livewire fetch with the exact reached URL', async t => {
    const app = setup(t);
    await app.ready();
    await app.navigation.visit('/docs/about?ref=a%2Bb#section');
    await app.swap(undefined, { page: null });
    await app.go(-1);
    await app.swap();
    await app.go(1);
    const request = app.requests.at(-1);
    assert.equal(request.href, 'https://rodnik.test/docs/about?ref=a%2Bb#section');
    assert.equal(request.history, true);
    await app.swap(request, { page: null });
    assert.equal(app.entries().length, 2);
    assert.deepEqual(app.reloads, []);
});

test('translation globals and the same map survive locale page navigation and Back', async t => {
    const app = setup(t);
    await app.ready();
    const map = app.window.rodnikMap;
    app.change({ center: [15, 45], zoom: 6, filters: { with_reports: true } });
    app.navigation.capture();
    await app.navigation.visit('/ru/1/', { preserveMap: true, preserveScroll: true });
    await app.swap();
    assert.equal(app.window.rodnikLocale, 'ru');
    assert.equal(app.window.rodnikTranslations.label, 'ru');
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.camera(), [15, 45]);
    await app.go(-1);
    await app.swap();
    assert.equal(app.window.rodnikLocale, 'en');
    assert.equal(app.window.rodnikMap, map);
    assert.equal(app.state().filters.with_reports, true);
    assert.deepEqual(app.reloads, []);
});

test('fresh body translations override a stale head script after Docs Back and locale navigation', async t => {
    const app = setup(t, { staleHeadLocale: 'en' });
    await app.ready();
    await app.navigation.visit('/docs/about');
    await app.swap(undefined, { page: null });
    await app.go(-1);
    await app.swap();
    const map = app.window.rodnikMap;
    await app.navigation.visit('/ru/1/', { preserveMap: true });
    await app.swap();

    assert.equal(JSON.parse(app.document.getElementById('rodnik-translations').textContent).locale, 'en');
    assert.equal(app.window.rodnikLocale, 'ru');
    assert.equal(app.window.rodnikTranslations.label, 'ru');
    assert.equal(app.window.rodnikMapTranslations.label, 'ru');
    assert.equal(app.window.rodnikPublicBaseUrl, 'https://rodnik.test/ru');
    assert.equal(map.locale, 'ru');
    const pageRequests = app.requests.length;
    await app.navigation.visit('/ru/2/');
    assert.deepEqual(app.calls, ['/ru/2/']);
    assert.equal(app.requests.length, pageRequests);
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.reloads, []);
});

test('locale preferences are serialized and only the latest choice navigates', async t => {
    const app = setup(t);
    await app.ready();
    const preferences = [];
    globalThis.fetch = (action, options) => { const deferred = Promise.withResolvers(); preferences.push({ action, options, ...deferred }); return deferred.promise; };
    app.navigation.changeLocale('ru', '/locale/ru');
    app.navigation.changeLocale('en', '/locale/en');
    await tick();
    assert.equal(preferences.length, 1);
    assert.deepEqual(JSON.parse(preferences[0].options.body), { redirect: '/ru/1/' });
    preferences[0].resolve({ ok: true });
    await tick();
    assert.equal(preferences.length, 2);
    assert.equal(app.requests.length, 0);
    preferences[1].resolve({ ok: true });
    await tick();
    assert.equal(app.requests.length, 0);
    assert.equal(app.window.location.pathname, '/1/');
});

test('failed locale writes keep the current page without assigning a new document', async t => {
    const app = setup(t, { expectedErrors: 1 });
    await app.ready();
    globalThis.fetch = async () => ({ ok: false });
    app.navigation.changeLocale('ru', '/locale/ru');
    await tick();
    assert.equal(app.window.rodnikLocale, 'en');
    assert.equal(app.errors.length, 1);
    assert.deepEqual(app.assignments, []);
    assert.deepEqual(app.reloads, []);
});

test('source anchors follow changed and removed context before a modified click', async t => {
    const app = setup(t);
    const anchor = navigationAnchor('https://rodnik.test/2/?user=17');
    app.anchors([anchor]);
    await app.ready();
    app.window.location.href = 'https://rodnik.test/1/?track=new#map=6/45/15';
    const event = click(app, anchor, { ctrlKey: true });
    let target = new URL(anchor.href);
    assert.equal(event.defaultPrevented, false);
    assert.equal(target.searchParams.get('user'), '17');
    assert.equal(target.searchParams.get('track'), 'new');
    assert.equal(target.hash, '#map=6/45/15');
    app.window.location.href = 'https://rodnik.test/1/#map=7/45/15';
    app.document.dispatchEvent(new Event('pointerdown'));
    target = new URL(anchor.href);
    assert.equal(target.searchParams.has('track'), false);
    assert.equal(target.hash, '#map=7/45/15');
    assert.equal(app.calls.length, 0);
});

test('locale anchors preserve latest query and fragment for modified clicks', async t => {
    const app = setup(t);
    const anchor = navigationAnchor('https://rodnik.test/ru/1/', { rodnikLocale: 'ru', rodnikNavigate: '' });
    app.anchors([anchor]);
    await app.ready();
    app.window.location.href = 'https://rodnik.test/1/?user=17&track=new#map=6/45/15';
    app.document.dispatchEvent(new Event('pointerdown'));
    const target = new URL(anchor.href);
    assert.equal(target.pathname, '/ru/1/');
    assert.equal(target.searchParams.get('user'), '17');
    assert.equal(target.hash, '#map=6/45/15');
});

test('source links recenter without restoring their inherited camera or filters', async t => {
    const app = setup(t);
    const anchor = navigationAnchor('https://rodnik.test/2/');
    app.anchors([anchor]);
    await app.ready();
    app.change({ center: [15, 45], filters: { spring: false } });
    app.navigation.capture();
    assert.equal(click(app, anchor).defaultPrevented, true);
    await tick();
    assert.deepEqual(app.camera(), coordinates(2));
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.restorations.length, 0);
    assert.deepEqual(app.calls, ['/2/']);
});

test('explicit map links restore their fragment with the authoritative server resource', async t => {
    const app = setup(t);
    const anchor = navigationAnchor('https://rodnik.test/2/#map=7/50/20&filters=water_tap');
    app.anchors([anchor]);
    await app.ready();
    click(app, anchor);
    await tick();
    assert.equal(app.restorations.length, 1);
    assert.deepEqual(app.restorations[0].state.center, [20, 50]);
    assert.deepEqual(app.restorations[0].state.page, { spring: 2, user: null, location: null });
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.visits.at(-1).preserveMapView, true);
});

for (const user of [null, 17]) {
    test(`${user ? 'user' : 'area'} report links preserve the live camera while updating the source content`, async t => {
        const app = setup(t, { href: user ? `https://rodnik.test/users/${user}/` : 'https://rodnik.test/' });
        const anchor = navigationAnchor(`https://rodnik.test/2/${user ? `?user=${user}` : ''}`, { rodnikPreserveMap: '' });
        app.anchors([anchor]);
        await app.ready();
        app.change({ center: [15, 45], zoom: 11.25 });
        app.navigation.capture();
        click(app, anchor);
        await tick();
        assert.deepEqual(app.camera(), [15, 45]);
        assert.equal(app.state().zoom, 11.25);
        assert.equal(app.data().page.spring, 2);
        assert.equal(app.data().page.user, user);
        assert.equal(app.window.location.pathname, '/2/');
        assert.equal(app.window.location.searchParams.get('user'), user ? String(user) : null);
        assert.equal(parseMapUrlState(app.window.location.href).state.zoom, 11.25);
        assert.equal(app.visits.at(-1).preserveMapView, true);
    });
}

test('map selection and deselection events update spring user and location through Duo', async t => {
    const app = setup(t);
    await app.ready();
    app.window.dispatchEvent(new CustomEvent('duo-visit', { detail: { spring: 2, user: 17 } }));
    await tick();
    app.window.dispatchEvent(new CustomEvent('duo-visit', { detail: { user: 17 } }));
    await tick();
    app.window.dispatchEvent(new CustomEvent('duo-visit', { detail: { location: 1 } }));
    await tick();
    assert.deepEqual(app.calls, ['/2/?user=17', '/users/17/', '/?location=1']);
    assert.equal(app.requests.length, 0);
});

test('marker clicks and background deselection update content and URL without moving the live map', async t => {
    const app = setup(t);
    await app.ready();
    app.change({ center: [15, 45], zoom: 11.25, filters: { spring: false } });
    app.navigation.capture();
    const map = app.window.rodnikMap;
    for (const spring of [2, null]) {
        app.window.dispatchEvent(new CustomEvent('duo-visit', {
            detail: { spring, user: 17, location: null, preserveMapView: true },
        }));
        await tick();
        assert.deepEqual(app.camera(), [15, 45]);
        assert.equal(app.state().zoom, 11.25);
        assert.equal(app.state().filters.spring, false);
        assert.equal(app.window.rodnikMap, map);
        assert.deepEqual(app.data().page, { spring, user: 17, location: null });
        assert.equal(app.visits.at(-1).preserveMapView, true);
        const urlState = parseMapUrlState(app.window.location.href);
        assert.deepEqual(urlState.state.center, [15, 45]);
        assert.equal(urlState.state.zoom, 11.25);
    }
    assert.deepEqual(app.calls, ['/2/?user=17', '/users/17/']);
    assert.equal(app.restorations.length, 0);
    assert.equal(app.requests.length, 0);
});

test('saving a new short link retains the normal URL and editing record without navigation', async t => {
    const app = setup(t);
    await app.ready();
    const before = app.entry();
    const record = { id: 1, url: 'https://rodnik.test/maps/shared01', title: 'Sunday', state: { page: { spring: 1 } } };
    app.navigation.savedMap(record);
    assert.deepEqual(app.entry(), before);
    assert.deepEqual(app.window.rodnikSharedMap, record);
    assert.deepEqual(JSON.parse(app.sharedData.textContent), record);
    assert.equal(app.requests.length, 0);
    assert.equal(app.calls.length, 0);
});

test('following a short link expands to the resource and complete live state without pushing history', async t => {
    const token = 'a'.repeat(10);
    const record = { id: 1, url: 'https://rodnik.test/maps/shared01', title: 'Sunday', track: { token }, state: normalizeSharedMapState({ version: 1, center: [37.6, 55.7], zoom: 12, page: { spring: 1 }, filters: { spring: false, along: true }, sourceName: 'terrain' }) };
    const app = setup(t, { href: record.url, sharedRecord: record, initialState: { custom: 'keep', alpine: { snapshotIdx: 'short', url: record.url, extra: true } } });
    await app.ready();
    const parsed = parseMapUrlState(app.window.location.href);
    assert.equal(app.window.location.pathname, '/1/');
    assert.equal(parsed.trackToken, token);
    assert.equal(parsed.state.sourceName, 'terrain');
    assert.equal(parsed.state.filters.spring, false);
    assert.equal(parsed.state.filters.along, true);
    assert.deepEqual(parsed.state.center, [37.6, 55.7]);
    assert.deepEqual(app.window.history.state.alpine, { extra: true });
    assert.equal(app.window.history.state.custom, 'keep');
    assert.equal(app.window.rodnikSharedMap.title, 'Sunday');
    assert.equal(app.entries().length, 1);
    assert.equal(app.requests.length, 0);
});

test('opening another short link through Livewire applies its saved state to the existing map', async t => {
    const app = setup(t);
    await app.ready();
    const map = app.window.rodnikMap;
    const record = { url: 'https://rodnik.test/maps/new', resource_url: 'https://rodnik.test/2/', track: null, state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7, page: { spring: 2 }, filters: { with_reports: true } }) };
    await app.navigation.visit(record.url);
    await app.swap(undefined, { sharedMap: record });
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.state().filters.with_reports, true);
    assert.equal(app.window.location.pathname, '/2/');
    assert.equal(app.entries().length, 2);
});

test('pan zoom filters and track persistence replace the address while retaining independent queries', async t => {
    const app = setup(t, { href: 'https://rodnik.test/1/?user=17&campaign=test' });
    await app.ready();
    app.change({ center: [-3.4, 40.5], zoom: 8.75, filters: { with_reports: true } });
    await app.settled();
    assert.equal(app.window.location.search, '?user=17&campaign=test');
    assert.equal(app.window.location.hash, '#m=8.75/40.5/-3.4&f=spring,well,tap,drinking,fountain,other,reports');
    app.window.rodnikMap.sharedTrack = { hash: 'b'.repeat(64), token: 'AbCd123456', status: 'saved' };
    app.window.dispatchEvent(new Event('map-track-persistence-changed'));
    await app.settled();
    assert.equal(parseMapUrlState(app.window.location.href).trackToken, 'AbCd123456');
    app.window.rodnikMap.sharedTrack.hash = null;
    app.window.rodnikMap.sharedTrack.token = null;
    app.window.dispatchEvent(new Event('map-track-persistence-changed'));
    await app.settled();
    assert.equal(parseMapUrlState(app.window.location.href).trackToken, null);
    assert.equal(app.entries().length, 1);
    assert.equal(app.requests.length, 0);
    assert.equal(app.calls.length, 0);
});

test('saving later changes retains the expanded URL and current history entry', async t => {
    const app = setup(t);
    await app.ready();
    const before = app.entry();
    app.navigation.savedMap({ id: 1, url: 'https://rodnik.test/maps/shared01', title: 'Updated' });
    assert.deepEqual(app.entry(), before);
    assert.equal(app.window.rodnikSharedMap.title, 'Updated');
    assert.equal(app.requests.length, 0);
});

test('stale Livewire share URLs on Forward are resolved from the actual normal address without reload', async t => {
    const app = setup(t);
    await app.ready();
    const event = new CustomEvent('livewire:navigate', { cancelable: true, detail: { url: new URL('https://rodnik.test/maps/shared01'), history: true, cached: true } });
    app.document.dispatchEvent(event);
    await tick();
    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(app.calls, ['/1/']);
    assert.equal(app.window.location.pathname, '/1/');
    assert.deepEqual(app.reloads, []);
});

test('pasting an explicit fragment restores in place and clears an omitted track', async t => {
    const app = setup(t);
    await app.ready();
    app.window.rodnikMap.sharedTrack.token = 'a'.repeat(10);
    await app.pasteHash('#map=6/45/15&layer=terrain');
    assert.equal(app.restorations.length, 1);
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.restorations[0].track, null);
    assert.equal(app.state().sourceName, 'terrain');
    assert.equal(app.calls.length, 0);
    assert.equal(app.requests.length, 0);
    assert.equal(app.navigation.restoring, false);
});

test('explicit fragment Back and Forward restore views without swapping server content', async t => {
    const app = setup(t, { href: 'https://rodnik.test/1/#map=12/55.7/37.6' });
    await app.ready();
    await app.pasteHash('#map=6/45/15');
    await app.go(-1);
    assert.deepEqual(app.camera(), [37.6, 55.7]);
    await app.go(1);
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.restorations.length, 3);
    assert.equal(app.requests.length, 0);
    assert.equal(app.calls.length, 0);
    assert.deepEqual(app.reloads, []);
});

test('explicit fragment changes retain an unchanged track without loading it again', async t => {
    const token = 'c'.repeat(10);
    const app = setup(t, { href: `https://rodnik.test/1/#map=12/55.7/37.6&track=${token}` });
    await app.ready();
    await app.pasteHash(`#map=6/45/15&track=${token}`);
    assert.equal(app.restorations[0].track, undefined);
    assert.equal(app.window.rodnikMap.sharedTrack.token, token);
});

test('an invalid fragment leaves the live map usable and makes no server request', async t => {
    const app = setup(t);
    await app.ready();
    const before = app.state();
    await app.pasteHash('#map=invalid');
    assert.deepEqual(app.state(), before);
    assert.equal(app.navigation.restoring, false);
    assert.equal(app.calls.length, 0);
    assert.equal(app.requests.length, 0);
    assert.deepEqual(app.reloads, []);
});

test('expanding a merged source retains the server URL required to revisit the original', async t => {
    const record = { url: 'https://rodnik.test/maps/shared01', resource_url: 'https://rodnik.test/1/?redirect=false&user=17' };
    const app = setup(t, { href: record.url, sharedRecord: record, page: { spring: 1, user: 17 } });
    await app.ready();
    assert.equal(app.window.location.pathname, '/1/');
    assert.equal(app.window.location.search, '?redirect=false&user=17');
    assert.ok(parseMapUrlState(app.window.location.href));
});

for (const path of ['/maps/shared01', '/maps/sunday-walk', '/ru/maps/sunday-walk', '/ru/maps/%D0%BF%D0%BE%D1%85%D0%BE%D0%B4']) {
    test(`following ${path} expands to its resource while retaining the saved record and track`, async t => {
        const token = 'd'.repeat(10);
        const resourceUrl = `https://rodnik.test${path.startsWith('/ru/') ? '/ru' : ''}/2/?user=17`;
        const record = {
            id: 1, title: 'Sunday walk', url: `https://rodnik.test${path}`, resource_url: resourceUrl,
            track: { token },
            state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7.5, page: { spring: 2, user: 17 }, filters: { with_reports: true }, sourceName: 'terrain' }),
        };
        const app = setup(t, { href: record.url, sharedRecord: record });
        app.window.rodnikMap.sharedTrack.status = 'loading';
        const map = app.window.rodnikMap;
        await app.ready();

        assert.equal(app.window.location.origin + app.window.location.pathname + app.window.location.search, resourceUrl);
        const restored = parseMapUrlState(app.window.location.href);
        assert.deepEqual(restored.state.center, [15, 45]);
        assert.equal(restored.state.zoom, 7.5);
        assert.equal(restored.state.filters.with_reports, true);
        assert.equal(restored.state.sourceName, 'terrain');
        assert.equal(restored.trackToken, token);
        assert.equal(app.window.rodnikMap, map);
        assert.deepEqual(app.window.rodnikSharedMap, record);
        assert.deepEqual(JSON.parse(app.sharedData.textContent), record);
        assert.equal(app.entries().length, 1);
        assert.equal(app.requests.length, 0);
        assert.equal(app.calls.length, 0);
    });
}

test('saved-map anchors open through Livewire without inheriting the current map fragment', async t => {
    const app = setup(t);
    const record = {
        id: 2, title: 'Mountain walk', url: 'https://rodnik.test/maps/mountain-walk', resource_url: 'https://rodnik.test/2/',
        track: null,
        state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7, page: { spring: 2 }, filters: { spring: false, with_reports: true } }),
    };
    const anchor = navigationAnchor(record.url);
    app.anchors([anchor]);
    await app.ready();
    app.change({ center: [-3, 40], zoom: 10, sourceName: 'satellite' });
    app.navigation.capture();
    const map = app.window.rodnikMap;
    assert.equal(click(app, anchor).defaultPrevented, true);
    assert.equal(anchor.href, record.url);
    assert.equal(app.requests.at(-1).href, record.url);
    assert.equal(app.calls.length, 0);
    await app.swap(undefined, { sharedMap: record });

    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.camera(), [15, 45]);
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.state().sourceName, 'osm');
    assert.equal(app.window.location.pathname, '/2/');
    assert.equal(app.entries().length, 2);
    assert.deepEqual(app.window.rodnikSharedMap, record);
    assert.deepEqual(app.reloads, []);
});

test('selecting a saved record for the current view preserves the camera and normal URL', async t => {
    const app = setup(t);
    await app.ready();
    app.change({ center: [-3, 40], zoom: 8, filters: { spring: false } });
    app.window.rodnikMap.sharedTrack.token = 'e'.repeat(10);
    app.navigation.capture();
    const before = app.entry();
    const map = app.window.rodnikMap;
    const record = {
        id: 3, title: 'Selected map', url: 'https://rodnik.test/maps/selected-map',
        state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 12, page: { spring: 2 } }),
        track: null,
    };
    app.navigation.savedMap(record);

    assert.deepEqual(app.entry(), before);
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.camera(), [-3, 40]);
    assert.equal(app.state().filters.spring, false);
    assert.equal(app.window.rodnikMap.sharedTrack.token, 'e'.repeat(10));
    assert.deepEqual(app.window.rodnikSharedMap, record);
    assert.deepEqual(JSON.parse(app.sharedData.textContent), record);
    assert.equal(app.restorations.length, 0);
    assert.equal(app.requests.length, 0);
    assert.equal(app.calls.length, 0);
});

test('user/maps links remain ordinary Livewire library pages without source expansion', async t => {
    const app = setup(t);
    const anchor = navigationAnchor('https://rodnik.test/user/maps?page=2');
    app.anchors([anchor]);
    await app.ready();
    assert.equal(click(app, anchor).defaultPrevented, true);
    assert.equal(app.requests.at(-1).href, 'https://rodnik.test/user/maps?page=2');
    await app.swap(undefined, { page: null });

    assert.equal(app.window.location.pathname, '/user/maps');
    assert.equal(app.window.location.search, '?page=2');
    assert.equal(app.window.location.hash, '');
    assert.equal(app.entries().length, 2);
    assert.equal(app.calls.length, 0);
    assert.deepEqual(app.reloads, []);
});

test('a saved map keeps its identity through resource updates and locale swaps without restoring its old view', async t => {
    const record = {
        id: 4, title: 'Lycian Way', url: 'https://rodnik.test/maps/lycian-way',
        state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7, page: { spring: 1 } }),
        track: null,
    };
    const app = setup(t, { ownerId: 3, sharedRecord: record });
    await app.ready();
    app.change({ center: [29, 36], zoom: 12.5 });
    await app.navigation.visit('/2/', { preserveMap: true });
    const map = app.window.rodnikMap;
    assert.deepEqual(app.window.rodnikSharedMap, record);

    await app.navigation.visit('/ru/2/', { preserveMap: true });
    await app.swap();
    assert.equal(app.window.rodnikMap, map);
    assert.deepEqual(app.window.rodnikSharedMap, record);
    assert.deepEqual(app.camera(), [29, 36]);
    assert.equal(app.state().zoom, 12.5);
    assert.equal(app.restorations.length, 0);
    assert.equal(JSON.parse(app.sharedData.textContent), null, 'retaining identity does not inject an old saved snapshot into the new page');
});

test('an ordinary cross-locale source visit moves to that source while retaining the same saved map identity', async t => {
    const record = {
        id: 4, title: 'Lycian Way',
        state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7, page: { spring: 1 } }),
        track: null,
    };
    const app = setup(t, { ownerId: 3, sharedRecord: record });
    await app.ready();
    await app.navigation.visit('/ru/2/');
    await app.swap();
    assert.deepEqual(app.window.rodnikSharedMap, record);
    assert.deepEqual(app.camera(), coordinates(2));
    assert.equal(app.state().zoom, 14);
    assert.equal(app.restorations.length, 0);
});

test('a saved map identity is cleared when a new account or a new map instance is loaded', async t => {
    for (const next of [{ nextOwnerId: 4 }, { keepMap: false }]) {
        const record = { id: 4, title: 'First account map', state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 7 }), track: null };
        const app = setup(t, { ownerId: 3, sharedRecord: record });
        await app.ready();
        await app.navigation.visit('/ru/2/');
        await app.swap(undefined, next);
        assert.equal(app.window.rodnikSharedMap, null);
    }
});

test('choosing a draft view from My Maps returns to the latest map URL for that account', async t => {
    const app = setup(t, { ownerId: 3 });
    await app.ready();
    app.change({ center: [29, 36], zoom: 11.5, filters: { with_reports: true }, sourceName: 'terrain' });
    app.window.rodnikMap.sharedTrack.token = 'b'.repeat(10);
    const expected = app.state();
    await app.navigation.visit('/user/maps');
    await app.swap(undefined, { page: null });
    const url = app.navigation.mapUrl('https://rodnik.test/ru/', 3);
    assert.equal(new URL(url).pathname, '/ru/1/');
    assert.deepEqual(parseMapUrlState(url).state.center, expected.center);
    assert.equal(parseMapUrlState(url).state.zoom, expected.zoom);
    assert.equal(parseMapUrlState(url).state.filters.with_reports, true);
    assert.equal(parseMapUrlState(url).state.sourceName, 'terrain');
    assert.equal(parseMapUrlState(url).trackToken, 'b'.repeat(10));

    await app.navigation.visit(url, { restoreUrl: true });
    await app.swap();
    assert.deepEqual(app.camera(), expected.center);
    assert.equal(app.state().zoom, expected.zoom);
});

test('the view handoff never reuses another account map and falls back when no map was visited', async t => {
    const app = setup(t, { ownerId: 3 });
    await app.ready();
    await app.navigation.visit('/user/maps');
    await app.swap(undefined, { page: null, nextOwnerId: 4 });
    assert.equal(app.navigation.mapUrl('/ru/', 4), '/ru/');
    assert.equal(app.navigation.mapUrl('/ru/', 3), '/ru/');

    const library = setup(t, { ownerId: 3, href: 'https://rodnik.test/user/maps', page: null });
    await library.ready();
    assert.equal(library.navigation.mapUrl('/ru/', 3), '/ru/');
});

test('an unshared local track survives reload privately with its URL camera and filters', async t => {
    const storage = new Map();
    const app = setup(t, { storage, ownerId: 7 });
    await app.ready();
    const track = privateTrack();
    app.window.rodnikMap.restoreLocalTrack(track);
    app.change({ center: [15, 45], zoom: 6, filters: { spring: false } });
    app.navigation.capture();
    const entry = app.entry();
    assert.ok(entry.state.rodnik.localTrackId);
    assert.equal(parseMapUrlState(entry.href).trackToken, null);

    const reloaded = setup(t, { href: entry.href, initialState: entry.state, storage, ownerId: 7 });
    await reloaded.ready();
    assert.deepEqual(reloaded.window.rodnikMap.trackGeometry, track);
    assert.equal(reloaded.window.rodnikMap.sharedTrack.status, 'local');
    assert.equal(parseMapUrlState(reloaded.window.location.href).trackToken, null);
    assert.deepEqual(reloaded.camera(), [15, 45]);
    assert.equal(reloaded.state().filters.spring, false);
    assert.equal(reloaded.requests.length, 0);
    assert.equal(storage.size, 1);
});

test('resource links carry one local track reference and Back restores it after leaving the map', async t => {
    const storage = new Map();
    const app = setup(t, { storage, ownerId: 7 });
    await app.ready();
    const track = privateTrack();
    app.window.rodnikMap.restoreLocalTrack(track);
    app.navigation.capture();
    const id = app.entry().state.rodnik.localTrackId;
    await app.navigation.visit('/2/');
    assert.equal(app.entry().state.rodnik.localTrackId, id);
    await app.navigation.visit('/docs/about');
    await app.swap(undefined, { page: null });
    await app.go(-1);
    await app.swap();

    assert.deepEqual(app.window.rodnikMap.trackGeometry, track);
    assert.equal(app.window.rodnikMap.sharedTrack.status, 'local');
    assert.equal(app.entry().state.rodnik.localTrackId, id);
    assert.equal(parseMapUrlState(app.window.location.href).trackToken, null);
    assert.equal(storage.size, 1);
});

test('pasted track-free fragments clear private tracks while Back restores the prior private entry', async t => {
    const app = setup(t, { storage: new Map() });
    await app.ready();
    const track = privateTrack();
    app.window.rodnikMap.restoreLocalTrack(track);
    app.navigation.capture();
    const id = app.entry().state.rodnik.localTrackId;
    await app.pasteHash('#map=6/45/15');

    assert.equal(app.window.rodnikMap.trackGeometry.features.length, 0);
    assert.equal(app.entry().state.rodnik.localTrackId, undefined);
    await app.go(-1);
    assert.deepEqual(app.window.rodnikMap.trackGeometry, track);
    assert.equal(app.window.rodnikMap.sharedTrack.status, 'local');
    assert.equal(app.entry().state.rodnik.localTrackId, id);
});

test('opening a track-free shared map never inherits the preceding private track', async t => {
    const app = setup(t, { storage: new Map() });
    await app.ready();
    app.window.rodnikMap.restoreLocalTrack(privateTrack());
    app.navigation.capture();
    const record = {
        url: 'https://rodnik.test/maps/shared01/', track: null,
        state: normalizeSharedMapState({ version: 1, center: [15, 45], zoom: 6, page: { spring: 2 } }),
    };
    await app.navigation.visit(record.url);
    await app.swap(undefined, { sharedMap: record });

    assert.equal(app.window.rodnikMap.trackGeometry.features.length, 0);
    assert.equal(app.entry().state.rodnik.localTrackId, undefined);
    assert.equal(parseMapUrlState(app.window.location.href).trackToken, null);
    await app.go(-1);
    assert.deepEqual(app.window.rodnikMap.trackGeometry, privateTrack());
    assert.equal(app.window.rodnikMap.sharedTrack.status, 'local');
});

test('report scope URL changes keep private track history bound to the exact current address', async t => {
    const storage = new Map();
    const app = setup(t, { storage, ownerId: 7 });
    await app.ready();
    const track = privateTrack();
    app.window.rodnikMap.restoreLocalTrack(track);
    app.navigation.capture();
    const id = app.entry().state.rodnik.localTrackId;
    const reports = mapReports();

    for (const inMapArea of [false, true]) {
        reports.inMapArea = inMapArea;
        reports.persistScope();
        assert.equal(app.entry().state.rodnik.url, app.window.location.href);
        assert.equal(app.entry().state.rodnik.localTrackId, id);
        assert.deepEqual(readLocalTrackHistory(app.window, 7), track);
        assert.equal(app.window.location.search.includes('w=1'), !inMapArea);
        assert.equal(parseMapUrlState(app.window.location.href).trackToken, null);
    }
    assert.equal(storage.size, 1);
});
