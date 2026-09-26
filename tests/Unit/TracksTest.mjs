import assert from 'node:assert/strict';
import { createHash, webcrypto } from 'node:crypto';
import test from 'node:test';
import Tracks, { serializeSharedTrack } from '../../resources/js/tracks.js';

const track = (offset = 0) => ({
    type: 'FeatureCollection',
    features: [
        { type: 'Feature', properties: { name: 'Route' }, geometry: { type: 'LineString', coordinates: [[offset, 40], [offset + 1, 41]] } },
        { type: 'Feature', properties: { name: 'Waypoint', desc: 'Water' }, geometry: { type: 'Point', coordinates: [offset, 40, 120] } },
    ],
});
const hashOf = value => createHash('sha256').update(JSON.stringify(value)).digest('hex');
const tokenOf = value => hashOf(value).slice(0, 10);
const record = value => ({ id: 1, hash: hashOf(value), token: tokenOf(value), track: value });
const tick = () => new Promise(setImmediate);

function setup(options = {}) {
    const requests = [];
    const applied = [];
    const persistence = new Tracks({
        endpoint: '/tracks', csrfToken: () => 'csrf', crypto: webcrypto,
        defer: async () => {},
        apply: value => applied.push(value),
        fetch: async (url, options) => {
            requests.push({ url, options });
            return Response.json({ id: 1, hash: JSON.parse(options.body).hash, token: JSON.parse(options.body).hash.slice(0, 10) });
        },
        ...options,
    });
    return { persistence, requests, applied };
}

test('imported tracks upload automatically after the map is ready', async () => {
    const ready = Promise.withResolvers();
    const { persistence, requests } = setup({ defer: () => ready.promise });
    const upload = persistence.replace(track(), { name: 'Coastal walk' });
    assert.equal(persistence.state.status, 'saving');
    assert.equal(persistence.state.hash, null);
    assert.equal(persistence.state.name, 'Coastal walk');
    await tick();
    assert.equal(requests.length, 0);
    ready.resolve();
    await upload;
    assert.equal(requests.length, 1);
    assert.equal(persistence.state.status, 'saved');
    assert.equal(JSON.parse(requests[0].options.body).name, 'Coastal walk');
});

test('copying while uploading waits for UI readiness and preserves route and waypoint data', async () => {
    const ready = Promise.withResolvers();
    const { persistence, requests } = setup({ defer: () => ready.promise });
    persistence.replace(track());
    const sharing = persistence.ensure();
    await tick();
    assert.equal(requests.length, 0);
    assert.equal(persistence.state.status, 'saving');
    ready.resolve();
    assert.deepEqual(await sharing, { id: 1, hash: hashOf(track()), token: tokenOf(track()) });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.deepEqual(JSON.parse(requests[0].options.body), { hash: hashOf(track()), track: JSON.stringify(track()), name: 'Route' });
    assert.equal(persistence.state.status, 'saved');
});

test('concurrent share requests reuse the same track upload', async () => {
    const { persistence, requests } = setup();
    persistence.replace(track());

    const records = await Promise.all([persistence.ensure(), persistence.ensure()]);

    assert.deepEqual(records, [{ id: 1, hash: hashOf(track()), token: tokenOf(track()) }, { id: 1, hash: hashOf(track()), token: tokenOf(track()) }]);
    assert.equal(requests.length, 1);
});

test('explicitly reimporting the same track checks the server again instead of reviving a deleted token', async () => {
    const { persistence, requests } = setup();
    persistence.replace(track());
    await persistence.ensure();
    persistence.replace(track());
    await persistence.ensure();
    assert.equal(requests.length, 2);
});

test('a deferred shared track never replaces or uploads a newer local track', async () => {
    const response = Promise.withResolvers();
    let downloadSignal;
    const { persistence, applied } = setup({
        fetch: (url, options) => {
            if (options.method === 'POST') return Promise.resolve(Response.json({ id: 2, hash: JSON.parse(options.body).hash, token: JSON.parse(options.body).hash.slice(0, 10) }));
            downloadSignal = options.signal;
            return response.promise;
        },
    });
    persistence.load(tokenOf(track()));
    await tick();
    const saving = persistence.ensure();
    const replaced = assert.rejects(saving, /could not be loaded or saved/);
    persistence.replace(track(10));
    response.resolve(Response.json(record(track())));
    await replaced;
    assert.equal(downloadSignal.aborted, true);
    assert.deepEqual(applied, []);
    assert.deepEqual(await persistence.ensure(), { id: 2, hash: hashOf(track(10)), token: tokenOf(track(10)) });
    assert.equal(persistence.state.status, 'saved');
    assert.equal(persistence.state.hash, hashOf(track(10)));
});

test('replacing a track during upload automatically starts the new upload', async () => {
    const response = Promise.withResolvers();
    const requests = [];
    const { persistence } = setup({ fetch: (url, options) => {
        requests.push(options);
        return requests.length === 1 ? response.promise : Promise.resolve(Response.json({ id: 2, hash: JSON.parse(options.body).hash, token: JSON.parse(options.body).hash.slice(0, 10) }));
    } });
    persistence.replace(track());
    const firstShare = assert.rejects(persistence.ensure(), /could not be loaded or saved/);
    while (!requests.length) await tick();
    persistence.replace(track(10));
    response.resolve(Response.json(record(track())));
    await firstShare;
    await tick();

    assert.equal(requests[0].signal.aborted, true);
    assert.equal(requests.length, 2);
    assert.deepEqual(await persistence.ensure(), { id: 2, hash: hashOf(track(10)), token: tokenOf(track(10)) });
    assert.equal(persistence.state.status, 'saved');
    assert.equal(requests.length, 2);
});

test('clearing a loading track cancels it and ignores a late response', async () => {
    const response = Promise.withResolvers();
    const { persistence, applied } = setup({ fetch: () => response.promise });
    persistence.load(tokenOf(track()));
    await tick();
    const loading = persistence.ensure();
    persistence.clear();
    response.resolve(Response.json(record(track())));
    assert.equal(await loading, null);
    assert.equal(persistence.state.status, 'idle');
    assert.deepEqual(applied, []);
});

test('failed upload blocks saving the map and can be retried', async () => {
    let calls = 0;
    const { persistence } = setup({ fetch: async (url, options) => {
        if (++calls === 1) return new Response(null, { status: 503 });
        return Response.json({ id: 4, hash: JSON.parse(options.body).hash, token: JSON.parse(options.body).hash.slice(0, 10) });
    } });
    persistence.replace(track());
    await assert.rejects(persistence.ensure(), /could not be loaded or saved/);
    assert.equal(persistence.state.status, 'failed');
    persistence.retry();
    assert.deepEqual(await persistence.ensure(), { id: 4, hash: hashOf(track()), token: tokenOf(track()) });
    assert.equal(persistence.state.error, null);
});

test('loaded shared tracks are visible and do not require another upload to copy the map', async () => {
    let calls = 0;
    const { persistence, applied } = setup({ fetch: async () => { calls++; return Response.json(record(track())); } });
    persistence.load({ token: tokenOf(track()), url: '/tracks/example' });
    await persistence.ensure();
    assert.deepEqual(applied, [track()]);
    assert.deepEqual(await persistence.ensure(), { id: 1, hash: hashOf(track()), token: tokenOf(track()) });
    assert.equal(calls, 1);
});

test('invalid server records and oversized tracks fail before they can be shared', async () => {
    const { persistence } = setup({ fetch: async () => Response.json({ ...record(track()), hash: 'invalid' }) });
    persistence.load(tokenOf(track()));
    await assert.rejects(persistence.ensure(), /could not be loaded or saved/);
    assert.throws(() => serializeSharedTrack({ type: 'FeatureCollection', features: [] }), /could not be loaded or saved/);
    const oversized = track();
    oversized.features[0].properties.name = 'x'.repeat(10 * 1024 * 1024);
    assert.throws(() => serializeSharedTrack(oversized), /10 MiB/);
});

test('content hashes cannot be used to load public tracks', async () => {
    let requests = 0;
    const { persistence } = setup({ fetch: async () => { requests++; return Response.json(record(track())); } });
    for (const reference of [hashOf(track()), { hash: hashOf(track()) }]) {
        await persistence.load(reference);
        await assert.rejects(persistence.ensure(), /could not be loaded or saved/);
    }
    assert.equal(requests, 0);
});

for (const token of [undefined, null, '', 'short', 'a'.repeat(64)]) {
    test(`a successful upload requires a public token: ${JSON.stringify(token)}`, async () => {
        const { persistence } = setup({ fetch: async () => Response.json({ ...record(track()), token }) });
        await persistence.replace(track());
        await assert.rejects(persistence.ensure(), /could not be loaded or saved/);
        assert.equal(persistence.state.status, 'failed');
    });
}

test('stalled track downloads and uploads time out, unblock saving, and remain retryable', async () => {
    for (const operation of ['load', 'replace']) {
        let stalled = true;
        let timedOutSignal;
        const { persistence } = setup({
            timeoutMs: 10,
            fetch: (url, options) => {
                if (!stalled) return Promise.resolve(Response.json(record(track())));
                timedOutSignal = options.signal;
                return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(options.signal.reason), { once: true }));
            },
        });
        persistence[operation](operation === 'load' ? tokenOf(track()) : track());
        const failed = assert.rejects(persistence.ensure(), /timed out/);
        await new Promise(resolve => setTimeout(resolve, 30));
        await failed;
        assert.equal(timedOutSignal.aborted, true);
        assert.equal(persistence.state.status, 'failed');
        assert.equal(persistence.state.token, operation === 'load' ? tokenOf(track()) : null);
        stalled = false;
        persistence.retry();
        assert.deepEqual(await persistence.ensure(), { id: 1, hash: hashOf(track()), token: tokenOf(track()) });
    }
});

test('network and track-size errors use the current UI translations', async t => {
    const previousWindow = globalThis.window;
    globalThis.window = { rodnikTranslations: {
        shared_track_failed: 'Не удалось загрузить трек. Попробуйте ещё раз.',
        shared_track_timed_out: 'Время загрузки трека истекло. Попробуйте ещё раз.',
        shared_track_too_large: 'Размер трека превышает 10 МиБ.',
    } };
    t.after(() => { globalThis.window = previousWindow; });

    const { persistence } = setup({ fetch: async () => { throw new TypeError('Failed to fetch'); } });
    persistence.replace(track());
    await assert.rejects(persistence.ensure(), /Не удалось загрузить трек/);
    assert.equal(persistence.state.error, 'Не удалось загрузить трек. Попробуйте ещё раз.');

    const oversized = track();
    oversized.features[0].properties.name = 'x'.repeat(10 * 1024 * 1024);
    assert.throws(() => serializeSharedTrack(oversized), /Размер трека превышает 10 МиБ/);
    persistence.replace(oversized);
    await assert.rejects(persistence.ensure(), /Размер трека превышает 10 МиБ/);

    const timeout = setup({ fetch: async () => { throw new DOMException('Timed out', 'TimeoutError'); } }).persistence;
    timeout.load(tokenOf(track()));
    await assert.rejects(timeout.ensure(), /Время загрузки трека истекло/);
});

test('server complexity rejections give localized actionable feedback instead of a generic retry', async t => {
    const previousWindow = globalThis.window;
    globalThis.window = { rodnikTranslations: { shared_track_too_complex: 'Упростите трек или сократите его метаданные.' } };
    t.after(() => { globalThis.window = previousWindow; });
    const { persistence } = setup({ fetch: async () => Response.json({
        errors: { track: ['The track is too complex to process. Simplify the track or reduce its metadata and try again.'] },
    }, { status: 422 }) });
    persistence.replace(track());
    await assert.rejects(persistence.ensure(), /Упростите трек/);
    assert.equal(persistence.state.error, 'Упростите трек или сократите его метаданные.');
});

test('a deleted shared track stays deleted and leaves a usable track-free map', async () => {
    const requests = [];
    const { persistence, applied } = setup({ fetch: async (url, options) => {
        requests.push({ url, options });
        return Response.json(record(track()));
    } });
    await persistence.load(tokenOf(track()));
    assert.equal(requests.length, 1);

    assert.equal(await persistence.recoverMissing(tokenOf(track())), null);
    assert.equal(requests.length, 1, 'deleted geometry is never reuploaded');
    assert.deepEqual(applied, [track(), { type: 'FeatureCollection', features: [] }]);
    assert.equal(persistence.state.status, 'missing');
    assert.equal(persistence.state.hash, null);
    assert.match(persistence.state.error, /deleted/);
    assert.equal(await persistence.ensure(), null);
    assert.equal(await persistence.retry(), null);
});

test('recovering an obsolete token never replaces a newer track', async () => {
    const { persistence, requests } = setup();
    await persistence.replace(track());
    await persistence.replace(track(10));
    await assert.rejects(persistence.recoverMissing(tokenOf(track())), /could not be loaded or saved/);
    assert.equal(requests.length, 2);
    assert.equal(persistence.state.status, 'saved');
    assert.deepEqual(persistence.operation.track, track(10));
});

test('an aborted missing-track notice leaves the current saved track intact', async () => {
    const { persistence, requests } = setup();
    await persistence.replace(track());
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(persistence.recoverMissing(tokenOf(track()), { signal: controller.signal }), { name: 'AbortError' });
    assert.equal(requests.length, 1);
    assert.equal(persistence.state.status, 'saved');
});

test('short tokens retain their case and content hash when uploaded and loaded', async () => {
    const token = 'AbCd123456';
    const requests = [];
    const { persistence, applied } = setup({ fetch: async (url, options) => {
        requests.push({ url, options });
        return Response.json({ ...record(track()), token, name: 'Coastal walk' });
    } });
    const saved = await persistence.replace(track(), { name: 'Coastal walk' });
    assert.deepEqual(saved, { id: 1, hash: hashOf(track()), token, name: 'Coastal walk' });
    assert.equal(persistence.state.token, token);
    await persistence.load(token);
    assert.equal(requests[1].url, `/tracks/${token}`);
    assert.deepEqual(applied, [track()]);
    assert.equal(persistence.state.token, token);
    assert.equal(persistence.state.hash, hashOf(track()));
});

test('a missing public token produces a notice without blocking the remaining map or retrying', async () => {
    const { persistence, requests, applied } = setup({ fetch: async () => new Response(null, { status: 404 }) });
    assert.equal(await persistence.load('AbCd123456'), null);
    assert.equal(persistence.state.status, 'missing');
    assert.match(persistence.state.error, /deleted/);
    assert.equal(persistence.state.token, null);
    assert.equal(await persistence.ensure(), null);
    assert.equal(await persistence.retry(), null);
    assert.deepEqual(applied, [{ type: 'FeatureCollection', features: [] }]);
});

test('a deleted token is rechecked on each load instead of showing stale cached geometry', async () => {
    let calls = 0;
    const { persistence, applied } = setup({ fetch: async () => ++calls === 1
        ? Response.json({ ...record(track()), token: 'AbCd123456' }) : new Response(null, { status: 404 }) });
    await persistence.load('AbCd123456');
    await persistence.load('AbCd123456');
    assert.equal(calls, 2);
    assert.equal(persistence.state.status, 'missing');
    assert.deepEqual(applied.at(-1), { type: 'FeatureCollection', features: [] });
});


test('long uploaded track names are capped at the server limit without splitting Unicode characters', async () => {
    const { persistence, requests } = setup();
    await persistence.replace(track(), { name: '🗺'.repeat(200) });
    assert.equal(persistence.state.name, '🗺'.repeat(160));
    assert.equal(JSON.parse(requests[0].options.body).name, '🗺'.repeat(160));
});
