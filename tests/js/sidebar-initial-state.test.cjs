const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const layout = fs.readFileSync('resources/views/layouts/dashboard.blade.php', 'utf8');
const source = layout.match(/<script id="sidebar-initial-state">([\s\S]*?)<\/script>/)[1];

function initialState(desktop, savedState, blockedStorage = false) {
    const classes = new Set();
    vm.runInNewContext(source, {
        window: {matchMedia: () => ({matches: desktop})},
        localStorage: {getItem(key) {
            assert.equal(key, 'lte.sidebar.state');
            if (blockedStorage) throw new Error('Storage unavailable');
            return savedState;
        }},
        document: {body: {classList: {add: name => classes.add(name)}}},
    });
    return [...classes];
}

test('the sidebar state is set before sidebar and main content markup', () => {
    assert(layout.indexOf('id="sidebar-initial-state"') < layout.indexOf('<div class="app-wrapper">'));
    assert.deepEqual(initialState(true, 'sidebar-collapse'), ['sidebar-collapse']);
    assert.deepEqual(initialState(true, 'sidebar-open'), ['sidebar-open']);
});

test('desktop defaults remain expanded while mobile starts hidden regardless of saved desktop state', () => {
    assert.deepEqual(initialState(true, null), ['sidebar-open']);
    assert.deepEqual(initialState(true, 'unexpected'), ['sidebar-open']);
    assert.deepEqual(initialState(false, 'sidebar-open'), ['sidebar-collapse']);
    assert.deepEqual(initialState(false, 'sidebar-collapse'), ['sidebar-collapse']);
});

test('unavailable local storage does not break page initialization', () => {
    assert.deepEqual(initialState(true, null, true), ['sidebar-open']);
    assert.deepEqual(initialState(false, null, true), ['sidebar-collapse']);
});
