import assert from 'node:assert/strict';
import test from 'node:test';
import { clearMapDraft, readMapDraft, writeMapDraft } from '../../resources/js/mapDraft.js';

function setup(t, { storage = new Map(), unavailable = false } = {}) {
    const original = globalThis.window;
    const window = new EventTarget();
    const events = [];
    Object.defineProperty(window, 'sessionStorage', {
        get() {
            if (unavailable) throw new Error('Storage unavailable');
            return {
                getItem: key => storage.get(key) ?? null,
                setItem: (key, value) => storage.set(key, value),
                removeItem: key => storage.delete(key),
            };
        },
    });
    window.addEventListener('map-draft-changed', event => events.push(event.detail));
    globalThis.window = window;
    t.after(() => { globalThis.window = original; });
    return { window, storage, events };
}

test('draft metadata survives a document reload for its owner without creating a map', t => {
    const { storage, events } = setup(t);
    assert.equal(writeMapDraft({ title: '  Lycian Way  ', description: '  South coast  ', slug: '  lycian-way  ', token: 'ignored' }, 3), true);
    const expected = { title: 'Lycian Way', slug: 'lycian-way' };
    assert.deepEqual(readMapDraft('3'), expected);
    assert.deepEqual(events, [{ ownerId: '3', draft: expected }]);
    setup(t, { storage });
    assert.deepEqual(readMapDraft(3), expected);
});

test('drafts are isolated between accounts and cannot be accessed by guests', t => {
    setup(t);
    writeMapDraft({ title: 'Private draft' }, 3);
    assert.equal(readMapDraft(4), null);
    for (const owner of [undefined, null, '', 0, -1, '3/4', {}, true]) {
        assert.equal(readMapDraft(owner), null);
        assert.equal(writeMapDraft({ title: 'Other' }, owner), false);
        clearMapDraft(owner);
    }
    assert.deepEqual(readMapDraft(3), { title: 'Private draft', slug: '' });
});

test('unavailable session storage keeps drafts alive during Livewire navigation', t => {
    const { events } = setup(t, { unavailable: true });
    assert.equal(writeMapDraft({ title: 'Choose this view', description: null, slug: null }, 3), true);
    assert.deepEqual(readMapDraft(3), { title: 'Choose this view', slug: '' });
    clearMapDraft(3);
    assert.equal(readMapDraft(3), null);
    assert.deepEqual(events.at(-1), { ownerId: '3', draft: null });
});

test('clear removes only the selected owner draft and it stays cleared after reload', t => {
    const { storage } = setup(t);
    writeMapDraft({ title: 'First' }, 3);
    writeMapDraft({ title: 'Second' }, 4);
    clearMapDraft(3);
    setup(t, { storage });
    assert.equal(readMapDraft(3), null);
    assert.equal(readMapDraft(4).title, 'Second');
});

test('malformed or excessive stored metadata is ignored safely', t => {
    for (const value of ['{', 'null', '[]', '{"title":9}', '{"title":""}', JSON.stringify({ title: 'a'.repeat(161) }), JSON.stringify({ title: 'Map', slug: {} })]) {
        setup(t, { storage: new Map([['rodnik:map-draft:3', value]]) });
        assert.equal(readMapDraft(3), null);
    }
});

test('invalid writes do not replace the existing draft or emit a change', t => {
    const { events } = setup(t);
    writeMapDraft({ title: 'Keep me' }, 3);
    for (const value of [null, {}, { title: ' ' }, { title: 'Map', slug: 'a'.repeat(81) }]) {
        assert.equal(writeMapDraft(value, 3), false);
    }
    assert.equal(readMapDraft(3).title, 'Keep me');
    assert.equal(events.length, 1);
});

test('callers cannot mutate the stored draft through reads or event details', t => {
    const { events } = setup(t);
    const input = { title: 'Original' };
    writeMapDraft(input, 3);
    input.title = 'Changed';
    readMapDraft(3).title = 'Changed again';
    events[0].draft.title = 'Also changed';
    assert.equal(readMapDraft(3).title, 'Original');
});

test('draft helpers are safe without a browser window', t => {
    setup(t);
    delete globalThis.window;
    assert.equal(readMapDraft(3), null);
    assert.equal(writeMapDraft({ title: 'No browser' }, 3), false);
    assert.doesNotThrow(() => clearMapDraft(3));
});
