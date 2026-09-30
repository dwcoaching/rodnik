import assert from 'node:assert/strict';
import test from 'node:test';
import springFocus from '../../resources/js/springFocus.js';

const coordinates = [34.610396, 44.857306];

function setup(t, { focused = false, animating = false, restoring = false } = {}) {
    const previousWindow = globalThis.window;
    const focusRequests = [];
    const map = {
        focused,
        animating,
        view: { getAnimating: () => map.animating },
        isSpringFocused(value) {
            assert.deepEqual(value, coordinates);
            return map.focused;
        },
        focusSpring(value) {
            focusRequests.push(value);
            map.animating = true;
        },
    };
    globalThis.window = { rodnikMap: map, rodnikNavigation: { restoring } };
    t.after(() => { globalThis.window = previousWindow; });
    const ui = springFocus(coordinates);
    ui.init();

    return {
        ui, map, focusRequests,
        settle(changes = {}) {
            Object.assign(map, { animating: false }, changes);
            window.rodnikNavigation.restoring = false;
            ui.refresh();
        },
    };
}

test('a page opened on its focused source never offers to show it', t => {
    const { ui, settle } = setup(t, { restoring: true, animating: true });
    assert.equal(ui.visible, false);
    settle({ focused: true });
    assert.equal(ui.visible, false);
});

test('a source selected without moving the camera offers to show it on the map', t => {
    const { ui, settle } = setup(t, { restoring: true });
    assert.equal(ui.visible, false, 'the camera is not judged until navigation finishes');
    settle();
    assert.equal(ui.visible, true);
});

test('showing the source hides the control until the map moves away again', t => {
    const { ui, map, focusRequests, settle } = setup(t);
    assert.equal(ui.visible, true);

    ui.showOnMap();
    assert.deepEqual(focusRequests, [coordinates]);
    assert.equal(ui.visible, false);
    ui.refresh();
    assert.equal(ui.visible, false, 'the control stays hidden while the camera flies to the source');
    settle({ focused: true });
    assert.equal(ui.visible, false);

    map.focused = false;
    ui.refresh();
    assert.equal(ui.visible, true);
});

test('a camera animation defers the decision to its moveend', t => {
    const { ui, settle } = setup(t, { animating: true });
    assert.equal(ui.visible, false);
    settle({ focused: false });
    assert.equal(ui.visible, true);
    settle({ focused: true });
    assert.equal(ui.visible, false);
});

test('the control stays inert without a map', t => {
    const { ui } = setup(t);
    window.rodnikMap = null;
    ui.visible = false;
    ui.refresh();
    assert.equal(ui.visible, false);
    assert.doesNotThrow(() => ui.showOnMap());
});
