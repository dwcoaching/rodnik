import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from '@vue/reactivity';
import mapTitles from '../../resources/js/mapTitles.js';

const record = { id: 13, title: 'My map', slug: 'weekend', url: 'https://rodnik.test/maps/weekend/', version: 3, starred: false };
const tick = () => new Promise(setImmediate);

function setup(options = {}) {
    const requests = [];
    const focus = [];
    const changes = [];
    const deleted = [];
    let closes = 0;
    const ui = mapTitles({
        ...record, endpoint: '/maps/13/details', starEndpoint: '/user/maps/13/star', deleteEndpoint: '/user/maps/13',
        slugEndpoint: 'https://rodnik.test/user/maps/check-slug',
        slugAvailableMessage: 'Available', slugUnavailableMessage: 'Choose another link.', slugInvalidMessage: 'Invalid link', slugCheckFailedMessage: 'Try again',
        titleRequiredMessage: 'Enter a name for your map.',
        csrfToken: 'csrf', locale: 'ru', errorMessage: 'Could not save', conflictMessage: 'Reload the list',
        sessionMessage: 'Session expired', rateLimitMessage: 'Try later', copyMessage: 'Copy manually',
        starMessage: 'Could not star', deleteMessage: 'Could not delete', feedbackDuration: 5,
        onSaved: change => changes.push(change), onDeleted: id => deleted.push(id), onClose: () => { closes += 1; },
        ...options.config,
        fetch: async (url, requestOptions) => {
            if (!requestOptions.method) return Response.json({ available: true });
            const payload = requestOptions.body ? JSON.parse(requestOptions.body) : undefined;
            requests.push({ url, ...requestOptions, payload });
            if (options.fetch) return options.fetch(url, requestOptions, payload);
            if (requestOptions.method === 'DELETE') return new Response(null, { status: 204 });
            if (url.endsWith('/star')) return Response.json({ ...record, starred: payload.starred });
            const slug = payload.slug ?? record.slug;
            return Response.json({ ...record, title: payload.title ?? 'Map of September 22', slug, url: `https://rodnik.test/maps/${slug}/`, version: payload.version + 1 });
        },
    });
    ui.$nextTick = callback => callback();
    ui.$refs = Object.fromEntries(['title', 'slug', 'edit', 'link', 'cancelDelete'].map(name => [name, {
        focus: () => focus.push(name), select: () => focus.push(`${name}:select`),
    }]));
    return { ui, requests, focus, changes, deleted, closes: () => closes };
}

test('names stay plain until pencil edit and Escape cancels without a request', async () => {
    const { ui, requests, focus, closes } = setup();
    ui.init();
    assert.equal(ui.inlineEditing, false);
    assert.deepEqual(focus, []);
    ui.edit();
    assert.equal(ui.inlineEditing, true);
    assert.deepEqual(focus, ['title', 'title:select']);
    ui.draftTitle = 'Draft';
    ui.cancelInline();
    assert.equal(ui.draftTitle, 'My map');
    assert.equal(ui.inlineEditing, false);
    assert.equal(closes(), 0);
    assert.equal(focus.at(-1), 'edit');
    assert.equal(requests.length, 0);
});

test('the legacy edit target activates its inline field immediately', () => {
    const { ui, focus } = setup({ config: { autoEdit: true } });
    ui.init();
    assert.equal(ui.inlineEditing, true);
    assert.deepEqual(focus, ['title', 'title:select']);
    ui.destroy();
});

test('saving sends only the trimmed title, custom slug and current version', async () => {
    const { ui, requests, changes, closes } = setup();
    ui.edit();
    ui.draftTitle = '  Weekend by the lake  ';
    await ui.saveInline();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/maps/13/details');
    assert.equal(requests[0].method, 'PATCH');
    assert.equal(requests[0].credentials, 'same-origin');
    assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(requests[0].headers['X-Rodnik-Locale'], 'ru');
    assert.deepEqual(requests[0].payload, { title: 'Weekend by the lake', slug: 'weekend', version: 3 });
    assert.deepEqual(changes, [{ id: 13, title: 'Weekend by the lake', slug: 'weekend', url: record.url, version: 4 }]);
    assert.equal(ui.title, 'Weekend by the lake');
    assert.equal(ui.draftTitle, ui.title);
    assert.equal(ui.url, record.url);
    assert.equal(ui.version, 4);
    assert.equal(ui.inlineEditing, false);
    assert.equal(ui.busy, false);
    assert.equal(closes(), 0);
    ui.destroy();
});

test('clearing a name keeps the editor open and requires a name before saving', async () => {
    const { ui, requests, focus } = setup();
    ui.edit();
    ui.draftTitle = ' \n ';
    await ui.saveInline();
    assert.deepEqual(requests, []);
    assert.equal(ui.title, 'My map');
    assert.equal(ui.draftTitle, ' \n ');
    assert.equal(ui.inlineEditing, true);
    assert.equal(ui.busy, false);
    assert.deepEqual(ui.fieldErrors.title, ['Enter a name for your map.']);
    assert.equal(focus.at(-1), 'title');
    ui.draftTitle = 'New name';
    await ui.saveInline();
    assert.equal(requests.length, 1);
    assert.equal(ui.title, 'New name');
    assert.deepEqual(ui.fieldErrors, {});
    ui.destroy();
});

test('unchanged names return to plain text without a write', async () => {
    const { ui, requests, closes } = setup();
    ui.edit();
    ui.draftTitle = '  My map  ';
    await ui.saveInline();
    assert.equal(requests.length, 0);
    assert.equal(ui.draftTitle, 'My map');
    assert.equal(closes(), 0);
    assert.equal(ui.inlineEditing, false);
});

test('save requires an active editor and valid version', async () => {
    const { ui, requests } = setup({ config: { version: 0 } });
    ui.draftTitle = 'Changed';
    await ui.saveInline();
    ui.edit();
    ui.draftTitle = 'Changed';
    await ui.saveInline();
    assert.equal(requests.length, 0);
});

test('explicit inline save runs once and keeps the list in place', async () => {
    const { ui, requests, changes, closes } = setup();
    ui.edit();
    ui.draftTitle = 'Inline change';
    await ui.saveInline();
    await ui.saveInline();
    assert.equal(requests.length, 1);
    assert.equal(ui.title, 'Inline change');
    assert.equal(ui.inlineEditing, false);
    assert.equal(changes.length, 1);
    assert.equal(closes(), 0);
    ui.edit();
    ui.draftTitle = 'Second change';
    await ui.saveInline();
    assert.equal(requests[1].payload.version, 4);
    ui.destroy();
});

test('Escape restores inline saved name and subsequent blur makes no request', async () => {
    const { ui, requests } = setup();
    ui.edit();
    ui.draftTitle = 'Discard';
    ui.cancelInline();
    await ui.saveInline();
    assert.equal(ui.draftTitle, 'My map');
    assert.equal(ui.inlineEditing, false);
    assert.equal(requests.length, 0);
});

test('unchanged inline save avoids requests and does not close dialogs', async () => {
    const { ui, requests, closes } = setup();
    ui.edit();
    await ui.saveInline();
    assert.equal(requests.length, 0);
    assert.equal(closes(), 0);
});

for (const [status, message] of [[401, 'Session expired'], [419, 'Session expired'], [409, 'Reload the list'], [429, 'Try later'], [403, 'Could not save'], [500, 'Could not save']]) {
    test(`name save handles HTTP ${status} without changing saved state`, async () => {
        const { ui, requests, changes } = setup({ fetch: async () => new Response('{}', { status }) });
        ui.edit();
        ui.draftTitle = 'Unsaved';
        await ui.saveInline();
        assert.equal(ui.error, message);
        assert.equal(ui.title, 'My map');
        assert.equal(ui.draftTitle, 'Unsaved');
        assert.equal(ui.version, 3);
        assert.equal(ui.inlineEditing, true);
        assert.equal(ui.busy, false);
        assert.equal(requests.length, 1);
        assert.deepEqual(changes, []);
    });
}

test('validation error keeps the inline draft visible without stealing focus', async () => {
    const { ui, focus } = setup({ fetch: async () => Response.json({ errors: { title: ['Name is too long.'] } }, { status: 422 }) });
    ui.edit();
    focus.length = 0;
    ui.draftTitle = 'Draft';
    await ui.saveInline();
    assert.equal(ui.error, 'Name is too long.');
    assert.deepEqual(ui.fieldErrors, { title: ['Name is too long.'] });
    assert.equal(ui.draftTitle, 'Draft');
    assert.equal(ui.inlineEditing, true);
    assert.deepEqual(focus, []);
});

test('failed inline save keeps draft for retry without moving keyboard focus', async () => {
    let fail = true;
    const { ui, focus, requests } = setup({ fetch: async (_url, _options, payload) => fail
        ? Response.json({ errors: { title: ['Bad name'] } }, { status: 422 })
        : Response.json({ ...record, title: payload.title, version: 4 }) });
    ui.edit();
    ui.draftTitle = 'Draft';
    focus.length = 0;
    await ui.saveInline();
    assert.equal(ui.error, 'Bad name');
    assert.equal(ui.draftTitle, 'Draft');
    assert.deepEqual(focus, []);
    fail = false;
    ui.edit();
    await ui.saveInline();
    assert.equal(ui.title, 'Draft');
    assert.equal(requests.length, 2);
    ui.destroy();
});

test('network errors do not automatically retry an ambiguous save', async () => {
    const { ui, requests } = setup({ fetch: async () => { throw new Error('Offline'); } });
    ui.edit();
    ui.draftTitle = 'Draft';
    await ui.saveInline();
    await tick();
    assert.equal(ui.error, 'Offline');
    assert.equal(ui.busy, false);
    assert.equal(requests.length, 1);
});

for (const bad of [null, {}, { title: 'Changed', version: 3 }, { title: '', version: 4 }, { title: 'Changed', version: '4' }, { title: 'Changed', version: 4, id: 99 }, { title: 'Changed', version: 4, id: '13' }]) {
    test(`malformed or unrelated response does not replace saved name: ${JSON.stringify(bad)}`, async () => {
        const { ui, changes } = setup({ fetch: async () => Response.json(bad) });
        ui.edit();
        ui.draftTitle = 'Changed';
        await ui.saveInline();
        assert.equal(ui.error, 'Could not save');
        assert.equal(ui.title, 'My map');
        assert.equal(ui.version, 3);
        assert.deepEqual(changes, []);
    });
}

test('pending save blocks duplicate saves, cancellation and starring', async () => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup({ fetch: () => pending.promise });
    ui.edit();
    ui.draftTitle = 'Draft';
    const saving = ui.saveInline();
    await ui.saveInline();
    await ui.cancel();
    await ui.toggleStar();
    ui.cancelInline();
    assert.equal(ui.busy, true);
    assert.equal(ui.inlineEditing, true);
    assert.equal(ui.draftTitle, 'Draft');
    assert.equal(requests.length, 1);
    pending.resolve(Response.json({ ...record, title: 'Draft', version: 4 }));
    await saving;
    ui.destroy();
});

test('disposal aborts pending save and ignores a late result', async () => {
    const pending = Promise.withResolvers();
    const { ui, requests, changes, closes } = setup({ fetch: () => pending.promise });
    ui.edit();
    ui.draftTitle = 'Draft';
    const saving = ui.saveInline();
    await tick();
    ui.destroy();
    assert.equal(requests[0].signal.aborted, true);
    pending.resolve(Response.json({ ...record, title: 'Draft', version: 4 }));
    await saving;
    assert.equal(ui.title, 'My map');
    assert.deepEqual(changes, []);
    assert.equal(closes(), 0);
});

test('disposal while parsing JSON ignores a late save result', async () => {
    const pending = Promise.withResolvers();
    const { ui, changes } = setup({ fetch: async () => ({ ok: true, status: 200, json: () => pending.promise }) });
    ui.edit();
    ui.draftTitle = 'Draft';
    const saving = ui.saveInline();
    await tick();
    ui.destroy();
    pending.resolve({ ...record, title: 'Draft', version: 4 });
    await saving;
    assert.deepEqual(changes, []);
    assert.equal(ui.title, 'My map');
});

test('saving a name never invokes modal closing or page navigation', async () => {
    const { ui, requests } = setup({ config: { onClose: async () => { throw new Error('Must not close'); } } });
    ui.edit();
    ui.draftTitle = 'Saved';
    await ui.saveInline();
    assert.equal(ui.version, 4);
    assert.equal(ui.inlineEditing, false);
    assert.equal(ui.error, '');
    await ui.saveInline();
    assert.equal(requests.length, 1);
    assert.equal(ui.inlineEditing, false);
    ui.destroy();
});

test('row receives refreshed records while preserving active or unsaved drafts', () => {
    const { ui } = setup();
    ui.receive({ ...record, title: 'Refreshed', version: 4 });
    assert.equal(ui.title, 'Refreshed');
    ui.edit();
    ui.draftTitle = 'Typed';
    ui.receive({ ...record, title: 'External', version: 5 });
    assert.equal(ui.draftTitle, 'Typed');
    assert.equal(ui.version, 4);
    ui.inlineEditing = false;
    ui.receive({ ...record, title: 'External', version: 5 });
    assert.equal(ui.draftTitle, 'Typed');
    ui.cancelInline();
    ui.receive({ ...record, title: 'External', version: 5 });
    assert.equal(ui.title, 'External');
    ui.receive({ ...record, title: 'Stale', version: 4 });
    ui.receive({ ...record, title: 'Other map', version: 6, id: 20 });
    assert.equal(ui.title, 'External');
});

test('toggling favorite updates row and parent without navigation', async () => {
    const { ui, requests, changes, closes } = setup();
    await ui.toggleStar();
    assert.equal(requests[0].url, '/user/maps/13/star');
    assert.deepEqual(requests[0].payload, { starred: true });
    assert.equal(ui.starred, true);
    assert.deepEqual(changes, [{ id: 13, starred: true }]);
    assert.equal(closes(), 0);
    await ui.toggleStar();
    assert.deepEqual(requests[1].payload, { starred: false });
});

test('favorite errors do not change the current star', async () => {
    const { ui } = setup({ fetch: async () => new Response('{}', { status: 500 }) });
    await ui.toggleStar();
    assert.equal(ui.starred, false);
    assert.equal(ui.error, 'Could not star');
    assert.equal(ui.busy, false);
});

test('deleting requires the confirmation dialog and focuses its safe action', async () => {
    const { ui, requests, deleted, focus } = setup();
    await ui.remove();
    assert.equal(requests.length, 0);
    ui.confirmingDelete = true;
    ui.init();
    assert.deepEqual(focus, ['cancelDelete']);
    await ui.remove();
    assert.equal(requests[0].method, 'DELETE');
    assert.equal(requests[0].body, undefined);
    assert.deepEqual(deleted, [13]);
    assert.equal(ui.confirmingDelete, false);
});

test('failed deletion keeps confirmation open and does not remove the row', async () => {
    const { ui, deleted } = setup({ config: { confirmDelete: true }, fetch: async () => new Response('{}', { status: 403 }) });
    await ui.remove();
    assert.equal(ui.confirmingDelete, true);
    assert.equal(ui.error, 'Could not delete');
    assert.equal(ui.deleted, false);
    assert.deepEqual(deleted, []);
});

test('successful deletion is not resent after close failure', async () => {
    let fail = true;
    const { ui, requests, deleted } = setup({ config: { confirmDelete: true, onClose: () => { if (fail) throw new Error(); } } });
    await ui.remove();
    assert.equal(ui.deleted, true);
    fail = false;
    await ui.remove();
    assert.equal(requests.length, 1);
    assert.deepEqual(deleted, [13]);
    assert.equal(ui.confirmingDelete, false);
});

test('pending deletion cannot be cancelled and ignores disposed completion', async () => {
    const pending = Promise.withResolvers();
    const { ui, requests, deleted } = setup({ config: { confirmDelete: true }, fetch: () => pending.promise });
    const removing = ui.remove();
    await ui.cancel();
    await ui.remove();
    assert.equal(ui.confirmingDelete, true);
    assert.equal(requests.length, 1);
    ui.destroy();
    pending.resolve(new Response(null, { status: 204 }));
    await removing;
    assert.deepEqual(deleted, []);
});

test('copy uses the full saved custom URL without waiting for unsaved name', async () => {
    const writes = [];
    const { ui, requests } = setup({ config: { clipboard: { writeText: async text => writes.push(text) } } });
    ui.draftTitle = 'Unsaved';
    await ui.copy();
    assert.deepEqual(writes, [record.url]);
    assert.equal(ui.copied, true);
    assert.equal(requests.length, 0);
    ui.destroy();
});

test('clipboard rejection offers selectable saved URL', async () => {
    const { ui, focus } = setup({ config: { clipboard: { writeText: async () => { throw new Error(); } } } });
    await ui.copy();
    assert.equal(ui.error, 'Copy manually');
    assert.equal(ui.manualCopyUrl, record.url);
    assert.deepEqual(focus, ['link', 'link:select']);
    assert.equal(ui.copying, false);
});

for (const url of ['', 'javascript:alert(1)', 'https://user:pass@rodnik.test/maps/test', 'not a URL']) {
    test(`copy refuses invalid URL ${url}`, async () => {
        const writes = [];
        const { ui } = setup({ config: { url, clipboard: { writeText: text => writes.push(text) } } });
        await ui.copy();
        assert.deepEqual(writes, []);
        assert.equal(ui.canCopyLink(), false);
    });
}

test('pending copy suppresses duplicates and late feedback after URL changed', async () => {
    const pending = Promise.withResolvers();
    let writes = 0;
    const { ui } = setup({ config: { clipboard: { writeText: () => { writes += 1; return pending.promise; } } } });
    const copying = ui.copy();
    await ui.copy();
    ui.url = 'https://rodnik.test/maps/another/';
    pending.resolve();
    await copying;
    assert.equal(writes, 1);
    assert.equal(ui.copied, false);
});

test('late rejected copy after disposal cannot show fallback or focus', async () => {
    const pending = Promise.withResolvers();
    const { ui, focus } = setup({ config: { clipboard: { writeText: () => pending.promise } } });
    const copying = ui.copy();
    ui.destroy();
    pending.reject(new Error());
    await copying;
    assert.equal(ui.manualCopyUrl, '');
    assert.deepEqual(focus, []);
});

test('delayed input focus is cancelled after inline edit ends or component is destroyed', async () => {
    const { ui, focus } = setup();
    const tasks = [];
    ui.$nextTick = callback => tasks.push(callback);
    ui.edit();
    ui.cancelInline();
    tasks.shift()();
    assert.deepEqual(focus, []);
    tasks.shift()();
    assert.deepEqual(focus, ['edit']);
    focus.length = 0;
    ui.edit();
    ui.destroy();
    tasks.shift()();
    assert.deepEqual(focus, []);
});

test('cancelling a legacy edit target restores plain text without navigation', async () => {
    const { ui, requests, closes } = setup({ config: { autoEdit: true } });
    ui.init();
    ui.draftTitle = 'Unsaved';
    ui.cancelInline();
    await ui.saveInline();
    assert.equal(closes(), 0);
    assert.equal(requests.length, 0);
    assert.equal(ui.inlineEditing, false);
    assert.equal(ui.busy, false);
});

test('pending favorite change aborts on disposal without publishing a late result', async () => {
    const pending = Promise.withResolvers();
    const { ui, requests, changes } = setup({ fetch: () => pending.promise });
    const starring = ui.toggleStar();
    ui.destroy();
    assert.equal(requests[0].signal.aborted, true);
    pending.resolve(Response.json({ ...record, starred: true }));
    await starring;
    assert.equal(ui.starred, false);
    assert.deepEqual(changes, []);
});

test('clicking the pencil again preserves the active draft', () => {
    const { ui, focus, requests } = setup();
    ui.edit();
    ui.draftTitle = 'Keep this draft';
    ui.edit();
    assert.equal(ui.draftTitle, 'Keep this draft');
    assert.equal(ui.inlineEditing, true);
    assert.equal(focus.at(-1), 'title:select');
    assert.equal(requests.length, 0);
});

test('Enter saves then returns keyboard focus to the pencil', async () => {
    const { ui, focus } = setup();
    ui.edit();
    ui.draftTitle = 'Saved with Enter';
    focus.length = 0;
    await ui.saveInline(true);
    assert.equal(ui.title, 'Saved with Enter');
    assert.equal(ui.inlineEditing, false);
    assert.deepEqual(focus, ['edit']);
    ui.destroy();
});

test('Enter completion does not take focus from another field', async t => {
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'document');
    Object.defineProperty(globalThis, 'document', { configurable: true, value: { body: {}, activeElement: { id: 'search' } } });
    t.after(() => {
        if (previous) Object.defineProperty(globalThis, 'document', previous);
        else delete globalThis.document;
    });
    const { ui, focus } = setup();
    ui.edit();
    ui.draftTitle = 'Saved';
    focus.length = 0;
    await ui.saveInline(true);
    assert.equal(ui.title, 'Saved');
    assert.deepEqual(focus, []);
    ui.destroy();
});

test('inline editing and saves work through Alpine reactivity proxies', async () => {
    const { ui: component, requests, changes } = setup();
    const ui = reactive(component);
    ui.edit();
    ui.draftTitle = 'Reactive name';
    await ui.saveInline();
    assert.equal(requests.length, 1);
    assert.deepEqual(requests[0].payload, { title: 'Reactive name', slug: 'weekend', version: 3 });
    assert.equal(ui.title, 'Reactive name');
    assert.equal(ui.inlineEditing, false);
    assert.deepEqual(changes, [{ id: 13, title: 'Reactive name', slug: 'weekend', url: record.url, version: 4 }]);
    ui.destroy();
});

test('name and slug remain drafts until the explicit save', async () => {
    const { ui, requests } = setup();
    ui.edit();
    ui.draftTitle = 'New map name';
    ui.draftSlug = 'new-route';
    await tick();
    assert.equal(requests.length, 0);
    assert.equal(ui.title, 'My map');
    assert.equal(ui.url, record.url);
    ui.cancelInline();
    assert.equal(ui.draftTitle, 'My map');
    assert.equal(ui.draftSlug, 'weekend');
    assert.equal(ui.inlineEditing, false);
});

test('combined save normalizes slug and adopts the returned canonical URL', async () => {
    const { ui, requests, changes } = setup();
    ui.edit();
    ui.draftTitle = '  New map  ';
    ui.draftSlug = '  NEW-ROUTE  ';
    await ui.saveInline();
    assert.deepEqual(requests[0].payload, { title: 'New map', slug: 'new-route', version: 3 });
    assert.equal(ui.title, 'New map');
    assert.equal(ui.slug, 'new-route');
    assert.equal(ui.draftSlug, 'new-route');
    assert.equal(ui.url, 'https://rodnik.test/maps/new-route/');
    assert.deepEqual(changes, [{ id: 13, title: 'New map', slug: 'new-route', url: ui.url, version: 4 }]);
    ui.destroy();
});

for (const draftSlug of ['', '  ', 'Ab12Cd34']) {
    test(`link field validates explicit names and normalizes ordinary slugs: ${JSON.stringify(draftSlug)}`, async () => {
        const { ui, requests } = setup();
        ui.edit();
        ui.draftSlug = draftSlug;
        await ui.saveInline();
        if (!draftSlug.trim()) {
            assert.equal(requests.length, 0);
            assert.equal(ui.slugStatus, 'invalid');
            assert.equal(ui.slug, 'weekend');
            assert.equal(ui.inlineEditing, true);
        } else {
            assert.deepEqual(requests[0].payload, { title: 'My map', slug: 'ab12cd34', version: 3 });
            assert.equal(ui.slug, 'ab12cd34');
            assert.equal(ui.draftSlug, 'ab12cd34');
            assert.equal(ui.url, 'https://rodnik.test/maps/ab12cd34/');
        }
        ui.destroy();
    });
}

test('a default short URL appears as the actual editable link', async () => {
    const { ui, requests } = setup({ config: { slug: 'ab12cd34', url: 'https://rodnik.test/maps/ab12cd34/' } });
    assert.equal(ui.draftSlug, 'ab12cd34');
    ui.edit();
    await ui.saveInline();
    assert.equal(requests.length, 0);
    assert.equal(ui.inlineEditing, false);
});

test('unchanged custom slug ignores case and surrounding whitespace without a write', async () => {
    const { ui, requests } = setup();
    ui.edit();
    ui.draftSlug = ' WEEKEND ';
    await ui.saveInline();
    assert.equal(requests.length, 0);
    assert.equal(ui.draftSlug, 'weekend');
    assert.equal(ui.inlineEditing, false);
});

test('slug uniqueness errors keep both drafts and the saved link unchanged', async () => {
    const { ui, changes } = setup({ fetch: async () => Response.json({ errors: { slug: ['This URL is taken.'] } }, { status: 422 }) });
    ui.edit();
    ui.draftTitle = 'Unsaved name';
    ui.draftSlug = 'taken-url';
    await ui.saveInline();
    assert.equal(ui.inlineEditing, true);
    assert.equal(ui.draftTitle, 'Unsaved name');
    assert.equal(ui.draftSlug, 'taken-url');
    assert.equal(ui.title, 'My map');
    assert.equal(ui.slug, 'weekend');
    assert.equal(ui.url, record.url);
    assert.deepEqual(ui.fieldErrors, { slug: ['This URL is taken.'] });
    assert.equal(ui.error, 'This URL is taken.');
    assert.deepEqual(changes, []);
});

test('validation can report both optional fields together', async () => {
    const errors = { title: ['Name is too long.'], slug: ['Invalid URL name.'] };
    const { ui } = setup({ fetch: async () => Response.json({ errors }, { status: 422 }) });
    ui.edit();
    ui.draftTitle = 'Bad';
    ui.draftSlug = 'taken-link';
    await ui.saveInline();
    assert.deepEqual(ui.fieldErrors, errors);
    assert.equal(ui.inlineEditing, true);
});

for (const response of [
    { slug: 'wrong', url: 'https://rodnik.test/maps/other/' },
    { slug: 'changed', url: 'https://other.test/maps/changed/' },
    { slug: 'changed', url: 'javascript:alert(1)' },
    { slug: 'changed', url: 'https://user:pass@rodnik.test/maps/changed/' },
    { slug: 'bad slug', url: 'https://rodnik.test/maps/bad%20slug/' },
]) {
    test(`malformed canonical link is not adopted: ${response.url}`, async () => {
        const { ui, changes } = setup({ fetch: async () => Response.json({ ...record, ...response, title: 'Changed', version: 4 }) });
        ui.edit();
        ui.draftSlug = 'changed';
        await ui.saveInline();
        assert.equal(ui.error, 'Could not save');
        assert.equal(ui.url, record.url);
        assert.equal(ui.slug, 'weekend');
        assert.equal(ui.version, 3);
        assert.deepEqual(changes, []);
    });
}

test('copy uses the saved URL during slug editing and canonical URL after save', async () => {
    const writes = [];
    const { ui } = setup({ config: { clipboard: { writeText: async text => writes.push(text) } } });
    ui.edit();
    ui.draftSlug = 'new-route';
    await ui.copy();
    assert.deepEqual(writes, [record.url]);
    assert.equal(ui.draftSlug, 'new-route');
    await ui.saveInline();
    await ui.copy();
    assert.deepEqual(writes, [record.url, 'https://rodnik.test/maps/new-route/']);
    ui.destroy();
});

test('old pending copy feedback cannot survive a successful slug update', async () => {
    const pending = Promise.withResolvers();
    const { ui } = setup({ config: { clipboard: { writeText: () => pending.promise } } });
    const copying = ui.copy();
    ui.edit();
    ui.draftSlug = 'new-route';
    await ui.saveInline();
    pending.reject(new Error());
    await copying;
    assert.equal(ui.manualCopyUrl, '');
    assert.equal(ui.copied, false);
    assert.equal(ui.error, '');
    assert.equal(ui.url, 'https://rodnik.test/maps/new-route/');
    ui.destroy();
});

test('receiving list refreshes cannot overwrite an unsaved slug', () => {
    const { ui } = setup();
    ui.edit();
    ui.draftSlug = 'keep-draft';
    ui.receive({ ...record, title: 'Refreshed', slug: 'server-route', url: 'https://rodnik.test/maps/server-route/', version: 4 });
    assert.equal(ui.draftSlug, 'keep-draft');
    assert.equal(ui.url, record.url);
    ui.cancelInline();
    ui.receive({ ...record, title: 'Refreshed', slug: 'server-route', url: 'https://rodnik.test/maps/server-route/', version: 4 });
    assert.equal(ui.draftSlug, 'server-route');
    assert.equal(ui.url, 'https://rodnik.test/maps/server-route/');
});

test('edit opens the name and assigned link together with name focus', () => {
    const { ui, focus } = setup();
    ui.menuOpen = true;
    ui.edit();
    assert.equal(ui.menuOpen, false);
    assert.equal(ui.inlineEditing, true);
    assert.equal(ui.draftSlug, 'weekend');
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(focus.at(-2), 'title');
    ui.destroy();
});

test('link validation marks the field invalid after a failed save', async () => {
    const { ui } = setup({ fetch: async () => Response.json({ errors: { slug: ['This link is taken.'] } }, { status: 422 }) });
    ui.edit();
    ui.draftTitle = 'Changed name';
    assert.equal(ui.slugStatus, 'valid');
    await ui.saveInline();
    assert.equal(ui.slugStatus, 'invalid');
    assert.equal(ui.fieldErrors.slug[0], 'This link is taken.');
    ui.destroy();
});
