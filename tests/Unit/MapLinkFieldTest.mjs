import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from '@vue/reactivity';
import mapLinkField, { randomMapSlug } from '../../resources/js/mapLinkField.js';

const endpoint = 'https://rodnik.test/user/maps/check-slug';
const tick = () => new Promise(setImmediate);

function setup(t, options = {}) {
    const requests = [];
    const ui = reactive({
        ...mapLinkField({
            slugEndpoint: endpoint, locale: 'ru', slugDebounce: 5,
            slugCheckingMessage: 'Checking', slugAvailableMessage: 'Available', slugInvalidMessage: 'Invalid link',
            slugUnavailableMessage: 'Taken', slugCheckFailedMessage: 'Check failed',
            ...options.config,
            fetch: async (url, request) => {
                requests.push({ url, ...request });
                return options.fetch ? options.fetch(url, request) : Response.json({ available: true });
            },
        }),
        draftSlug: '', fieldErrors: {}, disposed: false,
    });
    t.after(() => ui.disposeLinkField());
    return { ui, requests };
}

test('generated links use eight lowercase alphanumeric characters from secure randomness', () => {
    const slugs = Array.from({ length: 30 }, () => randomMapSlug());
    assert.equal(new Set(slugs).size, 30);
    assert.ok(slugs.every(slug => /^[a-z0-9]{8}$/.test(slug)));
    let calls = 0;
    const slug = randomMapSlug({ getRandomValues: bytes => bytes.fill(++calls === 1 ? 255 : 0) });
    assert.equal(slug, 'aaaaaaaa');
    assert.equal(calls, 2, 'out-of-range bytes are rejected instead of biasing the result');
});

test('a generated creation link is checked immediately and never treated as already assigned', async t => {
    const { ui, requests } = setup(t);
    const result = ui.resetLinkField({ slug: 'abc123xy', id: null });
    assert.equal(ui.slugStatus, 'checking');
    assert.equal(ui.slugNotice, 'Checking');
    assert.equal(await result, true);
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(ui.slugNotice, 'Available');
    assert.equal(new URL(requests[0].url).searchParams.get('slug'), 'abc123xy');
    assert.equal(new URL(requests[0].url).searchParams.has('map'), false);
    assert.equal(requests[0].headers['X-Rodnik-Locale'], 'ru');
});

test('an existing assigned link is valid immediately without requesting availability', async t => {
    const { ui, requests } = setup(t);
    assert.equal(await ui.resetLinkField({ slug: 'ab12cd34', id: 13 }), true);
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(ui.draftSlug, 'ab12cd34');
    assert.equal(await ui.validateSlug(), true);
    assert.equal(requests.length, 0);
});

test('editing a link checks normalized input and excludes its current map ID', async t => {
    const { ui, requests } = setup(t);
    await ui.resetLinkField({ slug: 'old-route', id: 13 });
    ui.draftSlug = ' New-Route ';
    assert.equal(await ui.validateSlug(), true);
    const url = new URL(requests[0].url);
    assert.equal(url.searchParams.get('slug'), 'new-route');
    assert.equal(url.searchParams.get('map'), '13');
});

for (const value of ['', ' ', 'ab', 'a--b', 'foo/bar', 'Карта', 'x'.repeat(81)]) {
    test(`invalid slug is rejected before any request: ${JSON.stringify(value)}`, async t => {
        const { ui, requests } = setup(t);
        ui.draftSlug = value;
        assert.equal(await ui.validateSlug(), false);
        assert.equal(ui.slugStatus, 'invalid');
        assert.equal(ui.slugNotice, 'Invalid link');
        assert.equal(requests.length, 0);
    });
}

test('a missing availability endpoint never claims a new link is available', async t => {
    const { ui, requests } = setup(t, { config: { slugEndpoint: null } });
    await ui.resetLinkField({ slug: 'new-route' });
    assert.equal(ui.slugStatus, 'failed');
    assert.equal(ui.slugNotice, 'Check failed');
    assert.equal(requests.length, 0);
});

test('availability conflicts use localized link wording instead of backend slug terminology', async t => {
    const { ui } = setup(t, { fetch: () => Response.json({ errors: { slug: ['The slug has already been taken.'] } }, { status: 422 }) });
    assert.equal(await ui.resetLinkField({ slug: 'taken-link' }), false);
    assert.equal(ui.slugStatus, 'invalid');
    assert.equal(ui.slugNotice, 'Taken');
});

test('failed availability requests can be retried without changing the link', async t => {
    let count = 0;
    const { ui, requests } = setup(t, { fetch: () => ++count === 1 ? new Response(null, { status: 503 }) : Response.json({ available: true }) });
    assert.equal(await ui.resetLinkField({ slug: 'new-route' }), false);
    assert.equal(ui.slugStatus, 'failed');
    assert.equal(await ui.validateSlug(), true);
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(requests.length, 2);
});

test('submitting while an identical check is pending reuses the request', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup(t, { fetch: () => pending.promise });
    const first = ui.resetLinkField({ slug: 'new-route' });
    const submitted = ui.validateSlug();
    assert.equal(requests.length, 1);
    pending.resolve(Response.json({ available: true }));
    assert.equal(await first, true);
    assert.equal(await submitted, true);
});

test('fast typing debounces checks and clears previous server field errors', async t => {
    const { ui, requests } = setup(t);
    ui.fieldErrors = { slug: ['Taken'], title: ['Name error'] };
    ui.draftSlug = 'first'; ui.slugInput();
    ui.draftSlug = 'second'; ui.slugInput();
    assert.deepEqual(ui.fieldErrors, { title: ['Name error'] });
    await new Promise(resolve => setTimeout(resolve, 20));
    assert.equal(requests.length, 1);
    assert.equal(new URL(requests[0].url).searchParams.get('slug'), 'second');
});

test('a stale check cannot replace newer valid link feedback', async t => {
    const old = Promise.withResolvers();
    let calls = 0;
    const { ui, requests } = setup(t, { fetch: () => ++calls === 1 ? old.promise : Response.json({ available: true }) });
    const first = ui.resetLinkField({ slug: 'old-route' });
    ui.draftSlug = 'new-route';
    await ui.validateSlug();
    assert.equal(requests[0].signal.aborted, true);
    old.resolve(Response.json({ errors: { slug: ['Old link taken'] } }, { status: 422 }));
    assert.equal(await first, false);
    assert.equal(ui.slugStatus, 'valid');
    assert.equal(ui.slugNotice, 'Available');
});

test('closing or resetting the form ignores late availability responses', async t => {
    const old = Promise.withResolvers();
    const { ui, requests } = setup(t, { fetch: () => old.promise });
    const first = ui.resetLinkField({ slug: 'draft-link' });
    await ui.resetLinkField({ slug: 'owned-link', id: 13 });
    assert.equal(requests[0].signal.aborted, true);
    old.resolve(Response.json({ errors: { slug: ['Taken'] } }, { status: 422 }));
    await first;
    assert.equal(ui.draftSlug, 'owned-link');
    assert.equal(ui.slugStatus, 'valid');
});

test('returning to the unchanged saved link aborts checks and restores valid feedback', async t => {
    const pending = Promise.withResolvers();
    const { ui, requests } = setup(t, { fetch: () => pending.promise });
    await ui.resetLinkField({ slug: 'owned-link', id: 13 });
    ui.draftSlug = 'new-link';
    const check = ui.validateSlug();
    ui.draftSlug = 'owned-link';
    assert.equal(await ui.validateSlug(), true);
    assert.equal(requests[0].signal.aborted, true);
    pending.resolve(Response.json({ available: false }));
    await check;
    assert.equal(ui.slugStatus, 'valid');
});
