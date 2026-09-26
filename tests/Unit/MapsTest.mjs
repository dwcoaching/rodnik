import assert from 'node:assert/strict';
import test from 'node:test';
import { effect, reactive, stop } from '@vue/reactivity';
import maps, { sharedMapFingerprint } from '../../resources/js/maps.js';
import { normalizeSharedMapState } from '../../resources/js/sharedMapState.js';
import { parseMapUrlState } from '../../resources/js/mapUrlState.js';

const state = () => normalizeSharedMapState({
    version: 1, center: [37.123456789, 55.987654321], zoom: 12.325,
    sourceName: 'satellite', filters: { spring: false, along: true },
    overlays: { osmTraces: true }, page: { user: 17, spring: 45 }, fullscreen: true,
});
const shortUrl = 'https://rodnik.test/maps/ab12cd34';
const savedMap = (overrides = {}) => ({
    id: 1, slug: 'ab12cd34', url: shortUrl, state: state(), track: null, can_update: true, version: 1, title: 'Coastal walk', ...overrides,
});
const tick = () => new Promise(setImmediate);

async function setup(t, options = {}) {
    const previousWindow = globalThis.window;
    globalThis.window = Object.assign(new EventTarget(), {
        rodnikLocale: 'ru', rodnikOwnerId: 3,
        location: new URL(options.href ?? 'https://rodnik.test/45/?user=17'),
        rodnikPublicBaseUrl: 'https://rodnik.test/',
    });
    if (Object.hasOwn(options, 'ownerId')) window.rodnikOwnerId = options.ownerId;
    if (options.record) window.rodnikSharedMap = options.record;
    const requests = [], slugRequests = [], copies = [], adopted = [];
    let currentState = state(), ensures = 0, retries = 0;
    let currentMap;
    const map = {
        ready: Promise.resolve(), sharedTrack: { status: 'idle', hash: null, token: null },
        captureSharedState: () => structuredClone(currentState),
        ensureSharedTrack: async () => {
            ensures++;
            return map.sharedTrack.status === 'saved'
                ? { id: 1, hash: map.sharedTrack.hash, ...(map.sharedTrack.token ? { token: map.sharedTrack.token } : {}) } : null;
        },
        retrySharedTrack: async () => { retries++; },
        ...options.map,
    };
    currentMap = map;
    window.rodnikNavigation = { savedMap(record) { adopted.push(record); window.rodnikSharedMap = record; } };
    const component = maps({
        getMap: () => currentMap,
        endpoint: '/maps', slugEndpoint: '/user/maps/check-slug', baseUrl: 'https://rodnik.test/', csrfToken: 'csrf', locale: options.locale,
        ownerId: options.configOwnerId, feedbackDuration: options.feedbackDuration,
        afterShow: options.afterShow, timeoutMs: options.timeoutMs,
        defaultTitleDate: options.defaultTitleDate,
        copiedMessage: 'Link copied', errorMessage: 'Could not save map',
        trackErrorMessage: 'Track is not ready', savedMessage: 'Map saved',
        updateErrorMessage: 'Could not update map', conflictMessage: 'This map changed. Reopen it before updating.',
        updatedMessage: 'The same link now opens this view.', sessionMessage: 'Session expired',
        rateLimitMessage: 'Try later', copyMessage: 'Copy manually',
        slugCheckingMessage: 'Checking link', slugAvailableMessage: 'Link available',
        slugInvalidMessage: 'Invalid link', slugUnavailableMessage: 'Link taken', slugCheckFailedMessage: 'Could not check link',
        titleRequiredMessage: 'Enter a name for your map.',
        clipboard: options.clipboard ?? { writeText: async text => copies.push(text) },
        fetch: async (url, requestOptions) => {
            if (!requestOptions.body) {
                slugRequests.push({ url, ...requestOptions });
                return options.slugFetch ? options.slugFetch(url, requestOptions) : Response.json({ available: true });
            }
            const payload = JSON.parse(requestOptions.body);
            requests.push({ url, ...requestOptions, payload });
            if (options.fetch) return options.fetch(url, requestOptions, payload);
            return Response.json(savedMap({
                ...(requestOptions.method === 'PATCH' ? window.rodnikSharedMap : {}),
                state: payload.state, title: payload.title ?? window.rodnikSharedMap?.title,
                version: requestOptions.method === 'PATCH' ? payload.version + 1 : 1,
                track: payload.track_token ? { hash: 'a'.repeat(64), token: payload.track_token } : null,
            }));
        },
    });
    const ui = options.reactive ? reactive(component) : component;
    t.after(() => { ui.destroy(); globalThis.window = previousWindow; });
    if (options.initialize !== false) await ui.init();
    const begin = () => {
        ui.open();
        const opened = ui.beginSave();
        if (opened && !ui.draftTitle) ui.draftTitle = 'My map';
        return opened;
    };
    return {
        ui, map, requests, slugRequests, copies, adopted,
        ensures: () => ensures, retries: () => retries,
        replaceMap(next) { currentMap = next; },
        changeState(next) { currentState = next; window.dispatchEvent(new Event('map-state-changed')); },
        begin,
        async save() { begin(); return ui.save(); },
    };
}

async function setupOwnedMap(t, options = {}) {
    const original = savedMap({
        id: 13, slug: 'lycian-way', url: 'https://rodnik.test/maps/lycian-way/',
        resource_url: 'https://rodnik.test/45/?user=17&redirect=false', title: 'Lycian Way', version: 5,
        ...options.original,
    });
    return { ...await setup(t, { ...options, record: original }), original };
}

for (const title of ['', '   ', '\n\t', '\u00a0']) {
    test(`a new map requires a nonblank name: ${JSON.stringify(title)}`, async t => {
        const context = await setup(t);
        context.begin();
        context.ui.draftTitle = title;
        assert.equal(await context.ui.save(), false);
        assert.deepEqual(context.requests, []);
        assert.equal(context.ensures(), 0);
        assert.equal(context.ui.saving, true);
        assert.equal(context.ui.busy, false);
        assert.deepEqual(context.ui.fieldErrors.title, ['Enter a name for your map.']);
        context.ui.draftTitle = '  Lycian Way  ';
        assert.equal(await context.ui.save(), true);
        assert.equal(context.requests[0].payload.title, 'Lycian Way');
        assert.deepEqual(context.ui.fieldErrors, {});
    });
}

for (const ownerId of [null, 3]) {
    for (const status of ['idle', 'saving', 'loading', 'failed', 'saved', 'missing']) {
        test(`opening sharing is read-only for owner ${ownerId} with ${status} track`, async t => {
            const context = await setup(t, { ownerId, map: { sharedTrack: { status, token: status === 'saved' ? 'AbCd123456' : null } } });
            assert.equal(context.ui.opened, false);
            assert.equal(context.ui.open(), true);
            assert.equal(context.ui.opened, true);
            assert.deepEqual(context.requests, []);
            assert.equal(context.ensures(), 0);
            assert.equal(context.retries(), 0);
            assert.equal(Boolean(context.ui.linkUrl), !['saving', 'loading', 'failed'].includes(status));
            assert.equal(context.ui.saving, false);
            assert.equal(context.ui.canSave(), ownerId === 3);
        });
    }
}

test('Copy link uses the full live URL, preserves resource options, and creates no saved map', async t => {
    const { ui, map, copies, requests } = await setup(t, { href: 'https://rodnik.test/45/?user=17&redirect=false&whole_world=1' });
    map.sharedTrack = { status: 'saved', hash: 'a'.repeat(64), token: 'AbCd123456' };
    ui.open();
    assert.equal(await ui.copy(), true);
    const url = new URL(copies[0]);
    assert.equal(url.pathname, '/45/');
    assert.deepEqual(Object.fromEntries(url.searchParams), { user: '17', redirect: 'false', w: '1' });
    assert.equal(parseMapUrlState(url).trackToken, 'AbCd123456');
    assert.equal(parseMapUrlState(url).state.sourceName, 'satellite');
    assert.equal(window.rodnikSharedMap, undefined);
    assert.deepEqual(requests, []);
});

test('Copy current view and Copy saved link are independent actions', async t => {
    const { ui, copies, requests, original, changeState } = await setupOwnedMap(t);
    changeState({ ...state(), zoom: 10 });
    ui.open();
    await ui.copy();
    await ui.copy(true);
    assert.equal(parseMapUrlState(copies[0]).state.zoom, 10);
    assert.equal(copies[1], original.url);
    assert.equal(ui.copiedSaved, true);
    assert.equal(ui.copied, false);
    assert.deepEqual(requests, []);
});

test('a guest can copy an uploaded track but never enter or submit Save map', async t => {
    const { ui, requests, copies } = await setup(t, { ownerId: null, map: { sharedTrack: { status: 'saved', token: 'AbCd123456' } } });
    ui.open();
    assert.equal(await ui.copy(), true);
    assert.equal(parseMapUrlState(copies[0]).trackToken, 'AbCd123456');
    assert.equal(ui.beginSave(), false);
    ui.saving = true;
    assert.equal(await ui.save(), false);
    assert.deepEqual(requests, []);
});

for (const ownerId of [undefined, null, 0, -1, '3', Number.NaN]) {
    test(`invalid or absent owner ${ownerId} cannot save or update even with stale config ownership`, async t => {
        const { ui, requests } = await setup(t, { ownerId, configOwnerId: 3, record: savedMap() });
        ui.open();
        assert.equal(ui.canSave(), false);
        assert.equal(ui.canUpdate(), false);
        assert.equal(ui.beginSave(), false);
        assert.equal(await ui.update(), false);
        assert.deepEqual(requests, []);
    });
}

test('Save map begins with the track name and an editable random link without creating anything', async t => {
    const { ui, requests } = await setup(t, { map: { sharedTrack: { status: 'saved', name: 'Coastal walk', token: 'AbCd123456' } } });
    ui.open();
    assert.equal(ui.beginSave(), true);
    assert.equal(ui.saving, true);
    assert.equal(ui.draftTitle, 'Coastal walk');
    assert.match(ui.draftSlug, /^[a-z0-9]{8}$/);
    await tick();
    assert.equal(ui.slugStatus, 'valid');
    ui.cancelSave();
    assert.equal(ui.saving, false);
    assert.deepEqual(requests, []);
});

test('explicitly saving posts a named view and track token then adopts the returned owned map', async t => {
    const { ui, map, requests, adopted, begin } = await setup(t);
    map.sharedTrack = { status: 'saved', hash: 'a'.repeat(64), token: 'AbCd123456', name: 'Coastal walk' };
    begin();
    ui.draftTitle = '  Weekend walk  ';
    ui.draftSlug = '  weekend-walk  ';
    assert.equal(await ui.save(), true);
    assert.equal(requests.length, 1);
    const request = requests[0];
    assert.equal(request.url, '/maps');
    assert.equal(request.method, 'POST');
    assert.equal(request.credentials, 'same-origin');
    assert.equal(request.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(request.headers['X-Rodnik-Locale'], 'ru');
    assert.deepEqual(request.payload, { state: state(), track_token: 'AbCd123456', title: 'Weekend walk', slug: 'weekend-walk' });
    assert.equal(adopted.length, 1);
    assert.equal(ui.savedUrl(), shortUrl);
    assert.equal(ui.canUpdate(), true);
    assert.equal(ui.dirty, false);
    assert.equal(ui.saving, false);
    assert.equal(ui.notice, 'Map saved');
    assert.ok(ui.linkUrl.includes('#m='));
});

test('configured locale takes precedence for explicitly saved maps', async t => {
    const { save, requests } = await setup(t, { locale: 'en' });
    await save();
    assert.equal(requests[0].headers['X-Rodnik-Locale'], 'en');
});

test('saving a guest map as an owned map is explicit and leaves the guest map unmodified', async t => {
    const guest = savedMap({ id: 2, can_update: false });
    const { ui, requests, save } = await setup(t, { record: guest });
    ui.open();
    assert.equal(window.rodnikSharedMap, guest);
    assert.equal(ui.canUpdate(), false);
    assert.equal(await save(), true);
    assert.equal(requests[0].method, 'POST');
    assert.equal(window.rodnikSharedMap.id, 1);
    assert.equal(guest.can_update, false);
});

test('new saves work inside Alpine reactivity without changing an existing map record', async t => {
    const { ui, original, requests } = await setupOwnedMap(t, { reactive: true });
    ui.open(); ui.beginSave();
    assert.equal(ui.draftTitle, 'Lycian Way');
    assert.equal(await ui.save(), true);
    assert.equal(requests[0].method, 'POST');
    assert.equal(original.version, 5);
    assert.equal(original.id, 13);
    assert.equal(window.rodnikSharedMap.id, 1);
});

for (const event of ['map-state-changed', 'map-viewport-changed', 'map-filters-changed', 'map-track-changed', 'map-track-persistence-changed', 'map-shared-state-restored', 'rodnik:navigated']) {
    test(`${event} refreshes the current URL and dirty state without creating a map`, async t => {
        const { ui, requests, map } = await setupOwnedMap(t);
        ui.open();
        map.captureSharedState = () => ({ ...state(), zoom: 10 });
        window.dispatchEvent(new Event(event));
        assert.equal(ui.dirty, true);
        assert.equal(parseMapUrlState(ui.linkUrl).state.zoom, 10);
        assert.deepEqual(requests, []);
    });
}

for (const [label, change] of [
    ['camera', value => ({ ...value, center: [40, 50] })], ['zoom', value => ({ ...value, zoom: 9 })],
    ['layer', value => ({ ...value, sourceName: 'osm' })], ['filters', value => ({ ...value, filters: { ...value.filters, spring: true } })],
    ['overlays', value => ({ ...value, overlays: { stravaPublic: true, osmTraces: true } })],
    ['page', value => ({ ...value, page: { user: 19 } })], ['layout', value => ({ ...value, fullscreen: false, minimized: true })],
]) {
    test(`a changed ${label} marks an owned map dirty and changes the copied URL`, async t => {
        const { ui, changeState, requests, copies } = await setupOwnedMap(t);
        ui.open();
        const before = ui.linkUrl;
        changeState(change(state()));
        assert.equal(ui.dirty, true);
        await ui.copy();
        assert.notEqual(copies[0], before);
        assert.deepEqual(requests, []);
    });
}

test('dirty comparison distinguishes upload ownership even when geometry hashes match', async t => {
    const { ui, map, original } = await setupOwnedMap(t);
    original.track = { token: 'AbCd123456', hash: 'a'.repeat(64) };
    map.sharedTrack = { status: 'saved', token: 'XyZ0987654', hash: 'a'.repeat(64) };
    assert.equal(ui.refreshDirty(), true);
    map.sharedTrack.token = original.track.token;
    assert.equal(ui.refreshDirty(), false);
});

test('pending track state marks a map dirty and keeps incomplete URLs unavailable', async t => {
    const { ui, map, requests } = await setupOwnedMap(t);
    ui.open();
    for (const status of ['local', 'saving', 'loading', 'failed']) {
        map.sharedTrack = { status, hash: null };
        ui.refresh();
        assert.equal(ui.dirty, true);
        assert.equal(ui.linkUrl, '');
        assert.equal(ui.beginSave(), false);
        assert.equal(await ui.copy(), false);
    }
    assert.deepEqual(requests, []);
});

for (const phase of ['ready', 'restoringSharedState', 'restoringNavigationState', 'navigation']) {
    test(`dirty detection waits for ${phase} before comparing map views`, async t => {
        const { ui, map, changeState } = await setupOwnedMap(t);
        if (phase === 'ready') ui.ready = false;
        else if (phase === 'navigation') window.rodnikNavigation.restoring = true;
        else map[phase] = true;
        changeState({ ...state(), zoom: 10 });
        assert.equal(ui.dirty, false);
        ui.ready = true;
        window.rodnikNavigation.restoring = false;
        map.restoringSharedState = map.restoringNavigationState = false;
        assert.equal(ui.refreshDirty(), true);
    });
}

test('losing ownership or disposing stops dirty tracking', async t => {
    const { ui, original, changeState } = await setupOwnedMap(t);
    changeState({ ...state(), zoom: 10 });
    assert.equal(ui.dirty, true);
    original.can_update = false;
    assert.equal(ui.refreshDirty(), false);
    original.can_update = true;
    ui.destroy();
    assert.equal(ui.refreshDirty(), false);
    assert.equal(ui.canSave(), false);
    assert.equal(ui.savedUrl(), '');
});

for (const record of [null, savedMap({ can_update: false }), savedMap({ version: 0 }), savedMap({ version: '1' }), savedMap({ version: 4294967295 }), savedMap({ id: '../other' }), savedMap({ url: 'https://evil.test/maps/Ab12Cd34' })]) {
    test(`only a valid owned record can be updated: ${JSON.stringify(record)}`, async t => {
        const { ui, requests } = await setup(t, { record });
        ui.open();
        assert.equal(ui.canUpdate(), false);
        assert.equal(await ui.update(), false);
        assert.deepEqual(requests, []);
    });
}

test('explicit updates keep the same map link and advance each returned revision', async t => {
    const { ui, original, requests, changeState } = await setupOwnedMap(t);
    ui.open();
    changeState({ ...state(), zoom: 10 });
    assert.equal(await ui.update(), true);
    assert.equal(ui.savedUrl(), original.url);
    assert.equal(ui.dirty, false);
    assert.equal(ui.notice, 'The same link now opens this view.');
    changeState({ ...state(), zoom: 8 });
    ui.open();
    assert.equal(await ui.update(), true);
    assert.deepEqual(requests.map(request => [request.url, request.method, request.payload.version]), [
        ['/maps/13', 'PATCH', 5], ['/maps/13', 'PATCH', 6],
    ]);
    assert.equal(window.rodnikSharedMap.version, 7);
    assert.equal(window.rodnikSharedMap.resource_url, original.resource_url);
});

for (const operation of ['save', 'update']) {
    test(`${operation} captures the latest view after complete track persistence`, async t => {
        const pending = Promise.withResolvers();
        const context = operation === 'update' ? await setupOwnedMap(t, { map: { ensureSharedTrack: () => pending.promise } })
            : await setup(t, { map: { ensureSharedTrack: () => pending.promise } });
        context.begin();
        context.map.sharedTrack = { status: 'saving' };
        const saving = context.ui[operation]();
        await tick();
        assert.deepEqual(context.requests, []);
        context.changeState({ ...state(), zoom: 8 });
        context.map.sharedTrack = { status: 'saved', token: 'AbCd123456', hash: 'a'.repeat(64) };
        pending.resolve({ id: 1, token: 'AbCd123456', hash: 'a'.repeat(64) });
        assert.equal(await saving, true);
        assert.equal(context.requests[0].payload.state.zoom, 8);
        assert.equal(context.requests[0].payload.track_token, 'AbCd123456');
    });

    test(`${operation} preserves the submitted view when the camera changes during its response`, async t => {
        const pending = Promise.withResolvers();
        const context = operation === 'update' ? await setupOwnedMap(t, { fetch: () => pending.promise }) : await setup(t, { fetch: () => pending.promise });
        context.begin();
        const saving = context.ui[operation]();
        await tick();
        const request = context.requests[0];
        context.changeState({ ...state(), zoom: 8 });
        pending.resolve(Response.json(savedMap({ ...(operation === 'update' ? context.original : {}), state: request.payload.state,
            version: operation === 'update' ? 6 : 1 })));
        assert.equal(await saving, true);
        assert.equal(window.rodnikSharedMap.state.zoom, state().zoom);
        assert.equal(context.ui.dirty, true);
        assert.equal(parseMapUrlState(context.ui.linkUrl).state.zoom, 8);
    });

    for (const phase of ['track', 'request', 'body']) {
        for (const change of ['account', 'map', 'record', ...(operation === 'update' ? ['version', 'permission'] : [])]) {
            test(`${operation} ignores a changed ${change} while waiting for ${phase}`, async t => {
                const pending = Promise.withResolvers();
                const options = phase === 'track' ? { map: { ensureSharedTrack: () => pending.promise } }
                    : { fetch: () => phase === 'request' ? pending.promise : { ok: true, status: 200, json: () => pending.promise } };
                const context = operation === 'update' ? await setupOwnedMap(t, options) : await setup(t, options);
                context.begin();
                const saving = context.ui[operation]();
                await tick();
                const before = window.rodnikSharedMap;
                if (change === 'account') window.rodnikOwnerId = 4;
                if (change === 'map') context.replaceMap({ ...context.map });
                if (change === 'record') window.rodnikSharedMap = savedMap({ id: 99 });
                if (change === 'version') before.version++;
                if (change === 'permission') before.can_update = false;
                const selected = window.rodnikSharedMap;
                const response = savedMap({ ...(operation === 'update' ? before : {}), version: operation === 'update' ? 6 : 1 });
                pending.resolve(phase === 'track' ? null : phase === 'body' ? response : Response.json(response));
                assert.equal(await saving, false);
                assert.equal(window.rodnikSharedMap, selected);
                assert.equal(context.adopted.length, 0);
                assert.equal(context.ui.confirmation, null);
                assert.equal(context.requests.length, phase === 'track' ? 0 : 1);
                assert.equal(context.ui.error, '');
            });
        }
    }

    test(`${operation} cannot overlap another request and disposal aborts late responses`, async t => {
        const pending = Promise.withResolvers();
        const context = operation === 'update' ? await setupOwnedMap(t, { fetch: () => pending.promise }) : await setup(t, { fetch: () => pending.promise });
        context.begin();
        const saving = context.ui[operation]();
        await tick();
        assert.equal(await context.ui[operation](), false);
        assert.equal(context.ui.open(), false);
        assert.equal(await context.ui.copy(), false);
        context.ui.destroy();
        assert.equal(context.requests[0].signal.aborted, true);
        pending.resolve(Response.json(savedMap()));
        assert.equal(await saving, false);
        assert.equal(context.adopted.length, 0);
        assert.equal(context.ui.confirmation, null);
    });

    for (const [status, message] of [[409, 'This map changed. Reopen it before updating.'], [401, 'Session expired'],
        [419, 'Session expired'], [429, 'Try later'], [422, 'Choose another map name'], [503, operation === 'update' ? 'Could not update map' : 'Could not save map']]) {
        test(`${operation} explains HTTP ${status} without adopting a failed response`, async t => {
            const options = { fetch: () => Response.json({ errors: { slug: ['Choose another map name'] } }, { status }) };
            const context = operation === 'update' ? await setupOwnedMap(t, options) : await setup(t, options);
            context.begin();
            assert.equal(await context.ui[operation](), false);
            assert.equal(context.ui.error, message);
            assert.equal(context.ui.busy, false);
            assert.equal(context.adopted.length, 0);
            assert.equal(context.ui.confirmation, null);
            assert.equal(context.requests.length, 1);
        });
    }
}

for (const overrides of [
    { can_update: false }, { version: 0 }, { version: '1' }, { version: 4294967296 }, { state: null },
    { state: { ...state(), zoom: 10 } }, { track: { token: 'AbCd123456', hash: 'a'.repeat(64) } }, { id: '../invalid' },
    { id: undefined }, { id: 0 }, { id: -1 }, { id: '1' }, { id: Number.MAX_SAFE_INTEGER + 1 }, { url: 'https://rodnik.test/share/Ab12Cd34' }, { url: '' }, { url: 'javascript:alert(1)' }, { url: 'https://evil.test/maps/Ab12Cd34' },
    { url: 'https://name:password@rodnik.test/maps/Ab12Cd34' }, { url: `${shortUrl}?redirect=/other` }, { url: `${shortUrl}#m=12/30/40` },
]) {
    test(`saving rejects inconsistent or unsafe response data: ${JSON.stringify(overrides)}`, async t => {
        const { ui, save, adopted } = await setup(t, { fetch: () => Response.json(savedMap(overrides)) });
        assert.equal(await save(), false);
        assert.equal(adopted.length, 0);
        assert.equal(ui.canUpdate(), false);
        assert.equal(ui.error, 'Could not save map');
    });
}

for (const overrides of [{ id: 99 }, { can_update: false }, { version: 5 }, { version: 7 }]) {
    test(`updating rejects a response for another map or version: ${JSON.stringify(overrides)}`, async t => {
        const context = await setupOwnedMap(t, { fetch: () => Response.json({ ...context.original, version: 6, ...overrides }) });
        context.ui.open();
        assert.equal(await context.ui.update(), false);
        assert.equal(window.rodnikSharedMap, context.original);
        assert.equal(context.ui.error, 'Could not update map');
    });
}

for (const failure of ['network', 'json', 'timeout']) {
    test(`a ${failure} failure releases busy state and requires an explicit new save attempt`, async t => {
        let calls = 0;
        const { ui, save, requests } = await setup(t, { fetch: (url, request, payload) => {
            if (++calls === 1) {
                if (failure === 'json') return new Response('not JSON');
                throw failure === 'network' ? new TypeError('Failed to fetch') : new DOMException('Timeout', 'TimeoutError');
            }
            return Response.json(savedMap({ state: payload.state }));
        } });
        assert.equal(await save(), false);
        assert.equal(ui.busy, false);
        assert.equal(ui.error, 'Could not save map');
        ui.close(); ui.open();
        assert.equal(requests.length, 1, 'opening never retries a write');
        ui.beginSave();
        ui.draftTitle = 'My map';
        assert.equal(await ui.save(), true);
        assert.equal(requests.length, 2);
    });
}

test('closing a pending save keeps it closed while preserving its deliberately saved result', async t => {
    const pending = Promise.withResolvers();
    const { ui, save } = await setup(t, { fetch: () => pending.promise });
    const saving = save();
    await tick();
    ui.close();
    pending.resolve(Response.json(savedMap()));
    assert.equal(await saving, true);
    assert.equal(ui.opened, false);
    assert.equal(ui.savedUrl(), shortUrl);
});

test('a failed track blocks map creation and explicit Retry only retries the track', async t => {
    const { ui, map, requests, retries } = await setup(t);
    ui.open(); ui.beginSave();
    ui.draftTitle = 'My map';
    map.sharedTrack.status = 'failed';
    assert.equal(await ui.save(), false);
    assert.equal(ui.error, 'Track is not ready');
    assert.deepEqual(requests, []);
    await ui.retry();
    assert.equal(retries(), 1);
    assert.deepEqual(requests, []);
});

test('track retry finishes before the full URL becomes copyable and does not create a saved map', async t => {
    const { ui, map, requests } = await setup(t);
    map.sharedTrack.status = 'failed';
    map.retrySharedTrack = async () => { map.sharedTrack = { status: 'saved', token: 'AbCd123456', hash: 'a'.repeat(64) }; };
    ui.open();
    assert.equal(await ui.retry(), true);
    assert.equal(parseMapUrlState(ui.linkUrl).trackToken, 'AbCd123456');
    assert.deepEqual(requests, []);
});

for (const operation of ['save', 'update']) {
    test(`${operation} after track deletion keeps the map usable without reuploading geometry`, async t => {
        let uploads = 0;
        const options = { map: { sharedTrack: { status: 'missing', error: 'Track deleted' }, ensureSharedTrack: async () => { uploads++; } } };
        const context = operation === 'update' ? await setupOwnedMap(t, options) : await setup(t, options);
        context.begin();
        assert.equal(context.ui.trackMissing(), true);
        assert.equal(parseMapUrlState(context.ui.linkUrl).trackToken, null);
        assert.equal(await context.ui[operation](), true);
        assert.equal(uploads, 0);
        assert.equal(context.requests[0].payload.track_token, null);
        assert.equal(Object.hasOwn(context.requests[0].payload, 'track_hash'), false);
    });

    test(`${operation} never republishes a track rejected by the server as deleted`, async t => {
        let recoveries = 0;
        const options = { fetch: () => Response.json({ errors: { track_token: ['This track has been deleted.'] } }, { status: 422 }),
            map: { sharedTrack: { status: 'saved', token: 'AbCd123456', hash: 'a'.repeat(64) }, recoverSharedTrack: () => { recoveries++; } } };
        const context = operation === 'update' ? await setupOwnedMap(t, options) : await setup(t, options);
        context.begin();
        assert.equal(await context.ui[operation](), false);
        assert.equal(context.requests.length, 1);
        assert.equal(recoveries, 0);
        assert.equal(context.ui.error, 'This track has been deleted.');
    });
}

test('reopening, focus and visibility never verify or recreate a saved link in the background', async t => {
    const { ui, requests, ensures, original } = await setupOwnedMap(t);
    for (let index = 0; index < 3; index++) {
        ui.open();
        window.dispatchEvent(new Event('focus'));
        window.dispatchEvent(new Event('visibilitychange'));
        await tick();
        assert.equal(ui.savedUrl(), original.url);
        ui.close();
    }
    assert.deepEqual(requests, []);
    assert.equal(ensures(), 0);
});

test('returning to a previously copied view deterministically restores the full URL without a cache lookup', async t => {
    const { ui, changeState, requests } = await setup(t);
    ui.open();
    const initial = ui.linkUrl;
    changeState({ ...state(), zoom: 8 });
    assert.notEqual(ui.linkUrl, initial);
    changeState(state());
    assert.equal(ui.linkUrl, initial);
    assert.deepEqual(requests, []);
});

test('fingerprints canonicalize object order and insignificant projection rounding', () => {
    const original = state();
    const reordered = { ...original, filters: Object.fromEntries(Object.entries(original.filters).reverse()),
        center: original.center.map(value => value + 1e-10), zoom: original.zoom + 1e-10 };
    assert.equal(sharedMapFingerprint(original), sharedMapFingerprint(reordered));
    assert.notEqual(sharedMapFingerprint(original, 'AbCd123456'), sharedMapFingerprint(original, 'XyZ0987654'));
});

test('insignificant projection rounding does not make a saved map dirty', async t => {
    const { ui, changeState } = await setupOwnedMap(t);
    changeState({ ...state(), center: state().center.map(value => value + 1e-10) });
    assert.equal(ui.dirty, false);
});

test('initial readiness and disposed controls cannot be activated by late initialization', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = await setup(t, { initialize: false, map: { ready: pending.promise } });
    const initialization = ui.init();
    assert.equal(ui.open(), false);
    ui.destroy();
    pending.resolve();
    await initialization;
    assert.equal(ui.ready, false);
    assert.equal(ui.open(), false);
    assert.deepEqual(requests, []);
});

test('changing maps before readiness never activates the removed sharing control', async t => {
    const pending = Promise.withResolvers();
    const { ui, replaceMap, map } = await setup(t, { initialize: false, map: { ready: pending.promise } });
    const initialization = ui.init();
    replaceMap({ ...map });
    pending.resolve();
    await initialization;
    assert.equal(ui.ready, false);
});

test('URL focus waits for Alpine and a visible frame', async t => {
    const ticks = [], frames = [], focused = [];
    const { ui } = await setup(t, { afterShow: callback => frames.push(callback) });
    ui.$nextTick = callback => ticks.push(callback);
    ui.$refs = { link: { focus: () => focused.push('link') } };
    ui.open();
    assert.deepEqual(focused, []);
    ticks.shift()();
    assert.deepEqual(focused, []);
    frames.shift()();
    assert.deepEqual(focused, ['link']);
});

test('closing before the visible frame cancels URL focus and restores the trigger after Alpine', async t => {
    const ticks = [], frames = [], focused = [];
    const { ui } = await setup(t, { afterShow: callback => frames.push(callback) });
    ui.$nextTick = callback => ticks.push(callback);
    ui.$refs = { link: { focus: () => focused.push('link') }, trigger: { focus: () => focused.push('trigger') } };
    ui.open(); ticks.shift()();
    ui.close();
    frames.shift()();
    assert.deepEqual(focused, []);
    ticks.shift()();
    assert.deepEqual(focused, ['trigger']);
});

test('a queued close cannot steal focus from a reopened dialog', async t => {
    const ticks = [], focused = [];
    const { ui } = await setup(t, { afterShow: callback => callback() });
    ui.$nextTick = callback => ticks.push(callback);
    ui.$refs = { link: { focus: () => focused.push('link') }, trigger: { focus: () => focused.push('trigger') } };
    ui.open(); ui.close(); ui.open();
    ticks.forEach(callback => callback());
    assert.deepEqual(focused, ['link']);
});

for (const saved of [false, true]) {
    test(`clipboard failure selects the ${saved ? 'saved' : 'current'} visible URL for manual copying`, async t => {
        const focused = [], selected = [];
        const { ui } = await setupOwnedMap(t, { clipboard: { writeText: async () => { throw new Error('Denied'); } } });
        for (const name of ['link', 'savedLink']) (ui.$refs ??= {})[name] = { focus: () => focused.push(name), select: () => selected.push(name) };
        ui.open();
        assert.equal(await ui.copy(saved), false);
        assert.equal(ui.notice, 'Copy manually');
        assert.equal(focused.at(-1), saved ? 'savedLink' : 'link');
        assert.deepEqual(selected, [saved ? 'savedLink' : 'link']);
    });
}

test('browsers without a clipboard API still offer the visible URL', async t => {
    let selected = 0;
    const { ui } = await setup(t, { clipboard: {} });
    ui.$refs = { link: { focus() {}, select() { selected++; } } };
    ui.open();
    assert.equal(await ui.copy(), false);
    assert.equal(selected, 1);
    assert.ok(ui.linkUrl.includes('#m='));
});

test('repeated Copy clicks cannot overlap clipboard operations', async t => {
    const pending = Promise.withResolvers();
    let writes = 0;
    const { ui } = await setup(t, { clipboard: { writeText: () => { writes++; return pending.promise; } } });
    ui.open();
    const copying = ui.copy();
    assert.equal(await ui.copy(), false);
    assert.equal(writes, 1);
    pending.resolve();
    assert.equal(await copying, true);
});

for (const outcome of ['resolve', 'reject']) {
    test(`a clipboard ${outcome} after destruction cannot publish stale feedback`, async t => {
        const pending = Promise.withResolvers();
        const { ui } = await setup(t, { clipboard: { writeText: () => pending.promise } });
        ui.open();
        const copying = ui.copy();
        ui.destroy();
        pending[outcome](outcome === 'reject' ? new Error('Denied') : undefined);
        assert.equal(await copying, false);
        assert.equal(ui.notice, '');
        assert.equal(ui.copied, false);
    });
}

test('closing during clipboard permissions never reopens or focuses the dialog on rejection', async t => {
    const pending = Promise.withResolvers();
    let focused = 0;
    const { ui } = await setup(t, { clipboard: { writeText: () => pending.promise } });
    ui.open();
    ui.$refs = { link: { focus() { focused++; }, select() {} } };
    const copying = ui.copy();
    ui.close();
    pending.reject(new Error('Denied'));
    await copying;
    assert.equal(ui.opened, false);
    assert.equal(focused, 0);
});

test('copy feedback expires while the full current URL remains available', async t => {
    const { ui } = await setup(t, { feedbackDuration: 5 });
    ui.open();
    const link = ui.linkUrl;
    await ui.copy();
    assert.equal(ui.copied, true);
    await new Promise(resolve => setTimeout(resolve, 15));
    assert.equal(ui.copied, false);
    assert.equal(ui.notice, '');
    assert.equal(ui.linkUrl, link);
});


test('saving and logout refresh Alpine effects that read the active saved record and owner', async t => {
    const { ui, save } = await setup(t, { reactive: true });
    let observedUrl, observedUpdate, observedSave;
    const runner = effect(() => {
        observedUrl = ui.savedUrl(); observedUpdate = ui.canUpdate(); observedSave = ui.canSave();
    });
    t.after(() => stop(runner));
    assert.equal(observedUrl, '');
    assert.equal(observedUpdate, false);
    assert.equal(await save(), true);
    assert.equal(observedUrl, shortUrl);
    assert.equal(observedUpdate, true);
    window.rodnikOwnerId = null;
    window.dispatchEvent(new Event('rodnik:navigated'));
    assert.equal(observedUpdate, false);
    assert.equal(observedSave, false);
});

test('new map link suggestions are editable and checked without reserving a saved map', async t => {
    const { ui, begin, requests, slugRequests } = await setup(t);
    begin();
    const suggestion = ui.draftSlug;
    assert.match(suggestion, /^[a-z0-9]{8}$/);
    await tick();
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(new URL(slugRequests[0].url).searchParams.get('slug'), suggestion);
    assert.equal(new URL(slugRequests[0].url).searchParams.has('map'), false);
    assert.deepEqual(requests, []);
    ui.draftSlug = 'lycian-way';
    ui.slugInput();
    assert.equal(await ui.validateSlug(), true);
    assert.equal(await ui.save(), true);
    assert.equal(requests[0].payload.slug, 'lycian-way');
});

test('a saved copy checks its new link without excluding the original map from uniqueness', async t => {
    const { ui, begin, slugRequests } = await setupOwnedMap(t);
    begin();
    await tick();
    assert.equal(ui.slugMapId, null);
    assert.equal(new URL(slugRequests[0].url).searchParams.has('map'), false);
});

test('live link conflicts prevent saving until the user chooses an available link', async t => {
    const { ui, begin, requests } = await setup(t, { slugFetch: url => new URL(url).searchParams.get('slug') === 'lycian-way'
        ? Response.json({ errors: { slug: ['That link is taken.'] } }, { status: 422 }) : Response.json({ available: true }) });
    begin();
    ui.draftSlug = 'lycian-way';
    ui.slugInput();
    assert.equal(await ui.save(), false);
    assert.equal(ui.slugStatus, 'invalid');
    assert.equal(ui.slugNotice, 'Link taken');
    assert.deepEqual(requests, []);
    ui.draftSlug = 'lycian-way-weekend';
    ui.slugInput();
    assert.equal(await ui.validateSlug(), true);
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(await ui.save(), true);
    assert.equal(requests[0].payload.slug, 'lycian-way-weekend');
});

test('invalid Unicode link names never reach map saving or the ASCII lookup endpoint', async t => {
    const { ui, begin, requests, slugRequests } = await setup(t);
    begin(); await tick();
    const initialChecks = slugRequests.length;
    ui.draftSlug = 'Ликийская-тропа';
    ui.slugInput();
    assert.equal(await ui.save(), false);
    assert.equal(ui.slugStatus, 'invalid');
    assert.equal(slugRequests.length, initialChecks);
    assert.deepEqual(requests, []);
});

test('a link check cannot finish a save after the signed-in user changes', async t => {
    const pending = Promise.withResolvers();
    const { ui, begin, requests, adopted } = await setup(t, { slugFetch: () => pending.promise });
    begin();
    const saving = ui.save();
    window.rodnikOwnerId = 4;
    pending.resolve(Response.json({ available: true }));
    assert.equal(await saving, false);
    assert.deepEqual(requests, []);
    assert.deepEqual(adopted, []);
});

test('a collision during Save appears at the link field and preserves both entered values', async t => {
    const { ui, begin } = await setup(t, { fetch: () => Response.json({ errors: { slug: ['This link was just taken.'] } }, { status: 422 }) });
    begin();
    ui.draftTitle = 'Ликийская тропа'; ui.draftSlug = 'lycian-way';
    assert.equal(await ui.save(), false);
    assert.equal(ui.draftTitle, 'Ликийская тропа');
    assert.equal(ui.draftSlug, 'lycian-way');
    assert.equal(ui.slugStatus, 'invalid');
    assert.equal(ui.slugNotice, 'This link was just taken.');
    assert.deepEqual(ui.fieldErrors.slug, ['This link was just taken.']);
});

for (const [locale, template, expected] of [
    ['en', 'Map of :date', 'Map of September 26, 2026 (55.99, 37.12)'],
    ['ru', 'Карта от :date', 'Карта от 26 сентября 2026 г. (55.99, 37.12)'],
]) {
    test(`unnamed maps receive an editable ${locale} date and latitude-longitude title`, async t => {
        t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 8, 26, 12).valueOf() });
        const { ui, changeState, requests } = await setup(t, { locale, defaultTitleDate: template });
        ui.open();
        assert.equal(ui.beginSave(), true);
        assert.equal(ui.draftTitle, expected);
        ui.draftTitle = 'Weekend walk';
        changeState({ ...state(), center: [30.45, 36.32] });
        assert.equal(ui.draftTitle, 'Weekend walk', 'moving the map must not overwrite an edited name');
        assert.equal(await ui.save(), true);
        assert.equal(requests[0].payload.title, 'Weekend walk');
    });
}

test('default names use the local calendar date rather than the UTC day', async t => {
    const localDate = new Date(2026, 8, 26, 0, 5);
    t.mock.timers.enable({ apis: ['Date'], now: localDate.valueOf() });
    const { ui } = await setup(t, { locale: 'en', defaultTitleDate: 'Map of :date' });
    ui.open(); ui.beginSave();
    assert.equal(ui.draftTitle, 'Map of September 26, 2026 (55.99, 37.12)');
});

for (const center of [undefined, null, [], [30], ['30', 36], [Infinity, 36], [30, Number.NaN], [181, 36], [30, -91]]) {
    test(`invalid center ${JSON.stringify(center)} gives a useful date-only default name`, async t => {
        t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 8, 26, 12).valueOf() });
        const { ui } = await setup(t, { locale: 'en', defaultTitleDate: 'Map of :date', map: { captureSharedState: () => ({ center }) } });
        ui.open(); ui.beginSave();
        assert.equal(ui.draftTitle, 'Map of September 26, 2026');
    });
}

test('a temporarily unavailable map center still supplies the default date name', async t => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 8, 26, 12).valueOf() });
    const { ui } = await setup(t, { locale: 'en', map: { captureSharedState() { throw new Error('Map not available'); } } });
    ui.open(); ui.beginSave();
    assert.equal(ui.draftTitle, 'Map of September 26, 2026');
});

test('default coordinate names round negative coordinates and normalize negative zero', async t => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 8, 26, 12).valueOf() });
    const { ui } = await setup(t, { locale: 'en', map: { captureSharedState: () => ({ ...state(), center: [-0.0001, -36.328] }) } });
    ui.open(); ui.beginSave();
    assert.equal(ui.draftTitle, 'Map of September 26, 2026 (-36.33, 0.00)');
});

for (const [track, title, expected] of [
    ['Coastal route', 'Saved route', 'Coastal route'],
    ['  Coastal route  ', 'Saved route', '  Coastal route  '],
    [' \t ', 'Saved route', 'Saved route'],
    [null, 'Saved route', 'Saved route'],
    [false, 'Saved route', 'Saved route'],
]) {
    test(`meaningful existing names survive save-copy prefill: ${JSON.stringify({ track, title })}`, async t => {
        const { ui } = await setup(t, { record: savedMap({ title }), map: { sharedTrack: { status: 'saved', name: track } } });
        ui.open(); ui.beginSave();
        assert.equal(ui.draftTitle, expected);
    });
}

test('blank track and saved map names fall back to the localized date and current center', async t => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 8, 26, 12).valueOf() });
    const { ui } = await setup(t, { defaultTitleDate: 'Карта от :date', record: savedMap({ title: '\u00a0' }),
        map: { sharedTrack: { status: 'saved', name: '  ' } } });
    ui.open(); ui.beginSave();
    assert.equal(ui.draftTitle, 'Карта от 26 сентября 2026 г. (55.99, 37.12)');
});

for (const target of ['link', 'savedLink']) {
    test(`programmatic focus keeps the start of the ${target} URL visible`, async t => {
        const { ui } = await setupOwnedMap(t);
        const ranges = [];
        const element = { value: 'https://rodnik.test/maps/long-name', scrollLeft: 80,
            focus() { this.scrollLeft = 100; }, setSelectionRange(...range) { ranges.push(range); this.scrollLeft = 200; } };
        ui.$refs = { [target]: element };
        ui.open();
        ranges.length = 0;
        ui.focusLink(false, target);
        assert.deepEqual(ranges, [[0, 0, 'none']]);
        assert.equal(element.scrollLeft, 0);
    });

    test(`manual copying selects the entire ${target} URL backward and keeps its start visible`, async t => {
        const { ui } = await setupOwnedMap(t, { clipboard: { writeText: async () => { throw new Error('Denied'); } } });
        const ranges = [];
        const element = { value: 'https://rodnik.test/maps/long-name', scrollLeft: 80,
            focus() { this.scrollLeft = 100; }, setSelectionRange(...range) { ranges.push(range); this.scrollLeft = 200; } };
        ui.$refs = { [target]: element };
        ui.open();
        ranges.length = 0;
        assert.equal(await ui.copy(target === 'savedLink'), false);
        assert.deepEqual(ranges, [[0, element.value.length, 'backward']]);
        assert.equal(element.scrollLeft, 0);
    });
}

test('title focus does not change its selection or horizontal position', async t => {
    const { ui } = await setup(t);
    let focused = 0;
    const ranges = [];
    const title = { value: 'A long editable map name', scrollLeft: 42,
        focus() { focused++; }, setSelectionRange(...range) { ranges.push(range); } };
    ui.$refs = { title };
    ui.open(); ui.beginSave();
    assert.equal(focused, 1);
    assert.deepEqual(ranges, []);
    assert.equal(title.scrollLeft, 42);
});

for (const [operation, confirmation] of [['save', 'created'], ['update', 'updated']]) {
    test(`successful ${operation} enters confirmation only after the validated saved record is adopted`, async t => {
        const pending = Promise.withResolvers();
        const context = operation === 'save' ? await setup(t, { fetch: () => pending.promise })
            : await setupOwnedMap(t, { fetch: () => pending.promise });
        assert.equal(context.ui.confirmation, null);
        context.begin();
        const saving = context.ui[operation]();
        await tick();
        assert.equal(context.ui.confirmation, null);
        const originalAdopt = window.rodnikNavigation.savedMap;
        window.rodnikNavigation.savedMap = record => {
            assert.equal(context.ui.confirmation, null);
            originalAdopt(record);
        };
        pending.resolve(Response.json(savedMap({ ...(operation === 'update' ? context.original : {}),
            state: context.requests[0].payload.state, version: operation === 'update' ? 6 : 1 })));
        assert.equal(await saving, true);
        assert.equal(context.ui.confirmation, confirmation);
        assert.equal(context.ui.saving, false);
        assert.equal(context.ui.opened, true);
        assert.equal(context.ui.savedUrl(), operation === 'update' ? context.original.url : shortUrl);
        assert.equal(await context.ui.update(), false, 'the confirmation cannot submit another mutation');
        assert.equal(await context.ui.save(), false);
        assert.equal(context.requests.length, 1);
    });

    test(`${confirmation} confirmation survives copying the permanent URL and expiration of copy feedback`, async t => {
        const context = operation === 'save' ? await setup(t, { feedbackDuration: 5 })
            : await setupOwnedMap(t, { feedbackDuration: 5 });
        context.begin();
        assert.equal(await context.ui[operation](), true);
        const permanent = context.ui.savedUrl();
        assert.equal(await context.ui.copy(), true, 'copying in confirmation always uses its sole permanent URL');
        assert.deepEqual(context.copies, [permanent]);
        assert.equal(context.ui.copiedSaved, true);
        assert.equal(context.ui.confirmation, confirmation);
        await new Promise(resolve => setTimeout(resolve, 15));
        assert.equal(context.ui.copiedSaved, false);
        assert.equal(context.ui.notice, '');
        assert.equal(context.ui.confirmation, confirmation);
        assert.equal(context.ui.savedUrl(), permanent);
        assert.equal(context.ui.opened, true);
    });

    test(`reopening after ${confirmation} returns to normal sharing without another save`, async t => {
        const context = operation === 'save' ? await setup(t) : await setupOwnedMap(t);
        context.begin();
        await context.ui[operation]();
        context.ui.close();
        assert.equal(context.ui.opened, false);
        assert.equal(context.ui.open(), true);
        assert.equal(context.ui.confirmation, null);
        assert.equal(context.ui.saving, false);
        assert.equal(context.ui.notice, '');
        assert.equal(context.requests.length, 1);
        await context.ui.copy();
        assert.equal(context.copies[0], context.ui.linkUrl);
        assert.notEqual(context.copies[0], context.ui.savedUrl());
    });

    test(`manual copying from ${confirmation} keeps confirmation and selects the visible permanent URL`, async t => {
        const options = { clipboard: { writeText: async () => { throw new Error('Denied'); } } };
        const context = operation === 'save' ? await setup(t, options) : await setupOwnedMap(t, options);
        const selections = [];
        context.begin();
        await context.ui[operation]();
        const field = { value: context.ui.savedUrl(), scrollLeft: 50, focus() {},
            setSelectionRange(...selection) { selections.push(selection); } };
        context.ui.$refs = { savedLink: field, link: { focus() { assert.fail('The hidden current URL must not receive focus'); } } };
        assert.equal(await context.ui.copy(), false);
        assert.equal(context.ui.confirmation, confirmation);
        assert.equal(context.ui.notice, 'Copy manually');
        assert.deepEqual(selections, [[0, field.value.length, 'backward']]);
        assert.equal(field.scrollLeft, 0);
        assert.equal(context.ui.opened, true);
    });
}

test('starting another Save map clears confirmation while preserving the previously saved map', async t => {
    const { ui, save, requests } = await setup(t);
    await save();
    const previousUrl = ui.savedUrl();
    assert.equal(ui.confirmation, 'created');
    assert.equal(ui.beginSave(), true);
    assert.equal(ui.confirmation, null);
    assert.equal(ui.saving, true);
    assert.equal(ui.savedUrl(), previousUrl);
    assert.equal(requests.length, 1);
    ui.cancelSave();
    assert.equal(ui.confirmation, null);
    assert.equal(ui.saving, false);
    assert.equal(requests.length, 1);
});

test('malformed successful responses and canceled forms never show creation confirmation', async t => {
    const { ui, begin, requests } = await setup(t, { fetch: () => Response.json(savedMap({ state: null })) });
    begin();
    ui.cancelSave();
    assert.equal(ui.confirmation, null);
    assert.equal(await ui.save(), false);
    assert.equal(requests.length, 0);
    begin();
    assert.equal(await ui.save(), false);
    assert.equal(ui.confirmation, null);
    assert.equal(ui.saving, true);
});

test('destroying a confirmed sharing control clears its confirmation state', async t => {
    const { ui, save } = await setup(t);
    await save();
    assert.equal(ui.confirmation, 'created');
    ui.destroy();
    assert.equal(ui.confirmation, null);
    assert.equal(ui.open(), false);
});
