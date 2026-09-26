import assert from 'node:assert/strict';
import test from 'node:test';
import { navigateHistoryWithLivewire, writeDuoHistory } from '../../resources/js/navigationHistory.js';

function browser(href = 'https://rodnik.test/123/?user=456#map=12/55.7/37.6', initialState = null) {
    const entries = [{ href, state: initialState }];
    const writes = [];
    const events = [];
    let sequence = 0;
    const window = {
        location: new URL(href),
        crypto: { randomUUID: () => String(++sequence) },
        PopStateEvent: class {
            constructor(type, { state }) {
                this.type = type;
                this.state = state;
            }
        },
        dispatchEvent(event) { events.push(event); },
        history: {
            get state() { return entries.at(-1).state; },
            pushState(state, title, nextHref) {
                entries.push({ state: structuredClone(state), href: nextHref });
                window.location = new URL(nextHref);
                writes.push({ method: 'pushState', state, title, href: nextHref });
            },
            replaceState(state, title, nextHref) {
                entries[entries.length - 1] = { state: structuredClone(state), href: nextHref };
                window.location = new URL(nextHref);
                writes.push({ method: 'replaceState', state, title, href: nextHref });
            },
        },
    };
    return { window, entries, writes, events };
}

test('resource visits push once while preserving independent history state and exact URL context', () => {
    const initial = {
        alpine: { snapshotIdx: 'old-page', url: 'https://rodnik.test/123/', panel: { value: 'open' } },
        rodnik: { scroll: [0, 400] },
        anotherLibrary: { key: 1 },
    };
    const { window, entries, writes, events } = browser(undefined, initial);
    const destination = 'https://rodnik.test/ru/789/?user=456&location=1&custom=a%2Bb&custom=c%26d#map=9/1/2&track=abc';

    assert.equal(writeDuoHistory(window, destination).href, destination);
    assert.equal(entries.length, 2);
    assert.equal(writes.length, 1);
    assert.equal(writes[0].method, 'pushState');
    assert.equal(window.location.href, destination);
    assert.deepEqual(window.history.state, {
        alpine: { panel: { value: 'open' } },
        rodnik: { scroll: [0, 400], duo: true, url: destination },
        anotherLibrary: { key: 1 },
    });
    assert.equal(initial.alpine.snapshotIdx, 'old-page');
    assert.equal(initial.rodnik.duo, undefined);
    assert.equal(events.length, 0);
});

test('resource URL replacement removes stale Livewire navigation without adding history entries', () => {
    const { window, entries, writes } = browser(undefined, {
        alpine: { snapshotIdx: 'cached-share', url: 'https://rodnik.test/maps/abc' },
        anotherLibrary: 42,
    });
    writeDuoHistory(window, '/123/?location=1#map=10/1/2', { replace: true });

    assert.equal(entries.length, 1);
    assert.equal(writes[0].method, 'replaceState');
    assert.equal('alpine' in window.history.state, false);
    assert.equal(window.history.state.anotherLibrary, 42);
    assert.equal(window.history.state.rodnik.url, 'https://rodnik.test/123/?location=1#map=10/1/2');
});

test('resource navigation accepts a URL and an empty initial history state', () => {
    const { window } = browser();
    const url = new URL('https://rodnik.test/users/456/');
    writeDuoHistory(window, url);
    assert.deepEqual(window.history.state, { rodnik: { duo: true, url: url.href } });
});

test('resource navigation rejects foreign origins before changing history', () => {
    for (const href of ['https://other.test/123/', '//other.test/123/', 'javascript:alert(1)', 'http://rodnik.test/123/', 'blob:https://rodnik.test/uuid']) {
        const { window, entries, writes } = browser();
        assert.throws(() => writeDuoHistory(window, href), TypeError);
        assert.equal(entries.length, 1);
        assert.equal(writes.length, 0);
    }
});

test('Livewire history restoration fetches the actual current address without pushing an entry', () => {
    const href = 'https://rodnik.test/ru/docs/about?custom=a%2Bb&custom=c%26d#section';
    const initial = {
        alpine: { snapshotIdx: 'wrong-cache', url: 'https://rodnik.test/maps/old', panel: { value: 2 } },
        rodnik: { duo: true },
        anotherLibrary: ['preserved'],
    };
    const { window, entries, writes, events } = browser(href, initial);
    assert.equal(navigateHistoryWithLivewire(window).href, href);

    assert.equal(entries.length, 1);
    assert.equal(writes.length, 1);
    assert.equal(writes[0].method, 'replaceState');
    assert.equal(writes[0].href, href);
    assert.deepEqual(window.history.state, {
        ...initial,
        alpine: { ...initial.alpine, snapshotIdx: 'rodnik-history:1', url: href },
    });
    assert.equal(events.length, 1);
    assert.equal(events[0].type, 'popstate');
    assert.deepEqual(events[0].state, window.history.state);
    assert.equal(initial.alpine.snapshotIdx, 'wrong-cache');
});

test('every Livewire history fallback gets a fresh cache key and dispatches after replacing state', () => {
    const { window, entries, events } = browser();
    const observed = [];
    window.dispatchEvent = event => {
        observed.push(window.history.state.alpine.snapshotIdx);
        events.push(event);
    };
    navigateHistoryWithLivewire(window);
    navigateHistoryWithLivewire(window);

    assert.equal(entries.length, 1);
    assert.deepEqual(observed, ['rodnik-history:1', 'rodnik-history:2']);
    assert.notEqual(events[0].state.alpine.snapshotIdx, events[1].state.alpine.snapshotIdx);
});
