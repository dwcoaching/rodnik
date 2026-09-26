import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from '@vue/reactivity';
import mapLibrary from '../../resources/js/mapLibrary.js';

const tick = () => new Promise(setImmediate);
const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
const indexUrl = 'https://rodnik.test/ru/user/maps';
const endpoint = `${indexUrl}/options`;
const record = (id, overrides = {}) => ({
    id, slug: `map-${id}`, title: `Map ${id}`, version: 1, starred: false,
    url: `https://rodnik.test/ru/maps/map-${id}/`, created_at: '2026-09-22T12:00:00+00:00',
    edit_url: `${indexUrl}/${id}/edit`, details_url: `/maps/${id}/details`,
    star_url: `${indexUrl}/${id}/star`, delete_url: `${indexUrl}/${id}`, ...overrides,
});
const page = (data, overrides = {}) => ({
    data, total: data.length, current_page: 1, last_page: 1, next_page_url: null, ...overrides,
});
const paginated = (rows, number = 1) => page(rows.slice((number - 1) * 20, number * 20), {
    total: rows.length, current_page: number, last_page: Math.max(1, Math.ceil(rows.length / 20)),
    next_page_url: rows.length > number * 20 ? `${endpoint}?page=${number + 1}` : null,
});

function setup(t, options = {}) {
    const requests = [];
    const focus = [];
    const ui = mapLibrary({
        endpoint, indexUrl, items: [record(1)], total: 1, locale: 'ru', csrfToken: 'csrf',
        loadMessage: 'Could not load maps', sessionMessage: 'Session expired', rateLimitMessage: 'Try later',
        messages: { errorMessage: 'Could not save', deleteMessage: 'Could not delete' },
        ...options.config,
        fetch: (url, request) => {
            requests.push({ url, ...request });
            return options.fetch ? options.fetch(url, request) : Promise.resolve(Response.json(page([record(2)])));
        },
    });
    ui.$nextTick = callback => callback();
    ui.$refs = { search: { focus: () => focus.push('search') } };
    t.after(() => ui.destroy());
    return { ui, requests, focus };
}

test('initial server results require no request and strip obsolete fields', t => {
    const { ui, requests } = setup(t, { config: {
        items: [record(1, { description: 'Removed field', state: { secret: true } }), { id: 2 }, null],
    } });
    assert.equal(ui.items.length, 1);
    assert.equal(ui.items[0].id, 1);
    assert.equal(ui.items[0].slug, 'map-1');
    assert.equal('description' in ui.items[0], false);
    assert.equal('state' in ui.items[0], false);
    assert.deepEqual(requests, []);
});

test('typing debounces live search and sends the latest query with the current locale', async t => {
    const { ui, requests } = setup(t, { config: { debounce: 5 } });
    ui.query = 'old';
    ui.scheduleSearch();
    ui.query = '  water & 100%  ';
    ui.scheduleSearch();
    assert.equal(requests.length, 0);
    assert.equal(ui.loading, true);
    await wait(20);
    assert.equal(requests.length, 1);
    assert.equal(new URL(requests[0].url).searchParams.get('q'), 'water & 100%');
    assert.equal(requests[0].credentials, 'same-origin');
    assert.equal(requests[0].headers.Accept, 'application/json');
    assert.equal(requests[0].headers['X-Rodnik-Locale'], 'ru');
    assert.equal(ui.loading, false);
    assert.deepEqual(ui.items.map(item => item.id), [2]);
});

test('favorites changes immediately replace results and reset pagination', async t => {
    const { ui, requests } = setup(t, {
        config: { nextPage: `${endpoint}?page=4`, query: '  river  ' },
        fetch: async () => Response.json(page([record(2, { starred: true }), record(3)])),
    });
    ui.favorites = true;
    await ui.filterChanged();
    const url = new URL(requests[0].url);
    assert.equal(url.searchParams.get('favorites'), '1');
    assert.equal(url.searchParams.get('q'), 'river');
    assert.equal(url.searchParams.has('page'), false);
    assert.deepEqual(ui.items.map(item => item.id), [2]);
    ui.query = '  ';
    ui.favorites = false;
    await ui.filterChanged();
    assert.equal(new URL(requests[1].url).search, '');
});

test('replaced searches abort and ignore late responses even when transport ignores abort', async t => {
    const first = Promise.withResolvers();
    const second = Promise.withResolvers();
    let count = 0;
    const { ui, requests } = setup(t, { fetch: () => (++count === 1 ? first : second).promise });
    ui.query = 'old';
    const oldLoad = ui.load();
    ui.query = 'new';
    const newLoad = ui.load();
    assert.equal(requests[0].signal.aborted, true);
    second.resolve(Response.json(page([record(3)])));
    await newLoad;
    first.resolve(Response.json(page([record(2)])));
    await oldLoad;
    assert.deepEqual(ui.items.map(item => item.id), [3]);
    assert.equal(ui.error, '');
    assert.equal(ui.loading, false);
});

test('a stale JSON body cannot replace a newer result', async t => {
    const body = Promise.withResolvers();
    let count = 0;
    const { ui } = setup(t, { fetch: async () => ++count === 1
        ? { ok: true, status: 200, json: () => body.promise }
        : Response.json(page([record(3)])) });
    const oldLoad = ui.load();
    await tick();
    await ui.load();
    body.resolve(page([record(2)]));
    await oldLoad;
    assert.deepEqual(ui.items.map(item => item.id), [3]);
});

test('late failures do not obscure a newer successful search', async t => {
    const pending = Promise.withResolvers();
    let count = 0;
    const { ui } = setup(t, { fetch: () => ++count === 1 ? pending.promise : Promise.resolve(Response.json(page([record(3)]))) });
    const oldLoad = ui.load();
    await ui.load();
    pending.reject(new Error('Old request failed'));
    await oldLoad;
    assert.equal(ui.error, '');
    assert.deepEqual(ui.items.map(item => item.id), [3]);
});

test('load more appends without duplicate rows and accepts newer server versions', async t => {
    const { ui, requests } = setup(t, {
        config: { nextPage: `${endpoint}?q=river&favorites=1&page=2`, query: 'river', favorites: true, items: [record(1, { starred: true })] },
        fetch: async () => Response.json(page([record(1, { title: 'Renamed', version: 2, starred: true }), record(2, { starred: true })], { total: 21, current_page: 2, last_page: 2 })),
    });
    await ui.load(true);
    assert.equal(new URL(requests[0].url).searchParams.get('page'), '2');
    assert.equal(new URL(requests[0].url).searchParams.get('favorites'), '1');
    assert.deepEqual(ui.items.map(item => item.id), [1, 2]);
    assert.equal(ui.items[0].title, 'Renamed');
    assert.equal(ui.items[0].version, 2);
    assert.equal(ui.total, 21);
    assert.equal(ui.nextPage, null);
});

test('load more is disabled without another page and while an existing request is pending', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup(t, { fetch: () => pending.promise });
    await ui.load(true);
    assert.equal(requests.length, 0);
    ui.nextPage = `${endpoint}?page=2`;
    const loading = ui.load(true);
    await ui.load(true);
    assert.equal(requests.length, 1);
    pending.resolve(Response.json(page([record(2)], { current_page: 2, last_page: 2 })));
    await loading;
});

for (const nextPage of ['https://other.example/options?page=2', 'https://rodnik.test/user/other?page=2']) {
    test(`pagination rejects an unexpected endpoint: ${nextPage}`, async t => {
        const { ui, requests } = setup(t, { config: { nextPage } });
        await ui.load(true);
        assert.deepEqual(requests, []);
        assert.equal(ui.error, 'Could not load maps');
        assert.equal(ui.loading, false);
    });
}

for (const [status, error] of [[401, 'Session expired'], [419, 'Session expired'], [429, 'Try later'], [500, 'Could not load maps']]) {
    test(`failed loading status ${status} preserves current rows and gives retry feedback`, async t => {
        const { ui } = setup(t, { fetch: async () => new Response(null, { status }) });
        await ui.load();
        assert.equal(ui.error, error);
        assert.deepEqual(ui.items.map(item => item.id), [1]);
        assert.equal(ui.loading, false);
    });
}

for (const body of [{}, page([{ id: 2 }]), page([record(2)], { total: -1 })]) {
    test(`malformed list response preserves current data: ${JSON.stringify(body)}`, async t => {
        const { ui } = setup(t, { fetch: async () => Response.json(body) });
        await ui.load();
        assert.equal(ui.error, 'Could not load maps');
        assert.deepEqual(ui.items.map(item => item.id), [1]);
    });
}

test('retrying a failed new search cannot append pages from the previous query', async t => {
    const { ui, requests } = setup(t, {
        config: { query: 'old', nextPage: `${endpoint}?q=old&page=2` },
        fetch: async () => new Response(null, { status: 500 }),
    });
    ui.query = 'new';
    await ui.load();
    await ui.load(true);
    assert.equal(requests.length, 1);
    assert.equal(ui.nextPage, null);
});

test('an in-flight list response cannot overwrite a freshly saved name', async t => {
    const pending = Promise.withResolvers();
    let count = 0;
    const { ui } = setup(t, { fetch: () => ++count === 1 ? pending.promise
        : Promise.resolve(Response.json(page([record(1, { title: 'Saved locally', version: 2 })]))) });
    const loading = ui.load();
    ui.applyRecord({ id: 1, title: 'Saved locally', version: 2 });
    await tick();
    pending.resolve(Response.json(page([record(1)])));
    await loading;
    assert.equal(ui.items[0].title, 'Saved locally');
    assert.equal(ui.items[0].version, 2);
});

test('a lower server revision never rolls back an existing saved name', async t => {
    const { ui } = setup(t, {
        config: { items: [record(1, { title: 'Latest name', version: 4 })] },
        fetch: async () => Response.json(page([record(1, { version: 3 })])),
    });
    await ui.load();
    assert.equal(ui.items[0].title, 'Latest name');
    assert.equal(ui.items[0].version, 4);
});

test('unfavoriting removes the row and refreshes the favorites result count', async t => {
    const { ui } = setup(t, {
        config: { favorites: true, items: [record(1, { starred: true })] },
        fetch: async () => Response.json(page([])),
    });
    ui.applyRecord({ id: 1, starred: false });
    await tick();
    assert.deepEqual(ui.items, []);
    assert.equal(ui.total, 0);
});

test('a stale list response cannot restore a deleted row or its result count', async t => {
    const pending = Promise.withResolvers();
    let count = 0;
    const { ui } = setup(t, {
        config: { items: [record(1), record(2)], total: 2 },
        fetch: () => ++count === 1 ? pending.promise : Promise.resolve(Response.json(page([record(2)]))),
    });
    const loading = ui.load();
    ui.removeRecord(1);
    await tick();
    pending.resolve(Response.json(page([record(1), record(2)])));
    await loading;
    assert.deepEqual(ui.items.map(item => item.id), [2]);
    assert.equal(ui.total, 1);
});

test('a stale favorites response respects an unstarred row and adjusted count', async t => {
    const pending = Promise.withResolvers();
    let count = 0;
    const { ui } = setup(t, {
        config: { favorites: true, items: [record(1, { starred: true })] },
        fetch: () => ++count === 1 ? pending.promise : Promise.resolve(Response.json(page([]))),
    });
    const loading = ui.load();
    ui.applyRecord({ id: 1, starred: false });
    await tick();
    pending.resolve(Response.json(page([record(1, { starred: true })])));
    await loading;
    assert.deepEqual(ui.items, []);
    assert.equal(ui.total, 0);
});

for (const mutation of ['delete', 'unfavorite']) {
    test(`${mutation} refreshes offset pagination so the shifted row is not skipped`, async t => {
        const initial = Array.from({ length: 22 }, (_, index) => record(index + 1, { starred: true }));
        const serverRows = initial.slice(1);
        const { ui, requests } = setup(t, {
            config: {
                favorites: mutation === 'unfavorite', items: initial.slice(0, 20), total: 22,
                nextPage: `${endpoint}?page=2${mutation === 'unfavorite' ? '&favorites=1' : ''}`,
            },
            fetch: async url => {
                const pageNumber = Number(new URL(url).searchParams.get('page') ?? 1);
                return Response.json(page(serverRows.slice((pageNumber - 1) * 20, pageNumber * 20), {
                    total: 21, current_page: pageNumber, last_page: 2,
                    next_page_url: pageNumber === 1 ? `${endpoint}?page=2${mutation === 'unfavorite' ? '&favorites=1' : ''}` : null,
                }));
            },
        });
        if (mutation === 'delete') ui.removeRecord(1);
        else ui.applyRecord({ id: 1, starred: false });
        await tick();
        await ui.load(true);
        assert.deepEqual(ui.items.map(item => item.id), serverRows.map(item => item.id));
        assert.equal(ui.total, 21);
        assert.equal(new URL(requests[0].url).searchParams.has('page'), false);
        if (mutation === 'unfavorite') assert.equal(new URL(requests[0].url).searchParams.get('favorites'), '1');
    });
}

for (const mutation of ['rename', 'delete']) {
    test(`${mutation} refreshes both loaded pages atomically without removing another draft row`, async t => {
        const initial = Array.from({ length: 43 }, (_, index) => record(index + 1));
        const serverRows = mutation === 'delete' ? initial.slice(1)
            : initial.map(item => item.id === 1 ? { ...item, title: 'Renamed', version: 2 } : item);
        const secondPage = Promise.withResolvers();
        const { ui, requests, focus } = setup(t, {
            config: { items: initial.slice(0, 40), total: 43, currentPage: 2, nextPage: `${endpoint}?page=3` },
            fetch: async url => new URL(url).searchParams.get('page') === '2'
                ? secondPage.promise : Response.json(paginated(serverRows)),
        });
        if (mutation === 'delete') ui.removeRecord(1);
        else ui.applyRecord({ id: 1, title: 'Renamed', version: 2 });
        await tick();
        assert.equal(requests.length, 2);
        assert.equal(ui.loading, true);
        assert.equal(ui.items.some(item => item.id === 35), true, 'page-one response must not unmount a page-two editor');
        assert.equal(ui.items.length, mutation === 'delete' ? 39 : 40);
        secondPage.resolve(Response.json(paginated(serverRows, 2)));
        await tick();
        assert.deepEqual(ui.items.map(item => item.id), serverRows.slice(0, 40).map(item => item.id));
        assert.equal(ui.items.some(item => item.id === 35), true);
        assert.equal(ui.nextPage, `${endpoint}?page=3`);
        assert.equal(ui.loadedThroughPage, 2);
        assert.equal(ui.total, serverRows.length);
        assert.deepEqual(focus, []);
    });
}

test('a mutation cancels an older append without losing the previously loaded window', async t => {
    const initial = Array.from({ length: 43 }, (_, index) => record(index + 1));
    const serverRows = initial.slice(1);
    const oldPage = Promise.withResolvers();
    let count = 0;
    const { ui, requests } = setup(t, {
        config: { items: initial.slice(0, 40), total: 43, currentPage: 2, nextPage: `${endpoint}?page=3` },
        fetch: url => ++count === 1 ? oldPage.promise
            : Promise.resolve(Response.json(paginated(serverRows, Number(new URL(url).searchParams.get('page') ?? 1)))),
    });
    const oldAppend = ui.load(true);
    ui.removeRecord(1);
    assert.equal(requests[0].signal.aborted, true);
    await tick();
    oldPage.resolve(Response.json(paginated(initial, 3)));
    await oldAppend;
    assert.deepEqual(ui.items.map(item => item.id), serverRows.slice(0, 40).map(item => item.id));
    assert.equal(ui.loadedThroughPage, 2);
    assert.equal(ui.total, 42);
});

test('a failed second refresh page preserves existing rows and retries the full visible window', async t => {
    const initial = Array.from({ length: 43 }, (_, index) => record(index + 1));
    const serverRows = initial.map(item => item.id === 1 ? { ...item, title: 'Renamed', version: 2 } : item);
    let failSecondPage = true;
    const { ui, requests } = setup(t, {
        config: { items: initial.slice(0, 40), total: 43, currentPage: 2, nextPage: `${endpoint}?page=3` },
        fetch: async url => {
            const number = Number(new URL(url).searchParams.get('page') ?? 1);
            return number === 2 && failSecondPage ? new Response(null, { status: 500 }) : Response.json(paginated(serverRows, number));
        },
    });
    ui.applyRecord({ id: 1, title: 'Renamed', version: 2 });
    await tick();
    assert.equal(ui.error, 'Could not load maps');
    assert.deepEqual(ui.items.map(item => item.id), initial.slice(0, 40).map(item => item.id));
    assert.equal(ui.items[0].title, 'Renamed');
    assert.equal(ui.loadedThroughPage, 2);
    assert.equal(ui.retryAppend, false);
    assert.equal(ui.retryPreserveWindow, true);
    failSecondPage = false;
    await ui.load(ui.retryAppend, ui.retryPreserveWindow);
    assert.equal(ui.error, '');
    assert.deepEqual(ui.items.map(item => item.id), initial.slice(0, 40).map(item => item.id));
    assert.equal(ui.items[0].title, 'Renamed');
    assert.equal(ui.loadedThroughPage, 2);
    assert.deepEqual(requests.map(request => Number(new URL(request.url).searchParams.get('page') ?? 1)), [1, 2, 1, 2]);
});

test('a fresh search resets a previously loaded two-page window', async t => {
    const { ui, requests } = setup(t, { config: {
        currentPage: 2, items: Array.from({ length: 40 }, (_, index) => record(index + 1)), total: 43,
    } });
    ui.query = 'new search';
    await ui.load();
    assert.equal(requests.length, 1);
    assert.deepEqual(ui.items.map(item => item.id), [2]);
    assert.equal(ui.loadedThroughPage, 1);
    assert.equal(ui.retryPreserveWindow, false);
});

test('renaming while searching rechecks membership without moving focus out of search', async t => {
    const { ui, requests, focus } = setup(t, {
        config: { query: 'Old title', items: [record(1, { title: 'Old title' })] },
        fetch: async () => Response.json(page([])),
    });
    ui.applyRecord({ id: 1, title: 'New title', version: 2 });
    await tick();
    assert.deepEqual(ui.items, []);
    assert.equal(ui.total, 0);
    assert.equal(ui.query, 'Old title');
    assert.equal(new URL(requests[0].url).searchParams.get('q'), 'Old title');
    assert.deepEqual(focus, []);
});

test('row callbacks update the parent record without changing its endpoints', t => {
    const { ui } = setup(t);
    const row = ui.rowConfig(ui.items[0]);
    assert.equal(row.endpoint, record(1).details_url);
    assert.equal(row.starEndpoint, record(1).star_url);
    assert.equal(row.deleteEndpoint, record(1).delete_url);
    assert.equal(row.csrfToken, 'csrf');
    assert.equal(row.locale, 'ru');
    assert.equal(row.slug, 'map-1');
    row.onSaved({ id: 1, title: 'New name', version: 2 });
    assert.equal(ui.items[0].title, 'New name');
    assert.equal(ui.items[0].url, record(1).url);
    row.onDeleted(1);
    assert.deepEqual(ui.items, []);
    assert.equal(ui.total, 0);
});

test('a saved custom URL reaches refreshed rows without changing mutation endpoints', async t => {
    const pending = Promise.withResolvers();
    const { ui } = setup(t, { fetch: () => pending.promise });
    const row = ui.rowConfig(ui.items[0]);
    const saved = record(1, { title: 'River walk', slug: 'river-walk', url: 'https://rodnik.test/ru/maps/river-walk/', version: 2 });
    row.onSaved(saved);
    assert.equal(ui.items[0].slug, 'river-walk');
    assert.equal(ui.items[0].url, saved.url);
    assert.equal(ui.rowConfig(ui.items[0]).endpoint, row.endpoint);
    pending.resolve(Response.json(page([saved])));
    await tick();
    const refreshed = ui.rowConfig(ui.items[0]);
    assert.equal(refreshed.slug, 'river-walk');
    assert.equal(refreshed.url, saved.url);
    assert.equal(refreshed.version, 2);
    assert.equal(refreshed.endpoint, row.endpoint);
    assert.equal(refreshed.starEndpoint, row.starEndpoint);
    assert.equal(refreshed.deleteEndpoint, row.deleteEndpoint);
});

test('the deletion dialog keeps its original record and restores its opening control focus', t => {
    const { ui, focus } = setup(t);
    const trigger = { isConnected: true, focus: () => focus.push('trigger') };
    ui.openDelete(ui.items[0], trigger);
    ui.openDelete(record(2), { isConnected: true, focus: () => focus.push('second') });
    assert.equal(ui.deletingRecord.id, 1);
    assert.equal(ui.deleteConfig(ui.deletingRecord).confirmDelete, true);
    ui.closeDelete();
    assert.equal(ui.deletingRecord, null);
    assert.deepEqual(focus, ['trigger']);
});

test('deleting a dialog row restores focus to search when its trigger disappeared', t => {
    const { ui, focus } = setup(t);
    const trigger = { isConnected: false, focus: () => focus.push('removed') };
    ui.openDelete(ui.items[0], trigger);
    const dialog = ui.deleteConfig(ui.deletingRecord);
    dialog.onDeleted(1);
    dialog.onClose();
    assert.deepEqual(ui.items, []);
    assert.equal(ui.deletingRecord, null);
    assert.deepEqual(focus, ['search']);
});

test('a directly routed map outside the active filters opens its editor dialog', t => {
    const { ui } = setup(t, { config: {
        editingRecord: record(3), query: 'river', favorites: true,
        items: [record(1, { starred: true }), record(2, { starred: true })], total: 2,
    } });
    assert.deepEqual(ui.items.map(item => item.id), [3, 1, 2]);
    assert.equal(ui.query, 'river');
    assert.equal(ui.favorites, true);
    assert.equal(ui.total, 2);
    assert.equal(ui.rowConfig(ui.items[1]).autoEdit, false);
    assert.equal(ui.editingRecord.id, 3);
    assert.equal(ui.editConfig(ui.editingRecord).autoEdit, true);
    ui.closeEdit();
    assert.equal(ui.editingRecord, null);
    assert.equal(ui.rowConfig(ui.items[0]).autoEdit, false);
    assert.equal(ui.deletingRecord, null);
});

test('a directly routed map already on the page is deduplicated with its current name', t => {
    const { ui } = setup(t, { config: {
        items: [record(1), record(2)], editingRecord: record(2, { title: 'Latest name', version: 2 }),
    } });
    assert.deepEqual(ui.items.map(item => item.id), [2, 1]);
    assert.equal(ui.items[0].title, 'Latest name');
    assert.equal(ui.items[0].version, 2);
    assert.equal(ui.editConfig(ui.editingRecord).autoEdit, true);
    assert.equal(ui.rowConfig(ui.items[1]).autoEdit, false);
});

test('Alpine reactive row proxies keep the dialog separate from row state', t => {
    const { ui: plain } = setup(t, { config: { editingRecord: record(1) } });
    const ui = reactive(plain);
    const target = ui.items[0];
    assert.notEqual(target, plain.items[0]);
    assert.equal(ui.editConfig(ui.editingRecord).autoEdit, true);
    ui.closeEdit();
    assert.equal(ui.editingRecord, null);
    assert.equal(ui.rowConfig(target).autoEdit, false);
    assert.equal(ui.rowConfig(reactive(record(2))).autoEdit, false);
});

test('disposing cancels debounce and ignores outstanding list and mutation callbacks', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup(t, { config: { debounce: 5 }, fetch: () => pending.promise });
    ui.scheduleSearch();
    ui.destroy();
    await wait(20);
    assert.equal(requests.length, 0);

    const active = setup(t, { fetch: () => pending.promise });
    const loading = active.ui.load();
    active.ui.destroy();
    assert.equal(active.requests[0].signal.aborted, true);
    pending.resolve(Response.json(page([record(2)])));
    await loading;
    active.ui.applyRecord({ id: 1, title: 'Late name', version: 2 });
    active.ui.removeRecord(1);
    active.ui.openDelete(record(1));
    assert.deepEqual(active.ui.items.map(item => item.id), [1]);
    assert.equal(active.ui.items[0].title, 'Map 1');
    assert.equal(active.ui.deletingRecord, null);
});

test('library rows preserve route previews, map coordinates', t => {
    const preview = [[29.6, 36.5], [29.7, 36.6]];
    const { ui } = setup(t, { config: { items: [record(1, { preview, center: [29.6, 36.5], track_name: 'Lycian Way' })] } });
    assert.deepEqual(ui.items[0].preview, preview);
    assert.equal(ui.items[0].track_name, 'Lycian Way');
    assert.equal(Object.hasOwn(ui.items[0], 'track_deleted'), false);
    assert.match(ui.preview(preview), /^M\d+\.\d,\d+\.\d L/);
    assert.equal(ui.coordinates(ui.items[0].center), '36.50° N, 29.60° E');
});
