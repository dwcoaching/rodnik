import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from '@vue/reactivity';
import trackLibrary from '../../resources/js/trackLibrary.js';
import { routePreview, libraryDate, mapCoordinates } from '../../resources/js/libraryPreview.js';

const endpoint = 'https://rodnik.test/user/tracks/options';
const record = (token = 'Ab12Cd34Ef', values = {}) => ({
    token, name: 'Coastal walk', url: `https://rodnik.test/?t=${token}`, map_count: 2,
    maps: [{ id: 1, title: 'Weekend' }, { id: 2, title: 'Water stops' }],
    other_maps_count: 0, distance_km: 12.78, preview: [[29.6, 36.5], [29.7, 36.6]],
    created_at: '2026-09-22T12:00:00Z', rename_url: `/user/tracks/${token}`, delete_url: `/user/tracks/${token}`,
    download_url: `/user/tracks/${token}/download`, ...values,
});
const page = (data, values = {}) => ({ data, next_page_url: null, ...values });
const tick = () => new Promise(setImmediate);

function setup(t, options = {}) {
    const requests = [];
    const focus = [];
    const ui = reactive(trackLibrary({
        endpoint, indexUrl: 'https://rodnik.test/user/tracks', locale: 'en', csrfToken: 'csrf',
        loadMessage: 'Load failed', renameMessage: 'Rename failed', deleteMessage: 'Delete failed',
        sessionMessage: 'Sign in again', rateLimitMessage: 'Try later', usedOnceMessage: 'Used in 1 map',
        usedCountMessage: 'Used in :count maps', unusedMessage: 'Not used in a saved map',
        ...options.config,
        fetch: async (url, config) => {
            requests.push({ url, ...config });
            return options.fetch ? options.fetch(url, config) : Response.json(page([record()]));
        },
    }));
    ui.$nextTick = callback => callback();
    ui.$refs = { search: { focus: () => focus.push('search') }, cancelDelete: { focus: () => focus.push('cancelDelete') } };
    ui.$el = { querySelector: () => ({ focus: () => focus.push('rename'), select: () => focus.push('select') }) };
    t.after(() => ui.destroy());
    return { ui, requests, focus };
}

test('track library loads owned tracks and displays usage with localized distance', async t => {
    const { ui, requests } = setup(t);
    await ui.init();
    assert.equal(ui.loaded, true);
    assert.equal(ui.items[0].name, 'Coastal walk');
    assert.equal(ui.used(ui.items[0]), 'Used in 2 maps');
    assert.equal(ui.used(record(undefined, { map_count: 1 })), 'Used in 1 map');
    assert.equal(ui.used(record(undefined, { map_count: 0 })), 'Not used in a saved map');
    assert.equal(ui.distance(12.78), '12.8');
    assert.equal(requests[0].headers['X-Rodnik-Locale'], 'en');
});

test('late previous searches cannot replace a newer track result', async t => {
    const old = Promise.withResolvers();
    let calls = 0;
    const { ui, requests } = setup(t, { fetch: () => ++calls === 1 ? old.promise : Response.json(page([record('Ab12Cd34Eg')])) });
    const first = ui.load();
    ui.query = '  new & route  ';
    await ui.load();
    old.resolve(Response.json(page([record()])));
    await first;
    assert.equal(requests[0].signal.aborted, true);
    assert.equal(new URL(requests[1].url).searchParams.get('q'), 'new & route');
    assert.equal(ui.items[0].token, 'Ab12Cd34Eg');
});

test('typing debounces track searches', async t => {
    const { ui, requests } = setup(t, { config: { debounce: 1 } });
    ui.query = 'old'; ui.scheduleSearch();
    ui.query = 'new'; ui.scheduleSearch();
    await new Promise(resolve => setTimeout(resolve, 20));
    assert.equal(requests.length, 1);
    assert.equal(new URL(requests[0].url).searchParams.get('q'), 'new');
});

test('pagination appends unique tracks and rejects foreign destinations', async t => {
    let calls = 0;
    const { ui, requests } = setup(t, { fetch: () => Response.json(++calls === 1
        ? page([record()], { next_page_url: `${endpoint}?page=2` })
        : page([record(), record('Ab12Cd34Eg')])) });
    await ui.load();
    await ui.load(true);
    assert.equal(ui.items.length, 2);
    ui.nextPage = 'https://foreign.test/tracks?page=3';
    await ui.load(true);
    assert.equal(requests.length, 2);
    assert.equal(ui.error, 'Load failed');
});

for (const [status, message] of [[401, 'Sign in again'], [419, 'Sign in again'], [429, 'Try later'], [500, 'Load failed']]) {
    test(`track fetch ${status} leaves the previous rows available with recovery feedback`, async t => {
        const { ui } = setup(t, { fetch: () => new Response(null, { status }) });
        ui.items = [record()];
        await ui.load();
        assert.equal(ui.items.length, 1);
        assert.equal(ui.error, message);
        assert.equal(ui.loading, false);
    });
}

test('track rename is explicit and sends only its trimmed name', async t => {
    const { ui, requests, focus } = setup(t, { fetch: (url, options) => Response.json(record(undefined, { name: JSON.parse(options.body).name })) });
    ui.items = [record()];
    ui.rename(ui.items[0]);
    assert.deepEqual(focus, ['rename', 'select']);
    ui.draftName = '  Sunday walk  ';
    assert.equal(requests.length, 0);
    await ui.saveName(ui.items[0]);
    assert.deepEqual(JSON.parse(requests[0].body), { name: 'Sunday walk' });
    assert.equal(requests[0].method, 'PATCH');
    assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(ui.items[0].name, 'Sunday walk');
    assert.equal(ui.editingToken, null);
});

test('rename validation leaves the draft and saved name intact', async t => {
    const { ui } = setup(t, { fetch: () => Response.json({ errors: { name: ['Choose a name.'] } }, { status: 422 }) });
    ui.items = [record()]; ui.rename(ui.items[0]); ui.draftName = '';
    await ui.saveName(ui.items[0]);
    assert.equal(ui.fieldError, 'Choose a name.');
    assert.equal(ui.mutationError, 'Choose a name.');
    assert.equal(ui.items[0].name, 'Coastal walk');
    assert.equal(ui.editingToken, 'Ab12Cd34Ef');
});

test('cancel rename performs no request and restores menu trigger focus', t => {
    const { ui, requests, focus } = setup(t);
    ui.rename(record(), { isConnected: true, focus: () => focus.push('trigger') });
    ui.draftName = 'discard';
    ui.cancelRename();
    assert.equal(ui.editingToken, null);
    assert.equal(focus.at(-1), 'trigger');
    assert.equal(requests.length, 0);
});

test('a successful rename invalidates stale list responses', async t => {
    const pending = Promise.withResolvers();
    const { ui } = setup(t, { fetch: (url, options) => options.method === 'PATCH'
        ? Response.json(record(undefined, { name: 'New name' })) : pending.promise });
    ui.items = [record()];
    const load = ui.load();
    ui.rename(ui.items[0]); ui.draftName = 'New name';
    await ui.saveName(ui.items[0]);
    pending.resolve(Response.json(page([record()]))); await load;
    assert.equal(ui.items[0].name, 'New name');
});

test('delete confirmation exposes affected maps and only deletes after confirmation', async t => {
    const { ui, requests, focus } = setup(t, { fetch: (url, options) => options.method === 'DELETE'
        ? new Response(null, { status: 204 }) : Response.json(page([])) });
    ui.items = [record()];
    ui.openDelete(ui.items[0]);
    assert.equal(ui.deletingRecord.maps[0].title, 'Weekend');
    assert.equal(focus.at(-1), 'cancelDelete');
    assert.equal(requests.length, 0);
    await ui.remove(); await tick();
    assert.equal(requests[0].method, 'DELETE');
    assert.equal(ui.items.length, 0);
    assert.equal(ui.deletingRecord, null);
});

test('failed deletion keeps the track and confirmation available for retry', async t => {
    const { ui } = setup(t, { fetch: () => new Response(null, { status: 500 }) });
    ui.items = [record()]; ui.openDelete(ui.items[0]);
    await ui.remove();
    assert.equal(ui.items.length, 1);
    assert.equal(ui.deletingRecord.token, 'Ab12Cd34Ef');
    assert.equal(ui.mutationError, 'Delete failed');
    assert.equal(ui.busy, false);
});

test('leaving the page aborts pending mutations without changing a disposed library', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup(t, { fetch: () => pending.promise });
    ui.items = [record()]; ui.openDelete(ui.items[0]);
    const removal = ui.remove(); ui.destroy();
    assert.equal(requests[0].signal.aborted, true);
    pending.resolve(new Response(null, { status: 204 })); await removal;
    assert.equal(ui.items.length, 1);
});

test('miniature routes preserve geographic aspect and support dateline crossings', () => {
    const path = routePreview([[179.9, 10], [-179.9, 10]]);
    assert.equal(path, 'M12.0,38.0 L88.0,38.0');
    assert.equal(routePreview([[0, 0], [0, 1]]), 'M50.0,64.0 L50.0,12.0');
    assert.equal(routePreview([['bad', 0], [1, 2]]), '');
    assert.equal(routePreview(null), '');
    assert.equal(mapCoordinates([29.6, -36.5]), '36.50° S, 29.60° E');
    assert.equal(mapCoordinates(null), '');
    assert.equal(libraryDate('invalid'), '');
});
