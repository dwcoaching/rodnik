import assert from 'node:assert/strict';
import test from 'node:test';
import trackNotice from '../../resources/js/trackNotice.js';

function setup(t, state = {}) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const track = { status: 'idle', token: null, name: null, uploaded: false, ...state };
    const ui = trackNotice(track);
    let watch;
    ui.$watch = (property, callback) => {
        assert.equal(property, 'track');
        watch = callback;
    };
    ui.init();
    t.after(() => ui.destroy());
    return {
        ui, track,
        change(next) { Object.assign(track, next); watch(); },
        tick(milliseconds) { t.mock.timers.tick(milliseconds); },
    };
}

test('the complete successful upload notice hides after three seconds', t => {
    const { ui, track, tick } = setup(t, { status: 'saved', uploaded: true, name: 'Coastal walk', token: 'AbCd123456' });
    assert.equal(ui.track, track);
    assert.equal(ui.visible, true);
    tick(2999);
    assert.equal(ui.visible, true);
    tick(1);
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
    assert.equal(track.status, 'saved', 'dismissing feedback must not change the saved track');
    assert.equal(track.name, 'Coastal walk');
});

for (const status of ['local', 'saving', 'failed', 'missing']) {
    test(`${status} notices remain visible until the track changes status`, t => {
        const { ui, tick } = setup(t, { status });
        assert.equal(ui.visible, true);
        tick(30000);
        assert.equal(ui.visible, true);
        assert.equal(ui.hideTimer, null);
    });
}

test('a successful upload starts its countdown after progress ends', t => {
    const { ui, change, tick } = setup(t, { status: 'saving' });
    tick(10000);
    assert.equal(ui.visible, true);
    change({ status: 'saved', uploaded: true, token: 'AbCd123456' });
    tick(2999);
    assert.equal(ui.visible, true);
    tick(1);
    assert.equal(ui.visible, false);
});

test('a repeat upload restores the notice and cancels the previous success timer', t => {
    const { ui, change, tick } = setup(t, { status: 'saved', uploaded: true, token: 'AbCd123456' });
    tick(2000);
    change({ status: 'saving', token: null, name: 'Second route' });
    tick(3000);
    assert.equal(ui.visible, true, 'the old success timer cannot dismiss an active upload');
    change({ status: 'saved', token: 'Next123456' });
    tick(2999);
    assert.equal(ui.visible, true);
    tick(1);
    assert.equal(ui.visible, false);
    change({ status: 'saving', token: null });
    assert.equal(ui.visible, true, 'a later upload restores an already dismissed notice');
});

test('a new saved track gets a fresh countdown even when its status stays saved', t => {
    const { ui, change, tick } = setup(t, { status: 'saved', uploaded: true, token: 'AbCd123456' });
    tick(2000);
    change({ status: 'saved', token: 'Next123456', name: 'New route' });
    tick(1000);
    assert.equal(ui.visible, true);
    tick(2000);
    assert.equal(ui.visible, false);
});

for (const status of ['failed', 'missing']) {
    test(`a ${status} notice replaces success and survives the old dismissal deadline`, t => {
        const { ui, change, tick } = setup(t, { status: 'saved', uploaded: true });
        tick(2000);
        change({ status });
        tick(30000);
        assert.equal(ui.visible, true);
        assert.equal(ui.hideTimer, null);
    });
}

test('idle and loading states do not expose an empty upload notice', t => {
    const { ui, change, tick } = setup(t);
    assert.equal(ui.visible, false);
    change({ status: 'saved', uploaded: true });
    change({ status: 'idle' });
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
    change({ status: 'loading' });
    tick(5000);
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
});

test('a queued stale success callback cannot dismiss a replacement notice', t => {
    const callbacks = [];
    const cleared = [];
    t.mock.method(globalThis, 'setTimeout', callback => { callbacks.push(callback); return callbacks.length; });
    t.mock.method(globalThis, 'clearTimeout', timer => cleared.push(timer));
    const track = { status: 'saved', uploaded: true, token: 'AbCd123456' };
    const ui = trackNotice(track);
    ui.$watch = () => {};
    ui.init();
    Object.assign(track, { status: 'saved', token: 'Next123456' });
    ui.refresh();
    assert.deepEqual(cleared, [1]);
    callbacks[0]();
    assert.equal(ui.visible, true);
    callbacks[1]();
    assert.equal(ui.visible, false);
    ui.destroy();
});

test('destroy clears the timer and ignores late watcher callbacks', t => {
    const { ui, change, tick } = setup(t, { status: 'saved', uploaded: true });
    ui.destroy();
    assert.equal(ui.hideTimer, null);
    assert.equal(ui.visible, false);
    change({ status: 'failed' });
    tick(30000);
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
});

test('opening a shared track never shows the upload success notice', t => {
    const { ui, change, tick } = setup(t, { status: 'loading' });
    assert.equal(ui.visible, false);
    change({ status: 'saved', token: 'AbCd123456', name: 'Lycian Way' });
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
    tick(3000);
    assert.equal(ui.visible, false);
});

test('an already loaded shared track stays quiet when the notice initializes', t => {
    const { ui } = setup(t, { status: 'saved', token: 'AbCd123456', name: 'Lycian Way' });
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
});

test('opening a shared track after an upload clears its success notice', t => {
    const { ui, change, tick } = setup(t, { status: 'saved', uploaded: true });
    assert.equal(ui.visible, true);
    change({ status: 'loading', uploaded: false });
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
    change({ status: 'saved', token: 'AbCd123456' });
    assert.equal(ui.visible, false);
    assert.equal(ui.hideTimer, null);
    tick(3000);
    assert.equal(ui.visible, false);
});
