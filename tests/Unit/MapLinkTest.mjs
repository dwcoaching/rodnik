import assert from 'node:assert/strict';
import test from 'node:test';
import { parseMapUrlState } from '../../resources/js/mapUrlState.js';
import { fullMapUrl, resolveMapLink } from '../../resources/js/mapLink.js';

const baseUrl = 'https://rodnik.test/';
const state = { version: 1, center: [30, 36], zoom: 12, page: { spring: 123, user: 5, location: 1 } };

test('normal map URLs preserve resource, filters, overlays, viewport and track', async () => {
    const input = `https://rodnik.test/ru/123/?user=5&location=1#map=12/36/30&layer=terrain&filters=spring&reports=1&overlays=osmTraces&fullscreen=1&minimized=1&track=${'AbCd123456'}`;
    const result = await resolveMapLink(input, { baseUrl });
    assert.deepEqual(result.state.page, state.page);
    assert.equal(result.state.filters.spring, true);
    assert.equal(result.state.filters.water_well, false);
    assert.equal(result.state.filters.with_reports, true);
    assert.equal(result.state.overlays.osmTraces, true);
    assert.equal(result.state.fullscreen, true);
    assert.equal(result.state.minimized, true);
    const rebuilt = new URL(fullMapUrl(result, 'https://rodnik.test/ru'));
    assert.equal(rebuilt.pathname, '/ru/123/');
    assert.equal(rebuilt.search, '?user=5&location=1');
    assert.deepEqual(parseMapUrlState(rebuilt), parseMapUrlState(input));
    assert.ok(rebuilt.hash.length < new URL(input).hash.length);
});

test('user and legacy query selections are preserved in full URLs', async () => {
    const user = await resolveMapLink('/users/5/?location=1#map=12/36/30', { baseUrl });
    assert.deepEqual(user.state.page, { spring: null, user: 5, location: 1 });
    const legacy = await resolveMapLink('/?page[spring]=123&page[user]=5&page[location]=1#map=12/36/30', { baseUrl });
    assert.equal(fullMapUrl(legacy, baseUrl), 'https://rodnik.test/123/?user=5&location=1#m=12/36/30');
});

for (const input of [
    'https://example.com/share/Ab12Cd34', 'http://rodnik.test/share/Ab12Cd34',
    'https://user:password@rodnik.test/share/Ab12Cd34', 'javascript:alert(1)',
    '/share/Ab12Cd34', '/ru/share/Ab12Cd34', '/profile#map=12/36/30', '/maps/options', '/share/Ab12Cd34/login',
    '/share/Ab12Cd34?redirect=https://example.com', '/share/Ab12Cd34#map=12/36/30',
    '/?user=oops#map=12/36/30', '/#map=12/91/30', '/#map=12/36/30&track=invalid', '/#m=12/36/30&t=' + 'a'.repeat(64), '', '/',
]) {
    test(`invalid or unrelated URL is rejected without fetching: ${input}`, async () => {
        let fetched = false;
        await assert.rejects(resolveMapLink(input, { baseUrl, fetch: async () => { fetched = true; } }));
        assert.equal(fetched, false);
    });
}

for (const path of ['/maps/ab12cd34', '/maps/lycian-way/', '/ru/maps/ab12cd34/']) {
    test(`same-origin short map URL resolves as JSON without following redirects: ${path}`, async () => {
        const calls = [];
        const result = await resolveMapLink(path, { baseUrl, fetch: async (url, options) => {
            calls.push({ url, options });
            return Response.json({ state, track: { token: 'AbCd123456', hash: 'b'.repeat(64) } });
        } });
        assert.equal(calls[0].url, `https://rodnik.test${path}`);
        assert.equal(calls[0].options.credentials, 'same-origin');
        assert.equal(calls[0].options.redirect, 'error');
        assert.deepEqual(result.state.page, state.page);
        assert.equal(result.track_token, 'AbCd123456');
    });
}

test('invalid server map data cannot be saved as an empty or invented view', async () => {
    await assert.rejects(resolveMapLink('/maps/ab12cd34', { baseUrl, fetch: async () => Response.json({ state: {} }) }));
    await assert.rejects(resolveMapLink('/maps/ab12cd34', { baseUrl, fetch: async () => Response.json({ state, track: { hash: 'invalid' } }) }));
});

test('full URL uses sanitized resource URLs while retaining redirected-source inspection', () => {
    assert.equal(fullMapUrl({ state, resource_url: 'https://rodnik.test/123/?user=5&location=1&redirect=false' }, baseUrl),
        'https://rodnik.test/123/?user=5&location=1&redirect=false#m=12/36/30');
    assert.equal(fullMapUrl({ state: { ...state, page: {} }, resource_url: 'https://rodnik.test/' }, baseUrl),
        'https://rodnik.test/#m=12/36/30');
    assert.equal(fullMapUrl({ state, resource_url: 'https://example.com/123/' }, baseUrl),
        'https://rodnik.test/123/?user=5&location=1#m=12/36/30');
});


test('compact track URLs preserve token identity when resolving or copying saved maps', async () => {
    const input = 'https://rodnik.test/#m=12/36/30&t=AbCd123456';
    const result = await resolveMapLink(input, { baseUrl });
    assert.equal(result.track_token, 'AbCd123456');
    assert.equal(Object.hasOwn(result, 'track_hash'), false);
    assert.equal(fullMapUrl(result, baseUrl), input);
    assert.equal(fullMapUrl({ state: { ...state, page: {} }, track: { token: 'AbCd123456', hash: 'a'.repeat(64) } }, baseUrl), input);
});

test('saved map JSON uses its public token without exposing its content hash as a link', async () => {
    const result = await resolveMapLink('/maps/coastal-walk', { baseUrl, fetch: async () => Response.json({
        state, track: { token: 'AbCd123456', hash: 'a'.repeat(64) },
    }) });
    assert.equal(result.track_token, 'AbCd123456');
    assert.equal(Object.hasOwn(result, 'track_hash'), false);
});
